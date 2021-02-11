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
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clement Wong <git@clement.hk>
 * @author Frédéric Fortier <frederic.fortier@oronospolytechnique.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nicolas Grekas <nicolas.grekas@gmail.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roland Tapken <roland@bitarbeiter.net>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobias Perschon <tobias@perschon.at>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

use Closure;
use Exception;
use OC;
use OC\Cache\CappedMemoryCache;
use OC\ServerNotAvailableException;
use OCP\Group\Backend\IGetDisplayNameBackend;
use OCP\GroupInterface;
use OCP\ILogger;

class Group_LDAP extends BackendUtility implements GroupInterface, IGroupLDAP, IGetDisplayNameBackend {
	protected $enabled = false;

	/** @var string[][] $cachedGroupMembers array of users with gid as key */
	protected $cachedGroupMembers;
	/** @var string[] $cachedGroupsByMember array of groups with uid as key */
	protected $cachedGroupsByMember;
	/** @var string[] $cachedNestedGroups array of groups with gid (DN) as key */
	protected $cachedNestedGroups;
	/** @var GroupPluginManager */
	protected $groupPluginManager;
	/** @var ILogger */
	protected $logger;

	/**
	 * @var string $ldapGroupMemberAssocAttr contains the LDAP setting (in lower case) with the same name
	 */
	protected $ldapGroupMemberAssocAttr;

	public function __construct(Access $access, GroupPluginManager $groupPluginManager) {
		parent::__construct($access);
		$filter = $this->access->connection->ldapGroupFilter;
		$gAssoc = $this->access->connection->ldapGroupMemberAssocAttr;
		if (!empty($filter) && !empty($gAssoc)) {
			$this->enabled = true;
		}

		$this->cachedGroupMembers = new CappedMemoryCache();
		$this->cachedGroupsByMember = new CappedMemoryCache();
		$this->cachedNestedGroups = new CappedMemoryCache();
		$this->groupPluginManager = $groupPluginManager;
		$this->logger = OC::$server->getLogger();
		$this->ldapGroupMemberAssocAttr = strtolower($gAssoc);
	}

