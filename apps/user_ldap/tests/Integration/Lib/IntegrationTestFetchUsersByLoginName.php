<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

namespace OCA\User_LDAP\Tests\Integration\Lib;

use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User_LDAP;

require_once __DIR__ . '/../Bootstrap.php';

class IntegrationTestFetchUsersByLoginName extends AbstractIntegrationTest {
	/** @var  UserMapping */
	protected $mapping;

	/** @var User_LDAP */
	protected $backend;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../setup-scripts/createExplicitUsers.php');
		parent::init();

		$this->mapping = new UserMapping(\OC::$server->getDatabaseConnection());
		$this->mapping->clear();
		$this->access->setUserMapper($this->mapping);
		$this->backend = new User_LDAP($this->access, \OC::$server->getConfig(), \OC::$server->getNotificationManager(), \OC::$server->getUserSession(), \OC::$server->query('LDAPUserPluginManager'));
	}

	/**
	 * tests fetchUserByLoginName where it is expected that the login name does
	 * not match any LDAP user
	 *
	 * @return bool
	 */
	protected function case1() {
		$result = $this->access->fetchUsersByLoginName('nothere');
		return $result === [];
	}

	/**
	 * tests fetchUserByLoginName where it is expected that the login name does
	 * match one LDAP user
	 *
	 * @return bool
	 */
	protected function case2() {
		$result = $this->access->fetchUsersByLoginName('alice');
		return count($result) === 1;
	}

}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestFetchUsersByLoginName($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
