<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Tests\Core\Controller;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OC\Core\Controller\AppPasswordController;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\Authentication\Exceptions\CredentialsUnavailableException;
use OCP\Authentication\Exceptions\PasswordUnavailableException;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\IRequest;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class AppPasswordControllerTest extends TestCase {

	/** @var ISession|MockObject */
	private $session;

	/** @var ISecureRandom|MockObject */
	private $random;

	/** @var IProvider|MockObject */
	private $tokenProvider;

	/** @var IStore|MockObject */
	private $credentialStore;

	/** @var IRequest|MockObject */
	private $request;

	/** @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $eventDispatcher;

	/** @var AppPasswordController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->session = $this->createMock(ISession::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->tokenProvider = $this->createMock(IProvider::class);
		$this->credentialStore = $this->createMock(IStore::class);
		$this->request = $this->createMock(IRequest::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

		$this->controller = new AppPasswordController(
			'core',
			$this->request,
			$this->session,
			$this->random,
			$this->tokenProvider,
			$this->credentialStore,
			$this->eventDispatcher
		);
	}

	public function testGetAppPasswordWithAppPassword() {
		$this->session->method('exists')
			->with('app_password')
			->willReturn(true);

		$this->expectException(OCSForbiddenException::class);

		$this->controller->getAppPassword();
	}

	public function testGetAppPasswordNoLoginCreds() {
		$this->session->method('exists')
			->with('app_password')
			->willReturn(false);
		$this->credentialStore->method('getLoginCredentials')
			->willThrowException(new CredentialsUnavailableException());

		$this->expectException(OCSForbiddenException::class);

		$this->controller->getAppPassword();
	}

	public function testGetAppPassword() {
		$credentials = $this->createMock(ICredentials::class);

		$this->session->method('exists')
			->with('app_password')
			->willReturn(false);
		$this->credentialStore->method('getLoginCredentials')
			->willReturn($credentials);
		$credentials->method('getUid')
			->willReturn('myUID');
		$credentials->method('getPassword')
			->willReturn('myPassword');
		$credentials->method('getLoginName')
			->willReturn('myLoginName');
		$this->request->method('getHeader')
			->with('USER_AGENT')
			->willReturn('myUA');
		$this->random->method('generate')
			->with(
				72,
				ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS
			)->willReturn('myToken');

		$this->tokenProvider->expects($this->once())
			->method('generateToken')
			->with(
				'myToken',
				'myUID',
				'myLoginName',
				'myPassword',
				'myUA',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			);

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$this->controller->getAppPassword();
	}

	public function testGetAppPasswordNoPassword() {
		$credentials = $this->createMock(ICredentials::class);

		$this->session->method('exists')
			->with('app_password')
			->willReturn(false);
		$this->credentialStore->method('getLoginCredentials')
			->willReturn($credentials);
		$credentials->method('getUid')
			->willReturn('myUID');
		$credentials->method('getPassword')
			->willThrowException(new PasswordUnavailableException());
		$credentials->method('getLoginName')
			->willReturn('myLoginName');
		$this->request->method('getHeader')
			->with('USER_AGENT')
			->willReturn('myUA');
		$this->random->method('generate')
			->with(
				72,
				ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS
			)->willReturn('myToken');

		$this->tokenProvider->expects($this->once())
			->method('generateToken')
			->with(
				'myToken',
				'myUID',
				'myLoginName',
				null,
				'myUA',
				IToken::PERMANENT_TOKEN,
				IToken::DO_NOT_REMEMBER
			);

		$this->eventDispatcher->expects($this->once())
			->method('dispatch');

		$this->controller->getAppPassword();
	}

	public function testDeleteAppPasswordNoAppPassword() {
		$this->session->method('exists')
			->with('app_password')
			->willReturn(false);

		$this->expectException(OCSForbiddenException::class);

		$this->controller->deleteAppPassword();
	}

	public function testDeleteAppPasswordFails() {
		$this->session->method('exists')
			->with('app_password')
			->willReturn(true);
		$this->session->method('get')
			->with('app_password')
			->willReturn('myAppPassword');

		$this->tokenProvider->method('getToken')
			->with('myAppPassword')
			->willThrowException(new InvalidTokenException());

		$this->expectException(OCSForbiddenException::class);

		$this->controller->deleteAppPassword();
	}

	public function testDeleteAppPasswordSuccess() {
		$this->session->method('exists')
			->with('app_password')
			->willReturn(true);
		$this->session->method('get')
			->with('app_password')
			->willReturn('myAppPassword');

		$token = $this->createMock(IToken::class);
		$this->tokenProvider->method('getToken')
			->with('myAppPassword')
			->willReturn($token);

		$token->method('getUID')
			->willReturn('myUID');
		$token->method('getId')
			->willReturn(42);

		$this->tokenProvider->expects($this->once())
			->method('invalidateTokenById')
			->with(
				'myUID',
				42
			);

		$result = $this->controller->deleteAppPassword();

		$this->assertEquals(new DataResponse(), $result);
	}
}
