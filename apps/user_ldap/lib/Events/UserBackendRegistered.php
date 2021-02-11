<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Events;

use OCA\User_LDAP\IUserLDAP;
use OCA\User_LDAP\UserPluginManager;
use OCP\EventDispatcher\Event;

/**
 * This event is triggered right after the LDAP user backend is registered.
 *
 * @since 20.0.0
 */
class UserBackendRegistered extends Event {

	/** @var IUserLDAP */
	private $backend;
	/** @var UserPluginManager */
	private $pluginManager;

	public function __construct(IUserLDAP $backend, UserPluginManager $pluginManager) {
		$this->backend = $backend;
		$this->pluginManager = $pluginManager;
	}

	public function getBackend(): IUserLDAP {
		return $this->backend;
	}

	public function getPluginManager(): UserPluginManager {
		return $this->pluginManager;
	}
}
