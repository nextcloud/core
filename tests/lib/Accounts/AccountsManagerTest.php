<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace Test\Accounts;

use OC\Accounts\Account;
use OC\Accounts\AccountManager;
use OCP\Accounts\IAccountManager;
use OCP\BackgroundJob\IJobList;
use OCP\IConfig;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;

/**
 * Class AccountsManagerTest
 *
 * @group DB
 * @package Test\Accounts
 */
class AccountsManagerTest extends TestCase {

	/** @var  \OCP\IDBConnection */
	private $connection;

	/** @var  IConfig|MockObject */
	private $config;

	/** @var  EventDispatcherInterface|MockObject */
	private $eventDispatcher;

	/** @var  IJobList|MockObject */
	private $jobList;

	/** @var string accounts table name */
	private $table = 'accounts';

	/** @var LoggerInterface|MockObject */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	protected function tearDown(): void {
		parent::tearDown();
		$query = $this->connection->getQueryBuilder();
		$query->delete($this->table)->execute();
	}

	/**
	 * get a instance of the accountManager
	 *
	 * @param array $mockedMethods list of methods which should be mocked
	 * @return MockObject | AccountManager
	 */
	public function getInstance($mockedMethods = null) {
		return $this->getMockBuilder(AccountManager::class)
			->setConstructorArgs([
				$this->connection,
				$this->config,
				$this->eventDispatcher,
				$this->jobList,
				$this->logger,
			])
			->setMethods($mockedMethods)
			->getMock();
	}

	/**
	 * @dataProvider dataTrueFalse
	 *
	 * @param array $newData
	 * @param array $oldData
	 * @param bool $insertNew
	 * @param bool $updateExisting
	 */
	public function testUpdateUser($newData, $oldData, $insertNew, $updateExisting) {
		$accountManager = $this->getInstance(['getUser', 'insertNewUser', 'updateExistingUser', 'updateVerifyStatus', 'checkEmailVerification']);
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);

		$accountManager->expects($this->once())->method('getUser')->with($user)->willReturn($oldData);

		if ($updateExisting) {
			$accountManager->expects($this->once())->method('checkEmailVerification')
				->with($oldData, $newData, $user)->willReturn($newData);
			$accountManager->expects($this->once())->method('updateVerifyStatus')
				->with($oldData, $newData)->willReturn($newData);
			$accountManager->expects($this->once())->method('updateExistingUser')
				->with($user, $newData);
			$accountManager->expects($this->never())->method('insertNewUser');
		}
		if ($insertNew) {
			$accountManager->expects($this->once())->method('insertNewUser')
				->with($user, $newData);
			$accountManager->expects($this->never())->method('updateExistingUser');
		}

		if (!$insertNew && !$updateExisting) {
			$accountManager->expects($this->never())->method('updateExistingUser');
			$accountManager->expects($this->never())->method('checkEmailVerification');
			$accountManager->expects($this->never())->method('updateVerifyStatus');
			$accountManager->expects($this->never())->method('insertNewUser');
			$this->eventDispatcher->expects($this->never())->method('dispatch');
		} else {
			$this->eventDispatcher->expects($this->once())->method('dispatch')
				->willReturnCallback(
					function ($eventName, $event) use ($user, $newData) {
						$this->assertSame('OC\AccountManager::userUpdated', $eventName);
						$this->assertInstanceOf(GenericEvent::class, $event);
						/** @var GenericEvent $event */
						$this->assertSame($user, $event->getSubject());
						$this->assertSame($newData, $event->getArguments());
					}
				);
		}

