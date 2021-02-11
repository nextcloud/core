<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\User_LDAP\Jobs;

use OC\BackgroundJob\TimedJob;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User_LDAP;
use OCA\User_LDAP\User_Proxy;

/**
 * Class CleanUp
 *
 * a Background job to clean up deleted users
 *
 * @package OCA\User_LDAP\Jobs;
 */
class CleanUp extends TimedJob {
	/** @var int $limit amount of users that should be checked per run */
	protected $limit;

	/** @var int $defaultIntervalMin default interval in minutes */
	protected $defaultIntervalMin = 51;

	/** @var User_LDAP|User_Proxy $userBackend */
	protected $userBackend;

	/** @var \OCP\IConfig $ocConfig */
	protected $ocConfig;

	/** @var \OCP\IDBConnection $db */
	protected $db;

	/** @var Helper $ldapHelper */
	protected $ldapHelper;

	/** @var UserMapping */
	protected $mapping;

	/** @var DeletedUsersIndex */
	protected $dui;

	public function __construct(User_Proxy $userBackend, DeletedUsersIndex $dui) {
		$minutes = \OC::$server->getConfig()->getSystemValue(
			'ldapUserCleanupInterval', (string)$this->defaultIntervalMin);
		$this->setInterval((int)$minutes * 60);
		$this->userBackend = $userBackend;
		$this->dui = $dui;
	}

	/**
	 * assigns the instances passed to run() to the class properties
	 * @param array $arguments
	 */
	public function setArguments($arguments) {
		//Dependency Injection is not possible, because the constructor will
		//only get values that are serialized to JSON. I.e. whatever we would
		//pass in app.php we do add here, except something else is passed e.g.
		//in tests.

		if (isset($arguments['helper'])) {
			$this->ldapHelper = $arguments['helper'];
		} else {
			$this->ldapHelper = new Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());
		}

		if (isset($arguments['ocConfig'])) {
			$this->ocConfig = $arguments['ocConfig'];
		} else {
			$this->ocConfig = \OC::$server->getConfig();
		}

		if (isset($arguments['userBackend'])) {
			$this->userBackend = $arguments['userBackend'];
		}

		if (isset($arguments['db'])) {
			$this->db = $arguments['db'];
		} else {
			$this->db = \OC::$server->getDatabaseConnection();
		}

		if (isset($arguments['mapping'])) {
			$this->mapping = $arguments['mapping'];
		} else {
			$this->mapping = new UserMapping($this->db);
		}

		if (isset($arguments['deletedUsersIndex'])) {
			$this->dui = $arguments['deletedUsersIndex'];
		}
	}

	/**
	 * makes the background job do its work
	 * @param array $argument
	 */
	public function run($argument) {
		$this->setArguments($argument);

		if (!$this->isCleanUpAllowed()) {
			return;
		}
		$users = $this->mapping->getList($this->getOffset(), $this->getChunkSize());
		if (!is_array($users)) {
			//something wrong? Let's start from the beginning next time and
			//abort
			$this->setOffset(true);
			return;
		}
		$resetOffset = $this->isOffsetResetNecessary(count($users));
		$this->checkUsers($users);
		$this->setOffset($resetOffset);
	}

	/**
	 * checks whether next run should start at 0 again
	 * @param int $resultCount
	 * @return bool
	 */
	public function isOffsetResetNecessary($resultCount) {
		return $resultCount < $this->getChunkSize();
	}

	/**
	 * checks whether cleaning up LDAP users is allowed
	 * @return bool
	 */
	public function isCleanUpAllowed() {
		try {
			if ($this->ldapHelper->haveDisabledConfigurations()) {
				return false;
			}
		} catch (\Exception $e) {
			return false;
		}

		return $this->isCleanUpEnabled();
	}

	/**
	 * checks whether clean up is enabled by configuration
	 * @return bool
	 */
	private function isCleanUpEnabled() {
		return (bool)$this->ocConfig->getSystemValue(
			'ldapUserCleanupInterval', (string)$this->defaultIntervalMin);
	}

	/**
	 * checks users whether they are still existing
	 * @param array $users result from getMappedUsers()
	 */
	private function checkUsers(array $users) {
		foreach ($users as $user) {
			$this->checkUser($user);
		}
	}

	/**
	 * checks whether a user is still existing in LDAP
	 * @param string[] $user
	 */
	private function checkUser(array $user) {
		if ($this->userBackend->userExistsOnLDAP($user['name'])) {
			//still available, all good

			return;
		}

		$this->dui->markUser($user['name']);
	}

	/**
	 * gets the offset to fetch users from the mappings table
	 * @return int
	 */
	private function getOffset() {
		return (int)$this->ocConfig->getAppValue('user_ldap', 'cleanUpJobOffset', 0);
	}

	/**
	 * sets the new offset for the next run
	 * @param bool $reset whether the offset should be set to 0
	 */
	public function setOffset($reset = false) {
		$newOffset = $reset ? 0 :
			$this->getOffset() + $this->getChunkSize();
		$this->ocConfig->setAppValue('user_ldap', 'cleanUpJobOffset', $newOffset);
	}

	/**
	 * returns the chunk size (limit in DB speak)
	 * @return int
	 */
	public function getChunkSize() {
		if ($this->limit === null) {
			$this->limit = (int)$this->ocConfig->getAppValue('user_ldap', 'cleanUpJobChunkSize', 50);
		}
		return $this->limit;
	}
}
