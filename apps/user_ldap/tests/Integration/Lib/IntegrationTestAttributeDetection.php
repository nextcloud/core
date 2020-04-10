<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\user_ldap\tests\Integration\Lib;

use OCA\User_LDAP\Group_LDAP;
use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User_LDAP;

require_once __DIR__ . '/../Bootstrap.php';

class IntegrationTestAttributeDetection extends AbstractIntegrationTest {
	public function init() {
		require(__DIR__ . '/../setup-scripts/createExplicitUsers.php');
		require(__DIR__ . '/../setup-scripts/createExplicitGroups.php');

		parent::init();

		$this->connection->setConfiguration(['ldapGroupFilter' => 'objectClass=groupOfNames']);
		$this->connection->setConfiguration(['ldapGroupMemberAssocAttr' => 'member']);

		$userMapper = new UserMapping(\OC::$server->getDatabaseConnection());
		$userMapper->clear();
		$this->access->setUserMapper($userMapper);

		$groupMapper = new GroupMapping(\OC::$server->getDatabaseConnection());
		$groupMapper->clear();
		$this->access->setGroupMapper($groupMapper);

		$userBackend = new User_LDAP($this->access, \OC::$server->getConfig(), \OC::$server->getNotificationManager(), \OC::$server->getUserSession(), \OC::$server->query('LDAPUserPluginManager'));
		$userManager = \OC::$server->getUserManager();
		$userManager->clearBackends();
		$userManager->registerBackend($userBackend);

		$groupBackend = new Group_LDAP($this->access, \OC::$server->query('LDAPGroupPluginManager'));
		$groupManger = \OC::$server->getGroupManager();
		$groupManger->clearBackends();
		$groupManger->addBackend($groupBackend);
	}

	protected function caseNativeUUIDAttributeUsers() {
		// trigger importing of users which also triggers UUID attribute detection
		\OC::$server->getUserManager()->search('', 5, 0);
		return $this->connection->ldapUuidUserAttribute === 'entryuuid';
	}

	protected function caseNativeUUIDAttributeGroups() {
		// essentially the same as 'caseNativeUUIDAttributeUsers', code paths
		// are similar, but we take no chances.

		// trigger importing of users which also triggers UUID attribute detection
		\OC::$server->getGroupManager()->search('', 5, 0);
		return $this->connection->ldapUuidGroupAttribute === 'entryuuid';
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestAttributeDetection($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
