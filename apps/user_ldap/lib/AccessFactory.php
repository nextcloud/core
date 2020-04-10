<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP;

use OCA\User_LDAP\User\Manager;
use OCP\IConfig;
use OCP\IUserManager;

class AccessFactory {
	/** @var ILDAPWrapper */
	protected $ldap;
	/** @var Manager */
	protected $userManager;
	/** @var Helper */
	protected $helper;
	/** @var IConfig */
	protected $config;
	/** @var IUserManager */
	private $ncUserManager;

	public function __construct(
		ILDAPWrapper $ldap,
		Manager $userManager,
		Helper $helper,
		IConfig $config,
		IUserManager $ncUserManager)
	{
		$this->ldap = $ldap;
		$this->userManager = $userManager;
		$this->helper = $helper;
		$this->config = $config;
		$this->ncUserManager = $ncUserManager;
	}

	public function get(Connection $connection) {
		return new Access(
			$connection,
			$this->ldap,
			$this->userManager,
			$this->helper,
			$this->config,
			$this->ncUserManager
		);
	}
}
