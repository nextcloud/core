<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Borjan Tchakaloff <borjan@tchakaloff.fr>
 * @author Brice Maron <brice@bmaron.net>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Rudolf <github.com@daniel-rudolf.de>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Kamil Domanski <kdomanski@kdemail.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Markus Goetz <markus@woboq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author RealRancor <Fisch.666@gmx.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sam Tuke <mail@samtuke.com>
 * @author Sebastian Wessalowski <sebastian@wessalowski.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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
use OC\App\DependencyAnalyzer;
use OC\App\Platform;
use OC\AppFramework\Bootstrap\Coordinator;
use OC\DB\MigrationService;
use OC\Installer;
use OC\Repair;
use OC\ServerNotAvailableException;
use OCP\App\ManagerEvent;
use OCP\AppFramework\QueryException;
use OCP\Authentication\IAlternativeLogin;
use OCP\ILogger;

/**
 * This class manages the apps. It allows them to register and integrate in the
 * ownCloud ecosystem. Furthermore, this class is responsible for installing,
 * upgrading and removing apps.
 */
class OC_App {
	private static $adminForms = [];
	private static $personalForms = [];
	private static $appTypes = [];
	private static $loadedApps = [];
	private static $altLogin = [];
	private static $alreadyRegistered = [];
	public const supportedApp = 300;
	public const officialApp = 200;

	/**
	 * clean the appId
	 *
	 * @psalm-taint-escape file
	 * @psalm-taint-escape include
	 *
	 * @param string $app AppId that needs to be cleaned
	 * @return string
	 */
	public static function cleanAppId(string $app): string {
		return str_replace(['\0', '/', '\\', '..'], '', $app);
	}

	/**
	 * Check if an app is loaded
	 *
	 * @param string $app
	 * @return bool
	 */
	public static function isAppLoaded(string $app): bool {
		return isset(self::$loadedApps[$app]);
	}

	/**
	 * loads all apps
	 *
	 * @param string[] $types
	 * @return bool
	 *
	 * This function walks through the ownCloud directory and loads all apps
	 * it can find. A directory contains an app if the file /appinfo/info.xml
	 * exists.
	 *
	 * if $types is set to non-empty array, only apps of those types will be loaded
	 */
	public static function loadApps(array $types = []): bool {
		if ((bool) \OC::$server->getSystemConfig()->getValue('maintenance', false)) {
			return false;
		}
		// Load the enabled apps here
		$apps = self::getEnabledApps();

		// Add each apps' folder as allowed class path
		foreach ($apps as $app) {
			// If the app is already loaded then autoloading it makes no sense
			if (!isset(self::$loadedApps[$app])) {
				$path = self::getAppPath($app);
				if ($path !== false) {
					self::registerAutoloading($app, $path);
				}
			}
		}

		// prevent app.php from printing output
		ob_start();
		foreach ($apps as $app) {
			if (!isset(self::$loadedApps[$app]) && ($types === [] || self::isType($app, $types))) {
				self::loadApp($app);
			}
		}
		ob_end_clean();

		return true;
	}

