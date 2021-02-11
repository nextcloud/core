<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCA\DAV\Connector\Sabre\Principal;
use OCA\Files_Trashbin\Capabilities;
use OCA\Files_Trashbin\Expiration;
use OCA\Files_Trashbin\Trash\ITrashManager;
use OCA\Files_Trashbin\Trash\TrashManager;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\ILogger;
use OCP\IServerContainer;

class Application extends App implements IBootstrap {
	public function __construct(array $urlParams = []) {
		parent::__construct('files_trashbin', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerCapability(Capabilities::class);

		$context->registerServiceAlias('Expiration', Expiration::class);
		$context->registerServiceAlias(ITrashManager::class, TrashManager::class);
		/** Register $principalBackend for the DAV collection */
		$context->registerServiceAlias('principalBackend', Principal::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn([$this, 'registerTrashBackends']);

		// create storage wrapper on setup
		\OCP\Util::connectHook('OC_Filesystem', 'preSetup', 'OCA\Files_Trashbin\Storage', 'setupStorage');
		//Listen to delete user signal
		\OCP\Util::connectHook('OC_User', 'pre_deleteUser', 'OCA\Files_Trashbin\Hooks', 'deleteUser_hook');
		//Listen to post write hook
		\OCP\Util::connectHook('OC_Filesystem', 'post_write', 'OCA\Files_Trashbin\Hooks', 'post_write_hook');
		// pre and post-rename, disable trash logic for the copy+unlink case
		\OCP\Util::connectHook('OC_Filesystem', 'delete', 'OCA\Files_Trashbin\Trashbin', 'ensureFileScannedHook');

		\OCA\Files\App::getNavigationManager()->add(function () {
			$l = \OC::$server->getL10N('files_trashbin');
			return [
				'id' => 'trashbin',
				'appname' => 'files_trashbin',
				'script' => 'list.php',
				'order' => 50,
				'name' => $l->t('Deleted files'),
				'classes' => 'pinned',
			];
		});
	}

	public function registerTrashBackends(IServerContainer $serverContainer, ILogger $logger, IAppManager $appManager, ITrashManager $trashManager) {
		foreach ($appManager->getInstalledApps() as $app) {
			$appInfo = $appManager->getAppInfo($app);
			if (isset($appInfo['trash'])) {
				$backends = $appInfo['trash'];
				foreach ($backends as $backend) {
					$class = $backend['@value'];
					$for = $backend['@attributes']['for'];

					try {
						$backendObject = $serverContainer->query($class);
						$trashManager->registerBackend($for, $backendObject);
					} catch (\Exception $e) {
						$logger->logException($e);
					}
				}
			}
		}
	}
}
