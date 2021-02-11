<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author GretaD <gretadoci@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author michag86 <micha_g@arcor.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sujith Haridasan <sujith.h@gmail.com>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
 * @author zulan <git@zulan.net>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Provisioning_API\Tests\Controller;

use Exception;
use OC\Accounts\AccountManager;
use OC\Authentication\Token\RemoteWipe;
use OC\Group\Manager;
use OC\SubAdmin;
use OCA\FederatedFileSharing\FederatedShareProvider;
use OCA\Provisioning_API\Controller\UsersController;
use OCA\Provisioning_API\FederatedShareProviderFactory;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\DataResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\ISecureRandom;
use OCP\UserInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UsersControllerTest extends TestCase {

	/** @var IUserManager|MockObject */
	protected $userManager;
	/** @var IConfig|MockObject */
	protected $config;
	/** @var IAppManager|MockObject */
	protected $appManager;
	/** @var Manager|MockObject */
	protected $groupManager;
	/** @var IUserSession|MockObject */
	protected $userSession;
	/** @var ILogger|MockObject */
	protected $logger;
	/** @var UsersController|MockObject */
	protected $api;
	/** @var AccountManager|MockObject */
	protected $accountManager;
	/** @var IURLGenerator|MockObject */
	protected $urlGenerator;
	/** @var IRequest|MockObject */
	protected $request;
	/** @var IFactory|MockObject */
	private $l10nFactory;
	/** @var NewUserMailHelper|MockObject */
	private $newUserMailHelper;
	/** @var FederatedShareProviderFactory|MockObject */
	private $federatedShareProviderFactory;
	/** @var ISecureRandom|MockObject */
	private $secureRandom;
	/** @var RemoteWipe|MockObject */
	private $remoteWipe;
	/** @var IEventDispatcher */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->groupManager = $this->createMock(Manager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->request = $this->createMock(IRequest::class);
		$this->accountManager = $this->createMock(AccountManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->newUserMailHelper = $this->createMock(NewUserMailHelper::class);
		$this->federatedShareProviderFactory = $this->createMock(FederatedShareProviderFactory::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->remoteWipe = $this->createMock(RemoteWipe::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);

		$this->api = $this->getMockBuilder(UsersController::class)
			->setConstructorArgs([
				'provisioning_api',
				$this->request,
				$this->userManager,
				$this->config,
				$this->appManager,
				$this->groupManager,
				$this->userSession,
				$this->accountManager,
				$this->urlGenerator,
				$this->logger,
				$this->l10nFactory,
				$this->newUserMailHelper,
				$this->federatedShareProviderFactory,
				$this->secureRandom,
				$this->remoteWipe,
				$this->eventDispatcher,
			])
			->setMethods(['fillStorageInfo'])
			->getMock();
	}

	public function testGetUsersAsAdmin() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->willReturn(true);
		$this->userManager
			->expects($this->once())
			->method('search')
			->with('MyCustomSearch')
			->willReturn(['Admin' => [], 'Foo' => [], 'Bar' => []]);

		$expected = ['users' => [
			'Admin',
			'Foo',
			'Bar',
		],
		];
		$this->assertEquals($expected, $this->api->getUsers('MyCustomSearch')->getData());
	}

	public function testGetUsersAsSubAdmin() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('subadmin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->willReturn(false);
		$firstGroup = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$firstGroup
			->expects($this->once())
			->method('getGID')
			->willReturn('FirstGroup');
		$secondGroup = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$secondGroup
			->expects($this->once())
			->method('getGID')
			->willReturn('SecondGroup');
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdmin')
			->with($loggedInUser)
			->willReturn(true);
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->with($loggedInUser)
			->willReturn([$firstGroup, $secondGroup]);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('displayNamesInGroup')
			->will($this->onConsecutiveCalls(['AnotherUserInTheFirstGroup' => []], ['UserInTheSecondGroup' => []]));

		$expected = [
			'users' => [
				'AnotherUserInTheFirstGroup',
				'UserInTheSecondGroup',
			],
		];
		$this->assertEquals($expected, $this->api->getUsers('MyCustomSearch')->getData());
	}


	public function testAddUserAlreadyExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('AlreadyExistingUser')
			->willReturn(true);
		$this->logger
			->expects($this->once())
			->method('error')
			->with('Failed addUser attempt: User already exists.', ['app' => 'ocs_api']);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);

		$this->api->addUser('AlreadyExistingUser', 'password', '', '', []);
	}


	public function testAddUserNonExistingGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('group NonExistingGroup does not exist');
		$this->expectExceptionCode(104);

		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('NonExistingGroup')
			->willReturn(false);

		$this->api->addUser('NewUser', 'pass', '', '', ['NonExistingGroup']);
	}


	public function testAddUserExistingGroupNonExistingGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('group NonExistingGroup does not exist');
		$this->expectExceptionCode(104);

		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$this->groupManager
			->expects($this->exactly(2))
			->method('groupExists')
			->withConsecutive(
				['ExistingGroup'],
				['NonExistingGroup']
			)
			->willReturnMap([
				['ExistingGroup', true],
				['NonExistingGroup', false]
			]);

		$this->api->addUser('NewUser', 'pass', '', '', ['ExistingGroup', 'NonExistingGroup']);
	}

	public function testAddUserSuccessful() {
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with('NewUser', 'PasswordOfTheNewUser');
		$this->logger
			->expects($this->once())
			->method('info')
			->with('Successful addUser call with userid: NewUser', ['app' => 'ocs_api']);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);

		$this->assertTrue(key_exists(
			'id',
			$this->api->addUser('NewUser', 'PasswordOfTheNewUser')->getData()
		));
	}

	public function testAddUserSuccessfulWithDisplayName() {
		$api = $this->getMockBuilder(UsersController::class)
			->setConstructorArgs([
				'provisioning_api',
				$this->request,
				$this->userManager,
				$this->config,
				$this->appManager,
				$this->groupManager,
				$this->userSession,
				$this->accountManager,
				$this->urlGenerator,
				$this->logger,
				$this->l10nFactory,
				$this->newUserMailHelper,
				$this->federatedShareProviderFactory,
				$this->secureRandom,
				$this->remoteWipe,
				$this->eventDispatcher,
			])
			->setMethods(['editUser'])
			->getMock();

		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with('NewUser', 'PasswordOfTheNewUser');
		$this->logger
			->expects($this->once())
			->method('info')
			->with('Successful addUser call with userid: NewUser', ['app' => 'ocs_api']);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->any())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$api
			->expects($this->once())
			->method('editUser')
			->with('NewUser', 'display', 'DisplayNameOfTheNewUser');

		$this->assertTrue(key_exists(
			'id',
			$api->addUser('NewUser', 'PasswordOfTheNewUser', 'DisplayNameOfTheNewUser')->getData()
		));
	}

	public function testAddUserSuccessfulGenerateUserID() {
		$this->config
			->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(function ($appid, $key, $default) {
				if ($key === 'newUser.generateUserID') {
					return 'yes';
				}
				return null;
			});
		$this->userManager
			->expects($this->any())
			->method('userExists')
			->with($this->anything())
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with($this->anything(), 'PasswordOfTheNewUser');
		$this->logger
			->expects($this->once())
			->method('info')
			->with($this->stringStartsWith('Successful addUser call with userid: '), ['app' => 'ocs_api']);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$this->secureRandom->expects($this->any())
			->method('generate')
			->with(10)
			->willReturnCallback(function () {
				return (string)rand(1000000000, 9999999999);
			});

		$this->assertTrue(key_exists(
			'id',
			$this->api->addUser('', 'PasswordOfTheNewUser')->getData()
		));
	}

	public function testAddUserSuccessfulGeneratePassword() {
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$newUser = $this->createMock(IUser::class);
		$newUser->expects($this->once())
			->method('setEMailAddress');
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->willReturn($newUser);
		$this->logger
			->expects($this->once())
			->method('info')
			->with('Successful addUser call with userid: NewUser', ['app' => 'ocs_api']);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$this->eventDispatcher
			->expects($this->once())
			->method('dispatchTyped')
			->with(new GenerateSecurePasswordEvent());

		$this->assertTrue(key_exists(
			'id',
			$this->api->addUser('NewUser', '', '', 'foo@bar')->getData()
		));
	}


	public function testAddUserFailedToGenerateUserID() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Could not create non-existing user id');
		$this->expectExceptionCode(111);

		$this->config
			->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(function ($appid, $key, $default) {
				if ($key === 'newUser.generateUserID') {
					return 'yes';
				}
				return null;
			});
		$this->userManager
			->expects($this->any())
			->method('userExists')
			->with($this->anything())
			->willReturn(true);
		$this->userManager
			->expects($this->never())
			->method('createUser');
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);

		$this->api->addUser('', 'PasswordOfTheNewUser')->getData();
	}


	public function testAddUserEmailRequired() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Required email address was not provided');
		$this->expectExceptionCode(110);

		$this->config
			->expects($this->any())
			->method('getAppValue')
			->willReturnCallback(function ($appid, $key, $default) {
				if ($key === 'newUser.requireEmail') {
					return 'yes';
				}
				return null;
			});
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$this->userManager
			->expects($this->never())
			->method('createUser');
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);

		$this->assertTrue(key_exists(
			'id',
			$this->api->addUser('NewUser', 'PasswordOfTheNewUser')->getData()
		));
	}

	public function testAddUserExistingGroup() {
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('ExistingGroup')
			->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with('NewUser', 'PasswordOfTheNewUser')
			->willReturn($user);
		$group = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$group
			->expects($this->once())
			->method('addUser')
			->with($user);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('ExistingGroup')
			->willReturn($group);
		$this->logger
			->expects($this->exactly(2))
			->method('info')
			->withConsecutive(
				['Successful addUser call with userid: NewUser', ['app' => 'ocs_api']],
				['Added userid NewUser to group ExistingGroup', ['app' => 'ocs_api']]
			);

		$this->assertTrue(key_exists(
			'id',
				$this->api->addUser('NewUser', 'PasswordOfTheNewUser', '', '', ['ExistingGroup'])->getData()
		));
	}


	public function testAddUserUnsuccessful() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Bad request');
		$this->expectExceptionCode(101);

		$exception = new Exception('User backend not found.');
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with('NewUser', 'PasswordOfTheNewUser')
			->will($this->throwException($exception));
		$this->logger
			->expects($this->once())
			->method('logException')
			->with($exception, [
				'message' => 'Failed addUser attempt with exception.',
				'level' => ILogger::ERROR,
				'app' => 'ocs_api',
			]);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('adminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('adminUser')
			->willReturn(true);

		$this->api->addUser('NewUser', 'PasswordOfTheNewUser');
	}


	public function testAddUserAsSubAdminNoGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('no group specified (required for subadmins)');
		$this->expectExceptionCode(106);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('regularUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('regularUser')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->with()
			->willReturn($subAdminManager);

		$this->api->addUser('NewUser', 'PasswordOfTheNewUser', '', '', []);
	}


	public function testAddUserAsSubAdminValidGroupNotSubAdmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('insufficient privileges for group ExistingGroup');
		$this->expectExceptionCode(105);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('regularUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('regularUser')
			->willReturn(false);
		$existingGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('ExistingGroup')
			->willReturn($existingGroup);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($loggedInUser, $existingGroup)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->with()
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->once())
			->method('groupExists')
			->with('ExistingGroup')
			->willReturn(true);

		$this->api->addUser('NewUser', 'PasswordOfTheNewUser', '', '', ['ExistingGroup'])->getData();
	}

	public function testAddUserAsSubAdminExistingGroups() {
		$this->userManager
			->expects($this->once())
			->method('userExists')
			->with('NewUser')
			->willReturn(false);
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('subAdminUser');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subAdminUser')
			->willReturn(false);
		$this->groupManager
			->expects($this->exactly(2))
			->method('groupExists')
			->withConsecutive(
				['ExistingGroup1'],
				['ExistingGroup2']
			)
			->willReturn(true);
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userManager
			->expects($this->once())
			->method('createUser')
			->with('NewUser', 'PasswordOfTheNewUser')
			->willReturn($user);
		$existingGroup1 = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$existingGroup2 = $this->getMockBuilder('OCP\IGroup')
			->disableOriginalConstructor()
			->getMock();
		$existingGroup1
			->expects($this->once())
			->method('addUser')
			->with($user);
		$existingGroup2
			->expects($this->once())
			->method('addUser')
			->with($user);
		$this->groupManager
			->expects($this->exactly(4))
			->method('get')
			->withConsecutive(
				['ExistingGroup1'],
				['ExistingGroup2'],
				['ExistingGroup1'],
				['ExistingGroup2']
			)
			->willReturnMap([
				['ExistingGroup1', $existingGroup1],
				['ExistingGroup2', $existingGroup2]
			]);
		$this->logger
			->expects($this->exactly(3))
			->method('info')
			->withConsecutive(
				['Successful addUser call with userid: NewUser', ['app' => 'ocs_api']],
				['Added userid NewUser to group ExistingGroup1', ['app' => 'ocs_api']],
				['Added userid NewUser to group ExistingGroup2', ['app' => 'ocs_api']]
			);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$subAdminManager
			->expects($this->exactly(2))
			->method('isSubAdminOfGroup')
			->withConsecutive(
				[$loggedInUser, $existingGroup1],
				[$loggedInUser, $existingGroup2]
			)
			->willReturn(true);

		$this->assertTrue(key_exists(
			'id',
			$this->api->addUser('NewUser', 'PasswordOfTheNewUser', '', '', ['ExistingGroup1', 'ExistingGroup2'])->getData()
		));
	}


	public function testGetUserTargetDoesNotExist() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('User does not exist');
		$this->expectExceptionCode(404);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn(null);

		$this->api->getUser('UserToGet');
	}

	public function testGetUserDataAsAdmin() {
		$group = $this->getMockBuilder(IGroup::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser->expects($this->once())
			->method('getEMailAddress')
			->willReturn('demo@nextcloud.com');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->exactly(2))
			->method('get')
			->with('UID')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$this->groupManager
			->expects($this->any())
			->method('getUserGroups')
			->willReturn([$group, $group, $group]);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->willReturn([$group]);
		$group->expects($this->at(0))
			->method('getGID')
			->willReturn('group0');
		$group->expects($this->at(1))
			->method('getGID')
			->willReturn('group1');
		$group->expects($this->at(2))
			->method('getGID')
			->willReturn('group2');
		$group->expects($this->at(3))
			->method('getGID')
			->willReturn('group3');
		$this->accountManager->expects($this->any())->method('getUser')
			->with($targetUser)
			->willReturn(
				[
					IAccountManager::PROPERTY_ADDRESS => ['value' => 'address'],
					IAccountManager::PROPERTY_PHONE => ['value' => 'phone'],
					IAccountManager::PROPERTY_TWITTER => ['value' => 'twitter'],
					IAccountManager::PROPERTY_WEBSITE => ['value' => 'website'],
				]
			);
		$this->config
			->expects($this->at(0))
			->method('getUserValue')
			->with('UID', 'core', 'enabled', 'true')
			->willReturn('true');
		$this->api
			->expects($this->once())
			->method('fillStorageInfo')
			->with('UID')
			->willReturn(['DummyValue']);

		$backend = $this->createMock(UserInterface::class);
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$targetUser
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Demo User');
		$targetUser
			->expects($this->once())
			->method('getHome')
			->willReturn('/var/www/newtcloud/data/UID');
		$targetUser
			->expects($this->once())
			->method('getLastLogin')
			->willReturn(1521191471);
		$targetUser
			->expects($this->once())
			->method('getBackendClassName')
			->willReturn('Database');
		$targetUser
			->expects($this->once())
			->method('getBackend')
			->willReturn($backend);
		$targetUser
			->method('getUID')
			->willReturn('UID');

		$this->l10nFactory
			->expects($this->once())
			->method('getUserLanguage')
			->with($targetUser)
			->willReturn('de');

		$expected = [
			'id' => 'UID',
			'enabled' => true,
			'storageLocation' => '/var/www/newtcloud/data/UID',
			'lastLogin' => 1521191471000,
			'backend' => 'Database',
			'subadmin' => ['group3'],
			'quota' => ['DummyValue'],
			'email' => 'demo@nextcloud.com',
			'displayname' => 'Demo User',
			'phone' => 'phone',
			'address' => 'address',
			'website' => 'website',
			'twitter' => 'twitter',
			'groups' => ['group0', 'group1', 'group2'],
			'language' => 'de',
			'locale' => null,
			'backendCapabilities' => [
				'setDisplayName' => true,
				'setPassword' => true,
			]
		];
		$this->assertEquals($expected, $this->invokePrivate($this->api, 'getUserData', ['UID']));
	}

	public function testGetUserDataAsSubAdminAndUserIsAccessible() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser
				->expects($this->once())
				->method('getEMailAddress')
				->willReturn('demo@nextcloud.com');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->exactly(2))
			->method('get')
			->with('UID')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$this->groupManager
			->expects($this->any())
			->method('getUserGroups')
			->willReturn([]);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->willReturn([]);
		$this->groupManager
			->expects($this->exactly(2))
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->config
			->expects($this->at(0))
			->method('getUserValue')
			->with('UID', 'core', 'enabled', 'true')
			->willReturn('true');
		$this->api
			->expects($this->once())
			->method('fillStorageInfo')
			->with('UID')
			->willReturn(['DummyValue']);

		$backend = $this->createMock(UserInterface::class);
		$backend->expects($this->any())
			->method('implementsActions')
			->willReturn(true);

		$targetUser
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Demo User');
		$targetUser
			->expects($this->once())
			->method('getHome')
			->willReturn('/var/www/newtcloud/data/UID');
		$targetUser
			->expects($this->once())
			->method('getLastLogin')
			->willReturn(1521191471);
		$targetUser
			->expects($this->once())
			->method('getBackendClassName')
			->willReturn('Database');
		$targetUser
			->expects($this->once())
			->method('getBackend')
			->willReturn($backend);
		$targetUser
			->method('getUID')
			->willReturn('UID');
		$this->accountManager->expects($this->any())->method('getUser')
			->with($targetUser)
			->willReturn(
				[
					IAccountManager::PROPERTY_ADDRESS => ['value' => 'address'],
					IAccountManager::PROPERTY_PHONE => ['value' => 'phone'],
					IAccountManager::PROPERTY_TWITTER => ['value' => 'twitter'],
					IAccountManager::PROPERTY_WEBSITE => ['value' => 'website'],
				]
			);

		$this->l10nFactory
			->expects($this->once())
			->method('getUserLanguage')
			->with($targetUser)
			->willReturn('da');

		$expected = [
			'id' => 'UID',
			'enabled' => true,
			'storageLocation' => '/var/www/newtcloud/data/UID',
			'lastLogin' => 1521191471000,
			'backend' => 'Database',
			'subadmin' => [],
			'quota' => ['DummyValue'],
			'email' => 'demo@nextcloud.com',
			'displayname' => 'Demo User',
			'phone' => 'phone',
			'address' => 'address',
			'website' => 'website',
			'twitter' => 'twitter',
			'groups' => [],
			'language' => 'da',
			'locale' => null,
			'backendCapabilities' => [
				'setDisplayName' => true,
				'setPassword' => true,
			]
		];
		$this->assertEquals($expected, $this->invokePrivate($this->api, 'getUserData', ['UID']));
	}



	public function testGetUserDataAsSubAdminAndUserIsNotAccessible() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->invokePrivate($this->api, 'getUser', ['UserToGet']);
	}

	public function testGetUserDataAsSubAdminSelfLookup() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->exactly(2))
			->method('get')
			->with('UID')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('UID')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->willReturn([]);
		$this->groupManager
			->expects($this->exactly(2))
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('getUserGroups')
			->willReturn([]);
		$this->api
			->expects($this->once())
			->method('fillStorageInfo')
			->with('UID')
			->willReturn(['DummyValue']);

		$backend = $this->createMock(UserInterface::class);
		$backend->expects($this->atLeastOnce())
			->method('implementsActions')
			->willReturn(false);

		$targetUser
			->expects($this->once())
			->method('getDisplayName')
			->willReturn('Subadmin User');
		$targetUser
			->expects($this->once())
			->method('getEMailAddress')
			->willReturn('subadmin@nextcloud.com');
		$targetUser
			->method('getUID')
			->willReturn('UID');
		$targetUser
			->expects($this->once())
			->method('getHome')
			->willReturn('/var/www/newtcloud/data/UID');
		$targetUser
			->expects($this->once())
			->method('getLastLogin')
			->willReturn(1521191471);
		$targetUser
			->expects($this->once())
			->method('getBackendClassName')
			->willReturn('Database');
		$targetUser
			->expects($this->once())
			->method('getBackend')
			->willReturn($backend);
		$this->accountManager->expects($this->any())->method('getUser')
			->with($targetUser)
			->willReturn(
				[
					IAccountManager::PROPERTY_ADDRESS => ['value' => 'address'],
					IAccountManager::PROPERTY_PHONE => ['value' => 'phone'],
					IAccountManager::PROPERTY_TWITTER => ['value' => 'twitter'],
					IAccountManager::PROPERTY_WEBSITE => ['value' => 'website'],
				]
			);

		$this->l10nFactory
			->expects($this->once())
			->method('getUserLanguage')
			->with($targetUser)
			->willReturn('ru');

		$expected = [
			'id' => 'UID',
			'storageLocation' => '/var/www/newtcloud/data/UID',
			'lastLogin' => 1521191471000,
			'backend' => 'Database',
			'subadmin' => [],
			'quota' => ['DummyValue'],
			'email' => 'subadmin@nextcloud.com',
			'displayname' => 'Subadmin User',
			'phone' => 'phone',
			'address' => 'address',
			'website' => 'website',
			'twitter' => 'twitter',
			'groups' => [],
			'language' => 'ru',
			'locale' => null,
			'backendCapabilities' => [
				'setDisplayName' => false,
				'setPassword' => false,
			]
		];
		$this->assertEquals($expected, $this->invokePrivate($this->api, 'getUserData', ['UID']));
	}

	public function dataSearchByPhoneNumbers(): array {
		return [
			'Invalid country' => ['Not a country code', ['12345' => ['NaN']], 400, null, null, []],
			'No number to search' => ['DE', ['12345' => ['NaN']], 200, null, null, []],
			'Valid number but no match' => ['DE', ['12345' => ['0711 / 25 24 28-90']], 200, ['+4971125242890'], [], []],
			'Invalid number' => ['FR', ['12345' => ['0711 / 25 24 28-90']], 200, null, null, []],
			'Invalid and valid number' => ['DE', ['12345' => ['NaN', '0711 / 25 24 28-90']], 200, ['+4971125242890'], [], []],
			'Valid and invalid number' => ['DE', ['12345' => ['0711 / 25 24 28-90', 'NaN']], 200, ['+4971125242890'], [], []],
			'Valid number and a match' => ['DE', ['12345' => ['0711 / 25 24 28-90']], 200, ['+4971125242890'], ['+4971125242890' => 'admin'], ['12345' => 'admin@localhost']],
			'Same number twice, later hits' => ['DE', ['12345' => ['0711 / 25 24 28-90'], '23456' => ['0711 / 25 24 28-90']], 200, ['+4971125242890'], ['+4971125242890' => 'admin'], ['23456' => 'admin@localhost']],
		];
	}

	/**
	 * @dataProvider dataSearchByPhoneNumbers
	 * @param string $location
	 * @param array $search
	 * @param int $status
	 * @param array $expected
	 */
	public function testSearchByPhoneNumbers(string $location, array $search, int $status, ?array $searchUsers, ?array $userMatches, array $expected) {
		if ($searchUsers === null) {
			$this->accountManager->expects($this->never())
				->method('searchUsers');
		} else {
			$this->accountManager->expects($this->once())
				->method('searchUsers')
				->with(IAccountManager::PROPERTY_PHONE, $searchUsers)
				->willReturn($userMatches);
		}

		$this->urlGenerator->method('getAbsoluteURL')
			->with('/')
			->willReturn('https://localhost/');

		$response = $this->api->searchByPhoneNumbers($location, $search);

		self::assertEquals($status, $response->getStatus());
		self::assertEquals($expected, $response->getData());
	}

	public function testEditUserRegularUserSelfEditChangeDisplayName() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$targetUser
			->expects($this->once())
			->method('setDisplayName')
			->with('NewDisplayName');
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'display', 'NewDisplayName')->getData());
	}

	public function testEditUserRegularUserSelfEditChangeEmailValid() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$targetUser
			->expects($this->once())
			->method('setEMailAddress')
			->with('demo@nextcloud.com');
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'email', 'demo@nextcloud.com')->getData());
	}



	public function testEditUserRegularUserSelfEditChangeEmailInvalid() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->api->editUser('UserToEdit', 'email', 'demo.org');
	}

	public function testEditUserRegularUserSelfEditChangePassword() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$targetUser
			->expects($this->once())
			->method('canChangePassword')
			->willReturn(true);
		$targetUser
			->expects($this->once())
			->method('setPassword')
			->with('NewPassword');
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'password', 'NewPassword')->getData());
	}



	public function testEditUserRegularUserSelfEditChangeQuota() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->api->editUser('UserToEdit', 'quota', 'NewQuota');
	}

	public function testEditUserAdminUserSelfEditChangeValidQuota() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser->expects($this->once())
			->method('setQuota')
			->with('2.9 MB');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->exactly(3))
			->method('isAdmin')
			->with('UID')
			->willReturn(true);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'quota', '3042824')->getData());
	}



	public function testEditUserAdminUserSelfEditChangeInvalidQuota() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Invalid quota value ABC');
		$this->expectExceptionCode(103);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->exactly(3))
			->method('isAdmin')
			->with('UID')
			->willReturn(true);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->api->editUser('UserToEdit', 'quota', 'ABC');
	}

	public function testEditUserAdminUserEditChangeValidQuota() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser->expects($this->once())
			->method('setQuota')
			->with('2.9 MB');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'quota', '3042824')->getData());
	}

	public function testEditUserSelfEditChangeLanguage() {
		$this->l10nFactory->expects($this->once())
			->method('findAvailableLanguages')
			->willReturn(['en', 'de', 'sv']);
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturnMap([
				['allow_user_to_change_display_name', true, true],
				['force_language', false, false],
			]);

		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');
		$targetUser = $this->createMock(IUser::class);
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('UserToEdit', 'core', 'lang', 'de');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->atLeastOnce())
			->method('isAdmin')
			->with('UserToEdit')
			->willReturn(false);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'language', 'de')->getData());
	}

	public function dataEditUserSelfEditChangeLanguageButForced() {
		return [
			['de'],
			[true],
		];
	}

	/**
	 * @dataProvider dataEditUserSelfEditChangeLanguageButForced
	 */
	public function testEditUserSelfEditChangeLanguageButForced($forced) {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);

		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturnMap([
				['allow_user_to_change_display_name', true, true],
				['force_language', false, $forced],
			]);

		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');
		$targetUser = $this->createMock(IUser::class);
		$this->config->expects($this->never())
			->method('setUserValue');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->atLeastOnce())
			->method('isAdmin')
			->with('UserToEdit')
			->willReturn(false);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'language', 'de')->getData());
	}

	public function testEditUserAdminEditChangeLanguage() {
		$this->l10nFactory->expects($this->once())
			->method('findAvailableLanguages')
			->willReturn(['en', 'de', 'sv']);

		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->createMock(IUser::class);
		$this->config->expects($this->once())
			->method('setUserValue')
			->with('UserToEdit', 'core', 'lang', 'de');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$subAdminManager = $this->createMock(SubAdmin::class);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'language', 'de')->getData());
	}

	/**
	 * @dataProvider dataEditUserSelfEditChangeLanguageButForced
	 */
	public function testEditUserAdminEditChangeLanguageInvalidLanguage() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);


		$this->l10nFactory->expects($this->once())
			->method('findAvailableLanguages')
			->willReturn(['en', 'de', 'sv']);

		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->createMock(IUser::class);
		$this->config->expects($this->never())
			->method('setUserValue');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$subAdminManager = $this->createMock(SubAdmin::class);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'language', 'ru')->getData());
	}

	public function testEditUserSubadminUserAccessible() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser->expects($this->once())
			->method('setQuota')
			->with('2.9 MB');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->assertEquals([], $this->api->editUser('UserToEdit', 'quota', '3042824')->getData());
	}


	public function testEditUserSubadminUserInaccessible() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToEdit')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');

		$this->api->editUser('UserToEdit', 'quota', 'value');
	}


	public function testDeleteUserNotExistingUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UserToEdit');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn(null);

		$this->api->deleteUser('UserToDelete');
	}


	public function testDeleteUserSelf() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('UID');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);

		$this->api->deleteUser('UserToDelete');
	}

	public function testDeleteSuccessfulUserAsAdmin() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$targetUser
			->expects($this->once())
			->method('delete')
			->willReturn(true);

		$this->assertEquals([], $this->api->deleteUser('UserToDelete')->getData());
	}


	public function testDeleteUnsuccessfulUserAsAdmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$targetUser
			->expects($this->once())
			->method('delete')
			->willReturn(false);

		$this->api->deleteUser('UserToDelete');
	}

	public function testDeleteSuccessfulUserAsSubadmin() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('delete')
			->willReturn(true);

		$this->assertEquals([], $this->api->deleteUser('UserToDelete')->getData());
	}


	public function testDeleteUnsuccessfulUserAsSubadmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('delete')
			->willReturn(false);

		$this->api->deleteUser('UserToDelete');
	}


	public function testDeleteUserAsSubAdminAndUserIsNotAccessible() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UID');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToDelete')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->api->deleteUser('UserToDelete');
	}


	public function testGetUsersGroupsTargetUserNotExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(998);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->api->getUsersGroups('UserToLookup');
	}

	public function testGetUsersGroupsSelfTargetted() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UserToLookup');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UserToLookup');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToLookup')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['DummyValue']);

		$this->assertEquals(['groups' => ['DummyValue']], $this->api->getUsersGroups('UserToLookup')->getData());
	}

	public function testGetUsersGroupsForAdminUser() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UserToLookup');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToLookup')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['DummyValue']);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->assertEquals(['groups' => ['DummyValue']], $this->api->getUsersGroups('UserToLookup')->getData());
	}

	public function testGetUsersGroupsForSubAdminUserAndUserIsAccessible() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UserToLookup');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToLookup')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$group1 = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$group1
			->expects($this->any())
			->method('getGID')
			->willReturn('Group1');
		$group2 = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$group2
			->expects($this->any())
			->method('getGID')
			->willReturn('Group2');
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->with($loggedInUser)
			->willReturn([$group1, $group2]);
		$this->groupManager
			->expects($this->any())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['Group1']);

		$this->assertEquals(['groups' => ['Group1']], $this->api->getUsersGroups('UserToLookup')->getData());
	}


	public function testGetUsersGroupsForSubAdminUserAndUserIsInaccessible() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('UserToLookup');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToLookup')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['Group1']);

		$this->api->getUsersGroups('UserToLookup');
	}


	public function testAddToGroupWithTargetGroupNotExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$this->groupManager->expects($this->once())
			->method('get')
			->with('GroupToAddTo')
			->willReturn(null);

		$this->api->addToGroup('TargetUser', 'GroupToAddTo');
	}


	public function testAddToGroupWithNoGroupSpecified() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$this->api->addToGroup('TargetUser');
	}


	public function testAddToGroupWithTargetUserNotExisting() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(103);

		$targetGroup = $this->createMock(IGroup::class);
		$this->groupManager->expects($this->once())
			->method('get')
			->with('GroupToAddTo')
			->willReturn($targetGroup);

		$this->api->addToGroup('TargetUser', 'GroupToAddTo');
	}


	public function testAddToGroupNoSubadmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(104);

		$targetUser = $this->createMock(IUser::class);
		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser->expects($this->once())
			->method('getUID')
			->willReturn('subadmin');

		$targetGroup = $this->createMock(IGroup::class);
		$targetGroup->expects($this->never())
			->method('addUser')
			->with($targetUser);

		$this->groupManager->expects($this->once())
			->method('get')
			->with('GroupToAddTo')
			->willReturn($targetGroup);


		$subAdminManager = $this->createMock(SubAdmin::class);
		$subAdminManager->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($loggedInUser, $targetGroup)
			->willReturn(false);

		$this->groupManager->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);

		$this->userManager->expects($this->once())
			->method('get')
			->with('TargetUser')
			->willReturn($targetUser);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->api->addToGroup('TargetUser', 'GroupToAddTo');
	}

	public function testAddToGroupSuccessAsSubadmin() {
		$targetUser = $this->createMock(IUser::class);
		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser->expects($this->once())
			->method('getUID')
			->willReturn('subadmin');

		$targetGroup = $this->createMock(IGroup::class);
		$targetGroup->expects($this->once())
			->method('addUser')
			->with($targetUser);

		$this->groupManager->expects($this->once())
			->method('get')
			->with('GroupToAddTo')
			->willReturn($targetGroup);


		$subAdminManager = $this->createMock(SubAdmin::class);
		$subAdminManager->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($loggedInUser, $targetGroup)
			->willReturn(true);

		$this->groupManager->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);

		$this->userManager->expects($this->once())
			->method('get')
			->with('TargetUser')
			->willReturn($targetUser);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->assertEquals(new DataResponse(), $this->api->addToGroup('TargetUser', 'GroupToAddTo'));
	}

	public function testAddToGroupSuccessAsAdmin() {
		$targetUser = $this->createMock(IUser::class);
		$loggedInUser = $this->createMock(IUser::class);
		$loggedInUser->expects($this->once())
			->method('getUID')
			->willReturn('admin');

		$targetGroup = $this->createMock(IGroup::class);
		$targetGroup->expects($this->once())
			->method('addUser')
			->with($targetUser);

		$this->groupManager->expects($this->once())
			->method('get')
			->with('GroupToAddTo')
			->willReturn($targetGroup);


		$subAdminManager = $this->createMock(SubAdmin::class);
		$subAdminManager->expects($this->never())
			->method('isSubAdminOfGroup');

		$this->groupManager->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->userManager->expects($this->once())
			->method('get')
			->with('TargetUser')
			->willReturn($targetUser);

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->assertEquals(new DataResponse(), $this->api->addToGroup('TargetUser', 'GroupToAddTo'));
	}


	public function testRemoveFromGroupWithNoTargetGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->api->removeFromGroup('TargetUser', '');
	}


	public function testRemoveFromGroupWithEmptyTargetGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);

		$this->api->removeFromGroup('TargetUser', '');
	}


	public function testRemoveFromGroupWithNotExistingTargetGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(102);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('TargetGroup')
			->willReturn(null);

		$this->api->removeFromGroup('TargetUser', 'TargetGroup');
	}


	public function testRemoveFromGroupWithNotExistingTargetUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(103);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('TargetGroup')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('TargetUser')
			->willReturn(null);

		$this->api->removeFromGroup('TargetUser', 'TargetGroup');
	}


	public function testRemoveFromGroupWithoutPermission() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(104);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->once())
			->method('getUID')
			->willReturn('unauthorizedUser');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('TargetGroup')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('TargetUser')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('unauthorizedUser')
			->willReturn(false);

		$this->api->removeFromGroup('TargetUser', 'TargetGroup');
	}


	public function testRemoveFromGroupAsAdminFromAdmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Cannot remove yourself from the admin group');
		$this->expectExceptionCode(105);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('admin');
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$targetGroup
			->expects($this->once())
			->method('getGID')
			->willReturn('admin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('Admin')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);

		$this->api->removeFromGroup('Admin', 'admin');
	}


	public function testRemoveFromGroupAsSubAdminFromSubAdmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Cannot remove yourself from this group as you are a SubAdmin');
		$this->expectExceptionCode(105);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser
			->expects($this->once())
			->method('getUID')
			->willReturn('subadmin');
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$targetGroup
			->expects($this->any())
			->method('getGID')
			->willReturn('subadmin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('subadmin')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('SubAdmin')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($loggedInUser, $targetGroup)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);

		$this->api->removeFromGroup('SubAdmin', 'subadmin');
	}


	public function testRemoveFromGroupAsSubAdminFromLastSubAdminGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Not viable to remove user from the last group you are SubAdmin of');
		$this->expectExceptionCode(105);

		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$targetGroup
			->expects($this->any())
			->method('getGID')
			->willReturn('subadmin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('subadmin')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('AnotherUser')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($loggedInUser, $targetGroup)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->with($loggedInUser)
			->willReturn([$targetGroup]);

		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getUserGroupIds')
			->with($targetUser)
			->willReturn(['subadmin', 'other group']);

		$this->api->removeFromGroup('AnotherUser', 'subadmin');
	}

	public function testRemoveFromGroupSuccessful() {
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->any())
			->method('getUID')
			->willReturn('admin');
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($targetGroup);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('AnotherUser')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$this->groupManager
			->expects($this->any())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$targetGroup
			->expects($this->once())
			->method('removeUser')
			->with($targetUser);

		$this->assertEquals([], $this->api->removeFromGroup('AnotherUser', 'admin')->getData());
	}


	public function testAddSubAdminWithNotExistingTargetUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('User does not exist');
		$this->expectExceptionCode(101);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$this->api->addSubAdmin('NotExistingUser', '');
	}


	public function testAddSubAdminWithNotExistingTargetGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Group does not exist');
		$this->expectExceptionCode(102);


		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('NotExistingGroup')
			->willReturn(null);

		$this->api->addSubAdmin('ExistingUser', 'NotExistingGroup');
	}


	public function testAddSubAdminToAdminGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Cannot create subadmins for admin group');
		$this->expectExceptionCode(103);

		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$targetGroup
			->expects($this->once())
			->method('getGID')
			->willReturn('admin');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('ADmiN')
			->willReturn($targetGroup);

		$this->api->addSubAdmin('ExistingUser', 'ADmiN');
	}

	public function testAddSubAdminTwice() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('TargetGroup')
			->willReturn($targetGroup);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($targetUser, $targetGroup)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->assertEquals([], $this->api->addSubAdmin('ExistingUser', 'TargetGroup')->getData());
	}

	public function testAddSubAdminSuccessful() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('TargetGroup')
			->willReturn($targetGroup);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($targetUser, $targetGroup)
			->willReturn(false);
		$subAdminManager
			->expects($this->once())
			->method('createSubAdmin')
			->with($targetUser, $targetGroup);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->assertEquals([], $this->api->addSubAdmin('ExistingUser', 'TargetGroup')->getData());
	}


	public function testRemoveSubAdminNotExistingTargetUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('User does not exist');
		$this->expectExceptionCode(101);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$this->api->removeSubAdmin('NotExistingUser', 'GroupToDeleteFrom');
	}


	public function testRemoveSubAdminNotExistingTargetGroup() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Group does not exist');
		$this->expectExceptionCode(101);

		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('GroupToDeleteFrom')
			->willReturn(null);

		$this->api->removeSubAdmin('ExistingUser', 'GroupToDeleteFrom');
	}



	public function testRemoveSubAdminFromNotASubadmin() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('User is not a subadmin of this group');
		$this->expectExceptionCode(102);

		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('GroupToDeleteFrom')
			->willReturn($targetGroup);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($targetUser, $targetGroup)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->api->removeSubAdmin('ExistingUser', 'GroupToDeleteFrom');
	}

	public function testRemoveSubAdminSuccessful() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('get')
			->with('GroupToDeleteFrom')
			->willReturn($targetGroup);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isSubAdminOfGroup')
			->with($targetUser, $targetGroup)
			->willReturn(true);
		$subAdminManager
			->expects($this->once())
			->method('deleteSubAdmin')
			->with($targetUser, $targetGroup);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->assertEquals([], $this->api->removeSubAdmin('ExistingUser', 'GroupToDeleteFrom')->getData());
	}


	public function testGetUserSubAdminGroupsNotExistingTargetUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('User does not exist');
		$this->expectExceptionCode(404);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('RequestedUser')
			->willReturn(null);

		$this->api->getUserSubAdminGroups('RequestedUser');
	}

	public function testGetUserSubAdminGroupsWithGroups() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetGroup = $this->getMockBuilder('\OCP\IGroup')->disableOriginalConstructor()->getMock();
		$targetGroup
			->expects($this->once())
			->method('getGID')
			->willReturn('TargetGroup');
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('RequestedUser')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()->getMock();
		$subAdminManager
			->expects($this->once())
			->method('getSubAdminsGroups')
			->with($targetUser)
			->willReturn([$targetGroup]);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->assertEquals(['TargetGroup'], $this->api->getUserSubAdminGroups('RequestedUser')->getData());
	}

	public function testEnableUser() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser->expects($this->once())
			->method('setEnabled')
			->with(true);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('RequestedUser')
			->willReturn($targetUser);
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->willReturn(true);

		$this->assertEquals([], $this->api->enableUser('RequestedUser')->getData());
	}

	public function testDisableUser() {
		$targetUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$targetUser->expects($this->once())
			->method('setEnabled')
			->with(false);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('RequestedUser')
			->willReturn($targetUser);
		$loggedInUser = $this->getMockBuilder(IUser::class)->disableOriginalConstructor()->getMock();
		$loggedInUser
			->expects($this->exactly(2))
			->method('getUID')
			->willReturn('admin');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->willReturn(true);

		$this->assertEquals([], $this->api->disableUser('RequestedUser')->getData());
	}

	public function testGetCurrentUserLoggedIn() {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getUID')->willReturn('UID');

		$this->userSession->expects($this->once())->method('getUser')
			->willReturn($user);

		/** @var UsersController | MockObject $api */
		$api = $this->getMockBuilder(UsersController::class)
			->setConstructorArgs([
				'provisioning_api',
				$this->request,
				$this->userManager,
				$this->config,
				$this->appManager,
				$this->groupManager,
				$this->userSession,
				$this->accountManager,
				$this->urlGenerator,
				$this->logger,
				$this->l10nFactory,
				$this->newUserMailHelper,
				$this->federatedShareProviderFactory,
				$this->secureRandom,
				$this->remoteWipe,
				$this->eventDispatcher,
			])
			->setMethods(['getUserData'])
			->getMock();

		$api->expects($this->once())->method('getUserData')->with('UID')
			->willReturn(
				[
					'id' => 'UID',
					'enabled' => 'true',
					'quota' => ['DummyValue'],
					'email' => 'demo@nextcloud.com',
					'displayname' => 'Demo User',
					'phone' => 'phone',
					'address' => 'address',
					'website' => 'website',
					'twitter' => 'twitter'
				]
			);

		$expected = [
			'id' => 'UID',
			'enabled' => 'true',
			'quota' => ['DummyValue'],
			'email' => 'demo@nextcloud.com',
			'phone' => 'phone',
			'address' => 'address',
			'website' => 'website',
			'twitter' => 'twitter',
			'display-name' => 'Demo User'
		];

		$this->assertSame($expected, $api->getCurrentUser()->getData());
	}


	public function testGetCurrentUserNotLoggedIn() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);


		$this->userSession->expects($this->once())->method('getUser')
			->willReturn(null);

		$this->api->getCurrentUser();
	}


	public function testGetUser() {
		/** @var UsersController | MockObject $api */
		$api = $this->getMockBuilder(UsersController::class)
			->setConstructorArgs([
				'provisioning_api',
				$this->request,
				$this->userManager,
				$this->config,
				$this->appManager,
				$this->groupManager,
				$this->userSession,
				$this->accountManager,
				$this->urlGenerator,
				$this->logger,
				$this->l10nFactory,
				$this->newUserMailHelper,
				$this->federatedShareProviderFactory,
				$this->secureRandom,
				$this->remoteWipe,
				$this->eventDispatcher,
			])
			->setMethods(['getUserData'])
			->getMock();

		$expected = [
			'id' => 'UID',
			'enabled' => 'true',
			'quota' => ['DummyValue'],
			'email' => 'demo@nextcloud.com',
			'phone' => 'phone',
			'address' => 'address',
			'website' => 'website',
			'twitter' => 'twitter',
			'displayname' => 'Demo User'
		];

		$api->expects($this->once())->method('getUserData')
			->with('uid')
			->willReturn($expected);

		$this->assertSame($expected, $api->getUser('uid')->getData());
	}


	public function testResendWelcomeMessageWithNotExistingTargetUser() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(998);

		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$this->api->resendWelcomeMessage('NotExistingUser');
	}


	public function testResendWelcomeMessageAsSubAdminAndUserIsNotAccessible() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionCode(997);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$loggedInUser
			->expects($this->exactly(1))
			->method('getUID')
			->willReturn('subadmin');
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$this->groupManager
			->expects($this->once())
			->method('isAdmin')
			->with('subadmin')
			->willReturn(false);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(false);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);

		$this->api->resendWelcomeMessage('UserToGet');
	}


	public function testResendWelcomeMessageNoEmail() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Email address not available');
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('getEmailAddress')
			->willReturn('');

		$this->api->resendWelcomeMessage('UserToGet');
	}


	public function testResendWelcomeMessageNullEmail() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Email address not available');
		$this->expectExceptionCode(101);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('getEmailAddress')
			->willReturn(null);

		$this->api->resendWelcomeMessage('UserToGet');
	}

	public function testResendWelcomeMessageSuccess() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser
			->method('getUID')
			->willReturn('user-id');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('getEmailAddress')
			->willReturn('abc@example.org');
		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$this->newUserMailHelper
			->expects($this->at(0))
			->method('generateTemplate')
			->willReturn($emailTemplate);
		$this->newUserMailHelper
			->expects($this->at(1))
			->method('sendMail')
			->with($targetUser, $emailTemplate);

		$this->api->resendWelcomeMessage('UserToGet');
	}

	public function testResendWelcomeMessageSuccessWithFallbackLanguage() {
		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser
			->method('getUID')
			->willReturn('user-id');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('getEmailAddress')
			->willReturn('abc@example.org');
		$l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$this->newUserMailHelper
			->expects($this->at(0))
			->method('generateTemplate')
			->willReturn($emailTemplate);
		$this->newUserMailHelper
			->expects($this->at(1))
			->method('sendMail')
			->with($targetUser, $emailTemplate);

		$this->api->resendWelcomeMessage('UserToGet');
	}


	public function testResendWelcomeMessageFailed() {
		$this->expectException(\OCP\AppFramework\OCS\OCSException::class);
		$this->expectExceptionMessage('Sending email failed');
		$this->expectExceptionCode(102);

		$loggedInUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$targetUser
			->method('getUID')
			->willReturn('user-id');
		$this->userSession
			->expects($this->once())
			->method('getUser')
			->willReturn($loggedInUser);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('UserToGet')
			->willReturn($targetUser);
		$subAdminManager = $this->getMockBuilder('OC\SubAdmin')
			->disableOriginalConstructor()
			->getMock();
		$subAdminManager
			->expects($this->once())
			->method('isUserAccessible')
			->with($loggedInUser, $targetUser)
			->willReturn(true);
		$this->groupManager
			->expects($this->once())
			->method('getSubAdmin')
			->willReturn($subAdminManager);
		$targetUser
			->expects($this->once())
			->method('getEmailAddress')
			->willReturn('abc@example.org');
		$emailTemplate = $this->createMock(IEMailTemplate::class);
		$this->newUserMailHelper
			->expects($this->at(0))
			->method('generateTemplate')
			->willReturn($emailTemplate);
		$this->newUserMailHelper
			->expects($this->at(1))
			->method('sendMail')
			->with($targetUser, $emailTemplate)
			->willThrowException(new \Exception());

		$this->api->resendWelcomeMessage('UserToGet');
	}


	public function dataGetEditableFields() {
		return [
			[false, false, []],
			[false,  true, [
				IAccountManager::PROPERTY_PHONE,
				IAccountManager::PROPERTY_ADDRESS,
				IAccountManager::PROPERTY_WEBSITE,
				IAccountManager::PROPERTY_TWITTER,
			]],
			[ true, false, [
				IAccountManager::PROPERTY_DISPLAYNAME,
				IAccountManager::PROPERTY_EMAIL,
			]],
			[ true,  true ,[
				IAccountManager::PROPERTY_DISPLAYNAME,
				IAccountManager::PROPERTY_EMAIL,
				IAccountManager::PROPERTY_PHONE,
				IAccountManager::PROPERTY_ADDRESS,
				IAccountManager::PROPERTY_WEBSITE,
				IAccountManager::PROPERTY_TWITTER,
			]]
		];
	}

	/**
	 * @dataProvider dataGetEditableFields
	 *
	 * @param bool $allowedToChangeDisplayName
	 * @param bool $federatedSharingEnabled
	 * @param array $expected
	 */
	public function testGetEditableFields(bool $allowedToChangeDisplayName, bool $federatedSharingEnabled, array $expected) {
		$this->config
			->method('getSystemValue')
			->with(
				$this->equalTo('allow_user_to_change_display_name'),
				$this->anything()
			)->willReturn($allowedToChangeDisplayName);
		$this->appManager
			->method('isEnabledForUser')
			->with($this->equalTo('federatedfilesharing'))
			->willReturn($federatedSharingEnabled);

		$shareprovider = $this->createMock(FederatedShareProvider::class);
		$shareprovider->method('isLookupServerUploadEnabled')->willReturn(true);

		$this->federatedShareProviderFactory
			->method('get')
			->willReturn($shareprovider);

		$expectedResp = new DataResponse($expected);
		$this->assertEquals($expectedResp, $this->api->getEditableFields());
	}
}