	/**
	 * load a single app
	 *
	 * @param string $app
	 * @throws Exception
	 */
	public static function loadApp(string $app) {
		self::$loadedApps[$app] = true;
		$appPath = self::getAppPath($app);
		if ($appPath === false) {
			return;
		}

		// in case someone calls loadApp() directly
		self::registerAutoloading($app, $appPath);

		/** @var Coordinator $coordinator */
		$coordinator = \OC::$server->query(Coordinator::class);
		$isBootable = $coordinator->isBootable($app);

		$hasAppPhpFile = is_file($appPath . '/appinfo/app.php');

		if ($isBootable && $hasAppPhpFile) {
			\OC::$server->getLogger()->error('/appinfo/app.php is not loaded when \OCP\AppFramework\Bootstrap\IBootstrap on the application class is used. Migrate everything from app.php to the Application class.', [
				'app' => $app,
			]);
		} elseif ($hasAppPhpFile) {
			\OC::$server->getLogger()->debug('/appinfo/app.php is deprecated, use \OCP\AppFramework\Bootstrap\IBootstrap on the application class instead.', [
				'app' => $app,
			]);
			\OC::$server->getEventLogger()->start('load_app_' . $app, 'Load app: ' . $app);
			try {
				self::requireAppFile($app);
			} catch (Throwable $ex) {
				if ($ex instanceof ServerNotAvailableException) {
					throw $ex;
				}
				if (!\OC::$server->getAppManager()->isShipped($app) && !self::isType($app, ['authentication'])) {
					\OC::$server->getLogger()->logException($ex, [
						'message' => "App $app threw an error during app.php load and will be disabled: " . $ex->getMessage(),
					]);

					// Only disable apps which are not shipped and that are not authentication apps
					\OC::$server->getAppManager()->disableApp($app, true);
				} else {
					\OC::$server->getLogger()->logException($ex, [
						'message' => "App $app threw an error during app.php load: " . $ex->getMessage(),
					]);
				}
			}
			\OC::$server->getEventLogger()->end('load_app_' . $app);
		}
		$coordinator->bootApp($app);

		$info = self::getAppInfo($app);
		if (!empty($info['activity']['filters'])) {
			foreach ($info['activity']['filters'] as $filter) {
				\OC::$server->getActivityManager()->registerFilter($filter);
			}
		}
		if (!empty($info['activity']['settings'])) {
			foreach ($info['activity']['settings'] as $setting) {
				\OC::$server->getActivityManager()->registerSetting($setting);
			}
		}
		if (!empty($info['activity']['providers'])) {
			foreach ($info['activity']['providers'] as $provider) {
				\OC::$server->getActivityManager()->registerProvider($provider);
			}
		}

		if (!empty($info['settings']['admin'])) {
			foreach ($info['settings']['admin'] as $setting) {
				\OC::$server->getSettingsManager()->registerSetting('admin', $setting);
			}
		}
		if (!empty($info['settings']['admin-section'])) {
			foreach ($info['settings']['admin-section'] as $section) {
				\OC::$server->getSettingsManager()->registerSection('admin', $section);
			}
		}
		if (!empty($info['settings']['personal'])) {
			foreach ($info['settings']['personal'] as $setting) {
				\OC::$server->getSettingsManager()->registerSetting('personal', $setting);
			}
		}
		if (!empty($info['settings']['personal-section'])) {
			foreach ($info['settings']['personal-section'] as $section) {
				\OC::$server->getSettingsManager()->registerSection('personal', $section);
			}
		}

		if (!empty($info['collaboration']['plugins'])) {
			// deal with one or many plugin entries
			$plugins = isset($info['collaboration']['plugins']['plugin']['@value']) ?
				[$info['collaboration']['plugins']['plugin']] : $info['collaboration']['plugins']['plugin'];
			foreach ($plugins as $plugin) {
				if ($plugin['@attributes']['type'] === 'collaborator-search') {
					$pluginInfo = [
						'shareType' => $plugin['@attributes']['share-type'],
						'class' => $plugin['@value'],
					];
					\OC::$server->getCollaboratorSearch()->registerPlugin($pluginInfo);
				} elseif ($plugin['@attributes']['type'] === 'autocomplete-sort') {
					\OC::$server->getAutoCompleteManager()->registerSorter($plugin['@value']);
				}
			}
		}
	}

	/**
	 * @internal
	 * @param string $app
	 * @param string $path
	 * @param bool $force
	 */
	public static function registerAutoloading(string $app, string $path, bool $force = false) {
		$key = $app . '-' . $path;
		if (!$force && isset(self::$alreadyRegistered[$key])) {
			return;
		}

		self::$alreadyRegistered[$key] = true;

		// Register on PSR-4 composer autoloader
		$appNamespace = \OC\AppFramework\App::buildAppNamespace($app);
		\OC::$server->registerNamespace($app, $appNamespace);

		if (file_exists($path . '/composer/autoload.php')) {
			require_once $path . '/composer/autoload.php';
		} else {
			\OC::$composerAutoloader->addPsr4($appNamespace . '\\', $path . '/lib/', true);
			// Register on legacy autoloader
			\OC::$loader->addValidRoot($path);
		}

		// Register Test namespace only when testing
		if (defined('PHPUNIT_RUN') || defined('CLI_TEST_RUN')) {
			\OC::$composerAutoloader->addPsr4($appNamespace . '\\Tests\\', $path . '/tests/', true);
		}
	}

	/**
	 * Load app.php from the given app
	 *
	 * @param string $app app name
	 * @throws Error
	 */
	private static function requireAppFile(string $app) {
		// encapsulated here to avoid variable scope conflicts
		require_once $app . '/appinfo/app.php';
	}

