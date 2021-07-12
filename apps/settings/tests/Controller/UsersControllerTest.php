<?php
/**
 * @copyright 2014-2015 Lukas Reschke lukas@owncloud.com
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Settings\Tests\Controller;

use OC\Accounts\AccountManager;
use OC\Encryption\Exceptions\ModuleDoesNotExistsException;
use OC\Group\Manager;
use OC\KnownUser\KnownUserService;
use OCA\Settings\Controller\UsersController;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\BackgroundJob\IJobList;
use OCP\Encryption\IEncryptionModule;
use OCP\Encryption\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;

/**
 * @group DB
 *
 * @package Tests\Settings\Controller
 */
class UsersControllerTest extends \Test\TestCase {
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	private $groupManager;
	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;
	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var IMailer|\PHPUnit\Framework\MockObject\MockObject */
	private $mailer;
	/** @var IFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $l10nFactory;
	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;
	/** @var IAvatarManager|\PHPUnit\Framework\MockObject\MockObject */
	private $avatarManager;
	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l;
	/** @var AccountManager | \PHPUnit\Framework\MockObject\MockObject */
	private $accountManager;
	/** @var ISecureRandom | \PHPUnit\Framework\MockObject\MockObject  */
	private $secureRandom;
	/** @var \OCA\Settings\Mailer\NewUserMailHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $newUserMailHelper;
	/** @var  IJobList | \PHPUnit\Framework\MockObject\MockObject */
	private $jobList;
	/** @var \OC\Security\IdentityProof\Manager |\PHPUnit\Framework\MockObject\MockObject  */
	private $securityManager;
	/** @var  IManager | \PHPUnit\Framework\MockObject\MockObject */
	private $encryptionManager;
	/** @var KnownUserService|\PHPUnit\Framework\MockObject\MockObject */
	private $knownUserService;
	/** @var  IEncryptionModule  | \PHPUnit\Framework\MockObject\MockObject */
	private $encryptionModule;
	/** @var IEventDispatcher|\PHPUnit\Framework\MockObject\MockObject */
	private $dispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(Manager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->config = $this->createMock(IConfig::class);
		$this->l = $this->createMock(IL10N::class);
		$this->mailer = $this->createMock(IMailer::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->securityManager = $this->getMockBuilder(\OC\Security\IdentityProof\Manager::class)->disableOriginalConstructor()->getMock();
		$this->jobList = $this->createMock(IJobList::class);
		$this->encryptionManager = $this->createMock(IManager::class);
		$this->knownUserService = $this->createMock(KnownUserService::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});

		$this->encryptionModule = $this->createMock(IEncryptionModule::class);
		$this->encryptionManager->expects($this->any())->method('getEncryptionModules')
			->willReturn(['encryptionModule' => ['callback' => function () {
				return $this->encryptionModule;
			}]]);
	}

