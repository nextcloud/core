<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\User_LDAP;

use OCA\User_LDAP\User\User;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;

class User_Proxy extends Proxy implements \OCP\IUserBackend, \OCP\UserInterface, IUserLDAP {
	private $backends = [];
	/** @var User_LDAP */
	private $refBackend = null;

	public function __construct(
		Helper $helper,
		ILDAPWrapper $ldap,
		IConfig $ocConfig,
		INotificationManager $notificationManager,
		IUserSession $userSession,
		UserPluginManager $userPluginManager
	) {
		parent::__construct($ldap);
		$serverConfigPrefixes = $helper->getServerConfigurationPrefixes(true);
		foreach ($serverConfigPrefixes as $configPrefix) {
			$this->backends[$configPrefix] =
				new User_LDAP($this->getAccess($configPrefix), $ocConfig, $notificationManager, $userSession, $userPluginManager);

			if (is_null($this->refBackend)) {
				$this->refBackend = &$this->backends[$configPrefix];
			}
		}
	}

	/**
	 * Tries the backends one after the other until a positive result is returned from the specified method
	 *
	 * @param string $id the uid connected to the request
	 * @param string $method the method of the user backend that shall be called
	 * @param array $parameters an array of parameters to be passed
	 * @return mixed the result of the method or false
	 */
	protected function walkBackends($id, $method, $parameters) {
		$uid = $id;
		$cacheKey = $this->getUserCacheKey($uid);
		foreach ($this->backends as $configPrefix => $backend) {
			$instance = $backend;
			if (!method_exists($instance, $method)
				&& method_exists($this->getAccess($configPrefix), $method)) {
				$instance = $this->getAccess($configPrefix);
			}
			if ($result = call_user_func_array([$instance, $method], $parameters)) {
				if (!$this->isSingleBackend()) {
					$this->writeToCache($cacheKey, $configPrefix);
				}
				return $result;
			}
		}
		return false;
	}

	/**
	 * Asks the backend connected to the server that supposely takes care of the uid from the request.
	 *
	 * @param string $id the uid connected to the request
	 * @param string $method the method of the user backend that shall be called
	 * @param array $parameters an array of parameters to be passed
	 * @param mixed $passOnWhen the result matches this variable
	 * @return mixed the result of the method or false
	 */
	protected function callOnLastSeenOn($id, $method, $parameters, $passOnWhen) {
		$uid = $id;
		$cacheKey = $this->getUserCacheKey($uid);
		$prefix = $this->getFromCache($cacheKey);
		//in case the uid has been found in the past, try this stored connection first
		if (!is_null($prefix)) {
			if (isset($this->backends[$prefix])) {
				$instance = $this->backends[$prefix];
				if (!method_exists($instance, $method)
					&& method_exists($this->getAccess($prefix), $method)) {
					$instance = $this->getAccess($prefix);
				}
				$result = call_user_func_array([$instance, $method], $parameters);
				if ($result === $passOnWhen) {
					//not found here, reset cache to null if user vanished
					//because sometimes methods return false with a reason
					$userExists = call_user_func_array(
						[$this->backends[$prefix], 'userExistsOnLDAP'],
						[$uid]
					);
					if (!$userExists) {
						$this->writeToCache($cacheKey, null);
					}
				}
				return $result;
			}
		}
		return false;
	}

	protected function activeBackends(): int {
		return count($this->backends);
	}

	/**
	 * Check if backend implements actions
	 *
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with \OC\User\Backend::CREATE_USER etc.
	 */
	public function implementsActions($actions) {
		//it's the same across all our user backends obviously
		return $this->refBackend->implementsActions($actions);
	}

	/**
	 * Backend name to be shown in user management
	 *
	 * @return string the name of the backend to be shown
	 */
	public function getBackendName() {
		return $this->refBackend->getBackendName();
	}

	/**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param null|int $limit
	 * @param null|int $offset
	 * @return string[] an array of all uids
	 */
	public function getUsers($search = '', $limit = 10, $offset = 0) {
		//we do it just as the /OC_User implementation: do not play around with limit and offset but ask all backends
		$users = [];
		foreach ($this->backends as $backend) {
			$backendUsers = $backend->getUsers($search, $limit, $offset);
			if (is_array($backendUsers)) {
				$users = array_merge($users, $backendUsers);
			}
		}
		return $users;
	}

	/**
	 * check if a user exists
	 *
	 * @param string $uid the username
	 * @return boolean
	 */
	public function userExists($uid) {
		$existsOnLDAP = false;
		$existsLocally = $this->handleRequest($uid, 'userExists', [$uid]);
		if ($existsLocally) {
			$existsOnLDAP = $this->userExistsOnLDAP($uid);
		}
		if ($existsLocally && !$existsOnLDAP) {
			try {
				$user = $this->getLDAPAccess($uid)->userManager->get($uid);
				if ($user instanceof User) {
					$user->markUser();
				}
			} catch (\Exception $e) {
				// ignore
			}
		}
		return $existsLocally;
	}

