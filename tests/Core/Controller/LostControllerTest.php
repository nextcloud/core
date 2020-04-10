<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Tests\Core\Controller;

use OC\Authentication\TwoFactorAuth\Manager;
use OC\Core\Controller\LostController;
use OC\Mail\Message;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Class LostControllerTest
 *
 * @package OC\Core\Controller
 */
class LostControllerTest extends \Test\TestCase {

	/** @var LostController */
	private $lostController;
	/** @var IUser */
	private $existingUser;
	/** @var IURLGenerator | PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;
	/** @var IL10N */
	private $l10n;
	/** @var IUserManager | PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var Defaults */
	private $defaults;
	/** @var IConfig | PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var IMailer | PHPUnit_Framework_MockObject_MockObject */
	private $mailer;
	/** @var ISecureRandom | PHPUnit_Framework_MockObject_MockObject */
	private $secureRandom;
	/** @var IManager|PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;
	/** @var ITimeFactory | PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;
	/** @var IRequest */
	private $request;
	/** @var ICrypto|\PHPUnit_Framework_MockObject_MockObject */
	private $crypto;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $twofactorManager;
	/** @var IInitialStateService|\PHPUnit_Framework_MockObject_MockObject */
	private $initialStateService;

	protected function setUp(): void {
		parent::setUp();

		$this->existingUser = $this->createMock(IUser::class);
		$this->existingUser->expects($this->any())
			->method('getEMailAddress')
			->willReturn('test@example.com');
		$this->existingUser->expects($this->any())
			->method('getUID')
			->willReturn('ExistingUser');
		$this->existingUser->expects($this->any())
			->method('isEnabled')
			->willReturn(true);

		$this->config = $this->createMock(IConfig::class);
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturnMap([
				['secret', null, 'SECRET'],
				['secret', '', 'SECRET'],
				['lost_password_link', '', ''],
			]);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$this->defaults = $this->getMockBuilder('\OCP\Defaults')
			->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()->getMock();
		$this->mailer = $this->getMockBuilder('\OCP\Mail\IMailer')
			->disableOriginalConstructor()->getMock();
		$this->secureRandom = $this->getMockBuilder('\OCP\Security\ISecureRandom')
			->disableOriginalConstructor()->getMock();
		$this->timeFactory = $this->getMockBuilder('\OCP\AppFramework\Utility\ITimeFactory')
			->disableOriginalConstructor()->getMock();
		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()->getMock();
		$this->encryptionManager = $this->getMockBuilder(IManager::class)
			->disableOriginalConstructor()->getMock();
		$this->encryptionManager->expects($this->any())
			->method('isEnabled')
			->willReturn(true);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->twofactorManager = $this->createMock(Manager::class);
		$this->initialStateService = $this->createMock(IInitialStateService::class);
		$this->lostController = new LostController(
			'Core',
			$this->request,
			$this->urlGenerator,
			$this->userManager,
			$this->defaults,
			$this->l10n,
			$this->config,
			$this->secureRandom,
			'lostpassword-noreply@localhost',
			$this->encryptionManager,
			$this->mailer,
			$this->timeFactory,
			$this->crypto,
			$this->logger,
			$this->twofactorManager,
			$this->initialStateService
		);
	}

	public function testResetFormWithNotExistingUser() {
		$this->userManager->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$expectedResponse = new TemplateResponse(
			'core',
			'error',
			[
				'errors' => [
					['error' => 'Couldn\'t reset password because the token is invalid'],
				]
			],
			'guest'
		);
		$this->assertEquals($expectedResponse, $this->lostController->resetform('MySecretToken', 'NotExistingUser'));
	}

