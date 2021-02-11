<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 * @copyright 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobia De Koninck <tobia@ledfan.be>
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

namespace OC\Contacts\ContactsMenu;

use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\IManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class ContactsStore implements IContactsStore {

	/** @var IManager */
	private $contactsManager;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/**
	 * @param IManager $contactsManager
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IManager $contactsManager,
								IConfig $config,
								IUserManager $userManager,
								IGroupManager $groupManager) {
		$this->contactsManager = $contactsManager;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * @param IUser $user
	 * @param string|null $filter
	 * @return IEntry[]
	 */
	public function getContacts(IUser $user, $filter, ?int $limit = null, ?int $offset = null) {
		$options = [];
		if ($limit !== null) {
			$options['limit'] = $limit;
		}
		if ($offset !== null) {
			$options['offset'] = $offset;
		}

		$allContacts = $this->contactsManager->search(
			$filter ?: '',
			[
				'FN',
				'EMAIL'
			],
			$options
		);

		$entries = array_map(function (array $contact) {
			return $this->contactArrayToEntry($contact);
		}, $allContacts);
		return $this->filterContacts(
			$user,
			$entries,
			$filter
		);
	}

	/**
	 * Filters the contacts. Applies 3 filters:
	 *  1. filter the current user
	 *  2. if the `shareapi_allow_share_dialog_user_enumeration` config option is
	 * enabled it will filter all local users
	 *  3. if the `shareapi_exclude_groups` config option is enabled and the
	 * current user is in an excluded group it will filter all local users.
	 *  4. if the `shareapi_only_share_with_group_members` config option is
	 * enabled it will filter all users which doens't have a common group
	 * with the current user.
	 *
	 * @param IUser $self
	 * @param Entry[] $entries
	 * @param string $filter
	 * @return Entry[] the filtered contacts
	 */
	private function filterContacts(IUser $self,
									array $entries,
									$filter) {
		$disallowEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') !== 'yes';
		$restrictEnumeration = $this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
		$excludedGroups = $this->config->getAppValue('core', 'shareapi_exclude_groups', 'no') === 'yes';

		// whether to filter out local users
		$skipLocal = false;
		// whether to filter out all users which doesn't have the same group as the current user
		$ownGroupsOnly = $this->config->getAppValue('core', 'shareapi_only_share_with_group_members', 'no') === 'yes' || $restrictEnumeration;

		$selfGroups = $this->groupManager->getUserGroupIds($self);

		if ($excludedGroups) {
			$excludedGroups = $this->config->getAppValue('core', 'shareapi_exclude_groups_list', '');
			$decodedExcludeGroups = json_decode($excludedGroups, true);
			$excludeGroupsList = ($decodedExcludeGroups !== null) ? $decodedExcludeGroups : [];

			if (count(array_intersect($excludeGroupsList, $selfGroups)) !== 0) {
				// a group of the current user is excluded -> filter all local users
				$skipLocal = true;
			}
		}

		$selfUID = $self->getUID();

		return array_values(array_filter($entries, function (IEntry $entry) use ($self, $skipLocal, $ownGroupsOnly, $selfGroups, $selfUID, $disallowEnumeration, $filter) {
			if ($skipLocal && $entry->getProperty('isLocalSystemBook') === true) {
				return false;
			}

			// Prevent enumerating local users
			if ($disallowEnumeration && $entry->getProperty('isLocalSystemBook')) {
				$filterUser = true;

				$mailAddresses = $entry->getEMailAddresses();
				foreach ($mailAddresses as $mailAddress) {
					if ($mailAddress === $filter) {
						$filterUser = false;
						break;
					}
				}

				if ($entry->getProperty('UID') && $entry->getProperty('UID') === $filter) {
					$filterUser = false;
				}

				if ($filterUser) {
					return false;
				}
			}

			if ($ownGroupsOnly && $entry->getProperty('isLocalSystemBook') === true) {
				$uid = $this->userManager->get($entry->getProperty('UID'));

				if ($uid === null) {
					return false;
				}

				$contactGroups = $this->groupManager->getUserGroupIds($uid);
				if (count(array_intersect($contactGroups, $selfGroups)) === 0) {
					// no groups in common, so shouldn't see the contact
					return false;
				}
			}

			return $entry->getProperty('UID') !== $selfUID;
		}));
	}

	/**
	 * @param IUser $user
	 * @param integer $shareType
	 * @param string $shareWith
	 * @return IEntry|null
	 */
	public function findOne(IUser $user, $shareType, $shareWith) {
		switch ($shareType) {
			case 0:
			case 6:
				$filter = ['UID'];
				break;
			case 4:
				$filter = ['EMAIL'];
				break;
			default:
				return null;
		}

		$userId = $user->getUID();
		$allContacts = $this->contactsManager->search($shareWith, $filter);
		$contacts = array_filter($allContacts, function ($contact) use ($userId) {
			return $contact['UID'] !== $userId;
		});
		$match = null;

		foreach ($contacts as $contact) {
			if ($shareType === 4 && isset($contact['EMAIL'])) {
				if (in_array($shareWith, $contact['EMAIL'])) {
					$match = $contact;
					break;
				}
			}
			if ($shareType === 0 || $shareType === 6) {
				$isLocal = $contact['isLocalSystemBook'] ?? false;
				if ($contact['UID'] === $shareWith && $isLocal === true) {
					$match = $contact;
					break;
				}
			}
		}

		if ($match) {
			$match = $this->filterContacts($user, [$this->contactArrayToEntry($match)], $shareWith);
			if (count($match) === 1) {
				$match = $match[0];
			} else {
				$match = null;
			}
		}

		return $match;
	}

	/**
	 * @param array $contact
	 * @return Entry
	 */
	private function contactArrayToEntry(array $contact) {
		$entry = new Entry();

		if (isset($contact['id'])) {
			$entry->setId($contact['id']);
		}

		if (isset($contact['FN'])) {
			$entry->setFullName($contact['FN']);
		}

		$avatarPrefix = "VALUE=uri:";
		if (isset($contact['PHOTO']) && strpos($contact['PHOTO'], $avatarPrefix) === 0) {
			$entry->setAvatar(substr($contact['PHOTO'], strlen($avatarPrefix)));
		}

		if (isset($contact['EMAIL'])) {
			foreach ($contact['EMAIL'] as $email) {
				$entry->addEMailAddress($email);
			}
		}

		// Attach all other properties to the entry too because some
		// providers might make use of it.
		$entry->setProperties($contact);

		return $entry;
	}
}
