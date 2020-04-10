<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\UpdateNotification\Tests\Notification;

use OC\Installer;
use OC\Updater\VersionCheck;
use OCA\UpdateNotification\Notification\BackgroundJob;
use OCP\App\IAppManager;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use Test\TestCase;

class BackgroundJobTest extends TestCase {

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $notificationManager;
	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;
	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $appManager;
	/** @var IClientService|\PHPUnit_Framework_MockObject_MockObject */
	protected $client;
	/** @var Installer|\PHPUnit_Framework_MockObject_MockObject */
	protected $installer;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->client = $this->createMock(IClientService::class);
		$this->installer = $this->createMock(Installer::class);
	}

	/**
	 * @param array $methods
	 * @return BackgroundJob|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getJob(array $methods = []) {
		if (empty($methods)) {
			return new BackgroundJob(
				$this->config,
				$this->notificationManager,
				$this->groupManager,
				$this->appManager,
				$this->client,
				$this->installer
			);
		} {
			return $this->getMockBuilder(BackgroundJob::class)
				->setConstructorArgs([
					$this->config,
					$this->notificationManager,
					$this->groupManager,
					$this->appManager,
					$this->client,
					$this->installer,
				])
				->setMethods($methods)
				->getMock();
		}
	}

	public function testRun() {
		$job = $this->getJob([
			'checkCoreUpdate',
			'checkAppUpdates',
		]);

		$job->expects($this->once())
			->method('checkCoreUpdate');
		$job->expects($this->once())
			->method('checkAppUpdates');

		self::invokePrivate($job, 'run', [null]);
	}

	public function dataCheckCoreUpdate(): array {
		return [
			['daily', null, null, null, null],
			['git', null, null, null, null],
			['beta', [], null, null, null],
			['beta', false, false, null, null],
			['beta', false, false, null, 13],
			['beta', [
				'version' => '9.2.0',
				'versionstring' => 'Nextcloud 11.0.0',
			], '9.2.0', 'Nextcloud 11.0.0', null],
			['stable', [], null, null, null],
			['stable', false, false, null, null],
			['stable', false, false, null, 6],
			['stable', [
				'version' => '9.2.0',
				'versionstring' => 'Nextcloud 11.0.0',
			], '9.2.0', 'Nextcloud 11.0.0', null],
			['production', [], null, null, null],
			['production', false, false, null, null],
			['production', false, false, null, 2],
			['production', [
				'version' => '9.2.0',
				'versionstring' => 'Nextcloud 11.0.0',
			], '9.2.0', 'Nextcloud 11.0.0', null],
		];
	}

	/**
	 * @dataProvider dataCheckCoreUpdate
	 *
	 * @param string $channel
	 * @param mixed $versionCheck
	 * @param null|string $version
	 * @param null|string $readableVersion
	 * @param null|int $errorDays
	 */
	public function testCheckCoreUpdate(string $channel, $versionCheck, $version, $readableVersion, $errorDays) {
		$job = $this->getJob([
			'getChannel',
			'createVersionCheck',
			'createNotifications',
			'clearErrorNotifications',
			'sendErrorNotifications',
		]);

		$job->expects($this->once())
			->method('getChannel')
			->willReturn($channel);

		if ($versionCheck === null) {
			$job->expects($this->never())
				->method('createVersionCheck');
		} else {
			$check = $this->createMock(VersionCheck::class);
			$check->expects($this->once())
				->method('check')
				->willReturn($versionCheck);

			$job->expects($this->once())
				->method('createVersionCheck')
				->willReturn($check);
		}

		if ($version === null) {
			$job->expects($this->never())
				->method('createNotifications');
			$job->expects($versionCheck === null ? $this->never() : $this->once())
				->method('clearErrorNotifications');
		} else if ($version === false) {
			$job->expects($this->never())
				->method('createNotifications');
			$job->expects($this->never())
				->method('clearErrorNotifications');

			$this->config->expects($this->once())
				->method('getAppValue')
				->willReturn($errorDays);
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('updatenotification', 'update_check_errors', $errorDays + 1);
			$job->expects($errorDays !== null ? $this->once() : $this->never())
				->method('sendErrorNotifications')
				->with($errorDays + 1);
		} else {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('updatenotification', 'update_check_errors', 0);
			$job->expects($this->once())
				->method('clearErrorNotifications');
			$job->expects($this->once())
				->method('createNotifications')
				->with('core', $version, $readableVersion);
		}

		self::invokePrivate($job, 'checkCoreUpdate');
	}

	public function dataCheckAppUpdates(): array {
		return [
			[
				['app1', 'app2'],
				[
					['app1', false],
					['app2', '1.9.2'],
				],
				[
					['app2', '1.9.2'],
				],
			],
		];
	}

	/**
	 * @dataProvider dataCheckAppUpdates
	 *
	 * @param string[] $apps
	 * @param array $isUpdateAvailable
	 * @param array $notifications
	 */
	public function testCheckAppUpdates(array $apps, array $isUpdateAvailable, array $notifications) {
		$job = $this->getJob([
			'isUpdateAvailable',
			'createNotifications',
		]);

		$this->appManager->expects($this->once())
			->method('getInstalledApps')
			->willReturn($apps);

		$job->expects($this->exactly(\count($apps)))
			->method('isUpdateAvailable')
			->willReturnMap($isUpdateAvailable);

		$mockedMethod = $job->expects($this->exactly(\count($notifications)))
			->method('createNotifications');
		\call_user_func_array([$mockedMethod, 'withConsecutive'], $notifications);

		self::invokePrivate($job, 'checkAppUpdates');
	}

	public function dataCreateNotifications(): array {
		return [
			['app1', '1.0.0', '1.0.0', false, false, null, null],
			['app2', '1.0.1', '1.0.0', '1.0.0', true, ['user1'], [['user1']]],
			['app3', '1.0.1', false, false, true, ['user2', 'user3'], [['user2'], ['user3']]],
		];
	}

	/**
	 * @dataProvider dataCreateNotifications
	 *
	 * @param string $app
	 * @param string $version
	 * @param string|false $lastNotification
	 * @param string|false $callDelete
	 * @param bool $createNotification
	 * @param string[]|null $users
	 * @param array|null $userNotifications
	 */
	public function testCreateNotifications(string $app, string $version, $lastNotification, $callDelete, $createNotification, $users, $userNotifications) {
		$job = $this->getJob([
			'deleteOutdatedNotifications',
			'getUsersToNotify',
		]);

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('updatenotification', $app, false)
			->willReturn($lastNotification);

		if ($lastNotification !== $version) {
			$this->config->expects($this->once())
				->method('setAppValue')
				->with('updatenotification', $app, $version);
		}

		if ($callDelete === false) {
			$job->expects($this->never())
				->method('deleteOutdatedNotifications');
		} else {
			$job->expects($this->once())
				->method('deleteOutdatedNotifications')
				->with($app, $callDelete);
		}

		if ($users === null) {
			$job->expects($this->never())
				->method('getUsersToNotify');
		} else {
			$job->expects($this->once())
				->method('getUsersToNotify')
				->willReturn($users);
		}

		if ($createNotification) {
			$notification = $this->createMock(INotification::class);
			$notification->expects($this->once())
				->method('setApp')
				->with('updatenotification')
				->willReturnSelf();
			$notification->expects($this->once())
				->method('setDateTime')
				->willReturnSelf();
			$notification->expects($this->once())
				->method('setObject')
				->with($app, $version)
				->willReturnSelf();
			$notification->expects($this->once())
				->method('setSubject')
				->with('update_available')
				->willReturnSelf();

			if ($userNotifications !== null) {
				$mockedMethod = $notification->expects($this->exactly(\count($userNotifications)))
					->method('setUser')
					->willReturnSelf();
				\call_user_func_array([$mockedMethod, 'withConsecutive'], $userNotifications);

				$this->notificationManager->expects($this->exactly(\count($userNotifications)))
					->method('notify')
					->willReturn($notification);
			}

			$this->notificationManager->expects($this->once())
				->method('createNotification')
				->willReturn($notification);
		} else {
			$this->notificationManager->expects($this->never())
				->method('createNotification');
		}

		self::invokePrivate($job, 'createNotifications', [$app, $version]);
	}

	public function dataGetUsersToNotify(): array {
		return [
			[['g1', 'g2'], ['g1' => null, 'g2' => ['u1', 'u2']], ['u1', 'u2']],
			[['g3', 'g4'], ['g3' => ['u1', 'u2'], 'g4' => ['u2', 'u3']], ['u1', 'u2', 'u3']],
		];
	}

	/**
	 * @dataProvider dataGetUsersToNotify
	 * @param string[] $groups
	 * @param array $groupUsers
	 * @param string[] $expected
	 */
	public function testGetUsersToNotify(array $groups, array $groupUsers, array $expected) {
		$job = $this->getJob();

		$this->config->expects($this->once())
			->method('getAppValue')
			->with('updatenotification', 'notify_groups', '["admin"]')
			->willReturn(json_encode($groups));

		$groupMap = [];
		foreach ($groupUsers as $gid => $uids) {
			if ($uids === null) {
				$group = null;
			} else {
				$group = $this->getGroup($gid);
				$group->expects($this->any())
					->method('getUsers')
					->willReturn($this->getUsers($uids));
			}
			$groupMap[] = [$gid, $group];
		}
		$this->groupManager->expects($this->exactly(\count($groups)))
			->method('get')
			->willReturnMap($groupMap);

		$result = self::invokePrivate($job, 'getUsersToNotify');
		$this->assertEquals($expected, $result);

		// Test caching
		$result = self::invokePrivate($job, 'getUsersToNotify');
		$this->assertEquals($expected, $result);
	}

	public function dataDeleteOutdatedNotifications(): array {
		return [
			['app1', '1.1.0'],
			['app2', '1.2.0'],
		];
	}

	/**
	 * @dataProvider dataDeleteOutdatedNotifications
	 * @param string $app
	 * @param string $version
	 */
	public function testDeleteOutdatedNotifications(string $app, string $version) {
		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())
			->method('setApp')
			->with('updatenotification')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setObject')
			->with($app, $version)
			->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$job = $this->getJob();
		self::invokePrivate($job, 'deleteOutdatedNotifications', [$app, $version]);
	}

	/**
	 * @param string[] $userIds
	 * @return IUser[]|\PHPUnit_Framework_MockObject_MockObject[]
	 */
	protected function getUsers(array $userIds): array {
		$users = [];
		foreach ($userIds as $uid) {
			$user = $this->createMock(IUser::class);
			$user->expects($this->any())
				->method('getUID')
				->willReturn($uid);
			$users[] = $user;
		}
		return $users;
	}

	/**
	 * @param string $gid
	 * @return \OCP\IGroup|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getGroup(string $gid) {
		$group = $this->createMock(IGroup::class);
		$group->expects($this->any())
			->method('getGID')
			->willReturn($gid);
		return $group;
	}
}
