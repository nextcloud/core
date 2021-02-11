<?php
/**
 * @copyright Copyright (c) 2016, Roger Szabo (roger.szabo@web.de)
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
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

namespace OCA\User_LDAP;

use OCA\User_LDAP\User\DeletedUsersIndex;
use OCP\IServerContainer;
use OCP\LDAP\IDeletionFlagSupport;
use OCP\LDAP\ILDAPProvider;

/**
 * LDAP provider for pulic access to the LDAP backend.
 */
class LDAPProvider implements ILDAPProvider, IDeletionFlagSupport {
	private $userBackend;
	private $groupBackend;
	private $logger;
	private $helper;
	private $deletedUsersIndex;

	/**
	 * Create new LDAPProvider
	 * @param \OCP\IServerContainer $serverContainer
	 * @param Helper $helper
	 * @param DeletedUsersIndex $deletedUsersIndex
	 * @throws \Exception if user_ldap app was not enabled
	 */
	public function __construct(IServerContainer $serverContainer, Helper $helper, DeletedUsersIndex $deletedUsersIndex) {
		$this->logger = $serverContainer->getLogger();
		$this->helper = $helper;
		$this->deletedUsersIndex = $deletedUsersIndex;
		$userBackendFound = false;
		$groupBackendFound = false;
		foreach ($serverContainer->getUserManager()->getBackends() as $backend) {
			$this->logger->debug('instance '.get_class($backend).' user backend.', ['app' => 'user_ldap']);
			if ($backend instanceof IUserLDAP) {
				$this->userBackend = $backend;
				$userBackendFound = true;
				break;
			}
		}
		foreach ($serverContainer->getGroupManager()->getBackends() as $backend) {
			$this->logger->debug('instance '.get_class($backend).' group backend.', ['app' => 'user_ldap']);
			if ($backend instanceof IGroupLDAP) {
				$this->groupBackend = $backend;
				$groupBackendFound = true;
				break;
			}
		}

		if (!$userBackendFound or !$groupBackendFound) {
			throw new \Exception('To use the LDAPProvider, user_ldap app must be enabled');
		}
	}

	/**
	 * Translate an user id to LDAP DN
	 * @param string $uid user id
	 * @return string with the LDAP DN
	 * @throws \Exception if translation was unsuccessful
	 */
	public function getUserDN($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		$result = $this->userBackend->getLDAPAccess($uid)->username2dn($uid);
		if (!$result) {
			throw new \Exception('Translation to LDAP DN unsuccessful');
		}
		return $result;
	}

	/**
	 * Translate a group id to LDAP DN.
	 * @param string $gid group id
	 * @return string
	 * @throws \Exception
	 */
	public function getGroupDN($gid) {
		if (!$this->groupBackend->groupExists($gid)) {
			throw new \Exception('Group id not found in LDAP');
		}
		$result = $this->groupBackend->getLDAPAccess($gid)->groupname2dn($gid);
		if (!$result) {
			throw new \Exception('Translation to LDAP DN unsuccessful');
		}
		return $result;
	}

	/**
	 * Translate a LDAP DN to an internal user name. If there is no mapping between
	 * the DN and the user name, a new one will be created.
	 * @param string $dn LDAP DN
	 * @return string with the internal user name
	 * @throws \Exception if translation was unsuccessful
	 */
	public function getUserName($dn) {
		$result = $this->userBackend->dn2UserName($dn);
		if (!$result) {
			throw new \Exception('Translation to internal user name unsuccessful');
		}
		return $result;
	}

	/**
	 * Convert a stored DN so it can be used as base parameter for LDAP queries.
	 * @param string $dn the DN in question
	 * @return string
	 */
	public function DNasBaseParameter($dn) {
		return $this->helper->DNasBaseParameter($dn);
	}

	/**
	 * Sanitize a DN received from the LDAP server.
	 * @param array $dn the DN in question
	 * @return array the sanitized DN
	 */
	public function sanitizeDN($dn) {
		return $this->helper->sanitizeDN($dn);
	}

	/**
	 * Return a new LDAP connection resource for the specified user.
	 * The connection must be closed manually.
	 * @param string $uid user id
	 * @return resource of the LDAP connection
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getLDAPConnection($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		return $this->userBackend->getNewLDAPConnection($uid);
	}

	/**
	 * Return a new LDAP connection resource for the specified user.
	 * The connection must be closed manually.
	 * @param string $gid group id
	 * @return resource of the LDAP connection
	 * @throws \Exception if group id was not found in LDAP
	 */
	public function getGroupLDAPConnection($gid) {
		if (!$this->groupBackend->groupExists($gid)) {
			throw new \Exception('Group id not found in LDAP');
		}
		return $this->groupBackend->getNewLDAPConnection($gid);
	}

