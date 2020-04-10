<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\DAV\Connector\Sabre;

use OCP\App\IAppManager;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\ServerPlugin;

/**
 * Plugin to check if an app is enabled for the current user
 */
class AppEnabledPlugin extends ServerPlugin {

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var string
	 */
	private $app;

	/**
	 * @var \OCP\App\IAppManager
	 */
	private $appManager;

	/**
	 * @param string $app
	 * @param \OCP\App\IAppManager $appManager
	 */
	public function __construct($app, IAppManager $appManager) {
		$this->app = $app;
		$this->appManager = $appManager;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by \Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {

		$this->server = $server;
		$this->server->on('beforeMethod:*', [$this, 'checkAppEnabled'], 30);
	}

	/**
	 * This method is called before any HTTP after auth and checks if the user has access to the app
	 *
	 * @throws \Sabre\DAV\Exception\Forbidden
	 * @return bool
	 */
	public function checkAppEnabled() {
		if (!$this->appManager->isEnabledForUser($this->app)) {
			throw new Forbidden();
		}
	}
}
