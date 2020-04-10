<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Alexander Bergolth <leo@strike.wu.ac.at>
 * @author Alex Weirig <alex.weirig@technolink.lu>
 * @author alexweirig <alex.weirig@technolink.lu>
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Andreas Pflug <dev@admin4.org>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Frédéric Fortier <frederic.fortier@oronospolytechnique.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nicolas Grekas <nicolas.grekas@gmail.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roland Tapken <roland@bitarbeiter.net>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
 * @author Xuanwo <xuanwo@yunify.com>
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

use OC\Cache\CappedMemoryCache;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\GroupInterface;
use OCP\ILogger;

class Group_LDAP extends BackendUtility implements \OCP\GroupInterface, IGroupLDAP, IGetDisplayNameBackend {
	protected $enabled = false;

	/**
	 * @var string[] $cachedGroupMembers array of users with gid as key
	 */
	protected $cachedGroupMembers;

	/**
	 * @var string[] $cachedGroupsByMember array of groups with uid as key
	 */
	protected $cachedGroupsByMember;

	/**
	 * @var string[] $cachedNestedGroups array of groups with gid (DN) as key
	 */
	protected $cachedNestedGroups;

	/** @var GroupPluginManager */
	protected $groupPluginManager;

	public function __construct(Access $access, GroupPluginManager $groupPluginManager) {
		parent::__construct($access);
		$filter = $this->access->connection->ldapGroupFilter;
		$gassoc = $this->access->connection->ldapGroupMemberAssocAttr;
		if(!empty($filter) && !empty($gassoc)) {
			$this->enabled = true;
		}

		$this->cachedGroupMembers = new CappedMemoryCache();
		$this->cachedGroupsByMember = new CappedMemoryCache();
		$this->cachedNestedGroups = new CappedMemoryCache();
		$this->groupPluginManager = $groupPluginManager;
	}

	/**
	 * is user in group?
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 *
	 * Checks whether the user is member of a group or not.
	 */
	public function inGroup($uid, $gid) {
		if(!$this->enabled) {
			return false;
		}
		$cacheKey = 'inGroup'.$uid.':'.$gid;
		$inGroup = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($inGroup)) {
			return (bool)$inGroup;
		}

		$userDN = $this->access->username2dn($uid);

		if(isset($this->cachedGroupMembers[$gid])) {
			$isInGroup = in_array($userDN, $this->cachedGroupMembers[$gid]);
			return $isInGroup;
		}

		$cacheKeyMembers = 'inGroup-members:'.$gid;
		$members = $this->access->connection->getFromCache($cacheKeyMembers);
		if(!is_null($members)) {
			$this->cachedGroupMembers[$gid] = $members;
			$isInGroup = in_array($userDN, $members, true);
			$this->access->connection->writeToCache($cacheKey, $isInGroup);
			return $isInGroup;
		}