	/**
	 * check if an app is of a specific type
	 *
	 * @param string $app
	 * @param array $types
	 * @return bool
	 */
	public static function isType(string $app, array $types): bool {
		$appTypes = self::getAppTypes($app);
		foreach ($types as $type) {
			if (array_search($type, $appTypes) !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * get the types of an app
	 *
	 * @param string $app
	 * @return array
	 */
	private static function getAppTypes(string $app): array {
		//load the cache
		if (count(self::$appTypes) == 0) {
			self::$appTypes = \OC::$server->getAppConfig()->getValues(false, 'types');
		}

		if (isset(self::$appTypes[$app])) {
			return explode(',', self::$appTypes[$app]);
		}

		return [];
	}

	/**
	 * read app types from info.xml and cache them in the database
	 */
	public static function setAppTypes(string $app) {
		$appManager = \OC::$server->getAppManager();
		$appData = $appManager->getAppInfo($app);
		if (!is_array($appData)) {
			return;
		}

		if (isset($appData['types'])) {
			$appTypes = implode(',', $appData['types']);
		} else {
			$appTypes = '';
			$appData['types'] = [];
		}

		$config = \OC::$server->getConfig();
		$config->setAppValue($app, 'types', $appTypes);

		if ($appManager->hasProtectedAppType($appData['types'])) {
			$enabled = $config->getAppValue($app, 'enabled', 'yes');
			if ($enabled !== 'yes' && $enabled !== 'no') {
				$config->setAppValue($app, 'enabled', 'yes');
			}
		}
	}

	/**
	 * Returns apps enabled for the current user.
	 *
	 * @param bool $forceRefresh whether to refresh the cache
	 * @param bool $all whether to return apps for all users, not only the
	 * currently logged in one
	 * @return string[]
	 */
	public static function getEnabledApps(bool $forceRefresh = false, bool $all = false): array {
		if (!\OC::$server->getSystemConfig()->getValue('installed', false)) {
			return [];
		}
		// in incognito mode or when logged out, $user will be false,
		// which is also the case during an upgrade
		$appManager = \OC::$server->getAppManager();
		if ($all) {
			$user = null;
		} else {
			$user = \OC::$server->getUserSession()->getUser();
		}

		if (is_null($user)) {
			$apps = $appManager->getInstalledApps();
		} else {
			$apps = $appManager->getEnabledAppsForUser($user);
		}
		$apps = array_filter($apps, function ($app) {
			return $app !== 'files';//we add this manually
		});
		sort($apps);
		array_unshift($apps, 'files');
		return $apps;
	}

	/**
	 * checks whether or not an app is enabled
	 *
	 * @param string $app app
	 * @return bool
	 * @deprecated 13.0.0 use \OC::$server->getAppManager()->isEnabledForUser($appId)
	 *
	 * This function checks whether or not an app is enabled.
	 */
	public static function isEnabled(string $app): bool {
		return \OC::$server->getAppManager()->isEnabledForUser($app);
	}

	/**
	 * enables an app
	 *
	 * @param string $appId
	 * @param array $groups (optional) when set, only these groups will have access to the app
	 * @throws \Exception
	 * @return void
	 *
	 * This function set an app as enabled in appconfig.
	 */
	public function enable(string $appId,
						   array $groups = []) {

		// Check if app is already downloaded
		/** @var Installer $installer */
		$installer = \OC::$server->query(Installer::class);
		$isDownloaded = $installer->isDownloaded($appId);

		if (!$isDownloaded) {
			$installer->downloadApp($appId);
		}

		$installer->installApp($appId);

		$appManager = \OC::$server->getAppManager();
		if ($groups !== []) {
			$groupManager = \OC::$server->getGroupManager();
			$groupsList = [];
			foreach ($groups as $group) {
				$groupItem = $groupManager->get($group);
				if ($groupItem instanceof \OCP\IGroup) {
					$groupsList[] = $groupManager->get($group);
				}
			}
			$appManager->enableAppForGroups($appId, $groupsList);
		} else {
			$appManager->enableApp($appId);
		}
	}

	/**
	 * Get the path where to install apps
	 *
	 * @return string|false
	 */
	public static function getInstallPath() {
		if (\OC::$server->getSystemConfig()->getValue('appstoreenabled', true) == false) {
			return false;
		}

		foreach (OC::$APPSROOTS as $dir) {
			if (isset($dir['writable']) && $dir['writable'] === true) {
				return $dir['path'];
			}
		}

		\OCP\Util::writeLog('core', 'No application directories are marked as writable.', ILogger::ERROR);
		return null;
	}


	/**
	 * search for an app in all app-directories
	 *
	 * @param string $appId
	 * @return false|string
	 */
	public static function findAppInDirectories(string $appId) {
		$sanitizedAppId = self::cleanAppId($appId);
		if ($sanitizedAppId !== $appId) {
			return false;
		}
		static $app_dir = [];

		if (isset($app_dir[$appId])) {
			return $app_dir[$appId];
		}

		$possibleApps = [];
		foreach (OC::$APPSROOTS as $dir) {
			if (file_exists($dir['path'] . '/' . $appId)) {
				$possibleApps[] = $dir;
			}
		}

		if (empty($possibleApps)) {
			return false;
		} elseif (count($possibleApps) === 1) {
			$dir = array_shift($possibleApps);
			$app_dir[$appId] = $dir;
			return $dir;
		} else {
			$versionToLoad = [];
			foreach ($possibleApps as $possibleApp) {
				$version = self::getAppVersionByPath($possibleApp['path'] . '/' . $appId);
				if (empty($versionToLoad) || version_compare($version, $versionToLoad['version'], '>')) {
					$versionToLoad = [
						'dir' => $possibleApp,
						'version' => $version,
					];
				}
			}
			$app_dir[$appId] = $versionToLoad['dir'];
			return $versionToLoad['dir'];
			//TODO - write test
		}
	}

	/**
	 * Get the directory for the given app.
	 * If the app is defined in multiple directories, the first one is taken. (false if not found)
	 *
	 * @psalm-taint-specialize
	 *
	 * @param string $appId
	 * @return string|false
	 * @deprecated 11.0.0 use \OC::$server->getAppManager()->getAppPath()
	 */
	public static function getAppPath(string $appId) {
		if ($appId === null || trim($appId) === '') {
			return false;
		}

		if (($dir = self::findAppInDirectories($appId)) != false) {
			return $dir['path'] . '/' . $appId;
		}
		return false;
	}

	/**
	 * Get the path for the given app on the access
	 * If the app is defined in multiple directories, the first one is taken. (false if not found)
	 *
	 * @param string $appId
	 * @return string|false
	 * @deprecated 18.0.0 use \OC::$server->getAppManager()->getAppWebPath()
	 */
	public static function getAppWebPath(string $appId) {
		if (($dir = self::findAppInDirectories($appId)) != false) {
			return OC::$WEBROOT . $dir['url'] . '/' . $appId;
		}
		return false;
	}

	/**
	 * get the last version of the app from appinfo/info.xml
	 *
	 * @param string $appId
	 * @param bool $useCache
	 * @return string
	 * @deprecated 14.0.0 use \OC::$server->getAppManager()->getAppVersion()
	 */
	public static function getAppVersion(string $appId, bool $useCache = true): string {
		return \OC::$server->getAppManager()->getAppVersion($appId, $useCache);
	}

	/**
	 * get app's version based on it's path
	 *
	 * @param string $path
	 * @return string
	 */
	public static function getAppVersionByPath(string $path): string {
		$infoFile = $path . '/appinfo/info.xml';
		$appData = \OC::$server->getAppManager()->getAppInfo($infoFile, true);
		return isset($appData['version']) ? $appData['version'] : '';
	}


	/**
	 * Read all app metadata from the info.xml file
	 *
	 * @param string $appId id of the app or the path of the info.xml file
	 * @param bool $path
	 * @param string $lang
	 * @return array|null
	 * @note all data is read from info.xml, not just pre-defined fields
	 * @deprecated 14.0.0 use \OC::$server->getAppManager()->getAppInfo()
	 */
	public static function getAppInfo(string $appId, bool $path = false, string $lang = null) {
		return \OC::$server->getAppManager()->getAppInfo($appId, $path, $lang);
	}

	/**
	 * Returns the navigation
	 *
	 * @return array
	 * @deprecated 14.0.0 use \OC::$server->getNavigationManager()->getAll()
	 *
	 * This function returns an array containing all entries added. The
	 * entries are sorted by the key 'order' ascending. Additional to the keys
	 * given for each app the following keys exist:
	 *   - active: boolean, signals if the user is on this navigation entry
	 */
	public static function getNavigation(): array {
		return OC::$server->getNavigationManager()->getAll();
	}

	/**
	 * Returns the Settings Navigation
	 *
	 * @return string[]
	 * @deprecated 14.0.0 use \OC::$server->getNavigationManager()->getAll('settings')
	 *
	 * This function returns an array containing all settings pages added. The
	 * entries are sorted by the key 'order' ascending.
	 */
	public static function getSettingsNavigation(): array {
		return OC::$server->getNavigationManager()->getAll('settings');
	}

	/**
	 * get the id of loaded app
	 *
	 * @return string
	 */
	public static function getCurrentApp(): string {
		$request = \OC::$server->getRequest();
		$script = substr($request->getScriptName(), strlen(OC::$WEBROOT) + 1);
		$topFolder = substr($script, 0, strpos($script, '/') ?: 0);
		if (empty($topFolder)) {
			$path_info = $request->getPathInfo();
			if ($path_info) {
				$topFolder = substr($path_info, 1, strpos($path_info, '/', 1) - 1);
			}
		}
		if ($topFolder == 'apps') {
			$length = strlen($topFolder);
			return substr($script, $length + 1, strpos($script, '/', $length + 1) - $length - 1) ?: '';
		} else {
			return $topFolder;
		}
	}

	/**
	 * @param string $type
	 * @return array
	 */
	public static function getForms(string $type): array {
		$forms = [];
		switch ($type) {
			case 'admin':
				$source = self::$adminForms;
				break;
			case 'personal':
				$source = self::$personalForms;
				break;
			default:
				return [];
		}
		foreach ($source as $form) {
			$forms[] = include $form;
		}
		return $forms;
	}

	/**
	 * register an admin form to be shown
	 *
	 * @param string $app
	 * @param string $page
	 */
	public static function registerAdmin(string $app, string $page) {
		self::$adminForms[] = $app . '/' . $page . '.php';
	}

	/**
	 * register a personal form to be shown
	 * @param string $app
	 * @param string $page
	 */
	public static function registerPersonal(string $app, string $page) {
		self::$personalForms[] = $app . '/' . $page . '.php';
	}

	/**
	 * @param array $entry
	 * @deprecated 20.0.0 Please register your alternative login option using the registerAlternativeLogin() on the RegistrationContext in your Application class implementing the OCP\Authentication\IAlternativeLogin interface
	 */
	public static function registerLogIn(array $entry) {
		\OC::$server->getLogger()->debug('OC_App::registerLogIn() is deprecated, please register your alternative login option using the registerAlternativeLogin() on the RegistrationContext in your Application class implementing the OCP\Authentication\IAlternativeLogin interface');
		self::$altLogin[] = $entry;
	}

	/**
	 * @return array
	 */
	public static function getAlternativeLogIns(): array {
		/** @var Coordinator $bootstrapCoordinator */
		$bootstrapCoordinator = \OC::$server->query(Coordinator::class);

		foreach ($bootstrapCoordinator->getRegistrationContext()->getAlternativeLogins() as $registration) {
			if (!in_array(IAlternativeLogin::class, class_implements($registration->getService()), true)) {
				\OC::$server->getLogger()->error('Alternative login option {option} does not implement {interface} and is therefore ignored.', [
					'option' => $registration->getService(),
					'interface' => IAlternativeLogin::class,
					'app' => $registration->getAppId(),
				]);
				continue;
			}

			try {
				/** @var IAlternativeLogin $provider */
				$provider = \OC::$server->query($registration->getService());
			} catch (QueryException $e) {
				\OC::$server->getLogger()->logException($e, [
					'message' => 'Alternative login option {option} can not be initialised.',
					'option' => $registration->getService(),
					'app' => $registration->getAppId(),
				]);
			}

			try {
				$provider->load();

				self::$altLogin[] = [
					'name' => $provider->getLabel(),
					'href' => $provider->getLink(),
					'style' => $provider->getClass(),
				];
			} catch (Throwable $e) {
				\OC::$server->getLogger()->logException($e, [
					'message' => 'Alternative login option {option} had an error while loading.',
					'option' => $registration->getService(),
					'app' => $registration->getAppId(),
				]);
			}
		}

		return self::$altLogin;
	}

	/**
	 * get a list of all apps in the apps folder
	 *
	 * @return string[] an array of app names (string IDs)
	 * @todo: change the name of this method to getInstalledApps, which is more accurate
	 */
	public static function getAllApps(): array {
		$apps = [];

		foreach (OC::$APPSROOTS as $apps_dir) {
			if (!is_readable($apps_dir['path'])) {
				\OCP\Util::writeLog('core', 'unable to read app folder : ' . $apps_dir['path'], ILogger::WARN);
				continue;
			}
			$dh = opendir($apps_dir['path']);

			if (is_resource($dh)) {
				while (($file = readdir($dh)) !== false) {
					if ($file[0] != '.' and is_dir($apps_dir['path'] . '/' . $file) and is_file($apps_dir['path'] . '/' . $file . '/appinfo/info.xml')) {
						$apps[] = $file;
					}
				}
			}
		}

		$apps = array_unique($apps);

		return $apps;
	}

	/**
	 * List all apps, this is used in apps.php
	 *
	 * @return array
	 */
	public function listAllApps(): array {
		$installedApps = OC_App::getAllApps();

		$appManager = \OC::$server->getAppManager();
		//we don't want to show configuration for these
		$blacklist = $appManager->getAlwaysEnabledApps();
		$appList = [];
		$langCode = \OC::$server->getL10N('core')->getLanguageCode();
		$urlGenerator = \OC::$server->getURLGenerator();
		/** @var \OCP\Support\Subscription\IRegistry $subscriptionRegistry */
		$subscriptionRegistry = \OC::$server->query(\OCP\Support\Subscription\IRegistry::class);
		$supportedApps = $subscriptionRegistry->delegateGetSupportedApps();

		foreach ($installedApps as $app) {
			if (array_search($app, $blacklist) === false) {
				$info = OC_App::getAppInfo($app, false, $langCode);
				if (!is_array($info)) {
					\OCP\Util::writeLog('core', 'Could not read app info file for app "' . $app . '"', ILogger::ERROR);
					continue;
				}

				if (!isset($info['name'])) {
					\OCP\Util::writeLog('core', 'App id "' . $app . '" has no name in appinfo', ILogger::ERROR);
					continue;
				}

				$enabled = \OC::$server->getConfig()->getAppValue($app, 'enabled', 'no');
				$info['groups'] = null;
				if ($enabled === 'yes') {
					$active = true;
				} elseif ($enabled === 'no') {
					$active = false;
				} else {
					$active = true;
					$info['groups'] = $enabled;
				}

				$info['active'] = $active;

				if ($appManager->isShipped($app)) {
					$info['internal'] = true;
					$info['level'] = self::officialApp;
					$info['removable'] = false;
				} else {
					$info['internal'] = false;
					$info['removable'] = true;
				}

				if (in_array($app, $supportedApps)) {
					$info['level'] = self::supportedApp;
				}

				$appPath = self::getAppPath($app);
				if ($appPath !== false) {
					$appIcon = $appPath . '/img/' . $app . '.svg';
					if (file_exists($appIcon)) {
						$info['preview'] = $urlGenerator->imagePath($app, $app . '.svg');
						$info['previewAsIcon'] = true;
					} else {
						$appIcon = $appPath . '/img/app.svg';
						if (file_exists($appIcon)) {
							$info['preview'] = $urlGenerator->imagePath($app, 'app.svg');
							$info['previewAsIcon'] = true;
						}
					}
				}
				// fix documentation
				if (isset($info['documentation']) && is_array($info['documentation'])) {
					foreach ($info['documentation'] as $key => $url) {
						// If it is not an absolute URL we assume it is a key
						// i.e. admin-ldap will get converted to go.php?to=admin-ldap
						if (stripos($url, 'https://') !== 0 && stripos($url, 'http://') !== 0) {
							$url = $urlGenerator->linkToDocs($url);
						}

						$info['documentation'][$key] = $url;
					}
				}

				$info['version'] = OC_App::getAppVersion($app);
				$appList[] = $info;
			}
		}

		return $appList;
	}

	public static function shouldUpgrade(string $app): bool {
		$versions = self::getAppVersions();
		$currentVersion = OC_App::getAppVersion($app);
		if ($currentVersion && isset($versions[$app])) {
			$installedVersion = $versions[$app];
			if (!version_compare($currentVersion, $installedVersion, '=')) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adjust the number of version parts of $version1 to match
	 * the number of version parts of $version2.
	 *
	 * @param string $version1 version to adjust
	 * @param string $version2 version to take the number of parts from
	 * @return string shortened $version1
	 */
	private static function adjustVersionParts(string $version1, string $version2): string {
		$version1 = explode('.', $version1);
		$version2 = explode('.', $version2);
		// reduce $version1 to match the number of parts in $version2
		while (count($version1) > count($version2)) {
			array_pop($version1);
		}
		// if $version1 does not have enough parts, add some
		while (count($version1) < count($version2)) {
			$version1[] = '0';
		}
		return implode('.', $version1);
	}

	/**
	 * Check whether the current ownCloud version matches the given
	 * application's version requirements.
	 *
	 * The comparison is made based on the number of parts that the
	 * app info version has. For example for ownCloud 6.0.3 if the
	 * app info version is expecting version 6.0, the comparison is
	 * made on the first two parts of the ownCloud version.
	 * This means that it's possible to specify "requiremin" => 6
	 * and "requiremax" => 6 and it will still match ownCloud 6.0.3.
	 *
	 * @param string $ocVersion ownCloud version to check against
	 * @param array $appInfo app info (from xml)
	 *
	 * @return boolean true if compatible, otherwise false
	 */
	public static function isAppCompatible(string $ocVersion, array $appInfo, bool $ignoreMax = false): bool {
		$requireMin = '';
		$requireMax = '';
		if (isset($appInfo['dependencies']['nextcloud']['@attributes']['min-version'])) {
			$requireMin = $appInfo['dependencies']['nextcloud']['@attributes']['min-version'];
		} elseif (isset($appInfo['dependencies']['owncloud']['@attributes']['min-version'])) {
			$requireMin = $appInfo['dependencies']['owncloud']['@attributes']['min-version'];
		} elseif (isset($appInfo['requiremin'])) {
			$requireMin = $appInfo['requiremin'];
		} elseif (isset($appInfo['require'])) {
			$requireMin = $appInfo['require'];
		}

		if (isset($appInfo['dependencies']['nextcloud']['@attributes']['max-version'])) {
			$requireMax = $appInfo['dependencies']['nextcloud']['@attributes']['max-version'];
		} elseif (isset($appInfo['dependencies']['owncloud']['@attributes']['max-version'])) {
			$requireMax = $appInfo['dependencies']['owncloud']['@attributes']['max-version'];
		} elseif (isset($appInfo['requiremax'])) {
			$requireMax = $appInfo['requiremax'];
		}

		if (!empty($requireMin)
			&& version_compare(self::adjustVersionParts($ocVersion, $requireMin), $requireMin, '<')
		) {
			return false;
		}

		if (!$ignoreMax && !empty($requireMax)
			&& version_compare(self::adjustVersionParts($ocVersion, $requireMax), $requireMax, '>')
		) {
			return false;
		}

		return true;
	}

	/**
	 * get the installed version of all apps
	 */
	public static function getAppVersions() {
		static $versions;

		if (!$versions) {
			$appConfig = \OC::$server->getAppConfig();
			$versions = $appConfig->getValues(false, 'installed_version');
		}
		return $versions;
	}

	/**
	 * update the database for the app and call the update script
	 *
	 * @param string $appId
	 * @return bool
	 */
	public static function updateApp(string $appId): bool {
		$appPath = self::getAppPath($appId);
		if ($appPath === false) {
			return false;
		}

		\OC::$server->getAppManager()->clearAppsCache();
		$appData = self::getAppInfo($appId);

		$ignoreMaxApps = \OC::$server->getConfig()->getSystemValue('app_install_overwrite', []);
		$ignoreMax = in_array($appId, $ignoreMaxApps, true);
		\OC_App::checkAppDependencies(
			\OC::$server->getConfig(),
			\OC::$server->getL10N('core'),
			$appData,
			$ignoreMax
		);

		self::registerAutoloading($appId, $appPath, true);
		self::executeRepairSteps($appId, $appData['repair-steps']['pre-migration']);

		if (file_exists($appPath . '/appinfo/database.xml')) {
			OC_DB::updateDbFromStructure($appPath . '/appinfo/database.xml');
		} else {
			$ms = new MigrationService($appId, \OC::$server->get(\OC\DB\Connection::class));
			$ms->migrate();
		}

		self::executeRepairSteps($appId, $appData['repair-steps']['post-migration']);
		self::setupLiveMigrations($appId, $appData['repair-steps']['live-migration']);
		// update appversion in app manager
		\OC::$server->getAppManager()->clearAppsCache();
		\OC::$server->getAppManager()->getAppVersion($appId, false);

		self::setupBackgroundJobs($appData['background-jobs']);

		//set remote/public handlers
		if (array_key_exists('ocsid', $appData)) {
			\OC::$server->getConfig()->setAppValue($appId, 'ocsid', $appData['ocsid']);
		} elseif (\OC::$server->getConfig()->getAppValue($appId, 'ocsid', null) !== null) {
			\OC::$server->getConfig()->deleteAppValue($appId, 'ocsid');
		}
		foreach ($appData['remote'] as $name => $path) {
			\OC::$server->getConfig()->setAppValue('core', 'remote_' . $name, $appId . '/' . $path);
		}
		foreach ($appData['public'] as $name => $path) {
			\OC::$server->getConfig()->setAppValue('core', 'public_' . $name, $appId . '/' . $path);
		}

		self::setAppTypes($appId);

		$version = \OC_App::getAppVersion($appId);
		\OC::$server->getConfig()->setAppValue($appId, 'installed_version', $version);

		\OC::$server->getEventDispatcher()->dispatch(ManagerEvent::EVENT_APP_UPDATE, new ManagerEvent(
			ManagerEvent::EVENT_APP_UPDATE, $appId
		));

		return true;
	}

	/**
	 * @param string $appId
	 * @param string[] $steps
	 * @throws \OC\NeedsUpdateException
	 */
	public static function executeRepairSteps(string $appId, array $steps) {
		if (empty($steps)) {
			return;
		}
		// load the app
		self::loadApp($appId);

		$dispatcher = OC::$server->getEventDispatcher();

		// load the steps
		$r = new Repair([], $dispatcher);
		foreach ($steps as $step) {
			try {
				$r->addStep($step);
			} catch (Exception $ex) {
				$r->emit('\OC\Repair', 'error', [$ex->getMessage()]);
				\OC::$server->getLogger()->logException($ex);
			}
		}
		// run the steps
		$r->run();
	}

	public static function setupBackgroundJobs(array $jobs) {
		$queue = \OC::$server->getJobList();
		foreach ($jobs as $job) {
			$queue->add($job);
		}
	}

	/**
	 * @param string $appId
	 * @param string[] $steps
	 */
	private static function setupLiveMigrations(string $appId, array $steps) {
		$queue = \OC::$server->getJobList();
		foreach ($steps as $step) {
			$queue->add('OC\Migration\BackgroundRepair', [
				'app' => $appId,
				'step' => $step]);
		}
	}

	/**
	 * @param string $appId
	 * @return \OC\Files\View|false
	 */
	public static function getStorage(string $appId) {
		if (\OC::$server->getAppManager()->isEnabledForUser($appId)) { //sanity check
			if (\OC::$server->getUserSession()->isLoggedIn()) {
				$view = new \OC\Files\View('/' . OC_User::getUser());
				if (!$view->file_exists($appId)) {
					$view->mkdir($appId);
				}
				return new \OC\Files\View('/' . OC_User::getUser() . '/' . $appId);
			} else {
				\OCP\Util::writeLog('core', 'Can\'t get app storage, app ' . $appId . ', user not logged in', ILogger::ERROR);
				return false;
			}
		} else {
			\OCP\Util::writeLog('core', 'Can\'t get app storage, app ' . $appId . ' not enabled', ILogger::ERROR);
			return false;
		}
	}

	protected static function findBestL10NOption(array $options, string $lang): string {
		// only a single option
		if (isset($options['@value'])) {
			return $options['@value'];
		}

		$fallback = $similarLangFallback = $englishFallback = false;

		$lang = strtolower($lang);
		$similarLang = $lang;
		if (strpos($similarLang, '_')) {
			// For "de_DE" we want to find "de" and the other way around
			$similarLang = substr($lang, 0, strpos($lang, '_'));
		}

		foreach ($options as $option) {
			if (is_array($option)) {
				if ($fallback === false) {
					$fallback = $option['@value'];
				}

				if (!isset($option['@attributes']['lang'])) {
					continue;
				}

				$attributeLang = strtolower($option['@attributes']['lang']);
				if ($attributeLang === $lang) {
					return $option['@value'];
				}

				if ($attributeLang === $similarLang) {
					$similarLangFallback = $option['@value'];
				} elseif (strpos($attributeLang, $similarLang . '_') === 0) {
					if ($similarLangFallback === false) {
						$similarLangFallback = $option['@value'];
					}
				}
			} else {
				$englishFallback = $option;
			}
		}

		if ($similarLangFallback !== false) {
			return $similarLangFallback;
		} elseif ($englishFallback !== false) {
			return $englishFallback;
		}
		return (string) $fallback;
	}

	/**
	 * parses the app data array and enhanced the 'description' value
	 *
	 * @param array $data the app data
	 * @param string $lang
	 * @return array improved app data
	 */
	public static function parseAppInfo(array $data, $lang = null): array {
		if ($lang && isset($data['name']) && is_array($data['name'])) {
			$data['name'] = self::findBestL10NOption($data['name'], $lang);
		}
		if ($lang && isset($data['summary']) && is_array($data['summary'])) {
			$data['summary'] = self::findBestL10NOption($data['summary'], $lang);
		}
		if ($lang && isset($data['description']) && is_array($data['description'])) {
			$data['description'] = trim(self::findBestL10NOption($data['description'], $lang));
		} elseif (isset($data['description']) && is_string($data['description'])) {
			$data['description'] = trim($data['description']);
		} else {
			$data['description'] = '';
		}

		return $data;
	}

	/**
	 * @param \OCP\IConfig $config
	 * @param \OCP\IL10N $l
	 * @param array $info
	 * @throws \Exception
	 */
	public static function checkAppDependencies(\OCP\IConfig $config, \OCP\IL10N $l, array $info, bool $ignoreMax) {
		$dependencyAnalyzer = new DependencyAnalyzer(new Platform($config), $l);
		$missing = $dependencyAnalyzer->analyze($info, $ignoreMax);
		if (!empty($missing)) {
			$missingMsg = implode(PHP_EOL, $missing);
			throw new \Exception(
				$l->t('App "%1$s" cannot be installed because the following dependencies are not fulfilled: %2$s',
					[$info['name'], $missingMsg]
				)
			);
		}
	}
}
