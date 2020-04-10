<?php

declare(strict_types=1);

/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\App;

use OC\App\AppManager;
use OC\AppConfig;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class AppManagerTest
 *
 * @package Test\App
 */
class AppManagerTest extends TestCase {
	/**
	 * @return AppConfig|MockObject
	 */
	protected function getAppConfig() {
		$appConfig = [];
		$config = $this->createMock(AppConfig::class);

		$config->expects($this->any())
			->method('getValue')
			->willReturnCallback(function ($app, $key, $default) use (&$appConfig) {
				return (isset($appConfig[$app]) and isset($appConfig[$app][$key])) ? $appConfig[$app][$key] : $default;
			});
		$config->expects($this->any())
			->method('setValue')
			->willReturnCallback(function ($app, $key, $value) use (&$appConfig) {
				if (!isset($appConfig[$app])) {
					$appConfig[$app] = [];
				}
				$appConfig[$app][$key] = $value;
			});
		$config->expects($this->any())
			->method('getValues')
			->willReturnCallback(function ($app, $key) use (&$appConfig) {
				if ($app) {
					return $appConfig[$app];
				} else {
					$values = [];
					foreach ($appConfig as $appid => $appData) {
						if (isset($appData[$key])) {
							$values[$appid] = $appData[$key];
						}
					}
					return $values;
				}
			});

		return $config;
	}

	/** @var IUserSession|MockObject */
	protected $userSession;

	/** @var IConfig|MockObject */
	private $config;

	/** @var IGroupManager|MockObject */
	protected $groupManager;

	/** @var AppConfig|MockObject */
	protected $appConfig;

	/** @var ICache|MockObject */
	protected $cache;

	/** @var ICacheFactory|MockObject */
	protected $cacheFactory;

	/** @var EventDispatcherInterface|MockObject */
	protected $eventDispatcher;

	/** @var ILogger|MockObject */
	protected $logger;