	/**
	 * @param bool $isAdmin
	 * @return UsersController | \PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getController($isAdmin = false, $mockedMethods = []) {
		if (empty($mockedMethods)) {
			return new UsersController(
				'settings',
				$this->createMock(IRequest::class),
				$this->userManager,
				$this->groupManager,
				$this->userSession,
				$this->config,
				$isAdmin,
				$this->l,
				$this->mailer,
				$this->l10nFactory,
				$this->appManager,
				$this->accountManager,
				$this->securityManager,
				$this->jobList,
				$this->encryptionManager,
				$this->knownUserService,
				$this->dispatcher
			);
		} else {
			return $this->getMockBuilder(UsersController::class)
				->setConstructorArgs(
					[
						'settings',
						$this->createMock(IRequest::class),
						$this->userManager,
						$this->groupManager,
						$this->userSession,
						$this->config,
						$isAdmin,
						$this->l,
						$this->mailer,
						$this->l10nFactory,
						$this->appManager,
						$this->accountManager,
						$this->securityManager,
						$this->jobList,
						$this->encryptionManager,
						$this->knownUserService,
						$this->dispatcher
					]
				)->setMethods($mockedMethods)->getMock();
		}
	}

	protected function getDefaultAccountManagerUserData() {
		return [
			IAccountManager::PROPERTY_DISPLAYNAME =>
				[
					'value' => 'Default display name',
					'scope' => IAccountManager::SCOPE_FEDERATED,
					'verified' => IAccountManager::VERIFIED,
				],
			IAccountManager::PROPERTY_ADDRESS =>
				[
					'value' => 'Default address',
					'scope' => IAccountManager::SCOPE_LOCAL,
					'verified' => IAccountManager::VERIFIED,
				],
			IAccountManager::PROPERTY_WEBSITE =>
				[
					'value' => 'Default website',
					'scope' => IAccountManager::SCOPE_LOCAL,
					'verified' => IAccountManager::VERIFIED,
				],
			IAccountManager::PROPERTY_EMAIL =>
				[
					'value' => 'Default email',
					'scope' => IAccountManager::SCOPE_FEDERATED,
					'verified' => IAccountManager::VERIFIED,
				],
			IAccountManager::PROPERTY_AVATAR =>
				[
					'scope' => IAccountManager::SCOPE_FEDERATED
				],
			IAccountManager::PROPERTY_PHONE =>
				[
					'value' => 'Default phone',
					'scope' => IAccountManager::SCOPE_LOCAL,
					'verified' => IAccountManager::VERIFIED,
				],
			IAccountManager::PROPERTY_TWITTER =>
				[
					'value' => 'Default twitter',
					'scope' => IAccountManager::SCOPE_LOCAL,
					'verified' => IAccountManager::VERIFIED,
				],
		];
	}

	/**
	 * @dataProvider dataTestSetUserSettings
	 *
	 * @param string $email
	 * @param bool $validEmail
	 * @param $expectedStatus
	 */
	public function testSetUserSettings($email, $validEmail, $expectedStatus) {
		$controller = $this->getController(false, ['saveUserSettings']);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('johndoe');

		$this->userSession->method('getUser')->willReturn($user);

		if (!empty($email) && $validEmail) {
			$this->mailer->expects($this->once())->method('validateMailAddress')
				->willReturn($validEmail);
		}

		$saveData = (!empty($email) && $validEmail) || empty($email);

		if ($saveData) {
			$this->accountManager->expects($this->once())
				->method('getUser')
				->with($user)
				->willReturn($this->getDefaultAccountManagerUserData());

			$controller->expects($this->once())
				->method('saveUserSettings')
				->willReturnArgument(1);
		} else {
			$controller->expects($this->never())->method('saveUserSettings');
		}

		$result = $controller->setUserSettings(//
			AccountManager::SCOPE_FEDERATED,
			'displayName',
			AccountManager::SCOPE_FEDERATED,
			'47658468',
			AccountManager::SCOPE_FEDERATED,
			$email,
			AccountManager::SCOPE_FEDERATED,
			'nextcloud.com',
			AccountManager::SCOPE_FEDERATED,
			'street and city',
			AccountManager::SCOPE_FEDERATED,
			'@nextclouders',
			AccountManager::SCOPE_FEDERATED
		);

		$this->assertSame($expectedStatus, $result->getStatus());
	}

	public function dataTestSetUserSettings() {
		return [
			['', true, Http::STATUS_OK],
			['', false, Http::STATUS_OK],
			['example.com', false, Http::STATUS_UNPROCESSABLE_ENTITY],
			['john@example.com', true, Http::STATUS_OK],
		];
	}