		$groupDN = $this->access->groupname2dn($gid);
		// just in case
		if(!$groupDN || !$userDN) {
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		//check primary group first
		if($gid === $this->getUserPrimaryGroup($userDN)) {
			$this->access->connection->writeToCache($cacheKey, true);
			return true;
		}

		//usually, LDAP attributes are said to be case insensitive. But there are exceptions of course.
		$members = $this->_groupMembers($groupDN);
		if(!is_array($members) || count($members) === 0) {
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		//extra work if we don't get back user DNs
		if(strtolower($this->access->connection->ldapGroupMemberAssocAttr) === 'memberuid') {
			$dns = [];
			$filterParts = [];
			$bytes = 0;
			foreach($members as $mid) {
				$filter = str_replace('%uid', $mid, $this->access->connection->ldapLoginFilter);
				$filterParts[] = $filter;
				$bytes += strlen($filter);
				if($bytes >= 9000000) {
					// AD has a default input buffer of 10 MB, we do not want
					// to take even the chance to exceed it
					$filter = $this->access->combineFilterWithOr($filterParts);
					$bytes = 0;
					$filterParts = [];
					$users = $this->access->fetchListOfUsers($filter, 'dn', count($filterParts));
					$dns = array_merge($dns, $users);
				}
			}
			if(count($filterParts) > 0) {
				$filter = $this->access->combineFilterWithOr($filterParts);
				$users = $this->access->fetchListOfUsers($filter, 'dn', count($filterParts));
				$dns = array_merge($dns, $users);
			}
			$members = $dns;
		}

		$isInGroup = in_array($userDN, $members);
		$this->access->connection->writeToCache($cacheKey, $isInGroup);
		$this->access->connection->writeToCache($cacheKeyMembers, $members);
		$this->cachedGroupMembers[$gid] = $members;

		return $isInGroup;
	}

	/**
	 * @param string $dnGroup
	 * @return array
	 *
	 * For a group that has user membership defined by an LDAP search url attribute returns the users
	 * that match the search url otherwise returns an empty array.
	 */
	public function getDynamicGroupMembers($dnGroup) {
		$dynamicGroupMemberURL = strtolower($this->access->connection->ldapDynamicGroupMemberURL);

		if (empty($dynamicGroupMemberURL)) {
			return [];
		}

		$dynamicMembers = [];
		$memberURLs = $this->access->readAttribute(
			$dnGroup,
			$dynamicGroupMemberURL,
			$this->access->connection->ldapGroupFilter
		);
		if ($memberURLs !== false) {
			// this group has the 'memberURL' attribute so this is a dynamic group
			// example 1: ldap:///cn=users,cn=accounts,dc=dcsubbase,dc=dcbase??one?(o=HeadOffice)
			// example 2: ldap:///cn=users,cn=accounts,dc=dcsubbase,dc=dcbase??one?(&(o=HeadOffice)(uidNumber>=500))
			$pos = strpos($memberURLs[0], '(');
			if ($pos !== false) {
				$memberUrlFilter = substr($memberURLs[0], $pos);
				$foundMembers = $this->access->searchUsers($memberUrlFilter,'dn');
				$dynamicMembers = [];
				foreach($foundMembers as $value) {
					$dynamicMembers[$value['dn'][0]] = 1;
				}
			} else {
				\OCP\Util::writeLog('user_ldap', 'No search filter found on member url '.
					'of group ' . $dnGroup, ILogger::DEBUG);
			}
		}
		return $dynamicMembers;
	}

	/**
	 * @param string $dnGroup
	 * @param array|null &$seen
	 * @return array|mixed|null
	 * @throws \OC\ServerNotAvailableException
	 */
	private function _groupMembers($dnGroup, &$seen = null) {
		if ($seen === null) {
			$seen = [];
		}
		$allMembers = [];
		if (array_key_exists($dnGroup, $seen)) {
			// avoid loops
			return [];
		}
		// used extensively in cron job, caching makes sense for nested groups
		$cacheKey = '_groupMembers'.$dnGroup;
		$groupMembers = $this->access->connection->getFromCache($cacheKey);
		if($groupMembers !== null) {
			return $groupMembers;
		}
		$seen[$dnGroup] = 1;
		$members = $this->access->readAttribute($dnGroup, $this->access->connection->ldapGroupMemberAssocAttr);
		if (is_array($members)) {
			$fetcher = function ($memberDN, &$seen) {
				return $this->_groupMembers($memberDN, $seen);
			};
			$allMembers = $this->walkNestedGroups($dnGroup, $fetcher, $members);
		}

		$allMembers += $this->getDynamicGroupMembers($dnGroup);

		$this->access->connection->writeToCache($cacheKey, $allMembers);
		return $allMembers;
	}

	/**
	 * @param string $DN
	 * @param array|null &$seen
	 * @return array
	 * @throws \OC\ServerNotAvailableException
	 */
	private function _getGroupDNsFromMemberOf($DN) {
		$groups = $this->access->readAttribute($DN, 'memberOf');
		if (!is_array($groups)) {
			return [];
		}

		$fetcher = function ($groupDN) {
			if (isset($this->cachedNestedGroups[$groupDN])) {
				$nestedGroups = $this->cachedNestedGroups[$groupDN];
			} else {
				$nestedGroups = $this->access->readAttribute($groupDN, 'memberOf');
				if (!is_array($nestedGroups)) {
					$nestedGroups = [];
				}
				$this->cachedNestedGroups[$groupDN] = $nestedGroups;
			}
			return $nestedGroups;
		};

		$groups = $this->walkNestedGroups($DN, $fetcher, $groups);
		return $this->access->groupsMatchFilter($groups);
	}

	/**
	 * @param string $dn
	 * @param \Closure $fetcher args: string $dn, array $seen, returns: string[] of dns
	 * @param array $list
	 * @return array
	 */
	private function walkNestedGroups(string $dn, \Closure $fetcher, array $list): array {
		$nesting = (int) $this->access->connection->ldapNestedGroups;
		// depending on the input, we either have a list of DNs or a list of LDAP records
		// also, the output expects either DNs or records. Testing the first element should suffice.
		$recordMode = is_array($list) && isset($list[0]) && is_array($list[0]) && isset($list[0]['dn'][0]);

		if ($nesting !== 1) {
			if($recordMode) {
				// the keys are numeric, but should hold the DN
				return array_reduce($list, function ($transformed, $record) use ($dn) {
					if($record['dn'][0] != $dn) {
						$transformed[$record['dn'][0]] = $record;
					}
					return $transformed;
				}, []);
			}
			return $list;
		}

		$seen = [];
		while ($record = array_pop($list)) {
			$recordDN = $recordMode ? $record['dn'][0] : $record;
			if ($recordDN === $dn || array_key_exists($recordDN, $seen)) {
				// Prevent loops
				continue;
			}
			$fetched = $fetcher($record, $seen);
			$list = array_merge($list, $fetched);
			$seen[$recordDN] = $record;
		}

		return $recordMode ? $seen : array_keys($seen);
	}

	/**
	 * translates a gidNumber into an ownCloud internal name
	 * @param string $gid as given by gidNumber on POSIX LDAP
	 * @param string $dn a DN that belongs to the same domain as the group
	 * @return string|bool
	 */
	public function gidNumber2Name($gid, $dn) {
		$cacheKey = 'gidNumberToName' . $gid;
		$groupName = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($groupName) && isset($groupName)) {
			return $groupName;
		}

		//we need to get the DN from LDAP
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			'objectClass=posixGroup',
			$this->access->connection->ldapGidNumber . '=' . $gid
		]);
		$result = $this->access->searchGroups($filter, ['dn'], 1);
		if(empty($result)) {
			return false;
		}
		$dn = $result[0]['dn'][0];

		//and now the group name
		//NOTE once we have separate ownCloud group IDs and group names we can
		//directly read the display name attribute instead of the DN
		$name = $this->access->dn2groupname($dn);

		$this->access->connection->writeToCache($cacheKey, $name);

		return $name;
	}

	/**
	 * returns the entry's gidNumber
	 * @param string $dn
	 * @param string $attribute
	 * @return string|bool
	 */
	private function getEntryGidNumber($dn, $attribute) {
		$value = $this->access->readAttribute($dn, $attribute);
		if(is_array($value) && !empty($value)) {
			return $value[0];
		}
		return false;
	}

	/**
	 * returns the group's primary ID
	 * @param string $dn
	 * @return string|bool
	 */
	public function getGroupGidNumber($dn) {
		return $this->getEntryGidNumber($dn, 'gidNumber');
	}

	/**
	 * returns the user's gidNumber
	 * @param string $dn
	 * @return string|bool
	 */
	public function getUserGidNumber($dn) {
		$gidNumber = false;
		if($this->access->connection->hasGidNumber) {
			$gidNumber = $this->getEntryGidNumber($dn, $this->access->connection->ldapGidNumber);
			if($gidNumber === false) {
				$this->access->connection->hasGidNumber = false;
			}
		}
		return $gidNumber;
	}

	/**
	 * returns a filter for a "users has specific gid" search or count operation
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @return string
	 * @throws \Exception
	 */
	private function prepareFilterForUsersHasGidNumber($groupDN, $search = '') {
		$groupID = $this->getGroupGidNumber($groupDN);
		if($groupID === false) {
			throw new \Exception('Not a valid group');
		}

		$filterParts = [];
		$filterParts[] = $this->access->getFilterForUserCount();
		if ($search !== '') {
			$filterParts[] = $this->access->getFilterPartForUserSearch($search);
		}
		$filterParts[] = $this->access->connection->ldapGidNumber .'=' . $groupID;

		return $this->access->combineFilterWithAnd($filterParts);
	}

	/**
	 * returns a list of users that have the given group as gid number
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return string[]
	 */
	public function getUsersInGidNumber($groupDN, $search = '', $limit = -1, $offset = 0) {
		try {
			$filter = $this->prepareFilterForUsersHasGidNumber($groupDN, $search);
			$users = $this->access->fetchListOfUsers(
				$filter,
				[$this->access->connection->ldapUserDisplayName, 'dn'],
				$limit,
				$offset
			);
			return $this->access->nextcloudUserNames($users);
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * returns the number of users that have the given group as gid number
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return int
	 */
	public function countUsersInGidNumber($groupDN, $search = '', $limit = -1, $offset = 0) {
		try {
			$filter = $this->prepareFilterForUsersHasGidNumber($groupDN, $search);
			$users = $this->access->countUsers($filter, ['dn'], $limit, $offset);
			return (int)$users;
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * gets the gidNumber of a user
	 * @param string $dn
	 * @return string
	 */
	public function getUserGroupByGid($dn) {
		$groupID = $this->getUserGidNumber($dn);
		if($groupID !== false) {
			$groupName = $this->gidNumber2Name($groupID, $dn);
			if($groupName !== false) {
				return $groupName;
			}
		}

		return false;
	}

	/**
	 * translates a primary group ID into an Nextcloud internal name
	 * @param string $gid as given by primaryGroupID on AD
	 * @param string $dn a DN that belongs to the same domain as the group
	 * @return string|bool
	 */
	public function primaryGroupID2Name($gid, $dn) {
		$cacheKey = 'primaryGroupIDtoName';
		$groupNames = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($groupNames) && isset($groupNames[$gid])) {
			return $groupNames[$gid];
		}

		$domainObjectSid = $this->access->getSID($dn);
		if($domainObjectSid === false) {
			return false;
		}

		//we need to get the DN from LDAP
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			'objectsid=' . $domainObjectSid . '-' . $gid
		]);
		$result = $this->access->searchGroups($filter, ['dn'], 1);
		if(empty($result)) {
			return false;
		}
		$dn = $result[0]['dn'][0];

		//and now the group name
		//NOTE once we have separate Nextcloud group IDs and group names we can
		//directly read the display name attribute instead of the DN
		$name = $this->access->dn2groupname($dn);

		$this->access->connection->writeToCache($cacheKey, $name);

		return $name;
	}

	/**
	 * returns the entry's primary group ID
	 * @param string $dn
	 * @param string $attribute
	 * @return string|bool
	 */
	private function getEntryGroupID($dn, $attribute) {
		$value = $this->access->readAttribute($dn, $attribute);
		if(is_array($value) && !empty($value)) {
			return $value[0];
		}
		return false;
	}

	/**
	 * returns the group's primary ID
	 * @param string $dn
	 * @return string|bool
	 */
	public function getGroupPrimaryGroupID($dn) {
		return $this->getEntryGroupID($dn, 'primaryGroupToken');
	}

	/**
	 * returns the user's primary group ID
	 * @param string $dn
	 * @return string|bool
	 */
	public function getUserPrimaryGroupIDs($dn) {
		$primaryGroupID = false;
		if($this->access->connection->hasPrimaryGroups) {
			$primaryGroupID = $this->getEntryGroupID($dn, 'primaryGroupID');
			if($primaryGroupID === false) {
				$this->access->connection->hasPrimaryGroups = false;
			}
		}
		return $primaryGroupID;
	}

	/**
	 * returns a filter for a "users in primary group" search or count operation
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @return string
	 * @throws \Exception
	 */
	private function prepareFilterForUsersInPrimaryGroup($groupDN, $search = '') {
		$groupID = $this->getGroupPrimaryGroupID($groupDN);
		if($groupID === false) {
			throw new \Exception('Not a valid group');
		}

		$filterParts = [];
		$filterParts[] = $this->access->getFilterForUserCount();
		if ($search !== '') {
			$filterParts[] = $this->access->getFilterPartForUserSearch($search);
		}
		$filterParts[] = 'primaryGroupID=' . $groupID;

		return $this->access->combineFilterWithAnd($filterParts);
	}

	/**
	 * returns a list of users that have the given group as primary group
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return string[]
	 */
	public function getUsersInPrimaryGroup($groupDN, $search = '', $limit = -1, $offset = 0) {
		try {
			$filter = $this->prepareFilterForUsersInPrimaryGroup($groupDN, $search);
			$users = $this->access->fetchListOfUsers(
				$filter,
				[$this->access->connection->ldapUserDisplayName, 'dn'],
				$limit,
				$offset
			);
			return $this->access->nextcloudUserNames($users);
		} catch (\Exception $e) {
			return [];
		}
	}

	/**
	 * returns the number of users that have the given group as primary group
	 *
	 * @param string $groupDN
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return int
	 */
	public function countUsersInPrimaryGroup($groupDN, $search = '', $limit = -1, $offset = 0) {
		try {
			$filter = $this->prepareFilterForUsersInPrimaryGroup($groupDN, $search);
			$users = $this->access->countUsers($filter, ['dn'], $limit, $offset);
			return (int)$users;
		} catch (\Exception $e) {
			return 0;
		}
	}

	/**
	 * gets the primary group of a user
	 * @param string $dn
	 * @return string
	 */
	public function getUserPrimaryGroup($dn) {
		$groupID = $this->getUserPrimaryGroupIDs($dn);
		if($groupID !== false) {
			$groupName = $this->primaryGroupID2Name($groupID, $dn);
			if($groupName !== false) {
				return $groupName;
			}
		}

		return false;
	}

	/**
	 * Get all groups a user belongs to
	 * @param string $uid Name of the user
	 * @return array with group names
	 *
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 *
	 * This function includes groups based on dynamic group membership.
	 */
	public function getUserGroups($uid) {
		if(!$this->enabled) {
			return [];
		}
		$cacheKey = 'getUserGroups'.$uid;
		$userGroups = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($userGroups)) {
			return $userGroups;
		}
		$userDN = $this->access->username2dn($uid);
		if(!$userDN) {
			$this->access->connection->writeToCache($cacheKey, []);
			return [];
		}

		$groups = [];
		$primaryGroup = $this->getUserPrimaryGroup($userDN);
		$gidGroupName = $this->getUserGroupByGid($userDN);

		$dynamicGroupMemberURL = strtolower($this->access->connection->ldapDynamicGroupMemberURL);

		if (!empty($dynamicGroupMemberURL)) {
			// look through dynamic groups to add them to the result array if needed
			$groupsToMatch = $this->access->fetchListOfGroups(
				$this->access->connection->ldapGroupFilter,['dn',$dynamicGroupMemberURL]);
			foreach($groupsToMatch as $dynamicGroup) {
				if (!array_key_exists($dynamicGroupMemberURL, $dynamicGroup)) {
					continue;
				}
				$pos = strpos($dynamicGroup[$dynamicGroupMemberURL][0], '(');
				if ($pos !== false) {
					$memberUrlFilter = substr($dynamicGroup[$dynamicGroupMemberURL][0],$pos);
					// apply filter via ldap search to see if this user is in this
					// dynamic group
					$userMatch = $this->access->readAttribute(
						$userDN,
						$this->access->connection->ldapUserDisplayName,
						$memberUrlFilter
					);
					if ($userMatch !== false) {
						// match found so this user is in this group
						$groupName = $this->access->dn2groupname($dynamicGroup['dn'][0]);
						if(is_string($groupName)) {
							// be sure to never return false if the dn could not be
							// resolved to a name, for whatever reason.
							$groups[] = $groupName;
						}
					}
				} else {
					\OCP\Util::writeLog('user_ldap', 'No search filter found on member url '.
						'of group ' . print_r($dynamicGroup, true), ILogger::DEBUG);
				}
			}
		}

		// if possible, read out membership via memberOf. It's far faster than
		// performing a search, which still is a fallback later.
		// memberof doesn't support memberuid, so skip it here.
		if((int)$this->access->connection->hasMemberOfFilterSupport === 1
			&& (int)$this->access->connection->useMemberOfToDetectMembership === 1
			&& strtolower($this->access->connection->ldapGroupMemberAssocAttr) !== 'memberuid'
			) {
			$groupDNs = $this->_getGroupDNsFromMemberOf($userDN);
			if (is_array($groupDNs)) {
				foreach ($groupDNs as $dn) {
					$groupName = $this->access->dn2groupname($dn);
					if(is_string($groupName)) {
						// be sure to never return false if the dn could not be
						// resolved to a name, for whatever reason.
						$groups[] = $groupName;
					}
				}
			}

			if($primaryGroup !== false) {
				$groups[] = $primaryGroup;
			}
			if($gidGroupName !== false) {
				$groups[] = $gidGroupName;
			}
			$this->access->connection->writeToCache($cacheKey, $groups);
			return $groups;
		}

		//uniqueMember takes DN, memberuid the uid, so we need to distinguish
		if((strtolower($this->access->connection->ldapGroupMemberAssocAttr) === 'uniquemember')
			|| (strtolower($this->access->connection->ldapGroupMemberAssocAttr) === 'member')
		) {
			$uid = $userDN;
		} else if(strtolower($this->access->connection->ldapGroupMemberAssocAttr) === 'memberuid') {
			$result = $this->access->readAttribute($userDN, 'uid');
			if ($result === false) {
				\OCP\Util::writeLog('user_ldap', 'No uid attribute found for DN ' . $userDN . ' on '.
					$this->access->connection->ldapHost, ILogger::DEBUG);
				$uid = false;
			} else {
				$uid = $result[0];
			}
		} else {
			// just in case
			$uid = $userDN;
		}

		if($uid !== false) {
			if (isset($this->cachedGroupsByMember[$uid])) {
				$groups = array_merge($groups, $this->cachedGroupsByMember[$uid]);
			} else {
				$groupsByMember = array_values($this->getGroupsByMember($uid));
				$groupsByMember = $this->access->nextcloudGroupNames($groupsByMember);
				$this->cachedGroupsByMember[$uid] = $groupsByMember;
				$groups = array_merge($groups, $groupsByMember);
			}
		}

		if($primaryGroup !== false) {
			$groups[] = $primaryGroup;
		}
		if($gidGroupName !== false) {
			$groups[] = $gidGroupName;
		}

		$groups = array_unique($groups, SORT_LOCALE_STRING);
		$this->access->connection->writeToCache($cacheKey, $groups);

		return $groups;
	}

	/**
	 * @param string $dn
	 * @param array|null &$seen
	 * @return array
	 */
	private function getGroupsByMember($dn, &$seen = null) {
		if ($seen === null) {
			$seen = [];
		}
		if (array_key_exists($dn, $seen)) {
			// avoid loops
			return [];
		}
		$allGroups = [];
		$seen[$dn] = true;
		$filter = $this->access->connection->ldapGroupMemberAssocAttr.'='.$dn;
		$groups = $this->access->fetchListOfGroups($filter,
			[$this->access->connection->ldapGroupDisplayName, 'dn']);
		if (is_array($groups)) {
			$fetcher = function ($dn, &$seen) {
				if(is_array($dn) && isset($dn['dn'][0])) {
					$dn = $dn['dn'][0];
				}
				return $this->getGroupsByMember($dn, $seen);
			};
			$allGroups = $this->walkNestedGroups($dn, $fetcher, $groups);
		}
		$visibleGroups = $this->access->groupsMatchFilter(array_keys($allGroups));
		return array_intersect_key($allGroups, array_flip($visibleGroups));
	}

	/**
	 * get a list of all users in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array with user ids
	 * @throws \Exception
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		if(!$this->enabled) {
			return [];
		}
		if(!$this->groupExists($gid)) {
			return [];
		}
		$search = $this->access->escapeFilterPart($search, true);
		$cacheKey = 'usersInGroup-'.$gid.'-'.$search.'-'.$limit.'-'.$offset;
		// check for cache of the exact query
		$groupUsers = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($groupUsers)) {
			return $groupUsers;
		}

		// check for cache of the query without limit and offset
		$groupUsers = $this->access->connection->getFromCache('usersInGroup-'.$gid.'-'.$search);
		if(!is_null($groupUsers)) {
			$groupUsers = array_slice($groupUsers, $offset, $limit);
			$this->access->connection->writeToCache($cacheKey, $groupUsers);
			return $groupUsers;
		}

		if($limit === -1) {
			$limit = null;
		}
		$groupDN = $this->access->groupname2dn($gid);
		if(!$groupDN) {
			// group couldn't be found, return empty resultset
			$this->access->connection->writeToCache($cacheKey, []);
			return [];
		}

		$primaryUsers = $this->getUsersInPrimaryGroup($groupDN, $search, $limit, $offset);
		$posixGroupUsers = $this->getUsersInGidNumber($groupDN, $search, $limit, $offset);
		$members = $this->_groupMembers($groupDN);
		if(!$members && empty($posixGroupUsers) && empty($primaryUsers)) {
			//in case users could not be retrieved, return empty result set
			$this->access->connection->writeToCache($cacheKey, []);
			return [];
		}

		$groupUsers = [];
		$isMemberUid = (strtolower($this->access->connection->ldapGroupMemberAssocAttr) === 'memberuid');
		$attrs = $this->access->userManager->getAttributes(true);
		foreach($members as $member) {
			if($isMemberUid) {
				//we got uids, need to get their DNs to 'translate' them to user names
				$filter = $this->access->combineFilterWithAnd([
					str_replace('%uid', trim($member), $this->access->connection->ldapLoginFilter),
					$this->access->combineFilterWithAnd([
						$this->access->getFilterPartForUserSearch($search),
						$this->access->connection->ldapUserFilter
					])
				]);
				$ldap_users = $this->access->fetchListOfUsers($filter, $attrs, 1);
				if(count($ldap_users) < 1) {
					continue;
				}
				$groupUsers[] = $this->access->dn2username($ldap_users[0]['dn'][0]);
			} else {
				//we got DNs, check if we need to filter by search or we can give back all of them
				$uid = $this->access->dn2username($member);
				if(!$uid) {
					continue;
				}

				$cacheKey = 'userExistsOnLDAP' . $uid;
				$userExists = $this->access->connection->getFromCache($cacheKey);
				if($userExists === false) {
					continue;
				}
				if($userExists === null || $search !== '') {
					if (!$this->access->readAttribute($member,
						$this->access->connection->ldapUserDisplayName,
						$this->access->combineFilterWithAnd([
							$this->access->getFilterPartForUserSearch($search),
							$this->access->connection->ldapUserFilter
						])))
					{
						if($search === '') {
							$this->access->connection->writeToCache($cacheKey, false);
						}
						continue;
					}
					$this->access->connection->writeToCache($cacheKey, true);
				}
				$groupUsers[] = $uid;
			}
		}

		$groupUsers = array_unique(array_merge($groupUsers, $primaryUsers, $posixGroupUsers));
		natsort($groupUsers);
		$this->access->connection->writeToCache('usersInGroup-'.$gid.'-'.$search, $groupUsers);
		$groupUsers = array_slice($groupUsers, $offset, $limit);

		$this->access->connection->writeToCache($cacheKey, $groupUsers);

		return $groupUsers;
	}

	/**
	 * returns the number of users in a group, who match the search term
	 * @param string $gid the internal group name
	 * @param string $search optional, a search string
	 * @return int|bool
	 */
	public function countUsersInGroup($gid, $search = '') {
		if ($this->groupPluginManager->implementsActions(GroupInterface::COUNT_USERS)) {
			return $this->groupPluginManager->countUsersInGroup($gid, $search);
		}

		$cacheKey = 'countUsersInGroup-'.$gid.'-'.$search;
		if(!$this->enabled || !$this->groupExists($gid)) {
			return false;
		}
		$groupUsers = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($groupUsers)) {
			return $groupUsers;
		}

		$groupDN = $this->access->groupname2dn($gid);
		if(!$groupDN) {
			// group couldn't be found, return empty result set
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		$members = $this->_groupMembers($groupDN);
		$primaryUserCount = $this->countUsersInPrimaryGroup($groupDN, '');
		if(!$members && $primaryUserCount === 0) {
			//in case users could not be retrieved, return empty result set
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		if ($search === '') {
			$groupUsers = count($members) + $primaryUserCount;
			$this->access->connection->writeToCache($cacheKey, $groupUsers);
			return $groupUsers;
		}
		$search = $this->access->escapeFilterPart($search, true);
		$isMemberUid =
			(strtolower($this->access->connection->ldapGroupMemberAssocAttr)
			=== 'memberuid');

		//we need to apply the search filter
		//alternatives that need to be checked:
		//a) get all users by search filter and array_intersect them
		//b) a, but only when less than 1k 10k ?k users like it is
		//c) put all DNs|uids in a LDAP filter, combine with the search string
		//   and let it count.
		//For now this is not important, because the only use of this method
		//does not supply a search string
		$groupUsers = [];
		foreach($members as $member) {
			if($isMemberUid) {
				//we got uids, need to get their DNs to 'translate' them to user names
				$filter = $this->access->combineFilterWithAnd([
					str_replace('%uid', $member, $this->access->connection->ldapLoginFilter),
					$this->access->getFilterPartForUserSearch($search)
				]);
				$ldap_users = $this->access->fetchListOfUsers($filter, 'dn', 1);
				if(count($ldap_users) < 1) {
					continue;
				}
				$groupUsers[] = $this->access->dn2username($ldap_users[0]);
			} else {
				//we need to apply the search filter now
				if(!$this->access->readAttribute($member,
					$this->access->connection->ldapUserDisplayName,
					$this->access->getFilterPartForUserSearch($search))) {
					continue;
				}
				// dn2username will also check if the users belong to the allowed base
				if($ocname = $this->access->dn2username($member)) {
					$groupUsers[] = $ocname;
				}
			}
		}

		//and get users that have the group as primary
		$primaryUsers = $this->countUsersInPrimaryGroup($groupDN, $search);

		return count($groupUsers) + $primaryUsers;
	}

	/**
	 * get a list of all groups
	 *
	 * @param string $search
	 * @param $limit
	 * @param int $offset
	 * @return array with group names
	 *
	 * Returns a list with all groups (used by getGroups)
	 */
	protected function getGroupsChunk($search = '', $limit = -1, $offset = 0) {
		if(!$this->enabled) {
			return [];
		}
		$cacheKey = 'getGroups-'.$search.'-'.$limit.'-'.$offset;

		//Check cache before driving unnecessary searches
		\OCP\Util::writeLog('user_ldap', 'getGroups '.$cacheKey, ILogger::DEBUG);
		$ldap_groups = $this->access->connection->getFromCache($cacheKey);
		if(!is_null($ldap_groups)) {
			return $ldap_groups;
		}

		// if we'd pass -1 to LDAP search, we'd end up in a Protocol
		// error. With a limit of 0, we get 0 results. So we pass null.
		if($limit <= 0) {
			$limit = null;
		}
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			$this->access->getFilterPartForGroupSearch($search)
		]);
		\OCP\Util::writeLog('user_ldap', 'getGroups Filter '.$filter, ILogger::DEBUG);
		$ldap_groups = $this->access->fetchListOfGroups($filter,
				[$this->access->connection->ldapGroupDisplayName, 'dn'],
				$limit,
				$offset);
		$ldap_groups = $this->access->nextcloudGroupNames($ldap_groups);

		$this->access->connection->writeToCache($cacheKey, $ldap_groups);
		return $ldap_groups;
	}

	/**
	 * get a list of all groups using a paged search
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array with group names
	 *
	 * Returns a list with all groups
	 * Uses a paged search if available to override a
	 * server side search limit.
	 * (active directory has a limit of 1000 by default)
	 */
	public function getGroups($search = '', $limit = -1, $offset = 0) {
		if(!$this->enabled) {
			return [];
		}
		$search = $this->access->escapeFilterPart($search, true);
		$pagingSize = (int)$this->access->connection->ldapPagingSize;
		if ($pagingSize <= 0) {
			return $this->getGroupsChunk($search, $limit, $offset);
		}
		$maxGroups = 100000; // limit max results (just for safety reasons)
		if ($limit > -1) {
		   $overallLimit = min($limit + $offset, $maxGroups);
		} else {
		   $overallLimit = $maxGroups;
		}
		$chunkOffset = $offset;
		$allGroups = [];
		while ($chunkOffset < $overallLimit) {
			$chunkLimit = min($pagingSize, $overallLimit - $chunkOffset);
			$ldapGroups = $this->getGroupsChunk($search, $chunkLimit, $chunkOffset);
			$nread = count($ldapGroups);
			\OCP\Util::writeLog('user_ldap', 'getGroups('.$search.'): read '.$nread.' at offset '.$chunkOffset.' (limit: '.$chunkLimit.')', ILogger::DEBUG);
			if ($nread) {
				$allGroups = array_merge($allGroups, $ldapGroups);
				$chunkOffset += $nread;
			}
			if ($nread < $chunkLimit) {
				break;
			}
		}
		return $allGroups;
	}

	/**
	 * @param string $group
	 * @return bool
	 */
	public function groupMatchesFilter($group) {
		return (strripos($group, $this->groupSearch) !== false);
	}

	/**
	 * check if a group exists
	 * @param string $gid
	 * @return bool
	 */
	public function groupExists($gid) {
		$groupExists = $this->access->connection->getFromCache('groupExists'.$gid);
		if(!is_null($groupExists)) {
			return (bool)$groupExists;
		}

		//getting dn, if false the group does not exist. If dn, it may be mapped
		//only, requires more checking.
		$dn = $this->access->groupname2dn($gid);
		if(!$dn) {
			$this->access->connection->writeToCache('groupExists'.$gid, false);
			return false;
		}

		//if group really still exists, we will be able to read its objectclass
		if(!is_array($this->access->readAttribute($dn, ''))) {
			$this->access->connection->writeToCache('groupExists'.$gid, false);
			return false;
		}

		$this->access->connection->writeToCache('groupExists'.$gid, true);
		return true;
	}

	/**
	 * Check if backend implements actions
	 * @param int $actions bitwise-or'ed actions
	 * @return boolean
	 *
	 * Returns the supported actions as int to be
	 * compared with GroupInterface::CREATE_GROUP etc.
	 */
	public function implementsActions($actions) {
		return (bool)((GroupInterface::COUNT_USERS |
				$this->groupPluginManager->getImplementedActions()) & $actions);
	}

	/**
	 * Return access for LDAP interaction.
	 * @return Access instance of Access for LDAP interaction
	 */
	public function getLDAPAccess($gid) {
		return $this->access;
	}

	/**
	 * create a group
	 * @param string $gid
	 * @return bool
	 * @throws \Exception
	 */
	public function createGroup($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::CREATE_GROUP)) {
			if ($dn = $this->groupPluginManager->createGroup($gid)) {
				//updates group mapping
				$uuid = $this->access->getUUID($dn, false);
				if(is_string($uuid)) {
					$this->access->mapAndAnnounceIfApplicable(
						$this->access->getGroupMapper(),
						$dn,
						$gid,
						$uuid,
						false
					);
					$this->access->cacheGroupExists($gid);
				}
			}
			return $dn != null;
		}
		throw new \Exception('Could not create group in LDAP backend.');
	}

	/**
	 * delete a group
	 * @param string $gid gid of the group to delete
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteGroup($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::DELETE_GROUP)) {
			if ($ret = $this->groupPluginManager->deleteGroup($gid)) {
				#delete group in nextcloud internal db
				$this->access->getGroupMapper()->unmap($gid);
				$this->access->connection->writeToCache("groupExists".$gid, false);
			}
			return $ret;
		}
		throw new \Exception('Could not delete group in LDAP backend.');
	}

	/**
	 * Add a user to a group
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 * @throws \Exception
	 */
	public function addToGroup($uid, $gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::ADD_TO_GROUP)) {
			if ($ret = $this->groupPluginManager->addToGroup($uid, $gid)) {
				$this->access->connection->clearCache();
				unset($this->cachedGroupMembers[$gid]);
			}
			return $ret;
		}
		throw new \Exception('Could not add user to group in LDAP backend.');
	}

	/**
	 * Removes a user from a group
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 * @throws \Exception
	 */
	public function removeFromGroup($uid, $gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::REMOVE_FROM_GROUP)) {
			if ($ret = $this->groupPluginManager->removeFromGroup($uid, $gid)) {
				$this->access->connection->clearCache();
				unset($this->cachedGroupMembers[$gid]);
			}
			return $ret;
		}
		throw new \Exception('Could not remove user from group in LDAP backend.');
	}

	/**
	 * Gets group details
	 * @param string $gid Name of the group
	 * @return array | false
	 * @throws \Exception
	 */
	public function getGroupDetails($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::GROUP_DETAILS)) {
			return $this->groupPluginManager->getGroupDetails($gid);
		}
		throw new \Exception('Could not get group details in LDAP backend.');
	}

	/**
	 * Return LDAP connection resource from a cloned connection.
	 * The cloned connection needs to be closed manually.
	 * of the current access.
	 * @param string $gid
	 * @return resource of the LDAP connection
	 */
	public function getNewLDAPConnection($gid) {
		$connection = clone $this->access->getConnection();
		return $connection->getConnectionResource();
	}

	/**
	 * @throws \OC\ServerNotAvailableException
	 */
	public function getDisplayName(string $gid): string {
		if ($this->groupPluginManager instanceof IGetDisplayNameBackend) {
			return $this->groupPluginManager->getDisplayName($gid);
		}

		$cacheKey = 'group_getDisplayName' . $gid;
		if (!is_null($displayName = $this->access->connection->getFromCache($cacheKey))) {
			return $displayName;
		}

		$displayName = $this->access->readAttribute(
			$this->access->groupname2dn($gid),
			$this->access->connection->ldapGroupDisplayName);

		if ($displayName && (count($displayName) > 0)) {
			$displayName = $displayName[0];
			$this->access->connection->writeToCache($cacheKey, $displayName);
			return $displayName;
		}

		return '';
	}
}
