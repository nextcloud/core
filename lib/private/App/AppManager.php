<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Schaefer "christophł@wolkesicher.de"
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Daniel Rudolf <github.com@daniel-rudolf.de>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobia De Koninck <tobia@ledfan.be>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\App;

use OC\AppConfig;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\App\ManagerEvent;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AppManager implements IAppManager {

	/**
	 * Apps with these types can not be enabled for certain groups only
	 * @var string[]
	 */
	protected $protectedAppTypes = [
		'filesystem',
		'prelogin',
		'authentication',
		'logging',
		'prevent_group_restriction',
	];

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var AppConfig */
	private $appConfig;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ICacheFactory */
	private $memCacheFactory;

	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var ILogger */
	private $logger;

	/** @var string[] $appId => $enabled */
	private $installedAppsCache;

	/** @var string[] */
	private $shippedApps;

	/** @var string[] */
	private $alwaysEnabled;

	/** @var array */
	private $appInfos = [];

	/** @var array */
	private $appVersions = [];

	/** @var array */
	private $autoDisabledApps = [];

	/**
	 * @param IUserSession $userSession
	 * @param IConfig $config
	 * @param AppConfig $appConfig
	 * @param IGroupManager $groupManager
	 * @param ICacheFactory $memCacheFactory
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(IUserSession $userSession,
								IConfig $config,
								AppConfig $appConfig,
								IGroupManager $groupManager,
								ICacheFactory $memCacheFactory,
								EventDispatcherInterface $dispatcher,
								ILogger $logger) {
		$this->userSession = $userSession;
		$this->config = $config;
		$this->appConfig = $appConfig;
		$this->groupManager = $groupManager;
		$this->memCacheFactory = $memCacheFactory;
		$this->dispatcher = $dispatcher;
		$this->logger = $logger;
	}

	/**
	 * @return string[] $appId => $enabled
	 */
	private function getInstalledAppsValues() {
		if (!$this->installedAppsCache) {
			$values = $this->appConfig->getValues(false, 'enabled');

			$alwaysEnabledApps = $this->getAlwaysEnabledApps();
			foreach ($alwaysEnabledApps as $appId) {
				$values[$appId] = 'yes';
			}

			$this->installedAppsCache = array_filter($values, function ($value) {
				return $value !== 'no';
			});
			ksort($this->installedAppsCache);
		}
		return $this->installedAppsCache;
	}

	/**
	 * List all installed apps
	 *
	 * @return string[]
	 */
	public function getInstalledApps() {
		return array_keys($this->getInstalledAppsValues());
	}

	/**
	 * List all apps enabled for a user
	 *
	 * @param \OCP\IUser $user
	 * @return string[]
	 */
	public function getEnabledAppsForUser(IUser $user) {
		$apps = $this->getInstalledAppsValues();
		$appsForUser = array_filter($apps, function ($enabled) use ($user) {
			return $this->checkAppForUser($enabled, $user);
		});
		return array_keys($appsForUser);
	}

	/**
	 * @param \OCP\IGroup $group
	 * @return array
	 */
	public function getEnabledAppsForGroup(IGroup $group): array {
		$apps = $this->getInstalledAppsValues();
		$appsForGroups = array_filter($apps, function ($enabled) use ($group) {
			return $this->checkAppForGroups($enabled, $group);
		});
		return array_keys($appsForGroups);
	}

	/**
	 * @return array
	 */
	public function getAutoDisabledApps(): array {
		return $this->autoDisabledApps;
	}

	/**
	 * @param string $appId
	 * @return array
	 */
	public function getAppRestriction(string $appId): array {
		$values = $this->getInstalledAppsValues();

		if (!isset($values[$appId])) {
			return [];
		}

		if ($values[$appId] === 'yes' || $values[$appId] === 'no') {
			return [];
		}
		return json_decode($values[$appId]);
	}


	/**
	 * Check if an app is enabled for user
	 *
	 * @param string $appId
	 * @param \OCP\IUser $user (optional) if not defined, the currently logged in user will be used
	 * @return bool
	 */
	public function isEnabledForUser($appId, $user = null) {
		if ($this->isAlwaysEnabled($appId)) {
			return true;
		}
		if ($user === null) {
			$user = $this->userSession->getUser();
		}
		$installedApps = $this->getInstalledAppsValues();
		if (isset($installedApps[$appId])) {
			return $this->checkAppForUser($installedApps[$appId], $user);
		} else {
			return false;
		}
	}

	/**
	 * @param string $enabled
	 * @param IUser $user
	 * @return bool
	 */
	private function checkAppForUser($enabled, $user) {
		if ($enabled === 'yes') {
			return true;
		} elseif ($user === null) {
			return false;
		} else {
			if (empty($enabled)) {
				return false;
			}

			$groupIds = json_decode($enabled);

			if (!is_array($groupIds)) {
				$jsonError = json_last_error();
				$this->logger->warning('AppManger::checkAppForUser - can\'t decode group IDs: ' . print_r($enabled, true) . ' - json error code: ' . $jsonError, ['app' => 'lib']);
				return false;
			}

			$userGroups = $this->groupManager->getUserGroupIds($user);
			foreach ($userGroups as $groupId) {
				if (in_array($groupId, $groupIds, true)) {
					return true;
				}
			}
			return false;
		}
	}

	/**
	 * @param string $enabled
	 * @param IGroup $group
	 * @return bool
	 */
	private function checkAppForGroups(string $enabled, IGroup $group): bool {
		if ($enabled === 'yes') {
			return true;
		} elseif ($group === null) {
			return false;
		} else {
			if (empty($enabled)) {
				return false;
			}

			$groupIds = json_decode($enabled);

			if (!is_array($groupIds)) {
				$jsonError = json_last_error();
				$this->logger->warning('AppManger::checkAppForUser - can\'t decode group IDs: ' . print_r($enabled, true) . ' - json error code: ' . $jsonError, ['app' => 'lib']);
				return false;
			}

			return in_array($group->getGID(), $groupIds);
		}
	}

	/**
	 * Check if an app is enabled in the instance
	 *
	 * Notice: This actually checks if the app is enabled and not only if it is installed.
	 *
	 * @param string $appId
	 * @param \OCP\IGroup[]|String[] $groups
	 * @return bool
	 */
	public function isInstalled($appId) {
		$installedApps = $this->getInstalledAppsValues();
		return isset($installedApps[$appId]);
	}

	public function ignoreNextcloudRequirementForApp(string $appId): void {
		$ignoreMaxApps = $this->config->getSystemValue('app_install_overwrite', []);
		if (!in_array($appId, $ignoreMaxApps, true)) {
			$ignoreMaxApps[] = $appId;
			$this->config->setSystemValue('app_install_overwrite', $ignoreMaxApps);
		}
	}

	/**
	 * Enable an app for every user
	 *
	 * @param string $appId
	 * @param bool $forceEnable
	 * @throws AppPathNotFoundException
	 */
	public function enableApp(string $appId, bool $forceEnable = false): void {
		// Check if app exists
		$this->getAppPath($appId);

		if ($forceEnable) {
			$this->ignoreNextcloudRequirementForApp($appId);
		}

		$this->installedAppsCache[$appId] = 'yes';
		$this->appConfig->setValue($appId, 'enabled', 'yes');
		$this->dispatcher->dispatch(ManagerEvent::EVENT_APP_ENABLE, new ManagerEvent(
			ManagerEvent::EVENT_APP_ENABLE, $appId
		));
		$this->clearAppsCache();
	}

	/**
	 * Whether a list of types contains a protected app type
	 *
	 * @param string[] $types
	 * @return bool
	 */
	public function hasProtectedAppType($types) {
		if (empty($types)) {
			return false;
		}

		$protectedTypes = array_intersect($this->protectedAppTypes, $types);
		return !empty($protectedTypes);
	}

	/**
	 * Enable an app only for specific groups
	 *
	 * @param string $appId
	 * @param \OCP\IGroup[] $groups
	 * @param bool $forceEnable
	 * @throws \InvalidArgumentException if app can't be enabled for groups
	 * @throws AppPathNotFoundException
	 */
	public function enableAppForGroups(string $appId, array $groups, bool $forceEnable = false): void {
		// Check if app exists
		$this->getAppPath($appId);

		$info = $this->getAppInfo($appId);
		if (!empty($info['types']) && $this->hasProtectedAppType($info['types'])) {
			throw new \InvalidArgumentException("$appId can't be enabled for groups.");
		}

		if ($forceEnable) {
			$this->ignoreNextcloudRequirementForApp($appId);
		}

		$groupIds = array_map(function ($group) {
			/** @var \OCP\IGroup $group */
			return ($group instanceof IGroup)
				? $group->getGID()
				: $group;
		}, $groups);

		$this->installedAppsCache[$appId] = json_encode($groupIds);
		$this->appConfig->setValue($appId, 'enabled', json_encode($groupIds));
		$this->dispatcher->dispatch(ManagerEvent::EVENT_APP_ENABLE_FOR_GROUPS, new ManagerEvent(
			ManagerEvent::EVENT_APP_ENABLE_FOR_GROUPS, $appId, $groups
		));
		$this->clearAppsCache();
	}

	/**
	 * Disable an app for every user
	 *
	 * @param string $appId
	 * @param bool $automaticDisabled
	 * @throws \Exception if app can't be disabled
	 */
	public function disableApp($appId, $automaticDisabled = false) {
		if ($this->isAlwaysEnabled($appId)) {
			throw new \Exception("$appId can't be disabled.");
		}

		if ($automaticDisabled) {
			$this->autoDisabledApps[] = $appId;
		}

		unset($this->installedAppsCache[$appId]);
		$this->appConfig->setValue($appId, 'enabled', 'no');

		// run uninstall steps
		$appData = $this->getAppInfo($appId);
		if (!is_null($appData)) {
			\OC_App::executeRepairSteps($appId, $appData['repair-steps']['uninstall']);
		}

		$this->dispatcher->dispatch(ManagerEvent::EVENT_APP_DISABLE, new ManagerEvent(
			ManagerEvent::EVENT_APP_DISABLE, $appId
		));
		$this->clearAppsCache();
	}

	/**
	 * Get the directory for the given app.
	 *
	 * @param string $appId
	 * @return string
	 * @throws AppPathNotFoundException if app folder can't be found
	 */
	public function getAppPath($appId) {
		$appPath = \OC_App::getAppPath($appId);
		if ($appPath === false) {
			throw new AppPathNotFoundException('Could not find path for ' . $appId);
		}
		return $appPath;
	}

	/**
	 * Get the web path for the given app.
	 *
	 * @param string $appId
	 * @return string
	 * @throws AppPathNotFoundException if app path can't be found
	 */
	public function getAppWebPath(string $appId): string {
		$appWebPath = \OC_App::getAppWebPath($appId);
		if ($appWebPath === false) {
			throw new AppPathNotFoundException('Could not find web path for ' . $appId);
		}
		return $appWebPath;
	}

	/**
	 * Clear the cached list of apps when enabling/disabling an app
	 */
	public function clearAppsCache() {
		$settingsMemCache = $this->memCacheFactory->createDistributed('settings');
		$settingsMemCache->clear('listApps');
		$this->appInfos = [];
	}

	/**
	 * Returns a list of apps that need upgrade
	 *
	 * @param string $version Nextcloud version as array of version components
	 * @return array list of app info from apps that need an upgrade
	 *
	 * @internal
	 */
	public function getAppsNeedingUpgrade($version) {
		$appsToUpgrade = [];
		$apps = $this->getInstalledApps();
		foreach ($apps as $appId) {
			$appInfo = $this->getAppInfo($appId);
			$appDbVersion = $this->appConfig->getValue($appId, 'installed_version');
			if ($appDbVersion
				&& isset($appInfo['version'])
				&& version_compare($appInfo['version'], $appDbVersion, '>')
				&& \OC_App::isAppCompatible($version, $appInfo)
			) {
				$appsToUpgrade[] = $appInfo;
			}
		}

		return $appsToUpgrade;
	}

	/**
	 * Returns the app information from "appinfo/info.xml".
	 *
	 * @param string $appId app id
	 *
	 * @param bool $path
	 * @param null $lang
	 * @return array|null app info
	 */
	public function getAppInfo(string $appId, bool $path = false, $lang = null) {
		if ($path) {
			$file = $appId;
		} else {
			if ($lang === null && isset($this->appInfos[$appId])) {
				return $this->appInfos[$appId];
			}
			try {
				$appPath = $this->getAppPath($appId);
			} catch (AppPathNotFoundException $e) {
				return null;
			}
			$file = $appPath . '/appinfo/info.xml';
		}

		$parser = new InfoParser($this->memCacheFactory->createLocal('core.appinfo'));
		$data = $parser->parse($file);

		if (is_array($data)) {
			$data = \OC_App::parseAppInfo($data, $lang);
		}

		if ($lang === null) {
			$this->appInfos[$appId] = $data;
		}

		return $data;
	}

	public function getAppVersion(string $appId, bool $useCache = true): string {
		if (!$useCache || !isset($this->appVersions[$appId])) {
			$appInfo = $this->getAppInfo($appId);
			$this->appVersions[$appId] = ($appInfo !== null && isset($appInfo['version'])) ? $appInfo['version'] : '0';
		}
		return $this->appVersions[$appId];
	}

	/**
	 * Returns a list of apps incompatible with the given version
	 *
	 * @param string $version Nextcloud version as array of version components
	 *
	 * @return array list of app info from incompatible apps
	 *
	 * @internal
	 */
	public function getIncompatibleApps(string $version): array {
		$apps = $this->getInstalledApps();
		$incompatibleApps = [];
		foreach ($apps as $appId) {
			$info = $this->getAppInfo($appId);
			if ($info === null) {
				$incompatibleApps[] = ['id' => $appId];
			} elseif (!\OC_App::isAppCompatible($version, $info)) {
				$incompatibleApps[] = $info;
			}
		}
		return $incompatibleApps;
	}

	/**
	 * @inheritdoc
	 * In case you change this method, also change \OC\App\CodeChecker\InfoChecker::isShipped()
	 */
	public function isShipped($appId) {
		$this->loadShippedJson();
		return in_array($appId, $this->shippedApps, true);
	}

	private function isAlwaysEnabled($appId) {
		$alwaysEnabled = $this->getAlwaysEnabledApps();
		return in_array($appId, $alwaysEnabled, true);
	}

	/**
	 * In case you change this method, also change \OC\App\CodeChecker\InfoChecker::loadShippedJson()
	 * @throws \Exception
	 */
	private function loadShippedJson() {
		if ($this->shippedApps === null) {
			$shippedJson = \OC::$SERVERROOT . '/core/shipped.json';
			if (!file_exists($shippedJson)) {
				throw new \Exception("File not found: $shippedJson");
			}
			$content = json_decode(file_get_contents($shippedJson), true);
			$this->shippedApps = $content['shippedApps'];
			$this->alwaysEnabled = $content['alwaysEnabled'];
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getAlwaysEnabledApps() {
		$this->loadShippedJson();
		return $this->alwaysEnabled;
	}
}