	public function testResetFormInvalidTokenMatch() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedToken');
		$this->existingUser->method('getLastLogin')
			->willReturn(12344);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedToken'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->resetform('12345:MySecretToken', 'ValidTokenUser');
		$expectedResponse = new TemplateResponse('core',
			'error',
			[
				'errors' => [
					['error' => 'Couldn\'t reset password because the token is invalid'],
				]
			],
			'guest');
		$this->assertEquals($expectedResponse, $response);
	}


	public function testResetFormExpiredToken() {
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->config
			->expects($this->once())
			->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedToken');
		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedToken'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(999999);

		$response = $this->lostController->resetform('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser');
		$expectedResponse = new TemplateResponse('core',
			'error',
			[
				'errors' => [
					['error' => 'Couldn\'t reset password because the token is expired'],
				]
			],
			'guest');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testResetFormValidToken() {
		$this->existingUser->method('getLastLogin')
			->willReturn(12344);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(12348);

		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedToken');

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedToken'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.lost.setPassword', ['userId' => 'ValidTokenUser', 'token' => 'TheOnlyAndOnlyOneTokenToResetThePassword'])
			->willReturn('https://example.tld/index.php/lostpassword/');

		$this->initialStateService->expects($this->at(0))
			->method('provideInitialState')
			->with('core', 'resetPasswordUser', 'ValidTokenUser');
		$this->initialStateService->expects($this->at(1))
			->method('provideInitialState')
			->with('core', 'resetPasswordTarget', 'https://example.tld/index.php/lostpassword/');

		$response = $this->lostController->resetform('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser');
		$expectedResponse = new TemplateResponse('core',
			'login',
			[],
			'guest');
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEmailUnsuccessful() {
		$existingUser = 'ExistingUser';
		$nonExistingUser = 'NonExistingUser';
		$this->userManager
			->expects($this->any())
			->method('userExists')
			->willReturnMap([
				[true, $existingUser],
				[false, $nonExistingUser]
			]);

		$this->logger->expects($this->exactly(0))
			->method('logException');
		$this->logger->expects($this->exactly(2))
			->method('warning');

		$this->userManager
			->method('getByEmail')
			->willReturn([]);

		// With a non existing user
		$response = $this->lostController->email($nonExistingUser);
		$expectedResponse = new JSONResponse([
			'status' => 'success',
		]);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);

		// With no mail address
		$this->config
			->expects($this->any())
			->method('getUserValue')
			->with($existingUser, 'settings', 'email')
			->willReturn(null);
		$response = $this->lostController->email($existingUser);
		$expectedResponse = new JSONResponse([
			'status' => 'success',
		]);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEmailSuccessful() {
		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with('21')
			->willReturn('ThisIsMaybeANotSoSecretToken!');
		$this->userManager
				->expects($this->any())
				->method('get')
				->with('ExistingUser')
				->willReturn($this->existingUser);
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(12348);
		$this->config
			->expects($this->once())
			->method('setUserValue')
			->with('ExistingUser', 'core', 'lostpassword', 'encryptedToken');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.lost.resetform', ['userId' => 'ExistingUser', 'token' => 'ThisIsMaybeANotSoSecretToken!'])
			->willReturn('https://example.tld/index.php/lostpassword/');
		$message = $this->getMockBuilder('\OC\Mail\Message')
			->disableOriginalConstructor()->getMock();
		$message
			->expects($this->at(0))
			->method('setTo')
			->with(['test@example.com' => 'ExistingUser']);
		$message
			->expects($this->at(1))
			->method('setFrom')
			->with(['lostpassword-noreply@localhost' => null]);

		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$emailTemplate->expects($this->any())
			->method('renderHtml')
			->willReturn('HTML body');
		$emailTemplate->expects($this->any())
			->method('renderText')
			->willReturn('text body');

		$message
			->expects($this->at(2))
			->method('useTemplate')
			->with($emailTemplate);

		$this->mailer
			->expects($this->at(0))
			->method('createEMailTemplate')
			->willReturn($emailTemplate);
		$this->mailer
			->expects($this->at(1))
			->method('createMessage')
			->willReturn($message);
		$this->mailer
			->expects($this->at(2))
			->method('send')
			->with($message);

		$this->crypto->method('encrypt')
			->with(
				$this->equalTo('12348:ThisIsMaybeANotSoSecretToken!'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('encryptedToken');

		$response = $this->lostController->email('ExistingUser');
		$expectedResponse = new JSONResponse(['status' => 'success']);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEmailWithMailSuccessful() {
		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with('21')
			->willReturn('ThisIsMaybeANotSoSecretToken!');
		$this->userManager
				->expects($this->any())
				->method('get')
				->with('test@example.com')
				->willReturn(null);
		$this->userManager
				->expects($this->any())
				->method('getByEmail')
				->with('test@example.com')
				->willReturn([$this->existingUser]);
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(12348);
		$this->config
			->expects($this->once())
			->method('setUserValue')
			->with('ExistingUser', 'core', 'lostpassword', 'encryptedToken');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.lost.resetform', ['userId' => 'ExistingUser', 'token' => 'ThisIsMaybeANotSoSecretToken!'])
			->willReturn('https://example.tld/index.php/lostpassword/');
		$message = $this->getMockBuilder('\OC\Mail\Message')
			->disableOriginalConstructor()->getMock();
		$message
			->expects($this->at(0))
			->method('setTo')
			->with(['test@example.com' => 'ExistingUser']);
		$message
			->expects($this->at(1))
			->method('setFrom')
			->with(['lostpassword-noreply@localhost' => null]);

		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$emailTemplate->expects($this->any())
			->method('renderHtml')
			->willReturn('HTML body');
		$emailTemplate->expects($this->any())
			->method('renderText')
			->willReturn('text body');

		$message
			->expects($this->at(2))
			->method('useTemplate')
			->with($emailTemplate);

		$this->mailer
			->expects($this->at(0))
			->method('createEMailTemplate')
			->willReturn($emailTemplate);
		$this->mailer
			->expects($this->at(1))
			->method('createMessage')
			->willReturn($message);
		$this->mailer
			->expects($this->at(2))
			->method('send')
			->with($message);

		$this->crypto->method('encrypt')
			->with(
				$this->equalTo('12348:ThisIsMaybeANotSoSecretToken!'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('encryptedToken');

		$response = $this->lostController->email('test@example.com');
		$expectedResponse = new JSONResponse(['status' => 'success']);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testEmailCantSendException() {
		$this->secureRandom
			->expects($this->once())
			->method('generate')
			->with('21')
			->willReturn('ThisIsMaybeANotSoSecretToken!');
		$this->userManager
				->expects($this->any())
				->method('get')
				->with('ExistingUser')
				->willReturn($this->existingUser);
		$this->config
			->expects($this->once())
			->method('setUserValue')
			->with('ExistingUser', 'core', 'lostpassword', 'encryptedToken');
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(12348);
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('core.lost.resetform', ['userId' => 'ExistingUser', 'token' => 'ThisIsMaybeANotSoSecretToken!'])
			->willReturn('https://example.tld/index.php/lostpassword/');
		$message = $this->createMock(Message::class);
		$message
			->expects($this->at(0))
			->method('setTo')
			->with(['test@example.com' => 'ExistingUser']);
		$message
			->expects($this->at(1))
			->method('setFrom')
			->with(['lostpassword-noreply@localhost' => null]);

		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$emailTemplate->expects($this->any())
			->method('renderHtml')
			->willReturn('HTML body');
		$emailTemplate->expects($this->any())
			->method('renderText')
			->willReturn('text body');

		$message
			->expects($this->at(2))
			->method('useTemplate')
			->with($emailTemplate);

		$this->mailer
			->expects($this->at(0))
			->method('createEMailTemplate')
			->willReturn($emailTemplate);
		$this->mailer
			->expects($this->at(1))
			->method('createMessage')
			->willReturn($message);
		$this->mailer
			->expects($this->at(2))
			->method('send')
			->with($message)
			->will($this->throwException(new \Exception()));

		$this->crypto->method('encrypt')
			->with(
				$this->equalTo('12348:ThisIsMaybeANotSoSecretToken!'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('encryptedToken');

		$this->logger->expects($this->exactly(1))
			->method('logException');

		$response = $this->lostController->email('ExistingUser');
		$expectedResponse = new JSONResponse(['status' => 'success']);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testSetPasswordUnsuccessful() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->existingUser->method('getLastLogin')
			->willReturn(12344);
		$this->existingUser->expects($this->once())
			->method('setPassword')
			->with('NewPassword')
			->willReturn(false);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->config->expects($this->never())
			->method('deleteUserValue');
		$this->timeFactory->method('getTime')
			->willReturn(12348);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = ['status' => 'error', 'msg' => ''];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordSuccessful() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->existingUser->method('getLastLogin')
			->willReturn(12344);
		$this->existingUser->expects($this->once())
			->method('setPassword')
			->with('NewPassword')
			->willReturn(true);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword');
		$this->timeFactory->method('getTime')
			->willReturn(12348);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = ['user' => 'ValidTokenUser', 'status' => 'success'];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordExpiredToken() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->timeFactory->method('getTime')
			->willReturn(617146);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is expired',
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordInvalidDataInDb() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('invalidEncryptedData');
		$this->userManager
			->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('invalidEncryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is invalid',
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordExpiredTokenDueToLogin() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->existingUser->method('getLastLogin')
			->willReturn(12346);
		$this->userManager
			->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->timeFactory
			->method('getTime')
			->willReturn(12345);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is expired',
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testIsSetPasswordWithoutTokenFailing() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('aValidtoken');
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('aValidtoken'),
				$this->equalTo('test@example.comSECRET')
			)->willThrowException(new \Exception());

		$response = $this->lostController->setPassword('', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is invalid'
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testIsSetPasswordTokenNullFailing() {
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn(null);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);

		$response = $this->lostController->setPassword('', 'ValidTokenUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is invalid'
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordForDisabledUser() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('isEnabled')
			->willReturn(false);
		$user->expects($this->never())
			->method('setPassword');

		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->userManager->method('get')
			->with('DisabledUser')
			->willReturn($user);

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'DisabledUser', 'NewPassword', true);
		$expectedResponse = [
			'status' => 'error',
			'msg' => 'Couldn\'t reset password because the token is invalid'
		];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSendEmailNoEmail() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->any())
			->method('isEnabled')
			->willReturn(true);
		$this->userManager->method('userExists')
			->with('ExistingUser')
			->willReturn(true);
		$this->userManager->method('get')
			->with('ExistingUser')
			->willReturn($user);

		$this->logger->expects($this->exactly(0))
			->method('logException');
		$this->logger->expects($this->once())
			->method('warning');

		$response = $this->lostController->email('ExistingUser');
		$expectedResponse = new JSONResponse(['status' => 'success']);
		$expectedResponse->throttle();
		$this->assertEquals($expectedResponse, $response);
	}

	public function testSetPasswordEncryptionDontProceedPerUserKey() {
		/** @var IEncryptionModule|PHPUnit_Framework_MockObject_MockObject $encryptionModule */
		$encryptionModule = $this->createMock(IEncryptionModule::class);
		$encryptionModule->expects($this->once())->method('needDetailedAccessList')->willReturn(true);
		$this->encryptionManager->expects($this->once())->method('getEncryptionModules')
			->willReturn([0 => ['callback' => function () use ($encryptionModule) { return $encryptionModule; }]]);
		$response = $this->lostController->setPassword('myToken', 'user', 'newpass', false);
		$expectedResponse = ['status' => 'error', 'msg' => '', 'encryption' => true];
		$this->assertSame($expectedResponse, $response);
	}

	public function testSetPasswordDontProceedMasterKey() {
		$encryptionModule = $this->createMock(IEncryptionModule::class);
		$encryptionModule->expects($this->once())->method('needDetailedAccessList')->willReturn(false);
		$this->encryptionManager->expects($this->once())->method('getEncryptionModules')
			->willReturn([0 => ['callback' => function () use ($encryptionModule) { return $encryptionModule; }]]);
		$this->config->method('getUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword', null)
			->willReturn('encryptedData');
		$this->existingUser->method('getLastLogin')
			->willReturn(12344);
		$this->existingUser->expects($this->once())
			->method('setPassword')
			->with('NewPassword')
			->willReturn(true);
		$this->userManager->method('get')
			->with('ValidTokenUser')
			->willReturn($this->existingUser);
		$this->config->expects($this->once())
			->method('deleteUserValue')
			->with('ValidTokenUser', 'core', 'lostpassword');
		$this->timeFactory->method('getTime')
			->willReturn(12348);

		$this->crypto->method('decrypt')
			->with(
				$this->equalTo('encryptedData'),
				$this->equalTo('test@example.comSECRET')
			)->willReturn('12345:TheOnlyAndOnlyOneTokenToResetThePassword');

		$response = $this->lostController->setPassword('TheOnlyAndOnlyOneTokenToResetThePassword', 'ValidTokenUser', 'NewPassword', false);
		$expectedResponse = ['user' => 'ValidTokenUser', 'status' => 'success'];
		$this->assertSame($expectedResponse, $response);
	}

	public function testTwoUsersWithSameEmail() {
		$user1 = $this->createMock(IUser::class);
		$user1->expects($this->any())
			->method('getEMailAddress')
			->willReturn('test@example.com');
		$user1->expects($this->any())
			->method('getUID')
			->willReturn('User1');
		$user1->expects($this->any())
			->method('isEnabled')
			->willReturn(true);

		$user2 = $this->createMock(IUser::class);
		$user2->expects($this->any())
			->method('getEMailAddress')
			->willReturn('test@example.com');
		$user2->expects($this->any())
			->method('getUID')
			->willReturn('User2');
		$user2->expects($this->any())
			->method('isEnabled')
			->willReturn(true);

		$this->userManager
			->method('get')
			->willReturn(null);

		$this->userManager
			->method('getByEmail')
			->willReturn([$user1, $user2]);

		$this->logger->expects($this->exactly(0))
			->method('logException');
		$this->logger->expects($this->once())
			->method('warning');

		// request password reset for test@example.com
		$response = $this->lostController->email('test@example.com');

		$expectedResponse = new JSONResponse([
			'status' => 'success'
		]);
		$expectedResponse->throttle();

		$this->assertEquals($expectedResponse, $response);
	}


	/**
	 * @return array
	 */
	public function dataTwoUserswithSameEmailOneDisabled(): array {
		return [
			['user1' => true, 'user2' => false],
			['user1' => false, 'user2' => true]
		];
	}

	/**
	 * @dataProvider dataTwoUserswithSameEmailOneDisabled
	 * @param bool $userEnabled1
	 * @param bool $userEnabled2
	 */
	public function testTwoUsersWithSameEmailOneDisabled(bool $userEnabled1, bool $userEnabled2): void {
		$user1 = $this->createMock(IUser::class);
		$user1->method('getEMailAddress')
			->willReturn('test@example.com');
		$user1->method('getUID')
			->willReturn('User1');
		$user1->method('isEnabled')
			->willReturn($userEnabled1);

		$user2 = $this->createMock(IUser::class);
		$user2->method('getEMailAddress')
			->willReturn('test@example.com');
		$user2->method('getUID')
			->willReturn('User2');
		$user2->method('isEnabled')
			->willReturn($userEnabled2);

		$this->userManager
			->method('get')
			->willReturn(null);

		$this->userManager
			->method('getByEmail')
			->willReturn([$user1, $user2]);

		$result = self::invokePrivate($this->lostController, 'findUserByIdOrMail', ['test@example.com']);
		$this->assertInstanceOf(IUser::class, $result);
	}
}