	public function testSetUserSettingsWhenUserDisplayNameChangeNotAllowed() {
		$controller = $this->getController(false, ['saveUserSettings']);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('johndoe');

		$this->userSession->method('getUser')->willReturn($user);

		$defaultProperties = $this->getDefaultAccountManagerUserData();

		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn($defaultProperties);

		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('allow_user_to_change_display_name')
			->willReturn(false);

		$this->appManager->expects($this->any())
			->method('isEnabledForUser')
			->with('federatedfilesharing')
			->willReturn(true);

		$avatarScope = IAccountManager::SCOPE_PUBLISHED;
		$displayName = 'Display name';
		$displayNameScope = IAccountManager::SCOPE_PUBLISHED;
		$phone = '47658468';
		$phoneScope = IAccountManager::SCOPE_PUBLISHED;
		$email = 'john@example.com';
		$emailScope = IAccountManager::SCOPE_PUBLISHED;
		$website = 'nextcloud.com';
		$websiteScope = IAccountManager::SCOPE_PUBLISHED;
		$address = 'street and city';
		$addressScope = IAccountManager::SCOPE_PUBLISHED;
		$twitter = '@nextclouders';
		$twitterScope = IAccountManager::SCOPE_PUBLISHED;

		// Display name and email are not changed.
		$expectedProperties = $defaultProperties;
		$expectedProperties[IAccountManager::PROPERTY_AVATAR]['scope'] = $avatarScope;
		$expectedProperties[IAccountManager::PROPERTY_PHONE]['value'] = $phone;
		$expectedProperties[IAccountManager::PROPERTY_PHONE]['scope'] = $phoneScope;
		$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['value'] = $website;
		$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['scope'] = $websiteScope;
		$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['value'] = $address;
		$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['scope'] = $addressScope;
		$expectedProperties[IAccountManager::PROPERTY_TWITTER]['value'] = $twitter;
		$expectedProperties[IAccountManager::PROPERTY_TWITTER]['scope'] = $twitterScope;

		$this->mailer->expects($this->once())->method('validateMailAddress')
			->willReturn(true);

		$controller->expects($this->once())
			->method('saveUserSettings')
			->with($user, $expectedProperties)
			->willReturnArgument(1);

		$result = $controller->setUserSettings(
			$avatarScope,
			$displayName,
			$displayNameScope,
			$phone,
			$phoneScope,
			$email,
			$emailScope,
			$website,
			$websiteScope,
			$address,
			$addressScope,
			$twitter,
			$twitterScope
		);
	}

	public function testSetUserSettingsWhenFederatedFilesharingNotEnabled() {
		$controller = $this->getController(false, ['saveUserSettings']);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('johndoe');

		$this->userSession->method('getUser')->willReturn($user);

		$defaultProperties = $this->getDefaultAccountManagerUserData();

		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn($defaultProperties);

		$this->appManager->expects($this->any())
			->method('isEnabledForUser')
			->with('federatedfilesharing')
			->willReturn(false);

		$avatarScope = IAccountManager::SCOPE_PUBLISHED;
		$displayName = 'Display name';
		$displayNameScope = IAccountManager::SCOPE_PUBLISHED;
		$phone = '47658468';
		$phoneScope = IAccountManager::SCOPE_PUBLISHED;
		$email = 'john@example.com';
		$emailScope = IAccountManager::SCOPE_PUBLISHED;
		$website = 'nextcloud.com';
		$websiteScope = IAccountManager::SCOPE_PUBLISHED;
		$address = 'street and city';
		$addressScope = IAccountManager::SCOPE_PUBLISHED;
		$twitter = '@nextclouders';
		$twitterScope = IAccountManager::SCOPE_PUBLISHED;

		// All settings are changed (in the past phone, website, address and
		// twitter were not changed).
		$expectedProperties = $defaultProperties;
		$expectedProperties[IAccountManager::PROPERTY_AVATAR]['scope'] = $avatarScope;
		$expectedProperties[IAccountManager::PROPERTY_DISPLAYNAME]['value'] = $displayName;
		$expectedProperties[IAccountManager::PROPERTY_DISPLAYNAME]['scope'] = $displayNameScope;
		$expectedProperties[IAccountManager::PROPERTY_EMAIL]['value'] = $email;
		$expectedProperties[IAccountManager::PROPERTY_EMAIL]['scope'] = $emailScope;
		$expectedProperties[IAccountManager::PROPERTY_PHONE]['value'] = $phone;
		$expectedProperties[IAccountManager::PROPERTY_PHONE]['scope'] = $phoneScope;
		$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['value'] = $website;
		$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['scope'] = $websiteScope;
		$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['value'] = $address;
		$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['scope'] = $addressScope;
		$expectedProperties[IAccountManager::PROPERTY_TWITTER]['value'] = $twitter;
		$expectedProperties[IAccountManager::PROPERTY_TWITTER]['scope'] = $twitterScope;

		$this->mailer->expects($this->once())->method('validateMailAddress')
			->willReturn(true);

		$controller->expects($this->once())
			->method('saveUserSettings')
			->with($user, $expectedProperties)
			->willReturnArgument(1);

		$result = $controller->setUserSettings(
			$avatarScope,
			$displayName,
			$displayNameScope,
			$phone,
			$phoneScope,
			$email,
			$emailScope,
			$website,
			$websiteScope,
			$address,
			$addressScope,
			$twitter,
			$twitterScope
		);
	}

