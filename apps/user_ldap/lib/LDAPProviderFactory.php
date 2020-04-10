<?php
/**
 * @copyright Copyright (c) 2016, Roger Szabo (roger.szabo@web.de)
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
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

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCP\IServerContainer;
use OCP\LDAP\ILDAPProviderFactory;

class LDAPProviderFactory implements ILDAPProviderFactory {
	/**
	 * Server container
	 *
	 * @var IServerContainer
	 */
	private $serverContainer;
	
	/**
	 * Constructor for the LDAP provider factory
	 *
	 * @param IServerContainer $serverContainer server container
	 */
	public function __construct(IServerContainer $serverContainer) {
		$this->serverContainer = $serverContainer;
	}
	
	/**
	 * creates and returns an instance of the ILDAPProvider
	 *
	 * @return OCP\LDAP\ILDAPProvider
	 */
	public function getLDAPProvider() {
		$dbConnection = $this->serverContainer->getDatabaseConnection();
		$userMapping = new UserMapping($dbConnection);
		return new LDAPProvider($this->serverContainer, new Helper($this->serverContainer->getConfig()),
					new DeletedUsersIndex($this->serverContainer->getConfig(),
					$dbConnection, $userMapping));
	}
}
