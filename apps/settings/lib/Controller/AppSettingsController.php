<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Settings\Controller;

use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\App\AppStore\Fetcher\CategoryFetcher;
use OC\App\AppStore\Version\VersionParser;
use OC\App\DependencyAnalyzer;
use OC\App\Platform;
use OC\Installer;
use OC_App;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class AppSettingsController extends Controller {

	/** @var \OCP\IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;
	/** @var INavigationManager */
	private $navigationManager;
	/** @var IAppManager */
	private $appManager;
	/** @var CategoryFetcher */
	private $categoryFetcher;
	/** @var AppFetcher */
	private $appFetcher;
	/** @var IFactory */
	private $l10nFactory;
	/** @var BundleFetcher */
	private $bundleFetcher;
	/** @var Installer */
	private $installer;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ILogger */
	private $logger;

	/** @var array */
	private $allApps = [];

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param IConfig $config
	 * @param INavigationManager $navigationManager
	 * @param IAppManager $appManager
	 * @param CategoryFetcher $categoryFetcher
	 * @param AppFetcher $appFetcher
	 * @param IFactory $l10nFactory
	 * @param BundleFetcher $bundleFetcher
	 * @param Installer $installer
	 * @param IURLGenerator $urlGenerator
	 * @param ILogger $logger
	 */
	public function __construct(string $appName,
								IRequest $request,
								IL10N $l10n,
								IConfig $config,
								INavigationManager $navigationManager,
								IAppManager $appManager,
								CategoryFetcher $categoryFetcher,
								AppFetcher $appFetcher,
								IFactory $l10nFactory,
								BundleFetcher $bundleFetcher,
								Installer $installer,
								IURLGenerator $urlGenerator,
								ILogger $logger) {
		parent::__construct($appName, $request);
		$this->l10n = $l10n;
		$this->config = $config;
		$this->navigationManager = $navigationManager;
		$this->appManager = $appManager;
		$this->categoryFetcher = $categoryFetcher;
		$this->appFetcher = $appFetcher;
		$this->l10nFactory = $l10nFactory;
		$this->bundleFetcher = $bundleFetcher;
		$this->installer = $installer;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
	}

	/**
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function viewApps(): TemplateResponse {
		\OC_Util::addScript('settings', 'apps');
		$params = [];
		$params['appstoreEnabled'] = $this->config->getSystemValue('appstoreenabled', true) === true;
		$params['updateCount'] = count($this->getAppsWithUpdates());
		$params['developerDocumentation'] = $this->urlGenerator->linkToDocs('developer-manual');
		$params['bundles'] = $this->getBundles();
		$this->navigationManager->setActiveEntry('core_apps');

		$templateResponse = new TemplateResponse('settings', 'settings-vue', ['serverData' => $params]);
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://usercontent.apps.nextcloud.com');
		$templateResponse->setContentSecurityPolicy($policy);

		return $templateResponse;
	}

	private function getAppsWithUpdates() {
		$appClass = new \OC_App();
		$apps = $appClass->listAllApps();
		foreach ($apps as $key => $app) {
			$newVersion = $this->installer->isUpdateAvailable($app['id']);
			if ($newVersion === false) {
				unset($apps[$key]);
			}
		}
		return $apps;
	}

	private function getBundles() {
		$result = [];
		$bundles = $this->bundleFetcher->getBundles();
		foreach ($bundles as $bundle) {
			$result[] = [
				'name' => $bundle->getName(),
				'id' => $bundle->getIdentifier(),
				'appIdentifiers' => $bundle->getAppIdentifiers()
			];
		}
		return $result;
	}

	/**
	 * Get all available categories
	 *
	 * @return JSONResponse
	 */
	public function listCategories(): JSONResponse {
		return new JSONResponse($this->getAllCategories());
	}

	private function getAllCategories() {
		$currentLanguage = substr($this->l10nFactory->findLanguage(), 0, 2);

		$formattedCategories = [];
		$categories = $this->categoryFetcher->get();
		foreach ($categories as $category) {
			$formattedCategories[] = [
				'id' => $category['id'],
				'ident' => $category['id'],
				'displayName' => isset($category['translations'][$currentLanguage]['name']) ? $category['translations'][$currentLanguage]['name'] : $category['translations']['en']['name'],
			];
		}

		return $formattedCategories;
	}

	private function fetchApps() {
		$appClass = new \OC_App();
		$apps = $appClass->listAllApps();
		foreach ($apps as $app) {
			$app['installed'] = true;
			$this->allApps[$app['id']] = $app;
		}

		$apps = $this->getAppsForCategory('');
		foreach ($apps as $app) {
			$app['appstore'] = true;
			if (!array_key_exists($app['id'], $this->allApps)) {
				$this->allApps[$app['id']] = $app;
			} else {
				$this->allApps[$app['id']] = array_merge($app, $this->allApps[$app['id']]);
			}
		}

		// add bundle information
		$bundles = $this->bundleFetcher->getBundles();
		foreach ($bundles as $bundle) {
			foreach ($bundle->getAppIdentifiers() as $identifier) {
				foreach ($this->allApps as &$app) {
					if ($app['id'] === $identifier) {
						$app['bundleIds'][] = $bundle->getIdentifier();
						continue;
					}
				}
			}
		}
	}

	private function getAllApps() {
		return $this->allApps;
	}
	/**
	 * Get all available apps in a category
	 *
	 * @param string $category
	 * @return JSONResponse
	 * @throws \Exception
	 */
	public function listApps(): JSONResponse {
		$this->fetchApps();
		$apps = $this->getAllApps();

		$dependencyAnalyzer = new DependencyAnalyzer(new Platform($this->config), $this->l10n);

		// Extend existing app details
		$apps = array_map(function ($appData) use ($dependencyAnalyzer) {
			if (isset($appData['appstoreData'])) {
				$appstoreData = $appData['appstoreData'];
				$appData['screenshot'] = isset($appstoreData['screenshots'][0]['url']) ? 'https://usercontent.apps.nextcloud.com/' . base64_encode($appstoreData['screenshots'][0]['url']) : '';
				$appData['category'] = $appstoreData['categories'];
				$appData['releases'] = $appstoreData['releases'];
			}

			$newVersion = $this->installer->isUpdateAvailable($appData['id']);
			if ($newVersion) {
				$appData['update'] = $newVersion;
			}

			// fix groups to be an array
			$groups = [];
			if (is_string($appData['groups'])) {
				$groups = json_decode($appData['groups']);
			}
			$appData['groups'] = $groups;
			$appData['canUnInstall'] = !$appData['active'] && $appData['removable'];

			// fix licence vs license
			if (isset($appData['license']) && !isset($appData['licence'])) {
				$appData['licence'] = $appData['license'];
			}

			$ignoreMaxApps = $this->config->getSystemValue('app_install_overwrite', []);
			if (!is_array($ignoreMaxApps)) {
				$this->logger->warning('The value given for app_install_overwrite is not an array. Ignoring...');
				$ignoreMaxApps = [];
			}
			$ignoreMax = in_array($appData['id'], $ignoreMaxApps);

			// analyse dependencies
			$missing = $dependencyAnalyzer->analyze($appData, $ignoreMax);
			$appData['canInstall'] = empty($missing);
			$appData['missingDependencies'] = $missing;

			$appData['missingMinOwnCloudVersion'] = !isset($appData['dependencies']['nextcloud']['@attributes']['min-version']);
			$appData['missingMaxOwnCloudVersion'] = !isset($appData['dependencies']['nextcloud']['@attributes']['max-version']);
			$appData['isCompatible'] = $dependencyAnalyzer->isMarkedCompatible($appData);

			return $appData;
		}, $apps);

		usort($apps, [$this, 'sortApps']);

		return new JSONResponse(['apps' => $apps, 'status' => 'success']);
	}

	/**
	 * Get all apps for a category from the app store
	 *
	 * @param string $requestedCategory
	 * @return array
	 * @throws \Exception
	 */
	private function getAppsForCategory($requestedCategory = ''): array {
		$versionParser = new VersionParser();
		$formattedApps = [];
		$apps = $this->appFetcher->get();
		foreach ($apps as $app) {
			// Skip all apps not in the requested category
			if ($requestedCategory !== '') {
				$isInCategory = false;
				foreach ($app['categories'] as $category) {
					if ($category === $requestedCategory) {
						$isInCategory = true;
					}
				}
				if (!$isInCategory) {
					continue;
				}
			}

			if (!isset($app['releases'][0]['rawPlatformVersionSpec'])) {
				continue;
			}
			$nextCloudVersion = $versionParser->getVersion($app['releases'][0]['rawPlatformVersionSpec']);
			$nextCloudVersionDependencies = [];
			if ($nextCloudVersion->getMinimumVersion() !== '') {
				$nextCloudVersionDependencies['nextcloud']['@attributes']['min-version'] = $nextCloudVersion->getMinimumVersion();
			}
			if ($nextCloudVersion->getMaximumVersion() !== '') {
				$nextCloudVersionDependencies['nextcloud']['@attributes']['max-version'] = $nextCloudVersion->getMaximumVersion();
			}
			$phpVersion = $versionParser->getVersion($app['releases'][0]['rawPhpVersionSpec']);
			$existsLocally = \OC_App::getAppPath($app['id']) !== false;
			$phpDependencies = [];
			if ($phpVersion->getMinimumVersion() !== '') {
				$phpDependencies['php']['@attributes']['min-version'] = $phpVersion->getMinimumVersion();
			}
			if ($phpVersion->getMaximumVersion() !== '') {
				$phpDependencies['php']['@attributes']['max-version'] = $phpVersion->getMaximumVersion();
			}
			if (isset($app['releases'][0]['minIntSize'])) {
				$phpDependencies['php']['@attributes']['min-int-size'] = $app['releases'][0]['minIntSize'];
			}
			$authors = '';
			foreach ($app['authors'] as $key => $author) {
				$authors .= $author['name'];
				if ($key !== count($app['authors']) - 1) {
					$authors .= ', ';
				}
			}

			$currentLanguage = substr(\OC::$server->getL10NFactory()->findLanguage(), 0, 2);
			$enabledValue = $this->config->getAppValue($app['id'], 'enabled', 'no');
			$groups = null;
			if ($enabledValue !== 'no' && $enabledValue !== 'yes') {
				$groups = $enabledValue;
			}

			$currentVersion = '';
			if ($this->appManager->isInstalled($app['id'])) {
				$currentVersion = $this->appManager->getAppVersion($app['id']);
			} else {
				$currentLanguage = $app['releases'][0]['version'];
			}

			$formattedApps[] = [
				'id' => $app['id'],
				'name' => isset($app['translations'][$currentLanguage]['name']) ? $app['translations'][$currentLanguage]['name'] : $app['translations']['en']['name'],
				'description' => isset($app['translations'][$currentLanguage]['description']) ? $app['translations'][$currentLanguage]['description'] : $app['translations']['en']['description'],
				'summary' => isset($app['translations'][$currentLanguage]['summary']) ? $app['translations'][$currentLanguage]['summary'] : $app['translations']['en']['summary'],
				'license' => $app['releases'][0]['licenses'],
				'author' => $authors,
				'shipped' => false,
				'version' => $currentVersion,
				'default_enable' => '',
				'types' => [],
				'documentation' => [
					'admin' => $app['adminDocs'],
					'user' => $app['userDocs'],
					'developer' => $app['developerDocs']
				],
				'website' => $app['website'],
				'bugs' => $app['issueTracker'],
				'detailpage' => $app['website'],
				'dependencies' => array_merge(
					$nextCloudVersionDependencies,
					$phpDependencies
				),
				'level' => ($app['isFeatured'] === true) ? 200 : 100,
				'missingMaxOwnCloudVersion' => false,
				'missingMinOwnCloudVersion' => false,
				'canInstall' => true,
				'screenshot' => isset($app['screenshots'][0]['url']) ? 'https://usercontent.apps.nextcloud.com/'.base64_encode($app['screenshots'][0]['url']) : '',
				'score' => $app['ratingOverall'],
				'ratingNumOverall' => $app['ratingNumOverall'],
				'ratingNumThresholdReached' => $app['ratingNumOverall'] > 5,
				'removable' => $existsLocally,
				'active' => $this->appManager->isEnabledForUser($app['id']),
				'needsDownload' => !$existsLocally,
				'groups' => $groups,
				'fromAppStore' => true,
				'appstoreData' => $app,
			];
		}

		return $formattedApps;
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $appId
	 * @param array $groups
	 * @return JSONResponse
	 */
	public function enableApp(string $appId, array $groups = []): JSONResponse {
		return $this->enableApps([$appId], $groups);
	}

	/**
	 * Enable one or more apps
	 *
	 * apps will be enabled for specific groups only if $groups is defined
	 *
	 * @PasswordConfirmationRequired
	 * @param array $appIds
	 * @param array $groups
	 * @return JSONResponse
	 */
	public function enableApps(array $appIds, array $groups = []): JSONResponse {
		try {
			$updateRequired = false;

			foreach ($appIds as $appId) {
				$appId = OC_App::cleanAppId($appId);

				// Check if app is already downloaded
				/** @var Installer $installer */
				$installer = \OC::$server->query(Installer::class);
				$isDownloaded = $installer->isDownloaded($appId);

				if (!$isDownloaded) {
					$installer->downloadApp($appId);
				}

				$installer->installApp($appId);

				if (count($groups) > 0) {
					$this->appManager->enableAppForGroups($appId, $this->getGroupList($groups));
				} else {
					$this->appManager->enableApp($appId);
				}
				if (\OC_App::shouldUpgrade($appId)) {
					$updateRequired = true;
				}
			}
			return new JSONResponse(['data' => ['update_required' => $updateRequired]]);
		} catch (\Exception $e) {
			$this->logger->logException($e);
			return new JSONResponse(['data' => ['message' => $e->getMessage()]], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	private function getGroupList(array $groups) {
		$groupManager = \OC::$server->getGroupManager();
		$groupsList = [];
		foreach ($groups as $group) {
			$groupItem = $groupManager->get($group);
			if ($groupItem instanceof \OCP\IGroup) {
				$groupsList[] = $groupManager->get($group);
			}
		}
		return $groupsList;
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $appId
	 * @return JSONResponse
	 */
	public function disableApp(string $appId): JSONResponse {
		return $this->disableApps([$appId]);
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param array $appIds
	 * @return JSONResponse
	 */
	public function disableApps(array $appIds): JSONResponse {
		try {
			foreach ($appIds as $appId) {
				$appId = OC_App::cleanAppId($appId);
				$this->appManager->disableApp($appId);
			}
			return new JSONResponse([]);
		} catch (\Exception $e) {
			$this->logger->logException($e);
			return new JSONResponse(['data' => ['message' => $e->getMessage()]], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * @PasswordConfirmationRequired
	 *
	 * @param string $appId
	 * @return JSONResponse
	 */
	public function uninstallApp(string $appId): JSONResponse {
		$appId = OC_App::cleanAppId($appId);
		$result = $this->installer->removeApp($appId);
		if ($result !== false) {
			$this->appManager->clearAppsCache();
			return new JSONResponse(['data' => ['appid' => $appId]]);
		}
		return new JSONResponse(['data' => ['message' => $this->l10n->t('Couldn\'t remove app.')]], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * @param string $appId
	 * @return JSONResponse
	 */
	public function updateApp(string $appId): JSONResponse {
		$appId = OC_App::cleanAppId($appId);

		$this->config->setSystemValue('maintenance', true);
		try {
			$result = $this->installer->updateAppstoreApp($appId);
			$this->config->setSystemValue('maintenance', false);
		} catch (\Exception $ex) {
			$this->config->setSystemValue('maintenance', false);
			return new JSONResponse(['data' => ['message' => $ex->getMessage()]], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($result !== false) {
			return new JSONResponse(['data' => ['appid' => $appId]]);
		}
		return new JSONResponse(['data' => ['message' => $this->l10n->t('Couldn\'t update app.')]], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	private function sortApps($a, $b) {
		$a = (string)$a['name'];
		$b = (string)$b['name'];
		if ($a === $b) {
			return 0;
		}
		return ($a < $b) ? -1 : 1;
	}

	public function force(string $appId): JSONResponse {
		$appId = OC_App::cleanAppId($appId);
		$this->appManager->ignoreNextcloudRequirementForApp($appId);
		return new JSONResponse();
	}
}