	/**
	 * @dataProvider dataTestSetUserSettingsSubset
	 *
	 * @param string $property
	 * @param string $propertyValue
	 */
	public function testSetUserSettingsSubset($property, $propertyValue) {
		$controller = $this->getController(false, ['saveUserSettings']);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('johndoe');

		$this->userSession->method('getUser')->willReturn($user);

		$defaultProperties = $this->getDefaultAccountManagerUserData();

		$this->accountManager->expects($this->once())
			->method('getUser')
			->with($user)
			->willReturn($defaultProperties);

		$avatarScope = ($property === 'avatarScope') ? $propertyValue : null;
		$displayName = ($property === 'displayName') ? $propertyValue : null;
		$displayNameScope = ($property === 'displayNameScope') ? $propertyValue : null;
		$phone = ($property === 'phone') ? $propertyValue : null;
		$phoneScope = ($property === 'phoneScope') ? $propertyValue : null;
		$email = ($property === 'email') ? $propertyValue : null;
		$emailScope = ($property === 'emailScope') ? $propertyValue : null;
		$website = ($property === 'website') ? $propertyValue : null;
		$websiteScope = ($property === 'websiteScope') ? $propertyValue : null;
		$address = ($property === 'address') ? $propertyValue : null;
		$addressScope = ($property === 'addressScope') ? $propertyValue : null;
		$twitter = ($property === 'twitter') ? $propertyValue : null;
		$twitterScope = ($property === 'twitterScope') ? $propertyValue : null;

		$expectedProperties = $defaultProperties;
		if ($property === 'avatarScope') {
			$expectedProperties[IAccountManager::PROPERTY_AVATAR]['scope'] = $propertyValue;
		}
		if ($property === 'displayName') {
			$expectedProperties[IAccountManager::PROPERTY_DISPLAYNAME]['value'] = $propertyValue;
		}
		if ($property === 'displayNameScope') {
			$expectedProperties[IAccountManager::PROPERTY_DISPLAYNAME]['scope'] = $propertyValue;
		}
		if ($property === 'phone') {
			$expectedProperties[IAccountManager::PROPERTY_PHONE]['value'] = $propertyValue;
		}
		if ($property === 'phoneScope') {
			$expectedProperties[IAccountManager::PROPERTY_PHONE]['scope'] = $propertyValue;
		}
		if ($property === 'email') {
			$expectedProperties[IAccountManager::PROPERTY_EMAIL]['value'] = $propertyValue;
		}
		if ($property === 'emailScope') {
			$expectedProperties[IAccountManager::PROPERTY_EMAIL]['scope'] = $propertyValue;
		}
		if ($property === 'website') {
			$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['value'] = $propertyValue;
		}
		if ($property === 'websiteScope') {
			$expectedProperties[IAccountManager::PROPERTY_WEBSITE]['scope'] = $propertyValue;
		}
		if ($property === 'address') {
			$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['value'] = $propertyValue;
		}
		if ($property === 'addressScope') {
			$expectedProperties[IAccountManager::PROPERTY_ADDRESS]['scope'] = $propertyValue;
		}
		if ($property === 'twitter') {
			$expectedProperties[IAccountManager::PROPERTY_TWITTER]['value'] = $propertyValue;
		}
		if ($property === 'twitterScope') {
			$expectedProperties[IAccountManager::PROPERTY_TWITTER]['scope'] = $propertyValue;
		}

		if (!empty($email)) {
			$this->mailer->expects($this->once())->method('validateMailAddress')
			->willReturn(true);
		}

		$controller->expects($this->once())
			->method('saveUserSettings')
			->with($user, $expectedProperties)
			->willReturnArgument(1);

		$result = $controller->setUserSettings(
			$avatarScope,
			$displayName,
			$displayNameScope,
			$phone,
			$phoneScope,
			$email,
			$emailScope,
			$website,
			$websiteScope,
			$address,
			$addressScope,
			$twitter,
			$twitterScope
		);
	}

