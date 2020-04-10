<?php
/**
 * @copyright Copyright (c) 2017 Roger Szabo <roger.szabo@web.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roger Szabo <roger.szabo@web.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\User_LDAP\AppInfo;

use OCA\Files_External\Service\BackendService;
use OCA\User_LDAP\Controller\RenewPasswordController;
use OCA\User_LDAP\Handler\ExtStorageConfigHandler;
use OCA\User_LDAP\ILDAPWrapper;
use OCA\User_LDAP\LDAP;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\IL10N;

class Application extends App {
	public function __construct() {
		parent::__construct('user_ldap');
		$container = $this->getContainer();

		/**
		 * Controller
		 */
		$container->registerService('RenewPasswordController', function (IAppContainer $c) {
			/** @var \OC\Server $server */
			$server = $c->query('ServerContainer');

			return new RenewPasswordController(
				$c->getAppName(),
				$server->getRequest(),
				$c->query('UserManager'),
				$server->getConfig(),
				$c->query(IL10N::class),
				$c->query('Session'),
				$server->getURLGenerator()
			);
		});

		$container->registerService(ILDAPWrapper::class, function () {
			return new LDAP();
		});
	}

	public function registerBackendDependents() {
		$container = $this->getContainer();

		$container->getServer()->getEventDispatcher()->addListener(
			'OCA\\Files_External::loadAdditionalBackends',
			function () use ($container) {
				$storagesBackendService = $container->query(BackendService::class);
				$storagesBackendService->registerConfigHandler('home', function () use ($container) {
					return $container->query(ExtStorageConfigHandler::class);
				});
			}
		);
	}
}
