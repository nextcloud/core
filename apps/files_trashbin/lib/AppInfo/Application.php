<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\Files_Trashbin\AppInfo;

use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\Files_Trashbin\Capabilities;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Files_Trashbin\Trash\TrashManager;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('files_trashbin', $urlParams);

		$container = $this->getContainer();
		/*
		 * Register capabilities
		 */
		$container->registerCapability(Capabilities::class);

		/*
		 * Register expiration
		 */
		$container->registerAlias('Expiration', Expiration::class);

		/*
		 * Register $principalBackend for the DAV collection
		 */
		$container->registerService('principalBackend', function () {
			return new Principal(
				\OC::$server->getUserManager(),
				\OC::$server->getGroupManager(),
				\OC::$server->getShareManager(),
				\OC::$server->getUserSession(),
				\OC::$server->getAppManager(),
				\OC::$server->query(ProxyMapper::class),
				\OC::$server->getConfig()
			);
		});

		$container->registerService(ITrashManager::class, function (IAppContainer $c) {
			return new TrashManager();
		});

		$this->registerTrashBackends();
	}

	public function registerTrashBackends() {
		$server = $this->getContainer()->getServer();
		$logger = $server->getLogger();
		$appManager = $server->getAppManager();
		/** @var ITrashManager $trashManager */
		$trashManager = $this->getContainer()->getServer()->query(ITrashManager::class);
		foreach($appManager->getInstalledApps() as $app) {
			$appInfo = $appManager->getAppInfo($app);
			if (isset($appInfo['trash'])) {
				$backends = $appInfo['trash'];
				foreach($backends as $backend) {
					$class = $backend['@value'];
					$for = $backend['@attributes']['for'];

					try {
						$backendObject = $server->query($class);
						$trashManager->registerBackend($for, $backendObject);
					} catch (\Exception $e) {
						$logger->logException($e);
					}
				}
			}
		}
	}
}
