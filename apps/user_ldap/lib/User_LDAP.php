<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Dominik Schmidt <dev@dominik-schmidt.de>
 * @author felixboehm <felix@webhippie.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tom Needham <tom@owncloud.com>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

use Exception;
use OC\ServerNotAvailableException;
use OC\User\Backend;
use OC\User\NoUserException;
use OCA\User_LDAP\Exceptions\NotOnLDAP;
use OCA\User_LDAP\User\OfflineUser;
use OCA\User_LDAP\User\User;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;
use OCP\Util;

class User_LDAP extends BackendUtility implements \OCP\IUserBackend, \OCP\UserInterface, IUserLDAP {
	/** @var \OCP\IConfig */
	protected $ocConfig;

	/** @var INotificationManager */
	protected $notificationManager;

	/** @var UserPluginManager */
	protected $userPluginManager;

	/**
	 * @param Access $access
	 * @param \OCP\IConfig $ocConfig
	 * @param \OCP\Notification\IManager $notificationManager
	 * @param IUserSession $userSession
	 */
	public function __construct(Access $access, IConfig $ocConfig, INotificationManager $notificationManager, IUserSession $userSession, UserPluginManager $userPluginManager) {
		parent::__construct($access);
		$this->ocConfig = $ocConfig;
		$this->notificationManager = $notificationManager;
		$this->userPluginManager = $userPluginManager;
	}

	/**
	 * checks whether the user is allowed to change his avatar in Nextcloud
	 *
	 * @param string $uid the Nextcloud user name
	 * @return boolean either the user can or cannot
	 * @throws Exception
	 */
	public function canChangeAvatar($uid) {
		if ($this->userPluginManager->implementsActions(Backend::PROVIDE_AVATAR)) {
			return $this->userPluginManager->canChangeAvatar($uid);
		}

		if (!$this->implementsActions(Backend::PROVIDE_AVATAR)) {
			return true;
		}

		$user = $this->access->userManager->get($uid);
		if (!$user instanceof User) {
			return false;
		}
		$imageData = $user->getAvatarImage();
		if ($imageData === false) {
			return true;
		}
		return !$user->updateAvatar(true);
	}

	/**
	 * Return the username for the given login name, if available
	 *
	 * @param string $loginName
	 * @return string|false
	 * @throws Exception
	 */
	public function loginName2UserName($loginName) {
		$cacheKey = 'loginName2UserName-' . $loginName;
		$username = $this->access->connection->getFromCache($cacheKey);

		if ($username !== null) {
			return $username;
		}

		try {
			$ldapRecord = $this->getLDAPUserByLoginName($loginName);
			$user = $this->access->userManager->get($ldapRecord['dn'][0]);
			if ($user === null || $user instanceof OfflineUser) {
				// this path is not really possible, however get() is documented
				// to return User, OfflineUser or null so we are very defensive here.
				$this->access->connection->writeToCache($cacheKey, false);
				return false;
			}
			$username = $user->getUsername();
			$this->access->connection->writeToCache($cacheKey, $username);
			return $username;
		} catch (NotOnLDAP $e) {
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}
	}

	/**
	 * returns the username for the given LDAP DN, if available
	 *
	 * @param string $dn
	 * @return string|false with the username
	 */
	public function dn2UserName($dn) {
		return $this->access->dn2username($dn);
	}

	/**
	 * returns an LDAP record based on a given login name
	 *
	 * @param string $loginName
	 * @return array
	 * @throws NotOnLDAP
	 */
	public function getLDAPUserByLoginName($loginName) {
		//find out dn of the user name
		$attrs = $this->access->userManager->getAttributes();
		$users = $this->access->fetchUsersByLoginName($loginName, $attrs);
		if (count($users) < 1) {
			throw new NotOnLDAP('No user available for the given login name on ' .
				$this->access->connection->ldapHost . ':' . $this->access->connection->ldapPort);
		}
		return $users[0];
	}

