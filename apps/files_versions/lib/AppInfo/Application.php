<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OCA\Files_Versions\AppInfo;

use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Files_Versions\Capabilities;
use OCA\Files_Versions\Hooks;
use OCA\Files_Versions\Listener\LoadAdditionalListener;
use OCA\Files_Versions\Listener\LoadSidebarListener;
use OCA\Files_Versions\Versions\IVersionManager;
use OCA\Files_Versions\Versions\VersionManager;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\ILogger;
use OCP\IServerContainer;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_versions';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		/**
		 * Register capabilities
		 */
		$context->registerCapability(Capabilities::class);

		/**
		 * Register $principalBackend for the DAV collection
		 */
		$context->registerService('principalBackend', function (ContainerInterface $c) {
			/** @var IServerContainer $server */
			$server = $c->get(IServerContainer::class);
			return new Principal(
				$server->getUserManager(),
				$server->getGroupManager(),
				$server->getShareManager(),
				$server->getUserSession(),
				$server->getAppManager(),
				$server->get(ProxyMapper::class),
				$server->getConfig()
			);
		});

		$context->registerService(IVersionManager::class, function () {
			return new VersionManager();
		});

		/**
		 * Register Events
		 */
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(\Closure::fromCallable([$this, 'registerVersionBackends']));

		/**
		 * Register hooks
		 */
		Hooks::connectHooks();
	}

	public function registerVersionBackends(ContainerInterface $container, IAppManager $appManager, ILogger $logger) {
		foreach ($appManager->getInstalledApps() as $app) {
			$appInfo = $appManager->getAppInfo($app);
			if (isset($appInfo['versions'])) {
				$backends = $appInfo['versions'];
				foreach ($backends as $backend) {
					if (isset($backend['@value'])) {
						$this->loadBackend($backend, $container, $logger);
					} else {
						foreach ($backend as $singleBackend) {
							$this->loadBackend($singleBackend, $container, $logger);
						}
					}
				}
			}
		}
	}

	private function loadBackend(array $backend, ContainerInterface $container, ILogger $logger) {
		/** @var IVersionManager $versionManager */
		$versionManager = $container->get(IVersionManager::class);
		$class = $backend['@value'];
		$for = $backend['@attributes']['for'];
		try {
			$backendObject = $container->get($class);
			$versionManager->registerBackend($for, $backendObject);
		} catch (\Exception $e) {
			$logger->logException($e);
		}
	}
}