	public function dataTestSetUserSettingsSubset() {
		return [
			['avatarScope', IAccountManager::SCOPE_PUBLISHED],
			['displayName', 'Display name'],
			['displayNameScope', IAccountManager::SCOPE_PUBLISHED],
			['phone', '47658468'],
			['phoneScope', IAccountManager::SCOPE_PUBLISHED],
			['email', 'john@example.com'],
			['emailScope', IAccountManager::SCOPE_PUBLISHED],
			['website', 'nextcloud.com'],
			['websiteScope', IAccountManager::SCOPE_PUBLISHED],
			['address', 'street and city'],
			['addressScope', IAccountManager::SCOPE_PUBLISHED],
			['twitter', '@nextclouders'],
			['twitterScope', IAccountManager::SCOPE_PUBLISHED],
		];
	}

	/**
	 * @dataProvider dataTestSaveUserSettings
	 *
	 * @param array $data
	 * @param string $oldEmailAddress
	 * @param string $oldDisplayName
	 */
	public function testSaveUserSettings($data,
										 $oldEmailAddress,
										 $oldDisplayName
	) {
		$controller = $this->getController();
		$user = $this->createMock(IUser::class);

		$user->method('getDisplayName')->willReturn($oldDisplayName);
		$user->method('getEMailAddress')->willReturn($oldEmailAddress);
		$user->method('canChangeDisplayName')->willReturn(true);

		if ($data[IAccountManager::PROPERTY_EMAIL]['value'] === $oldEmailAddress ||
			($oldEmailAddress === null && $data[IAccountManager::PROPERTY_EMAIL]['value'] === '')) {
			$user->expects($this->never())->method('setEMailAddress');
		} else {
			$user->expects($this->once())->method('setEMailAddress')
				->with($data[IAccountManager::PROPERTY_EMAIL]['value'])
				->willReturn(true);
		}

		if ($data[IAccountManager::PROPERTY_DISPLAYNAME]['value'] === $oldDisplayName ||
			($oldDisplayName === null && $data[IAccountManager::PROPERTY_DISPLAYNAME]['value'] === '')) {
			$user->expects($this->never())->method('setDisplayName');
		} else {
			$user->expects($this->once())->method('setDisplayName')
				->with($data[IAccountManager::PROPERTY_DISPLAYNAME]['value'])
				->willReturn(true);
		}

		$this->accountManager->expects($this->once())->method('updateUser')
			->with($user, $data);

		$this->invokePrivate($controller, 'saveUserSettings', [$user, $data]);
	}