	/**
	 * check if a user exists on LDAP
	 *
	 * @param string|\OCA\User_LDAP\User\User $user either the Nextcloud user
	 * name or an instance of that user
	 * @return boolean
	 */
	public function userExistsOnLDAP($user) {
		$id = ($user instanceof User) ? $user->getUsername() : $user;
		return $this->handleRequest($id, 'userExistsOnLDAP', [$user]);
	}

	/**
	 * Check if the password is correct
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return bool
	 *
	 * Check if the password is correct without logging in the user
	 */
	public function checkPassword($uid, $password) {
		return $this->handleRequest($uid, 'checkPassword', [$uid, $password]);
	}

	/**
	 * returns the username for the given login name, if available
	 *
	 * @param string $loginName
	 * @return string|false
	 */
	public function loginName2UserName($loginName) {
		$id = 'LOGINNAME,' . $loginName;
		return $this->handleRequest($id, 'loginName2UserName', [$loginName]);
	}

	/**
	 * returns the username for the given LDAP DN, if available
	 *
	 * @param string $dn
	 * @return string|false with the username
	 */
	public function dn2UserName($dn) {
		$id = 'DN,' . $dn;
		return $this->handleRequest($id, 'dn2UserName', [$dn]);
	}

	/**
	 * get the user's home directory
	 *
	 * @param string $uid the username
	 * @return boolean
	 */
	public function getHome($uid) {
		return $this->handleRequest($uid, 'getHome', [$uid]);
	}

	/**
	 * get display name of the user
	 *
	 * @param string $uid user ID of the user
	 * @return string display name
	 */
	public function getDisplayName($uid) {
		return $this->handleRequest($uid, 'getDisplayName', [$uid]);
	}

	/**
	 * set display name of the user
	 *
	 * @param string $uid user ID of the user
	 * @param string $displayName new display name
	 * @return string display name
	 */
	public function setDisplayName($uid, $displayName) {
		return $this->handleRequest($uid, 'setDisplayName', [$uid, $displayName]);
	}

	/**
	 * checks whether the user is allowed to change his avatar in Nextcloud
	 *
	 * @param string $uid the Nextcloud user name
	 * @return boolean either the user can or cannot
	 */
	public function canChangeAvatar($uid) {
		return $this->handleRequest($uid, 'canChangeAvatar', [$uid], true);
	}

	/**
	 * Get a list of all display names and user ids.
	 *
	 * @param string $search
	 * @param string|null $limit
	 * @param string|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		//we do it just as the /OC_User implementation: do not play around with limit and offset but ask all backends
		$users = [];
		foreach ($this->backends as $backend) {
			$backendUsers = $backend->getDisplayNames($search, $limit, $offset);
			if (is_array($backendUsers)) {
				$users = $users + $backendUsers;
			}
		}
		return $users;
	}

	/**
	 * delete a user
	 *
	 * @param string $uid The username of the user to delete
	 * @return bool
	 *
	 * Deletes a user
	 */
	public function deleteUser($uid) {
		return $this->handleRequest($uid, 'deleteUser', [$uid]);
	}

	/**
	 * Set password
	 *
	 * @param string $uid The username
	 * @param string $password The new password
	 * @return bool
	 *
	 */
	public function setPassword($uid, $password) {
		return $this->handleRequest($uid, 'setPassword', [$uid, $password]);
	}

	/**
	 * @return bool
	 */
	public function hasUserListings() {
		return $this->refBackend->hasUserListings();
	}

	/**
	 * Count the number of users
	 *
	 * @return int|bool
	 */
	public function countUsers() {
		$users = false;
		foreach ($this->backends as $backend) {
			$backendUsers = $backend->countUsers();
			if ($backendUsers !== false) {
				$users += $backendUsers;
			}
		}
		return $users;
	}

	/**
	 * Return access for LDAP interaction.
	 *
	 * @param string $uid
	 * @return Access instance of Access for LDAP interaction
	 */
	public function getLDAPAccess($uid) {
		return $this->handleRequest($uid, 'getLDAPAccess', [$uid]);
	}

	/**
	 * Return a new LDAP connection for the specified user.
	 * The connection needs to be closed manually.
	 *
	 * @param string $uid
	 * @return resource of the LDAP connection
	 */
	public function getNewLDAPConnection($uid) {
		return $this->handleRequest($uid, 'getNewLDAPConnection', [$uid]);
	}

	/**
	 * Creates a new user in LDAP
	 *
	 * @param $username
	 * @param $password
	 * @return bool
	 */
	public function createUser($username, $password) {
		return $this->handleRequest($username, 'createUser', [$username, $password]);
	}
}
