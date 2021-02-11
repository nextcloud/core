<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Tests\Integration\Lib\User;

use OCA\User_LDAP\Jobs\CleanUp;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\Tests\Integration\AbstractIntegrationTest;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\UserPluginManager;

require_once __DIR__ . '/../../Bootstrap.php';

class IntegrationTestUserCleanUp extends AbstractIntegrationTest {
	/** @var  UserMapping */
	protected $mapping;

	/**
	 * prepares the LDAP environment and sets up a test configuration for
	 * the LDAP backend.
	 */
	public function init() {
		require(__DIR__ . '/../../setup-scripts/createExplicitUsers.php');
		parent::init();
		$this->mapping = new UserMapping(\OC::$server->getDatabaseConnection());
		$this->mapping->clear();
		$this->access->setUserMapper($this->mapping);

		$userBackend = new User_LDAP($this->access, \OC::$server->getConfig(), \OC::$server->getNotificationManager(), \OC::$server->getUserSession(), \OC::$server->query(UserPluginManager::class));
		\OC_User::useBackend($userBackend);
	}

	/**
	 * adds a map entry for the user, so we know the username
	 *
	 * @param $dn
	 * @param $username
	 */
	private function prepareUser($dn, $username) {
		// assigns our self-picked oc username to the dn
		$this->mapping->map($dn, $username, 'fakeUUID-' . $username);
	}

	private function deleteUserFromLDAP($dn) {
		$cr = $this->connection->getConnectionResource();
		ldap_delete($cr, $dn);
	}

	/**
	 * tests whether a display name consisting of two parts is created correctly
	 *
	 * @return bool
	 */
	protected function case1() {
		$username = 'alice1337';
		$dn = 'uid=alice,ou=Users,' . $this->base;
		$this->prepareUser($dn, $username);

		$this->deleteUserFromLDAP($dn);

		$job = new CleanUp();
		$job->run([]);

		// user instance must not be requested from global user manager, before
		// it is deleted from the LDAP server. The instance will be returned
		// from cache and may false-positively confirm the correctness.
		$user = \OC::$server->getUserManager()->get($username);
		if ($user === null) {
			return false;
		}
		$user->delete();

		return null === \OC::$server->getUserManager()->get($username);
	}
}

/** @var string $host */
/** @var int $port */
/** @var string $adn */
/** @var string $apwd */
/** @var string $bdn */
$test = new IntegrationTestUserCleanUp($host, $port, $adn, $apwd, $bdn);
$test->init();
$test->run();
