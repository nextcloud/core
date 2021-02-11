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

use OCA\User_LDAP\GroupPluginManager;
use OCA\User_LDAP\IGroupLDAP;
use OCP\EventDispatcher\Event;

/**
 * This event is triggered right after the LDAP group backend is registered.
 *
 * @since 20.0.0
 */
class GroupBackendRegistered extends Event {

	/** @var GroupPluginManager */
	private $pluginManager;
	/** @var IGroupLDAP */
	private $backend;

	public function __construct(IGroupLDAP $backend, GroupPluginManager $pluginManager) {
		$this->pluginManager = $pluginManager;
		$this->backend = $backend;
	}

	public function getBackend(): IGroupLDAP {
		return $this->backend;
	}

	public function getPluginManager(): GroupPluginManager {
		return $this->pluginManager;
	}
}