	/**
	 * Check if the password is correct without logging in the user
	 *
	 * @param string $uid The username
	 * @param string $password The password
	 * @return false|string
	 */
	public function checkPassword($uid, $password) {
		try {
			$ldapRecord = $this->getLDAPUserByLoginName($uid);
		} catch (NotOnLDAP $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'user_ldap', 'level' => ILogger::DEBUG]);
			return false;
		}
		$dn = $ldapRecord['dn'][0];
		$user = $this->access->userManager->get($dn);

		if (!$user instanceof User) {
			Util::writeLog('user_ldap',
				'LDAP Login: Could not get user object for DN ' . $dn .
				'. Maybe the LDAP entry has no set display name attribute?',
				ILogger::WARN);
			return false;
		}
		if ($user->getUsername() !== false) {
			//are the credentials OK?
			if (!$this->access->areCredentialsValid($dn, $password)) {
				return false;
			}

			$this->access->cacheUserExists($user->getUsername());
			$user->processAttributes($ldapRecord);
			$user->markLogin();

			return $user->getUsername();
		}

		return false;
	}

	/**
	 * Set password
	 * @param string $uid The username
	 * @param string $password The new password
	 * @return bool
	 */
	public function setPassword($uid, $password) {
		if ($this->userPluginManager->implementsActions(Backend::SET_PASSWORD)) {
			return $this->userPluginManager->setPassword($uid, $password);
		}

		$user = $this->access->userManager->get($uid);

		if (!$user instanceof User) {
			throw new Exception('LDAP setPassword: Could not get user object for uid ' . $uid .
				'. Maybe the LDAP entry has no set display name attribute?');
		}
		if ($user->getUsername() !== false && $this->access->setPassword($user->getDN(), $password)) {
			$ldapDefaultPPolicyDN = $this->access->connection->ldapDefaultPPolicyDN;
			$turnOnPasswordChange = $this->access->connection->turnOnPasswordChange;
			if (!empty($ldapDefaultPPolicyDN) && ((int)$turnOnPasswordChange === 1)) {
				//remove last password expiry warning if any
				$notification = $this->notificationManager->createNotification();
				$notification->setApp('user_ldap')
					->setUser($uid)
					->setObject('pwd_exp_warn', $uid)
				;
				$this->notificationManager->markProcessed($notification);
			}
			return true;
		}

		return false;
	}

	/**
	 * Get a list of all users
	 *
	 * @param string $search
	 * @param integer $limit
	 * @param integer $offset
	 * @return string[] an array of all uids
	 */
	public function getUsers($search = '', $limit = 10, $offset = 0) {
		$search = $this->access->escapeFilterPart($search, true);
		$cachekey = 'getUsers-'.$search.'-'.$limit.'-'.$offset;

		//check if users are cached, if so return
		$ldap_users = $this->access->connection->getFromCache($cachekey);
		if (!is_null($ldap_users)) {
			return $ldap_users;
		}

		// if we'd pass -1 to LDAP search, we'd end up in a Protocol
		// error. With a limit of 0, we get 0 results. So we pass null.
		if ($limit <= 0) {
			$limit = null;
		}
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapUserFilter,
			$this->access->connection->ldapUserDisplayName . '=*',
			$this->access->getFilterPartForUserSearch($search)
		]);

		Util::writeLog('user_ldap',
			'getUsers: Options: search '.$search.' limit '.$limit.' offset '.$offset.' Filter: '.$filter,
			ILogger::DEBUG);
		//do the search and translate results to Nextcloud names
		$ldap_users = $this->access->fetchListOfUsers(
			$filter,
			$this->access->userManager->getAttributes(true),
			$limit, $offset);
		$ldap_users = $this->access->nextcloudUserNames($ldap_users);
		Util::writeLog('user_ldap', 'getUsers: '.count($ldap_users). ' Users found', ILogger::DEBUG);

		$this->access->connection->writeToCache($cachekey, $ldap_users);
		return $ldap_users;
	}

	/**
	 * checks whether a user is still available on LDAP
	 *
	 * @param string|\OCA\User_LDAP\User\User $user either the Nextcloud user
	 * name or an instance of that user
	 * @return bool
	 * @throws Exception
	 * @throws \OC\ServerNotAvailableException
	 */
	public function userExistsOnLDAP($user) {
		if (is_string($user)) {
			$user = $this->access->userManager->get($user);
		}
		if (is_null($user)) {
			return false;
		}
		$uid = $user instanceof User ? $user->getUsername() : $user->getOCName();
		$cacheKey = 'userExistsOnLDAP' . $uid;
		$userExists = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($userExists)) {
			return (bool)$userExists;
		}

		$dn = $user->getDN();
		//check if user really still exists by reading its entry
		if (!is_array($this->access->readAttribute($dn, '', $this->access->connection->ldapUserFilter))) {
			try {
				$uuid = $this->access->getUserMapper()->getUUIDByDN($dn);
				if (!$uuid) {
					$this->access->connection->writeToCache($cacheKey, false);
					return false;
				}
				$newDn = $this->access->getUserDnByUuid($uuid);
				//check if renamed user is still valid by reapplying the ldap filter
				if ($newDn === $dn || !is_array($this->access->readAttribute($newDn, '', $this->access->connection->ldapUserFilter))) {
					$this->access->connection->writeToCache($cacheKey, false);
					return false;
				}
				$this->access->getUserMapper()->setDNbyUUID($newDn, $uuid);
				$this->access->connection->writeToCache($cacheKey, true);
				return true;
			} catch (ServerNotAvailableException $e) {
				throw $e;
			} catch (Exception $e) {
				$this->access->connection->writeToCache($cacheKey, false);
				return false;
			}
		}

		if ($user instanceof OfflineUser) {
			$user->unmark();
		}

		$this->access->connection->writeToCache($cacheKey, true);
		return true;
	}

	/**
	 * check if a user exists
	 * @param string $uid the username
	 * @return boolean
	 * @throws Exception when connection could not be established
	 */
	public function userExists($uid) {
		$userExists = $this->access->connection->getFromCache('userExists'.$uid);
		if (!is_null($userExists)) {
			return (bool)$userExists;
		}
		//getting dn, if false the user does not exist. If dn, he may be mapped only, requires more checking.
		$user = $this->access->userManager->get($uid);

		if (is_null($user)) {
			Util::writeLog('user_ldap', 'No DN found for '.$uid.' on '.
				$this->access->connection->ldapHost, ILogger::DEBUG);
			$this->access->connection->writeToCache('userExists'.$uid, false);
			return false;
		}

		$this->access->connection->writeToCache('userExists'.$uid, true);
		return true;
	}

	/**
	 * returns whether a user was deleted in LDAP
	 *
	 * @param string $uid The username of the user to delete
	 * @return bool
	 */
	public function deleteUser($uid) {
		if ($this->userPluginManager->canDeleteUser()) {
			$status = $this->userPluginManager->deleteUser($uid);
			if ($status === false) {
				return false;
			}
		}

		$marked = $this->ocConfig->getUserValue($uid, 'user_ldap', 'isDeleted', 0);
		if ((int)$marked === 0) {
			\OC::$server->getLogger()->notice(
				'User '.$uid . ' is not marked as deleted, not cleaning up.',
				['app' => 'user_ldap']);
			return false;
		}
		\OC::$server->getLogger()->info('Cleaning up after user ' . $uid,
			['app' => 'user_ldap']);

		$this->access->getUserMapper()->unmap($uid); // we don't emit unassign signals here, since it is implicit to delete signals fired from core
		$this->access->userManager->invalidate($uid);
		$this->access->connection->clearCache();
		return true;
	}

	/**
	 * get the user's home directory
	 *
	 * @param string $uid the username
	 * @return bool|string
	 * @throws NoUserException
	 * @throws Exception
	 */
	public function getHome($uid) {
		// user Exists check required as it is not done in user proxy!
		if (!$this->userExists($uid)) {
			return false;
		}

		if ($this->userPluginManager->implementsActions(Backend::GET_HOME)) {
			return $this->userPluginManager->getHome($uid);
		}

		$cacheKey = 'getHome'.$uid;
		$path = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($path)) {
			return $path;
		}

		// early return path if it is a deleted user
		$user = $this->access->userManager->get($uid);
		if ($user instanceof User || $user instanceof OfflineUser) {
			$path = $user->getHomePath() ?: false;
		} else {
			throw new NoUserException($uid . ' is not a valid user anymore');
		}

		$this->access->cacheUserHome($uid, $path);
		return $path;
	}

	/**
	 * get display name of the user
	 *
	 * @param string $uid user ID of the user
	 * @return string|false display name
	 * @throws Exception
	 */
	public function getDisplayName($uid) {
		if ($this->userPluginManager->implementsActions(Backend::GET_DISPLAYNAME)) {
			return $this->userPluginManager->getDisplayName($uid);
		}

		if (!$this->userExists($uid)) {
			return false;
		}

		$user = $this->access->userManager->get($uid);
		if (null === $user) {
			return false;
		}
		return $user->getDisplayName();
	}

	/**
	 * set display name of the user
	 * @param string $uid user ID of the user
	 * @param string $displayName new display name of the user
	 * @return string|false display name
	 */
	public function setDisplayName($uid, $displayName) {
		if ($this->userPluginManager->implementsActions(Backend::SET_DISPLAYNAME)) {
			$this->userPluginManager->setDisplayName($uid, $displayName);
			$this->access->storeUserDisplayName($uid, $displayName);
			return $displayName;
		}
		return false;
	}

	/**
	 * Get a list of all display names
	 *
	 * @param string $search
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return array an array of all displayNames (value) and the corresponding uids (key)
	 */
	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		$cacheKey = 'getDisplayNames-'.$search.'-'.$limit.'-'.$offset;
		if (!is_null($displayNames = $this->access->connection->getFromCache($cacheKey))) {
			return $displayNames;
		}

		$displayNames = [];
		$users = $this->getUsers($search, $limit, $offset);
		foreach ($users as $user) {
			$displayNames[$user] = $this->getDisplayName($user);
		}
		$this->access->connection->writeToCache($cacheKey, $displayNames);
		return $displayNames;
	}

	/**
	 * Check if backend implements actions
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with \OC\User\Backend::CREATE_USER etc.
	 */
	public function implementsActions($actions) {
		return (bool)((Backend::CHECK_PASSWORD
			| Backend::GET_HOME
			| Backend::GET_DISPLAYNAME
			| (($this->access->connection->ldapUserAvatarRule !== 'none') ? Backend::PROVIDE_AVATAR : 0)
			| Backend::COUNT_USERS
			| (((int)$this->access->connection->turnOnPasswordChange === 1)? Backend::SET_PASSWORD :0)
			| $this->userPluginManager->getImplementedActions())
			& $actions);
	}

	/**
	 * @return bool
	 */
	public function hasUserListings() {
		return true;
	}

	/**
	 * counts the users in LDAP
	 *
	 * @return int|bool
	 */
	public function countUsers() {
		if ($this->userPluginManager->implementsActions(Backend::COUNT_USERS)) {
			return $this->userPluginManager->countUsers();
		}

		$filter = $this->access->getFilterForUserCount();
		$cacheKey = 'countUsers-'.$filter;
		if (!is_null($entries = $this->access->connection->getFromCache($cacheKey))) {
			return $entries;
		}
		$entries = $this->access->countUsers($filter);
		$this->access->connection->writeToCache($cacheKey, $entries);
		return $entries;
	}

	/**
	 * Backend name to be shown in user management
	 * @return string the name of the backend to be shown
	 */
	public function getBackendName() {
		return 'LDAP';
	}

	/**
	 * Return access for LDAP interaction.
	 * @param string $uid
	 * @return Access instance of Access for LDAP interaction
	 */
	public function getLDAPAccess($uid) {
		return $this->access;
	}

	/**
	 * Return LDAP connection resource from a cloned connection.
	 * The cloned connection needs to be closed manually.
	 * of the current access.
	 * @param string $uid
	 * @return resource of the LDAP connection
	 */
	public function getNewLDAPConnection($uid) {
		$connection = clone $this->access->getConnection();
		return $connection->getConnectionResource();
	}

	/**
	 * create new user
	 * @param string $username username of the new user
	 * @param string $password password of the new user
	 * @throws \UnexpectedValueException
	 * @return bool
	 */
	public function createUser($username, $password) {
		if ($this->userPluginManager->implementsActions(Backend::CREATE_USER)) {
			if ($dn = $this->userPluginManager->createUser($username, $password)) {
				if (is_string($dn)) {
					// the NC user creation work flow requires a know user id up front
					$uuid = $this->access->getUUID($dn, true);
					if (is_string($uuid)) {
						$this->access->mapAndAnnounceIfApplicable(
							$this->access->getUserMapper(),
							$dn,
							$username,
							$uuid,
							true
						);
						$this->access->cacheUserExists($username);
					} else {
						\OC::$server->getLogger()->warning(
							'Failed to map created LDAP user with userid {userid}, because UUID could not be determined',
							[
								'app' => 'user_ldap',
								'userid' => $username,
							]
						);
					}
				} else {
					throw new \UnexpectedValueException("LDAP Plugin: Method createUser changed to return the user DN instead of boolean.");
				}
			}
			return (bool) $dn;
		}
		return false;
	}
}