	/**
	 * Get the LDAP base for users.
	 * @param string $uid user id
	 * @return string the base for users
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getLDAPBaseUsers($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		$access = $this->userBackend->getLDAPAccess($uid);
		$bases = $access->getConnection()->ldapBaseUsers;
		$dn = $this->getUserDN($uid);
		foreach ($bases as $base) {
			if ($access->isDNPartOfBase($dn, [$base])) {
				return $base;
			}
		}
		// should not occur, because the user does not qualify to use NC in this case
		$this->logger->info(
			'No matching user base found for user {dn}, available: {bases}.',
			[
				'app' => 'user_ldap',
				'dn' => $dn,
				'bases' => $bases,
			]
		);
		return array_shift($bases);
	}

	/**
	 * Get the LDAP base for groups.
	 * @param string $uid user id
	 * @return string the base for groups
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getLDAPBaseGroups($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		$bases = $this->userBackend->getLDAPAccess($uid)->getConnection()->ldapBaseGroups;
		return array_shift($bases);
	}

	/**
	 * Clear the cache if a cache is used, otherwise do nothing.
	 * @param string $uid user id
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function clearCache($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		$this->userBackend->getLDAPAccess($uid)->getConnection()->clearCache();
	}

	/**
	 * Clear the cache if a cache is used, otherwise do nothing.
	 * Acts on the LDAP connection of a group
	 * @param string $gid group id
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function clearGroupCache($gid) {
		if (!$this->groupBackend->groupExists($gid)) {
			throw new \Exception('Group id not found in LDAP');
		}
		$this->groupBackend->getLDAPAccess($gid)->getConnection()->clearCache();
	}

	/**
	 * Check whether a LDAP DN exists
	 * @param string $dn LDAP DN
	 * @return bool whether the DN exists
	 */
	public function dnExists($dn) {
		$result = $this->userBackend->dn2UserName($dn);
		return !$result ? false : true;
	}

	/**
	 * Flag record for deletion.
	 * @param string $uid user id
	 */
	public function flagRecord($uid) {
		$this->deletedUsersIndex->markUser($uid);
	}

	/**
	 * Unflag record for deletion.
	 * @param string $uid user id
	 */
	public function unflagRecord($uid) {
		//do nothing
	}

	/**
	 * Get the LDAP attribute name for the user's display name
	 * @param string $uid user id
	 * @return string the display name field
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getLDAPDisplayNameField($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		return $this->userBackend->getLDAPAccess($uid)->getConnection()->getConfiguration()['ldap_display_name'];
	}

	/**
	 * Get the LDAP attribute name for the email
	 * @param string $uid user id
	 * @return string the email field
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getLDAPEmailField($uid) {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		return $this->userBackend->getLDAPAccess($uid)->getConnection()->getConfiguration()['ldap_email_attr'];
	}

	/**
	 * Get the LDAP type of association between users and groups
	 * @param string $gid group id
	 * @return string the configuration, one of: 'memberUid', 'uniqueMember', 'member', 'gidNumber', ''
	 * @throws \Exception if group id was not found in LDAP
	 */
	public function getLDAPGroupMemberAssoc($gid) {
		if (!$this->groupBackend->groupExists($gid)) {
			throw new \Exception('Group id not found in LDAP');
		}
		return $this->groupBackend->getLDAPAccess($gid)->getConnection()->getConfiguration()['ldap_group_member_assoc_attribute'];
	}

	/**
	 * Get an LDAP attribute for a nextcloud user
	 * @param string $uid the nextcloud user id to get the attribute for
	 * @param string $attribute the name of the attribute to read
	 * @return string|null
	 * @throws \Exception if user id was not found in LDAP
	 */
	public function getUserAttribute(string $uid, string $attribute): ?string {
		if (!$this->userBackend->userExists($uid)) {
			throw new \Exception('User id not found in LDAP');
		}
		$access = $this->userBackend->getLDAPAccess($uid);
		$connection = $access->getConnection();
		$key = $uid . "::" . $attribute;
		$cached = $connection->getFromCache($key);

		if ($cached !== null) {
			return $cached;
		}

		$value = $access->readAttribute($access->username2dn($uid), $attribute);
		if (is_array($value) && count($value) > 0) {
			$value = current($value);
		} else {
			return null;
		}
		$connection->writeToCache($key, $value);

		return $value;
	}
}