	/** @var IAppManager */
	protected $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->getAppConfig();
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(ICache::class);
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->cacheFactory->expects($this->any())
			->method('createDistributed')
			->with('settings')
			->willReturn($this->cache);
		$this->manager = new AppManager(
			$this->userSession,
			$this->config,
			$this->appConfig,
			$this->groupManager,
			$this->cacheFactory,
			$this->eventDispatcher,
			$this->logger
		);
	}

	protected function expectClearCache() {
		$this->cache->expects($this->once())
			->method('clear')
			->with('listApps');
	}

	public function testEnableApp() {
		$this->expectClearCache();
		// making sure "files_trashbin" is disabled
		if ($this->manager->isEnabledForUser('files_trashbin')) {
			$this->manager->disableApp('files_trashbin');
		}
		$this->manager->enableApp('files_trashbin');
		$this->assertEquals('yes', $this->appConfig->getValue('files_trashbin', 'enabled', 'no'));
	}

	public function testDisableApp() {
		$this->expectClearCache();
		$this->manager->disableApp('files_trashbin');
		$this->assertEquals('no', $this->appConfig->getValue('files_trashbin', 'enabled', 'no'));
	}

	public function testNotEnableIfNotInstalled() {
		try {
			$this->manager->enableApp('some_random_name_which_i_hope_is_not_an_app');
			$this->assertFalse(true, 'If this line is reached the expected exception is not thrown.');
		} catch (AppPathNotFoundException $e) {
			// Exception is expected
			$this->assertEquals('Could not find path for some_random_name_which_i_hope_is_not_an_app', $e->getMessage());
		}

		$this->assertEquals('no', $this->appConfig->getValue(
			'some_random_name_which_i_hope_is_not_an_app', 'enabled', 'no'
		));
	}

	public function testEnableAppForGroups() {
		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')
			->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')
			->willReturn('group2');

		$groups = [$group1, $group2];
		$this->expectClearCache();

		/** @var AppManager|MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([
				$this->userSession, $this->config, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher, $this->logger
			])
			->setMethods([
				'getAppPath',
			])
			->getMock();

		$manager->expects($this->exactly(2))
			->method('getAppPath')
			->with('test')
			->willReturn('apps/test');

		$manager->enableAppForGroups('test', $groups);
		$this->assertEquals('["group1","group2"]', $this->appConfig->getValue('test', 'enabled', 'no'));
	}

	public function dataEnableAppForGroupsAllowedTypes() {
		return [
			[[]],
			[[
				'types' => [],
			]],
			[[
				'types' => ['nickvergessen'],
			]],
		];
	}

	/**
	 * @dataProvider dataEnableAppForGroupsAllowedTypes
	 *
	 * @param array $appInfo
	 */
	public function testEnableAppForGroupsAllowedTypes(array $appInfo) {
		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')
			->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')
			->willReturn('group2');

		$groups = [$group1, $group2];
		$this->expectClearCache();

		/** @var AppManager|MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([
				$this->userSession, $this->config, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher, $this->logger
			])
			->setMethods([
				'getAppPath',
				'getAppInfo',
			])
			->getMock();

		$manager->expects($this->once())
			->method('getAppPath')
			->with('test')
			->willReturn(null);

		$manager->expects($this->once())
			->method('getAppInfo')
			->with('test')
			->willReturn($appInfo);

		$manager->enableAppForGroups('test', $groups);
		$this->assertEquals('["group1","group2"]', $this->appConfig->getValue('test', 'enabled', 'no'));
	}

	public function dataEnableAppForGroupsForbiddenTypes() {
		return [
			['filesystem'],
			['prelogin'],
			['authentication'],
			['logging'],
			['prevent_group_restriction'],
		];
	}

	/**
	 * @dataProvider dataEnableAppForGroupsForbiddenTypes
	 *
	 * @param string $type
	 *
	 */
	public function testEnableAppForGroupsForbiddenTypes($type) {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('test can\'t be enabled for groups.');

		$group1 = $this->createMock(IGroup::class);
		$group1->method('getGID')
			->willReturn('group1');
		$group2 = $this->createMock(IGroup::class);
		$group2->method('getGID')
			->willReturn('group2');

		$groups = [$group1, $group2];

		/** @var AppManager|MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([
				$this->userSession, $this->config, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher, $this->logger
			])
			->setMethods([
				'getAppPath',
				'getAppInfo',
			])
			->getMock();

		$manager->expects($this->once())
			->method('getAppPath')
			->with('test')
			->willReturn(null);

		$manager->expects($this->once())
			->method('getAppInfo')
			->with('test')
			->willReturn([
				'types' => [$type],
			]);

		$manager->enableAppForGroups('test', $groups);
	}

	public function testIsInstalledEnabled() {
		$this->appConfig->setValue('test', 'enabled', 'yes');
		$this->assertTrue($this->manager->isInstalled('test'));
	}

	public function testIsInstalledDisabled() {
		$this->appConfig->setValue('test', 'enabled', 'no');
		$this->assertFalse($this->manager->isInstalled('test'));
	}

	public function testIsInstalledEnabledForGroups() {
		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isInstalled('test'));
	}

	private function newUser($uid) {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn($uid);

		return $user;
	}

	public function testIsEnabledForUserEnabled() {
		$this->appConfig->setValue('test', 'enabled', 'yes');
		$user = $this->newUser('user1');
		$this->assertTrue($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabled() {
		$this->appConfig->setValue('test', 'enabled', 'no');
		$user = $this->newUser('user1');
		$this->assertFalse($this->manager->isEnabledForUser('test', $user));
	}

	public function testGetAppPath() {
		$this->assertEquals(\OC::$SERVERROOT . '/apps/files', $this->manager->getAppPath('files'));
	}

	public function testGetAppPathFail() {
		$this->expectException(AppPathNotFoundException::class);
		$this->manager->getAppPath('testnotexisting');
	}

	public function testIsEnabledForUserEnabledForGroup() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['foo', 'bar']);

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserDisabledForGroup() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['bar']);

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($this->manager->isEnabledForUser('test', $user));
	}

	public function testIsEnabledForUserLoggedOut() {
		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertFalse($this->manager->isEnabledForUser('test'));
	}

	public function testIsEnabledForUserLoggedIn() {
		$user = $this->newUser('user1');

		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->groupManager->expects($this->once())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['foo', 'bar']);

		$this->appConfig->setValue('test', 'enabled', '["foo"]');
		$this->assertTrue($this->manager->isEnabledForUser('test'));
	}

	public function testGetInstalledApps() {
		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');
		$apps = [
			'cloud_federation_api',
			'dav',
			'federatedfilesharing',
			'files',
			'lookup_server_connector',
			'oauth2',
			'provisioning_api',
			'settings',
			'test1',
			'test3',
			'twofactor_backupcodes',
			'viewer',
			'workflowengine',
		];
		$this->assertEquals($apps, $this->manager->getInstalledApps());
	}

	public function testGetAppsForUser() {
		$user = $this->newUser('user1');
		$this->groupManager->expects($this->any())
			->method('getUserGroupIds')
			->with($user)
			->willReturn(['foo', 'bar']);

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');
		$this->appConfig->setValue('test4', 'enabled', '["asd"]');
		$enabled = [
			'cloud_federation_api',
			'dav',
			'federatedfilesharing',
			'files',
			'lookup_server_connector',
			'oauth2',
			'provisioning_api',
			'settings',
			'test1',
			'test3',
			'twofactor_backupcodes',
			'viewer',
			'workflowengine',
		];
		$this->assertEquals($enabled, $this->manager->getEnabledAppsForUser($user));
	}

	public function testGetAppsNeedingUpgrade() {
		/** @var AppManager|MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([$this->userSession, $this->config, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher, $this->logger])
			->setMethods(['getAppInfo'])
			->getMock();

		$appInfos = [
			'cloud_federation_api' => ['id' => 'cloud_federation_api'],
			'dav' => ['id' => 'dav'],
			'files' => ['id' => 'files'],
			'federatedfilesharing' => ['id' => 'federatedfilesharing'],
			'provisioning_api' => ['id' => 'provisioning_api'],
			'lookup_server_connector' => ['id' => 'lookup_server_connector'],
			'test1' => ['id' => 'test1', 'version' => '1.0.1', 'requiremax' => '9.0.0'],
			'test2' => ['id' => 'test2', 'version' => '1.0.0', 'requiremin' => '8.2.0'],
			'test3' => ['id' => 'test3', 'version' => '1.2.4', 'requiremin' => '9.0.0'],
			'test4' => ['id' => 'test4', 'version' => '3.0.0', 'requiremin' => '8.1.0'],
			'testnoversion' => ['id' => 'testnoversion', 'requiremin' => '8.2.0'],
			'settings' => ['id' => 'settings'],
			'twofactor_backupcodes' => ['id' => 'twofactor_backupcodes'],
			'viewer' => ['id' => 'viewer'],
			'workflowengine' => ['id' => 'workflowengine'],
			'oauth2' => ['id' => 'oauth2'],
		];

		$manager->expects($this->any())
			->method('getAppInfo')
			->willReturnCallback(
				function ($appId) use ($appInfos) {
					return $appInfos[$appId];
				}
			);

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test1', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test2', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test3', 'enabled', 'yes');
		$this->appConfig->setValue('test3', 'installed_version', '1.0.0');
		$this->appConfig->setValue('test4', 'enabled', 'yes');
		$this->appConfig->setValue('test4', 'installed_version', '2.4.0');

		$apps = $manager->getAppsNeedingUpgrade('8.2.0');

		$this->assertCount(2, $apps);
		$this->assertEquals('test1', $apps[0]['id']);
		$this->assertEquals('test4', $apps[1]['id']);
	}

	public function testGetIncompatibleApps() {
		/** @var AppManager|MockObject $manager */
		$manager = $this->getMockBuilder(AppManager::class)
			->setConstructorArgs([$this->userSession, $this->config, $this->appConfig, $this->groupManager, $this->cacheFactory, $this->eventDispatcher, $this->logger])
			->setMethods(['getAppInfo'])
			->getMock();

		$appInfos = [
			'cloud_federation_api' => ['id' => 'cloud_federation_api'],
			'dav' => ['id' => 'dav'],
			'files' => ['id' => 'files'],
			'federatedfilesharing' => ['id' => 'federatedfilesharing'],
			'provisioning_api' => ['id' => 'provisioning_api'],
			'lookup_server_connector' => ['id' => 'lookup_server_connector'],
			'test1' => ['id' => 'test1', 'version' => '1.0.1', 'requiremax' => '8.0.0'],
			'test2' => ['id' => 'test2', 'version' => '1.0.0', 'requiremin' => '8.2.0'],
			'test3' => ['id' => 'test3', 'version' => '1.2.4', 'requiremin' => '9.0.0'],
			'settings' => ['id' => 'settings'],
			'testnoversion' => ['id' => 'testnoversion', 'requiremin' => '8.2.0'],
			'twofactor_backupcodes' => ['id' => 'twofactor_backupcodes'],
			'workflowengine' => ['id' => 'workflowengine'],
			'oauth2' => ['id' => 'oauth2'],
			'viewer' => ['id' => 'viewer'],
		];

		$manager->expects($this->any())
			->method('getAppInfo')
			->willReturnCallback(
				function ($appId) use ($appInfos) {
					return $appInfos[$appId];
				}
			);

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'yes');
		$this->appConfig->setValue('test3', 'enabled', 'yes');

		$apps = $manager->getIncompatibleApps('8.2.0');

		$this->assertCount(2, $apps);
		$this->assertEquals('test1', $apps[0]['id']);
		$this->assertEquals('test3', $apps[1]['id']);
	}

	public function testGetEnabledAppsForGroup() {
		$group = $this->createMock(IGroup::class);
		$group->expects($this->any())
			->method('getGID')
			->willReturn('foo');

		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');
		$this->appConfig->setValue('test4', 'enabled', '["asd"]');
		$enabled = [
			'cloud_federation_api',
			'dav',
			'federatedfilesharing',
			'files',
			'lookup_server_connector',
			'oauth2',
			'provisioning_api',
			'settings',
			'test1',
			'test3',
			'twofactor_backupcodes',
			'viewer',
			'workflowengine',
		];
		$this->assertEquals($enabled, $this->manager->getEnabledAppsForGroup($group));
	}

	public function testGetAppRestriction() {
		$this->appConfig->setValue('test1', 'enabled', 'yes');
		$this->appConfig->setValue('test2', 'enabled', 'no');
		$this->appConfig->setValue('test3', 'enabled', '["foo"]');

		$this->assertEquals([], $this->manager->getAppRestriction('test1'));
		$this->assertEquals([], $this->manager->getAppRestriction('test2'));
		$this->assertEquals(['foo'], $this->manager->getAppRestriction('test3'));
	}
}