	public function dataTestSaveUserSettings() {
		return [
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'john@example.com',
				'john doe'
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'johnNew@example.com',
				'john New doe'
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'johnNew@example.com',
				'john doe'
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'john@example.com',
				'john New doe'
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => ''],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				null,
				'john New doe'
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'john@example.com',
				null
			],

		];
	}

	/**
	 * @dataProvider dataTestSaveUserSettingsException
	 *
	 * @param array $data
	 * @param string $oldEmailAddress
	 * @param string $oldDisplayName
	 * @param bool $setDisplayNameResult
	 * @param bool $canChangeEmail
	 *
	 */
	public function testSaveUserSettingsException($data,
												  $oldEmailAddress,
												  $oldDisplayName,
												  $setDisplayNameResult,
												  $canChangeEmail
	) {
		$this->expectException(\OC\ForbiddenException::class);

		$controller = $this->getController();
		$user = $this->createMock(IUser::class);

		$user->method('getDisplayName')->willReturn($oldDisplayName);
		$user->method('getEMailAddress')->willReturn($oldEmailAddress);

		if ($data[IAccountManager::PROPERTY_EMAIL]['value'] !== $oldEmailAddress) {
			$user->method('canChangeDisplayName')
				->willReturn($canChangeEmail);
		}

		if ($data[IAccountManager::PROPERTY_DISPLAYNAME]['value'] !== $oldDisplayName) {
			$user->method('setDisplayName')
				->with($data[IAccountManager::PROPERTY_DISPLAYNAME]['value'])
				->willReturn($setDisplayNameResult);
		}

		$this->invokePrivate($controller, 'saveUserSettings', [$user, $data]);
	}


	public function dataTestSaveUserSettingsException() {
		return [
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'johnNew@example.com',
				'john New doe',
				true,
				false
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'johnNew@example.com',
				'john New doe',
				false,
				true
			],
			[
				[
					IAccountManager::PROPERTY_EMAIL => ['value' => 'john@example.com'],
					IAccountManager::PROPERTY_DISPLAYNAME => ['value' => 'john doe'],
				],
				'johnNew@example.com',
				'john New doe',
				false,
				false
			],

		];
	}

	/**
	 * @param string $account
	 * @param string $type
	 * @param array $dataBefore
	 * @param array $expectedData
	 *
	 * @dataProvider dataTestGetVerificationCode
	 */
	public function testGetVerificationCode($account, $type, $dataBefore, $expectedData, $onlyVerificationCode) {
		$message = 'Use my Federated Cloud ID to share with me: user@nextcloud.com';
		$signature = 'theSignature';

		$code = $message . ' ' . $signature;
		if ($type === IAccountManager::PROPERTY_TWITTER) {
			$code = $message . ' ' . md5($signature);
		}

		$controller = $this->getController(false, ['signMessage', 'getCurrentTime']);

		$user = $this->createMock(IUser::class);
		$this->userSession->expects($this->once())->method('getUser')->willReturn($user);
		$this->accountManager->expects($this->once())->method('getUser')->with($user)->willReturn($dataBefore);
		$user->expects($this->any())->method('getCloudId')->willReturn('user@nextcloud.com');
		$user->expects($this->any())->method('getUID')->willReturn('uid');
		$controller->expects($this->once())->method('signMessage')->with($user, $message)->willReturn($signature);
		$controller->expects($this->any())->method('getCurrentTime')->willReturn(1234567);

		if ($onlyVerificationCode === false) {
			$this->accountManager->expects($this->once())->method('updateUser')->with($user, $expectedData)->willReturnArgument(1);
			$this->jobList->expects($this->once())->method('add')
				->with('OCA\Settings\BackgroundJobs\VerifyUserData',
					[
						'verificationCode' => $code,
						'data' => $dataBefore[$type]['value'],
						'type' => $type,
						'uid' => 'uid',
						'try' => 0,
						'lastRun' => 1234567
					]);
		}

		$result = $controller->getVerificationCode($account, $onlyVerificationCode);

		$data = $result->getData();
		$this->assertSame(Http::STATUS_OK, $result->getStatus());
		$this->assertSame($code, $data['code']);
	}

	public function dataTestGetVerificationCode() {
		$accountDataBefore = [
			IAccountManager::PROPERTY_WEBSITE => ['value' => 'https://nextcloud.com', 'verified' => IAccountManager::NOT_VERIFIED],
			IAccountManager::PROPERTY_TWITTER => ['value' => '@nextclouders', 'verified' => IAccountManager::NOT_VERIFIED, 'signature' => 'theSignature'],
		];

		$accountDataAfterWebsite = [
			IAccountManager::PROPERTY_WEBSITE => ['value' => 'https://nextcloud.com', 'verified' => IAccountManager::VERIFICATION_IN_PROGRESS, 'signature' => 'theSignature'],
			IAccountManager::PROPERTY_TWITTER => ['value' => '@nextclouders', 'verified' => IAccountManager::NOT_VERIFIED, 'signature' => 'theSignature'],
		];

		$accountDataAfterTwitter = [
			IAccountManager::PROPERTY_WEBSITE => ['value' => 'https://nextcloud.com', 'verified' => IAccountManager::NOT_VERIFIED],
			IAccountManager::PROPERTY_TWITTER => ['value' => '@nextclouders', 'verified' => IAccountManager::VERIFICATION_IN_PROGRESS, 'signature' => 'theSignature'],
		];

		return [
			['verify-twitter', IAccountManager::PROPERTY_TWITTER, $accountDataBefore, $accountDataAfterTwitter, false],
			['verify-website', IAccountManager::PROPERTY_WEBSITE, $accountDataBefore, $accountDataAfterWebsite, false],
			['verify-twitter', IAccountManager::PROPERTY_TWITTER, $accountDataBefore, $accountDataAfterTwitter, true],
			['verify-website', IAccountManager::PROPERTY_WEBSITE, $accountDataBefore, $accountDataAfterWebsite, true],
		];
	}

	/**
	 * test get verification code in case no valid user was given
	 */
	public function testGetVerificationCodeInvalidUser() {
		$controller = $this->getController();
		$this->userSession->expects($this->once())->method('getUser')->willReturn(null);
		$result = $controller->getVerificationCode('account', false);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $result->getStatus());
	}

	/**
	 * @dataProvider dataTestCanAdminChangeUserPasswords
	 *
	 * @param bool $encryptionEnabled
	 * @param bool $encryptionModuleLoaded
	 * @param bool $masterKeyEnabled
	 * @param bool $expected
	 */
	public function testCanAdminChangeUserPasswords($encryptionEnabled,
													$encryptionModuleLoaded,
													$masterKeyEnabled,
													$expected) {
		$controller = $this->getController();

		$this->encryptionManager->expects($this->any())
			->method('isEnabled')
			->willReturn($encryptionEnabled);
		$this->encryptionManager->expects($this->any())
			->method('getEncryptionModule')
			->willReturnCallback(function () use ($encryptionModuleLoaded) {
				if ($encryptionModuleLoaded) {
					return $this->encryptionModule;
				} else {
					throw new ModuleDoesNotExistsException();
				}
			});
		$this->encryptionModule->expects($this->any())
			->method('needDetailedAccessList')
			->willReturn(!$masterKeyEnabled);

		$result = $this->invokePrivate($controller, 'canAdminChangeUserPasswords', []);
		$this->assertSame($expected, $result);
	}

	public function dataTestCanAdminChangeUserPasswords() {
		return [
			// encryptionEnabled, encryptionModuleLoaded, masterKeyEnabled, expectedResult
			[true, true, true, true],
			[false, true, true, true],
			[true, false, true, false],
			[false, false, true, true],
			[true, true, false, false],
			[false, true, false, false],
			[true, false, false, false],
			[false, false, false, true],
		];
	}
}