		$accountManager->updateUser($user, $newData);
	}

	public function dataTrueFalse() {
		return [
			[['newData'], ['oldData'], false, true],
			[['newData'], [], true, false],
			[['oldData'], ['oldData'], false, false]
		];
	}


	/**
	 * @dataProvider dataTestGetUser
	 *
	 * @param string $setUser
	 * @param array $setData
	 * @param IUser $askUser
	 * @param array $expectedData
	 * @param bool $userAlreadyExists
	 */
	public function testGetUser($setUser, $setData, $askUser, $expectedData, $userAlreadyExists) {
		$accountManager = $this->getInstance(['buildDefaultUserRecord', 'insertNewUser', 'addMissingDefaultValues']);
		if (!$userAlreadyExists) {
			$accountManager->expects($this->once())->method('buildDefaultUserRecord')
				->with($askUser)->willReturn($expectedData);
			$accountManager->expects($this->once())->method('insertNewUser')
				->with($askUser, $expectedData);
		}

		if (empty($expectedData)) {
			$accountManager->expects($this->never())->method('addMissingDefaultValues');
		} else {
			$accountManager->expects($this->once())->method('addMissingDefaultValues')->with($expectedData)
				->willReturn($expectedData);
		}

		$this->addDummyValuesToTable($setUser, $setData);
		$this->assertEquals($expectedData,
			$accountManager->getUser($askUser)
		);
	}

	public function dataTestGetUser() {
		$user1 = $this->getMockBuilder(IUser::class)->getMock();
		$user1->expects($this->any())->method('getUID')->willReturn('user1');
		$user2 = $this->getMockBuilder(IUser::class)->getMock();
		$user2->expects($this->any())->method('getUID')->willReturn('user2');
		return [
			['user1', ['key' => 'value'], $user1, ['key' => 'value'], true],
			['user1', ['key' => 'value'], $user2, [], false],
		];
	}

	public function testUpdateExistingUser() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$user->expects($this->atLeastOnce())->method('getUID')->willReturn('uid');
		$oldData = ['key' => ['value' => 'value']];
		$newData = ['newKey' => ['value' => 'newValue']];

		$accountManager = $this->getInstance();
		$this->addDummyValuesToTable('uid', $oldData);
		$this->invokePrivate($accountManager, 'updateExistingUser', [$user, $newData]);
		$newDataFromTable = $this->getDataFromTable('uid');
		$this->assertEquals($newData, $newDataFromTable);
	}

	public function testInsertNewUser() {
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$uid = 'uid';
		$data = ['key' => ['value' => 'value']];

		$accountManager = $this->getInstance();
		$user->expects($this->atLeastOnce())->method('getUID')->willReturn($uid);
		$this->assertNull($this->getDataFromTable($uid));
		$this->invokePrivate($accountManager, 'insertNewUser', [$user, $data]);

		$dataFromDb = $this->getDataFromTable($uid);
		$this->assertEquals($data, $dataFromDb);
	}

	public function testAddMissingDefaultValues() {
		$accountManager = $this->getInstance();

		$input = [
			'key1' => ['value' => 'value1', 'verified' => '0'],
			'key2' => ['value' => 'value1'],
		];

		$expected = [
			'key1' => ['value' => 'value1', 'verified' => '0'],
			'key2' => ['value' => 'value1', 'verified' => '0'],
		];

		$result = $this->invokePrivate($accountManager, 'addMissingDefaultValues', [$input]);

		$this->assertSame($expected, $result);
	}

	private function addDummyValuesToTable($uid, $data) {
		$query = $this->connection->getQueryBuilder();
		$query->insert($this->table)
			->values(
				[
					'uid' => $query->createNamedParameter($uid),
					'data' => $query->createNamedParameter(json_encode($data)),
				]
			)
			->execute();
	}

	private function getDataFromTable($uid) {
		$query = $this->connection->getQueryBuilder();
		$query->select('data')->from($this->table)
			->where($query->expr()->eq('uid', $query->createParameter('uid')))
			->setParameter('uid', $uid);
		$query->execute();

		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		if (!empty($result)) {
			return json_decode($result[0]['data'], true);
		}
	}

	public function testGetAccount() {
		$accountManager = $this->getInstance(['getUser']);
		/** @var IUser $user */
		$user = $this->createMock(IUser::class);

		$data = [
			IAccountManager::PROPERTY_TWITTER =>
				[
					'value' => '@twitterhandle',
					'scope' => IAccountManager::VISIBILITY_PRIVATE,
					'verified' => IAccountManager::NOT_VERIFIED,
				],
			IAccountManager::PROPERTY_EMAIL =>
				[
					'value' => 'test@example.com',
					'scope' => IAccountManager::VISIBILITY_PUBLIC,
					'verified' => IAccountManager::VERIFICATION_IN_PROGRESS,
				],
			IAccountManager::PROPERTY_WEBSITE =>
				[
					'value' => 'https://example.com',
					'scope' => IAccountManager::VISIBILITY_CONTACTS_ONLY,
					'verified' => IAccountManager::VERIFIED,
				],
		];
		$expected = new Account($user);
		$expected->setProperty(IAccountManager::PROPERTY_TWITTER, '@twitterhandle', IAccountManager::VISIBILITY_PRIVATE, IAccountManager::NOT_VERIFIED);
		$expected->setProperty(IAccountManager::PROPERTY_EMAIL, 'test@example.com', IAccountManager::VISIBILITY_PUBLIC, IAccountManager::VERIFICATION_IN_PROGRESS);
		$expected->setProperty(IAccountManager::PROPERTY_WEBSITE, 'https://example.com', IAccountManager::VISIBILITY_CONTACTS_ONLY, IAccountManager::VERIFIED);

		$accountManager->expects($this->once())
			->method('getUser')
			->willReturn($data);
		$this->assertEquals($expected, $accountManager->getAccount($user));
	}

	public function dataParsePhoneNumber(): array {
		return [
			['0711 / 25 24 28-90', 'DE', '+4971125242890'],
			['0711 / 25 24 28-90', '', null],
			['+49 711 / 25 24 28-90', '', '+4971125242890'],
		];
	}

	/**
	 * @dataProvider dataParsePhoneNumber
	 * @param string $phoneInput
	 * @param string $defaultRegion
	 * @param string|null $phoneNumber
	 */
	public function testParsePhoneNumber(string $phoneInput, string $defaultRegion, ?string $phoneNumber): void {
		$this->config->method('getSystemValueString')
			->willReturn($defaultRegion);

		$instance = $this->getInstance();

		if ($phoneNumber === null) {
			$this->expectException(\InvalidArgumentException::class);
			self::invokePrivate($instance, 'parsePhoneNumber', [$phoneInput]);
		} else {
			self::assertEquals($phoneNumber, self::invokePrivate($instance, 'parsePhoneNumber', [$phoneInput]));
		}
	}
}