	/**
	 * is user in group?
	 *
	 * @param string $uid uid of the user
	 * @param string $gid gid of the group
	 * @return bool
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function inGroup($uid, $gid) {
		if (!$this->enabled) {
			return false;
		}
		$cacheKey = 'inGroup' . $uid . ':' . $gid;
		$inGroup = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($inGroup)) {
			return (bool)$inGroup;
		}

		$userDN = $this->access->username2dn($uid);

		if (isset($this->cachedGroupMembers[$gid])) {
			return in_array($userDN, $this->cachedGroupMembers[$gid]);
		}

		$cacheKeyMembers = 'inGroup-members:' . $gid;
		$members = $this->access->connection->getFromCache($cacheKeyMembers);
		if (!is_null($members)) {
			$this->cachedGroupMembers[$gid] = $members;
			$isInGroup = in_array($userDN, $members, true);
			$this->access->connection->writeToCache($cacheKey, $isInGroup);
			return $isInGroup;
		}

		$groupDN = $this->access->groupname2dn($gid);
		// just in case
		if (!$groupDN || !$userDN) {
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		//check primary group first
		if ($gid === $this->getUserPrimaryGroup($userDN)) {
			$this->access->connection->writeToCache($cacheKey, true);
			return true;
		}

		//usually, LDAP attributes are said to be case insensitive. But there are exceptions of course.
		$members = $this->_groupMembers($groupDN);

		//extra work if we don't get back user DNs
		switch ($this->ldapGroupMemberAssocAttr) {
			case 'memberuid':
			case 'zimbramailforwardingaddress':
				$requestAttributes = $this->access->userManager->getAttributes(true);
				$users = [];
				$filterParts = [];
				$bytes = 0;
				foreach ($members as $mid) {
					if ($this->ldapGroupMemberAssocAttr === 'zimbramailforwardingaddress') {
						$parts = explode('@', $mid); //making sure we get only the uid
						$mid = $parts[0];
					}
					$filter = str_replace('%uid', $mid, $this->access->connection->ldapLoginFilter);
					$filterParts[] = $filter;
					$bytes += strlen($filter);
					if ($bytes >= 9000000) {
						// AD has a default input buffer of 10 MB, we do not want
						// to take even the chance to exceed it
						// so we fetch results with the filterParts we collected so far
						$filter = $this->access->combineFilterWithOr($filterParts);
						$search = $this->access->fetchListOfUsers($filter, $requestAttributes, count($filterParts));
						$bytes = 0;
						$filterParts = [];
						$users = array_merge($users, $search);
					}
				}

				if (count($filterParts) > 0) {
					// if there are filterParts left we need to add their result
					$filter = $this->access->combineFilterWithOr($filterParts);
					$search = $this->access->fetchListOfUsers($filter, $requestAttributes, count($filterParts));
					$users = array_merge($users, $search);
				}

				// now we cleanup the users array to get only dns
				$dns = [];
				foreach ($users as $record) {
					$dns[$record['dn'][0]] = 1;
				}
				$members = array_keys($dns);

				break;
		}

		if (count($members) === 0) {
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		$isInGroup = in_array($userDN, $members);
		$this->access->connection->writeToCache($cacheKey, $isInGroup);
		$this->access->connection->writeToCache($cacheKeyMembers, $members);
		$this->cachedGroupMembers[$gid] = $members;

		return $isInGroup;
	}

	/**
	 * For a group that has user membership defined by an LDAP search url
	 * attribute returns the users that match the search url otherwise returns
	 * an empty array.
	 *
	 * @throws ServerNotAvailableException
	 */
	public function getDynamicGroupMembers(string $dnGroup): array {
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
				$foundMembers = $this->access->searchUsers($memberUrlFilter, ['dn']);
				$dynamicMembers = [];
				foreach ($foundMembers as $value) {
					$dynamicMembers[$value['dn'][0]] = 1;
				}
			} else {
				$this->logger->debug('No search filter found on member url of group {dn}',
					[
						'app' => 'user_ldap',
						'dn' => $dnGroup,
					]
				);
			}
		}
		return $dynamicMembers;
	}

	/**
	 * @throws ServerNotAvailableException
	 */
	private function _groupMembers(string $dnGroup, ?array &$seen = null): array {
		if ($seen === null) {
			$seen = [];
		}
		$allMembers = [];
		if (array_key_exists($dnGroup, $seen)) {
			return [];
		}
		// used extensively in cron job, caching makes sense for nested groups
		$cacheKey = '_groupMembers' . $dnGroup;
		$groupMembers = $this->access->connection->getFromCache($cacheKey);
		if ($groupMembers !== null) {
			return $groupMembers;
		}

		if ($this->access->connection->ldapNestedGroups
			&& $this->access->connection->useMemberOfToDetectMembership
			&& $this->access->connection->hasMemberOfFilterSupport
			&& $this->access->connection->ldapMatchingRuleInChainState !== Configuration::LDAP_SERVER_FEATURE_UNAVAILABLE
		) {
			$attemptedLdapMatchingRuleInChain = true;
			// compatibility hack with servers supporting :1.2.840.113556.1.4.1941:, and others)
			$filter = $this->access->combineFilterWithAnd([
				$this->access->connection->ldapUserFilter,
				$this->access->connection->ldapUserDisplayName . '=*',
				'memberof:1.2.840.113556.1.4.1941:=' . $dnGroup
			]);
			$memberRecords = $this->access->fetchListOfUsers(
				$filter,
				$this->access->userManager->getAttributes(true)
			);
			$result = array_reduce($memberRecords, function ($carry, $record) {
				$carry[] = $record['dn'][0];
				return $carry;
			}, []);
			if ($this->access->connection->ldapMatchingRuleInChainState === Configuration::LDAP_SERVER_FEATURE_AVAILABLE) {
				return $result;
			} elseif (!empty($memberRecords)) {
				$this->access->connection->ldapMatchingRuleInChainState = Configuration::LDAP_SERVER_FEATURE_AVAILABLE;
				$this->access->connection->saveConfiguration();
				return $result;
			}
			// when feature availability is unknown, and the result is empty, continue and test with original approach
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
		if (isset($attemptedLdapMatchingRuleInChain)
			&& $this->access->connection->ldapMatchingRuleInChainState === Configuration::LDAP_SERVER_FEATURE_UNKNOWN
			&& !empty($allMembers)
		) {
			$this->access->connection->ldapMatchingRuleInChainState = Configuration::LDAP_SERVER_FEATURE_UNAVAILABLE;
			$this->access->connection->saveConfiguration();
		}
		return $allMembers;
	}

	/**
	 * @throws ServerNotAvailableException
	 */
	private function _getGroupDNsFromMemberOf(string $dn): array {
		$groups = $this->access->readAttribute($dn, 'memberOf');
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

		$groups = $this->walkNestedGroups($dn, $fetcher, $groups);
		return $this->filterValidGroups($groups);
	}

	private function walkNestedGroups(string $dn, Closure $fetcher, array $list): array {
		$nesting = (int)$this->access->connection->ldapNestedGroups;
		// depending on the input, we either have a list of DNs or a list of LDAP records
		// also, the output expects either DNs or records. Testing the first element should suffice.
		$recordMode = is_array($list) && isset($list[0]) && is_array($list[0]) && isset($list[0]['dn'][0]);

		if ($nesting !== 1) {
			if ($recordMode) {
				// the keys are numeric, but should hold the DN
				return array_reduce($list, function ($transformed, $record) use ($dn) {
					if ($record['dn'][0] != $dn) {
						$transformed[$record['dn'][0]] = $record;
					}
					return $transformed;
				}, []);
			}
			return $list;
		}

		$seen = [];
		while ($record = array_shift($list)) {
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
	 *
	 * @return string|bool
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function gidNumber2Name(string $gid, string $dn) {
		$cacheKey = 'gidNumberToName' . $gid;
		$groupName = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($groupName) && isset($groupName)) {
			return $groupName;
		}

		//we need to get the DN from LDAP
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			'objectClass=posixGroup',
			$this->access->connection->ldapGidNumber . '=' . $gid
		]);
		return $this->getNameOfGroup($filter, $cacheKey) ?? false;
	}

	/**
	 * @throws ServerNotAvailableException
	 * @throws Exception
	 */
	private function getNameOfGroup(string $filter, string $cacheKey) {
		$result = $this->access->searchGroups($filter, ['dn'], 1);
		if (empty($result)) {
			return null;
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
	 * returns the entry's gidNumber
	 *
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	private function getEntryGidNumber(string $dn, string $attribute) {
		$value = $this->access->readAttribute($dn, $attribute);
		if (is_array($value) && !empty($value)) {
			return $value[0];
		}
		return false;
	}

	/**
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	public function getGroupGidNumber(string $dn) {
		return $this->getEntryGidNumber($dn, 'gidNumber');
	}

	/**
	 * returns the user's gidNumber
	 *
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	public function getUserGidNumber(string $dn) {
		$gidNumber = false;
		if ($this->access->connection->hasGidNumber) {
			$gidNumber = $this->getEntryGidNumber($dn, $this->access->connection->ldapGidNumber);
			if ($gidNumber === false) {
				$this->access->connection->hasGidNumber = false;
			}
		}
		return $gidNumber;
	}

	/**
	 * @throws ServerNotAvailableException
	 * @throws Exception
	 */
	private function prepareFilterForUsersHasGidNumber(string $groupDN, string $search = ''): string {
		$groupID = $this->getGroupGidNumber($groupDN);
		if ($groupID === false) {
			throw new Exception('Not a valid group');
		}

		$filterParts = [];
		$filterParts[] = $this->access->getFilterForUserCount();
		if ($search !== '') {
			$filterParts[] = $this->access->getFilterPartForUserSearch($search);
		}
		$filterParts[] = $this->access->connection->ldapGidNumber . '=' . $groupID;

		return $this->access->combineFilterWithAnd($filterParts);
	}

	/**
	 * returns a list of users that have the given group as gid number
	 *
	 * @throws ServerNotAvailableException
	 */
	public function getUsersInGidNumber(
		string $groupDN,
		string $search = '',
		?int $limit = -1,
		?int $offset = 0
	): array {
		try {
			$filter = $this->prepareFilterForUsersHasGidNumber($groupDN, $search);
			$users = $this->access->fetchListOfUsers(
				$filter,
				[$this->access->connection->ldapUserDisplayName, 'dn'],
				$limit,
				$offset
			);
			return $this->access->nextcloudUserNames($users);
		} catch (ServerNotAvailableException $e) {
			throw $e;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * @throws ServerNotAvailableException
	 * @return bool
	 */
	public function getUserGroupByGid(string $dn) {
		$groupID = $this->getUserGidNumber($dn);
		if ($groupID !== false) {
			$groupName = $this->gidNumber2Name($groupID, $dn);
			if ($groupName !== false) {
				return $groupName;
			}
		}

		return false;
	}

	/**
	 * translates a primary group ID into an Nextcloud internal name
	 *
	 * @return string|bool
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function primaryGroupID2Name(string $gid, string $dn) {
		$cacheKey = 'primaryGroupIDtoName';
		$groupNames = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($groupNames) && isset($groupNames[$gid])) {
			return $groupNames[$gid];
		}

		$domainObjectSid = $this->access->getSID($dn);
		if ($domainObjectSid === false) {
			return false;
		}

		//we need to get the DN from LDAP
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			'objectsid=' . $domainObjectSid . '-' . $gid
		]);
		return $this->getNameOfGroup($filter, $cacheKey) ?? false;
	}

	/**
	 * returns the entry's primary group ID
	 *
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	private function getEntryGroupID(string $dn, string $attribute) {
		$value = $this->access->readAttribute($dn, $attribute);
		if (is_array($value) && !empty($value)) {
			return $value[0];
		}
		return false;
	}

	/**
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	public function getGroupPrimaryGroupID(string $dn) {
		return $this->getEntryGroupID($dn, 'primaryGroupToken');
	}

	/**
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	public function getUserPrimaryGroupIDs(string $dn) {
		$primaryGroupID = false;
		if ($this->access->connection->hasPrimaryGroups) {
			$primaryGroupID = $this->getEntryGroupID($dn, 'primaryGroupID');
			if ($primaryGroupID === false) {
				$this->access->connection->hasPrimaryGroups = false;
			}
		}
		return $primaryGroupID;
	}

	/**
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	private function prepareFilterForUsersInPrimaryGroup(string $groupDN, string $search = ''): string {
		$groupID = $this->getGroupPrimaryGroupID($groupDN);
		if ($groupID === false) {
			throw new Exception('Not a valid group');
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
	 * @throws ServerNotAvailableException
	 */
	public function getUsersInPrimaryGroup(
		string $groupDN,
		string $search = '',
		?int $limit = -1,
		?int $offset = 0
	): array {
		try {
			$filter = $this->prepareFilterForUsersInPrimaryGroup($groupDN, $search);
			$users = $this->access->fetchListOfUsers(
				$filter,
				[$this->access->connection->ldapUserDisplayName, 'dn'],
				$limit,
				$offset
			);
			return $this->access->nextcloudUserNames($users);
		} catch (ServerNotAvailableException $e) {
			throw $e;
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * @throws ServerNotAvailableException
	 */
	public function countUsersInPrimaryGroup(
		string $groupDN,
		string $search = '',
		int $limit = -1,
		int $offset = 0
	): int {
		try {
			$filter = $this->prepareFilterForUsersInPrimaryGroup($groupDN, $search);
			$users = $this->access->countUsers($filter, ['dn'], $limit, $offset);
			return (int)$users;
		} catch (ServerNotAvailableException $e) {
			throw $e;
		} catch (Exception $e) {
			return 0;
		}
	}

	/**
	 * @return string|bool
	 * @throws ServerNotAvailableException
	 */
	public function getUserPrimaryGroup(string $dn) {
		$groupID = $this->getUserPrimaryGroupIDs($dn);
		if ($groupID !== false) {
			$groupName = $this->primaryGroupID2Name($groupID, $dn);
			if ($groupName !== false) {
				return $groupName;
			}
		}

		return false;
	}

	/**
	 * This function fetches all groups a user belongs to. It does not check
	 * if the user exists at all.
	 *
	 * This function includes groups based on dynamic group membership.
	 *
	 * @param string $uid Name of the user
	 * @return array with group names
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function getUserGroups($uid) {
		if (!$this->enabled) {
			return [];
		}
		$cacheKey = 'getUserGroups' . $uid;
		$userGroups = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($userGroups)) {
			return $userGroups;
		}
		$userDN = $this->access->username2dn($uid);
		if (!$userDN) {
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
				$this->access->connection->ldapGroupFilter, ['dn', $dynamicGroupMemberURL]);
			foreach ($groupsToMatch as $dynamicGroup) {
				if (!array_key_exists($dynamicGroupMemberURL, $dynamicGroup)) {
					continue;
				}
				$pos = strpos($dynamicGroup[$dynamicGroupMemberURL][0], '(');
				if ($pos !== false) {
					$memberUrlFilter = substr($dynamicGroup[$dynamicGroupMemberURL][0], $pos);
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
						if (is_string($groupName)) {
							// be sure to never return false if the dn could not be
							// resolved to a name, for whatever reason.
							$groups[] = $groupName;
						}
					}
				} else {
					$this->logger->debug('No search filter found on member url of group {dn}',
						[
							'app' => 'user_ldap',
							'dn' => $dynamicGroup,
						]
					);
				}
			}
		}

		// if possible, read out membership via memberOf. It's far faster than
		// performing a search, which still is a fallback later.
		// memberof doesn't support memberuid, so skip it here.
		if ((int)$this->access->connection->hasMemberOfFilterSupport === 1
			&& (int)$this->access->connection->useMemberOfToDetectMembership === 1
			&& $this->ldapGroupMemberAssocAttr !== 'memberuid'
			&& $this->ldapGroupMemberAssocAttr !== 'zimbramailforwardingaddress') {
			$groupDNs = $this->_getGroupDNsFromMemberOf($userDN);
			if (is_array($groupDNs)) {
				foreach ($groupDNs as $dn) {
					$groupName = $this->access->dn2groupname($dn);
					if (is_string($groupName)) {
						// be sure to never return false if the dn could not be
						// resolved to a name, for whatever reason.
						$groups[] = $groupName;
					}
				}
			}

			if ($primaryGroup !== false) {
				$groups[] = $primaryGroup;
			}
			if ($gidGroupName !== false) {
				$groups[] = $gidGroupName;
			}
			$this->access->connection->writeToCache($cacheKey, $groups);
			return $groups;
		}

		//uniqueMember takes DN, memberuid the uid, so we need to distinguish
		switch ($this->ldapGroupMemberAssocAttr) {
			case 'uniquemember':
			case 'member':
				$uid = $userDN;
				break;

			case 'memberuid':
			case 'zimbramailforwardingaddress':
				$result = $this->access->readAttribute($userDN, 'uid');
				if ($result === false) {
					$this->logger->debug('No uid attribute found for DN {dn} on {host}',
						[
							'app' => 'user_ldap',
							'dn' => $userDN,
							'host' => $this->access->connection->ldapHost,
						]
					);
					$uid = false;
				} else {
					$uid = $result[0];
				}
				break;

			default:
				// just in case
				$uid = $userDN;
				break;
		}

		if ($uid !== false) {
			if (isset($this->cachedGroupsByMember[$uid])) {
				$groups = array_merge($groups, $this->cachedGroupsByMember[$uid]);
			} else {
				$groupsByMember = array_values($this->getGroupsByMember($uid));
				$groupsByMember = $this->access->nextcloudGroupNames($groupsByMember);
				$this->cachedGroupsByMember[$uid] = $groupsByMember;
				$groups = array_merge($groups, $groupsByMember);
			}
		}

		if ($primaryGroup !== false) {
			$groups[] = $primaryGroup;
		}
		if ($gidGroupName !== false) {
			$groups[] = $gidGroupName;
		}

		$groups = array_unique($groups, SORT_LOCALE_STRING);
		$this->access->connection->writeToCache($cacheKey, $groups);

		return $groups;
	}

	/**
	 * @throws ServerNotAvailableException
	 */
	private function getGroupsByMember(string $dn, array &$seen = null): array {
		if ($seen === null) {
			$seen = [];
		}
		if (array_key_exists($dn, $seen)) {
			// avoid loops
			return [];
		}
		$allGroups = [];
		$seen[$dn] = true;
		$filter = $this->access->connection->ldapGroupMemberAssocAttr . '=' . $dn;

		if ($this->ldapGroupMemberAssocAttr === 'zimbramailforwardingaddress') {
			//in this case the member entries are email addresses
			$filter .= '@*';
		}

		$nesting = (int)$this->access->connection->ldapNestedGroups;
		if ($nesting === 0) {
			$filter = $this->access->combineFilterWithAnd([$filter, $this->access->connection->ldapGroupFilter]);
		}

		$groups = $this->access->fetchListOfGroups($filter,
			[strtolower($this->access->connection->ldapGroupMemberAssocAttr), $this->access->connection->ldapGroupDisplayName, 'dn']);
		if (is_array($groups)) {
			$fetcher = function ($dn, &$seen) {
				if (is_array($dn) && isset($dn['dn'][0])) {
					$dn = $dn['dn'][0];
				}
				return $this->getGroupsByMember($dn, $seen);
			};

			if (empty($dn)) {
				$dn = "";
			}

			$allGroups = $this->walkNestedGroups($dn, $fetcher, $groups);
		}
		$visibleGroups = $this->filterValidGroups($allGroups);
		return array_intersect_key($allGroups, $visibleGroups);
	}

	/**
	 * get a list of all users in a group
	 *
	 * @param string $gid
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return array with user ids
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function usersInGroup($gid, $search = '', $limit = -1, $offset = 0) {
		if (!$this->enabled) {
			return [];
		}
		if (!$this->groupExists($gid)) {
			return [];
		}
		$search = $this->access->escapeFilterPart($search, true);
		$cacheKey = 'usersInGroup-' . $gid . '-' . $search . '-' . $limit . '-' . $offset;
		// check for cache of the exact query
		$groupUsers = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($groupUsers)) {
			return $groupUsers;
		}

		if ($limit === -1) {
			$limit = null;
		}
		// check for cache of the query without limit and offset
		$groupUsers = $this->access->connection->getFromCache('usersInGroup-' . $gid . '-' . $search);
		if (!is_null($groupUsers)) {
			$groupUsers = array_slice($groupUsers, $offset, $limit);
			$this->access->connection->writeToCache($cacheKey, $groupUsers);
			return $groupUsers;
		}

		$groupDN = $this->access->groupname2dn($gid);
		if (!$groupDN) {
			// group couldn't be found, return empty resultset
			$this->access->connection->writeToCache($cacheKey, []);
			return [];
		}

		$primaryUsers = $this->getUsersInPrimaryGroup($groupDN, $search, $limit, $offset);
		$posixGroupUsers = $this->getUsersInGidNumber($groupDN, $search, $limit, $offset);
		$members = $this->_groupMembers($groupDN);
		if (!$members && empty($posixGroupUsers) && empty($primaryUsers)) {
			//in case users could not be retrieved, return empty result set
			$this->access->connection->writeToCache($cacheKey, []);
			return [];
		}

		$groupUsers = [];
		$attrs = $this->access->userManager->getAttributes(true);
		foreach ($members as $member) {
			switch ($this->ldapGroupMemberAssocAttr) {
				/** @noinspection PhpMissingBreakStatementInspection */
				case 'zimbramailforwardingaddress':
					//we get email addresses and need to convert them to uids
					$parts = explode('@', $member);
					$member = $parts[0];
					//no break needed because we just needed to remove the email part and now we have uids
				case 'memberuid':
					//we got uids, need to get their DNs to 'translate' them to user names
					$filter = $this->access->combineFilterWithAnd([
						str_replace('%uid', trim($member), $this->access->connection->ldapLoginFilter),
						$this->access->combineFilterWithAnd([
							$this->access->getFilterPartForUserSearch($search),
							$this->access->connection->ldapUserFilter
						])
					]);
					$ldap_users = $this->access->fetchListOfUsers($filter, $attrs, 1);
					if (empty($ldap_users)) {
						break;
					}
					$groupUsers[] = $this->access->dn2username($ldap_users[0]['dn'][0]);
					break;
				default:
					//we got DNs, check if we need to filter by search or we can give back all of them
					$uid = $this->access->dn2username($member);
					if (!$uid) {
						break;
					}

					$cacheKey = 'userExistsOnLDAP' . $uid;
					$userExists = $this->access->connection->getFromCache($cacheKey);
					if ($userExists === false) {
						break;
					}
					if ($userExists === null || $search !== '') {
						if (!$this->access->readAttribute($member,
							$this->access->connection->ldapUserDisplayName,
							$this->access->combineFilterWithAnd([
								$this->access->getFilterPartForUserSearch($search),
								$this->access->connection->ldapUserFilter
							]))) {
							if ($search === '') {
								$this->access->connection->writeToCache($cacheKey, false);
							}
							break;
						}
						$this->access->connection->writeToCache($cacheKey, true);
					}
					$groupUsers[] = $uid;
					break;
			}
		}

		$groupUsers = array_unique(array_merge($groupUsers, $primaryUsers, $posixGroupUsers));
		natsort($groupUsers);
		$this->access->connection->writeToCache('usersInGroup-' . $gid . '-' . $search, $groupUsers);
		$groupUsers = array_slice($groupUsers, $offset, $limit);

		$this->access->connection->writeToCache($cacheKey, $groupUsers);

		return $groupUsers;
	}

	/**
	 * returns the number of users in a group, who match the search term
	 *
	 * @param string $gid the internal group name
	 * @param string $search optional, a search string
	 * @return int|bool
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function countUsersInGroup($gid, $search = '') {
		if ($this->groupPluginManager->implementsActions(GroupInterface::COUNT_USERS)) {
			return $this->groupPluginManager->countUsersInGroup($gid, $search);
		}

		$cacheKey = 'countUsersInGroup-' . $gid . '-' . $search;
		if (!$this->enabled || !$this->groupExists($gid)) {
			return false;
		}
		$groupUsers = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($groupUsers)) {
			return $groupUsers;
		}

		$groupDN = $this->access->groupname2dn($gid);
		if (!$groupDN) {
			// group couldn't be found, return empty result set
			$this->access->connection->writeToCache($cacheKey, false);
			return false;
		}

		$members = $this->_groupMembers($groupDN);
		$primaryUserCount = $this->countUsersInPrimaryGroup($groupDN, '');
		if (!$members && $primaryUserCount === 0) {
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
			($this->ldapGroupMemberAssocAttr === 'memberuid' ||
				$this->ldapGroupMemberAssocAttr === 'zimbramailforwardingaddress');

		//we need to apply the search filter
		//alternatives that need to be checked:
		//a) get all users by search filter and array_intersect them
		//b) a, but only when less than 1k 10k ?k users like it is
		//c) put all DNs|uids in a LDAP filter, combine with the search string
		//   and let it count.
		//For now this is not important, because the only use of this method
		//does not supply a search string
		$groupUsers = [];
		foreach ($members as $member) {
			if ($isMemberUid) {
				if ($this->ldapGroupMemberAssocAttr === 'zimbramailforwardingaddress') {
					//we get email addresses and need to convert them to uids
					$parts = explode('@', $member);
					$member = $parts[0];
				}
				//we got uids, need to get their DNs to 'translate' them to user names
				$filter = $this->access->combineFilterWithAnd([
					str_replace('%uid', $member, $this->access->connection->ldapLoginFilter),
					$this->access->getFilterPartForUserSearch($search)
				]);
				$ldap_users = $this->access->fetchListOfUsers($filter, ['dn'], 1);
				if (count($ldap_users) < 1) {
					continue;
				}
				$groupUsers[] = $this->access->dn2username($ldap_users[0]);
			} else {
				//we need to apply the search filter now
				if (!$this->access->readAttribute($member,
					$this->access->connection->ldapUserDisplayName,
					$this->access->getFilterPartForUserSearch($search))) {
					continue;
				}
				// dn2username will also check if the users belong to the allowed base
				if ($ncGroupId = $this->access->dn2username($member)) {
					$groupUsers[] = $ncGroupId;
				}
			}
		}

		//and get users that have the group as primary
		$primaryUsers = $this->countUsersInPrimaryGroup($groupDN, $search);

		return count($groupUsers) + $primaryUsers;
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
	 * @throws Exception
	 */
	public function getGroups($search = '', $limit = -1, $offset = 0) {
		if (!$this->enabled) {
			return [];
		}
		$search = $this->access->escapeFilterPart($search, true);
		$cacheKey = 'getGroups-' . $search . '-' . $limit . '-' . $offset;

		//Check cache before driving unnecessary searches
		$ldap_groups = $this->access->connection->getFromCache($cacheKey);
		if (!is_null($ldap_groups)) {
			return $ldap_groups;
		}

		// if we'd pass -1 to LDAP search, we'd end up in a Protocol
		// error. With a limit of 0, we get 0 results. So we pass null.
		if ($limit <= 0) {
			$limit = null;
		}
		$filter = $this->access->combineFilterWithAnd([
			$this->access->connection->ldapGroupFilter,
			$this->access->getFilterPartForGroupSearch($search)
		]);
		$ldap_groups = $this->access->fetchListOfGroups($filter,
			[$this->access->connection->ldapGroupDisplayName, 'dn'],
			$limit,
			$offset);
		$ldap_groups = $this->access->nextcloudGroupNames($ldap_groups);

		$this->access->connection->writeToCache($cacheKey, $ldap_groups);
		return $ldap_groups;
	}

	/**
	 * check if a group exists
	 *
	 * @param string $gid
	 * @return bool
	 * @throws ServerNotAvailableException
	 */
	public function groupExists($gid) {
		$groupExists = $this->access->connection->getFromCache('groupExists' . $gid);
		if (!is_null($groupExists)) {
			return (bool)$groupExists;
		}

		//getting dn, if false the group does not exist. If dn, it may be mapped
		//only, requires more checking.
		$dn = $this->access->groupname2dn($gid);
		if (!$dn) {
			$this->access->connection->writeToCache('groupExists' . $gid, false);
			return false;
		}

		if (!$this->access->isDNPartOfBase($dn, $this->access->connection->ldapBaseGroups)) {
			$this->access->connection->writeToCache('groupExists' . $gid, false);
			return false;
		}

		//if group really still exists, we will be able to read its objectClass
		if (!is_array($this->access->readAttribute($dn, '', $this->access->connection->ldapGroupFilter))) {
			$this->access->connection->writeToCache('groupExists' . $gid, false);
			return false;
		}

		$this->access->connection->writeToCache('groupExists' . $gid, true);
		return true;
	}

	/**
	 * @throws ServerNotAvailableException
	 * @throws Exception
	 */
	protected function filterValidGroups(array $listOfGroups): array {
		$validGroupDNs = [];
		foreach ($listOfGroups as $key => $item) {
			$dn = is_string($item) ? $item : $item['dn'][0];
			$gid = $this->access->dn2groupname($dn);
			if (!$gid) {
				continue;
			}
			if ($this->groupExists($gid)) {
				$validGroupDNs[$key] = $item;
			}
		}
		return $validGroupDNs;
	}

	/**
	 * Check if backend implements actions
	 *
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
	 *
	 * @return Access instance of Access for LDAP interaction
	 */
	public function getLDAPAccess($gid) {
		return $this->access;
	}

	/**
	 * create a group
	 *
	 * @param string $gid
	 * @return bool
	 * @throws Exception
	 * @throws ServerNotAvailableException
	 */
	public function createGroup($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::CREATE_GROUP)) {
			if ($dn = $this->groupPluginManager->createGroup($gid)) {
				//updates group mapping
				$uuid = $this->access->getUUID($dn, false);
				if (is_string($uuid)) {
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
		throw new Exception('Could not create group in LDAP backend.');
	}

	/**
	 * delete a group
	 *
	 * @param string $gid gid of the group to delete
	 * @return bool
	 * @throws Exception
	 */
	public function deleteGroup($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::DELETE_GROUP)) {
			if ($ret = $this->groupPluginManager->deleteGroup($gid)) {
				#delete group in nextcloud internal db
				$this->access->getGroupMapper()->unmap($gid);
				$this->access->connection->writeToCache("groupExists" . $gid, false);
			}
			return $ret;
		}
		throw new Exception('Could not delete group in LDAP backend.');
	}

	/**
	 * Add a user to a group
	 *
	 * @param string $uid Name of the user to add to group
	 * @param string $gid Name of the group in which add the user
	 * @return bool
	 * @throws Exception
	 */
	public function addToGroup($uid, $gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::ADD_TO_GROUP)) {
			if ($ret = $this->groupPluginManager->addToGroup($uid, $gid)) {
				$this->access->connection->clearCache();
				unset($this->cachedGroupMembers[$gid]);
			}
			return $ret;
		}
		throw new Exception('Could not add user to group in LDAP backend.');
	}

	/**
	 * Removes a user from a group
	 *
	 * @param string $uid Name of the user to remove from group
	 * @param string $gid Name of the group from which remove the user
	 * @return bool
	 * @throws Exception
	 */
	public function removeFromGroup($uid, $gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::REMOVE_FROM_GROUP)) {
			if ($ret = $this->groupPluginManager->removeFromGroup($uid, $gid)) {
				$this->access->connection->clearCache();
				unset($this->cachedGroupMembers[$gid]);
			}
			return $ret;
		}
		throw new Exception('Could not remove user from group in LDAP backend.');
	}

	/**
	 * Gets group details
	 *
	 * @param string $gid Name of the group
	 * @return array|false
	 * @throws Exception
	 */
	public function getGroupDetails($gid) {
		if ($this->groupPluginManager->implementsActions(GroupInterface::GROUP_DETAILS)) {
			return $this->groupPluginManager->getGroupDetails($gid);
		}
		throw new Exception('Could not get group details in LDAP backend.');
	}

	/**
	 * Return LDAP connection resource from a cloned connection.
	 * The cloned connection needs to be closed manually.
	 * of the current access.
	 *
	 * @param string $gid
	 * @return resource of the LDAP connection
	 * @throws ServerNotAvailableException
	 */
	public function getNewLDAPConnection($gid) {
		$connection = clone $this->access->getConnection();
		return $connection->getConnectionResource();
	}

	/**
	 * @throws ServerNotAvailableException
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
