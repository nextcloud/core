<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sebastian Döll <sebastian.doell@libasys.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author Volkan Gezer <volkangezer@gmail.com>
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

namespace OC\Share;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\ILogger;
use OCP\Share\IShare;

/**
 * This class provides the ability for apps to share their content between users.
 * Apps must create a backend class that implements OCP\Share_Backend and register it with this class.
 *
 * It provides the following hooks:
 *  - post_shared
 */
class Share extends Constants {

	/** CRUDS permissions (Create, Read, Update, Delete, Share) using a bitmask
	 * Construct permissions for share() and setPermissions with Or (|) e.g.
	 * Give user read and update permissions: PERMISSION_READ | PERMISSION_UPDATE
	 *
	 * Check if permission is granted with And (&) e.g. Check if delete is
	 * granted: if ($permissions & PERMISSION_DELETE)
	 *
	 * Remove permissions with And (&) and Not (~) e.g. Remove the update
	 * permission: $permissions &= ~PERMISSION_UPDATE
	 *
	 * Apps are required to handle permissions on their own, this class only
	 * stores and manages the permissions of shares
	 * @see lib/public/constants.php
	 */

	/**
	 * Register a sharing backend class that implements OCP\Share_Backend for an item type
	 * @param string $itemType Item type
	 * @param string $class Backend class
	 * @param string $collectionOf (optional) Depends on item type
	 * @param array $supportedFileExtensions (optional) List of supported file extensions if this item type depends on files
	 * @return boolean true if backend is registered or false if error
	 */
	public static function registerBackend($itemType, $class, $collectionOf = null, $supportedFileExtensions = null) {
		if (\OC::$server->getConfig()->getAppValue('core', 'shareapi_enabled', 'yes') == 'yes') {
			if (!isset(self::$backendTypes[$itemType])) {
				self::$backendTypes[$itemType] = [
					'class' => $class,
					'collectionOf' => $collectionOf,
					'supportedFileExtensions' => $supportedFileExtensions
				];
				return true;
			}
			\OCP\Util::writeLog('OCP\Share',
				'Sharing backend '.$class.' not registered, '.self::$backendTypes[$itemType]['class']
				.' is already registered for '.$itemType,
				ILogger::WARN);
		}
		return false;
	}

	/**
	 * Unregister a sharing backend
	 * @param string $itemType Item type
	 * @internal FOR TESTING ONLY
	 */
	public static function unregisterBackend($itemType) {
		unset(self::$backendTypes[$itemType]);
	}

	/**
	 * Get the items of item type shared with the current user
	 * @param string $itemType
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters (optional)
	 * @param int $limit Number of items to return (optional) Returns all by default
	 * @param boolean $includeCollections (optional)
	 * @return mixed Return depends on format
	 * @deprecated TESTS ONLY - this methods is only used by tests
	 * called like this:
	 * \OC\Share\Share::getItemsSharedWith('folder'); (apps/files_sharing/tests/UpdaterTest.php)
	 */
	public static function getItemsSharedWith() {
		return self::getItems('folder', null, self::$shareTypeUserAndGroups, \OC_User::getUser());
	}

	/**
	 * Get the items of item type shared with a user
	 * @param string $itemType
	 * @param string $user id for which user we want the shares
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters (optional)
	 * @param int $limit Number of items to return (optional) Returns all by default
	 * @param boolean $includeCollections (optional)
	 * @return mixed Return depends on format
	 * @deprecated TESTS ONLY - this methods is only used by tests
	 * called like this:
	 * \OC\Share\Share::getItemsSharedWithUser('test', $shareWith); (tests/lib/Share/Backend.php)
	 */
	public static function getItemsSharedWithUser($itemType, $user) {
		return self::getItems('test', null, self::$shareTypeUserAndGroups, $user);
	}

	/**
	 * Get the item of item type shared with a given user by source
	 * @param string $itemType
	 * @param string $itemSource
	 * @param string $user User to whom the item was shared
	 * @param string $owner Owner of the share
	 * @param int $shareType only look for a specific share type
	 * @return array Return list of items with file_target, permissions and expiration
	 */
	public static function getItemSharedWithUser($itemType, $itemSource, $user, $owner = null, $shareType = null) {
		$shares = [];
		$fileDependent = false;

		$where = 'WHERE';
		$fileDependentWhere = '';
		if ($itemType === 'file' || $itemType === 'folder') {
			$fileDependent = true;
			$column = 'file_source';
			$fileDependentWhere = 'INNER JOIN `*PREFIX*filecache` ON `file_source` = `*PREFIX*filecache`.`fileid` ';
			$fileDependentWhere .= 'INNER JOIN `*PREFIX*storages` ON `numeric_id` = `*PREFIX*filecache`.`storage` ';
		} else {
			$column = 'item_source';
		}

		$select = self::createSelectStatement(self::FORMAT_NONE, $fileDependent);

		$where .= ' `' . $column . '` = ? AND `item_type` = ? ';
		$arguments = [$itemSource, $itemType];
		// for link shares $user === null
		if ($user !== null) {
			$where .= ' AND `share_with` = ? ';
			$arguments[] = $user;
		}

		if ($shareType !== null) {
			$where .= ' AND `share_type` = ? ';
			$arguments[] = $shareType;
		}

		if ($owner !== null) {
			$where .= ' AND `uid_owner` = ? ';
			$arguments[] = $owner;
		}

		$query = \OC_DB::prepare('SELECT ' . $select . ' FROM `*PREFIX*share` '. $fileDependentWhere . $where);

		$result = \OC_DB::executeAudited($query, $arguments);

		while ($row = $result->fetchRow()) {
			if ($fileDependent && !self::isFileReachable($row['path'], $row['storage_id'])) {
				continue;
			}
			if ($fileDependent && (int)$row['file_parent'] === -1) {
				// if it is a mount point we need to get the path from the mount manager
				$mountManager = \OC\Files\Filesystem::getMountManager();
				$mountPoint = $mountManager->findByStorageId($row['storage_id']);
				if (!empty($mountPoint)) {
					$path = $mountPoint[0]->getMountPoint();
					$path = trim($path, '/');
					$path = substr($path, strlen($owner) + 1); //normalize path to 'files/foo.txt`
					$row['path'] = $path;
				} else {
					\OC::$server->getLogger()->warning(
						'Could not resolve mount point for ' . $row['storage_id'],
						['app' => 'OCP\Share']
					);
				}
			}
			$shares[] = $row;
		}
		$result->closeCursor();

		//if didn't found a result than let's look for a group share.
		if (empty($shares) && $user !== null) {
			$userObject = \OC::$server->getUserManager()->get($user);
			$groups = [];
			if ($userObject) {
				$groups = \OC::$server->getGroupManager()->getUserGroupIds($userObject);
			}

			if (!empty($groups)) {
				$where = $fileDependentWhere . ' WHERE `' . $column . '` = ? AND `item_type` = ? AND `share_with` in (?)';
				$arguments = [$itemSource, $itemType, $groups];
				$types = [null, null, IQueryBuilder::PARAM_STR_ARRAY];

				if ($owner !== null) {
					$where .= ' AND `uid_owner` = ?';
					$arguments[] = $owner;
					$types[] = null;
				}

				// TODO: inject connection, hopefully one day in the future when this
				// class isn't static anymore...
				$conn = \OC::$server->getDatabaseConnection();
				$result = $conn->executeQuery(
					'SELECT ' . $select . ' FROM `*PREFIX*share` ' . $where,
					$arguments,
					$types
				);

				while ($row = $result->fetch()) {
					$shares[] = $row;
				}
			}
		}

		return $shares;
	}

	/**
	 * Get the shared item of item type owned by the current user
	 * @param string $itemType
	 * @param string $itemSource
	 * @param int $format (optional) Format type must be defined by the backend
	 * @param mixed $parameters
	 * @param boolean $includeCollections
	 * @return mixed Return depends on format
	 *
	 * Refactoring notes:
	 *   * defacto $parameters and $format is always the default and therefore is removed in the subsequent call
	 */
	public static function getItemShared($itemType, $itemSource, $format = self::FORMAT_NONE,
										 $parameters = null, $includeCollections = false) {
		return self::getItems($itemType, $itemSource, null, null, \OC_User::getUser(), self::FORMAT_NONE,
			null, -1, $includeCollections);
	}

	/**
	 * Get the backend class for the specified item type
	 * @param string $itemType
	 * @throws \Exception
	 * @return \OCP\Share_Backend
	 */
	public static function getBackend($itemType) {
		$l = \OC::$server->getL10N('lib');
		if (isset(self::$backends[$itemType])) {
			return self::$backends[$itemType];
		} elseif (isset(self::$backendTypes[$itemType]['class'])) {
			$class = self::$backendTypes[$itemType]['class'];
			if (class_exists($class)) {
				self::$backends[$itemType] = new $class;
				if (!(self::$backends[$itemType] instanceof \OCP\Share_Backend)) {
					$message = 'Sharing backend %s must implement the interface OCP\Share_Backend';
					$message_t = $l->t('Sharing backend %s must implement the interface OCP\Share_Backend', [$class]);
					\OCP\Util::writeLog('OCP\Share', sprintf($message, $class), ILogger::ERROR);
					throw new \Exception($message_t);
				}
				return self::$backends[$itemType];
			} else {
				$message = 'Sharing backend %s not found';
				$message_t = $l->t('Sharing backend %s not found', [$class]);
				\OCP\Util::writeLog('OCP\Share', sprintf($message, $class), ILogger::ERROR);
				throw new \Exception($message_t);
			}
		}
		$message = 'Sharing backend for %s not found';
		$message_t = $l->t('Sharing backend for %s not found', [$itemType]);
		\OCP\Util::writeLog('OCP\Share', sprintf($message, $itemType), ILogger::ERROR);
		throw new \Exception($message_t);
	}

	/**
	 * Check if resharing is allowed
	 * @return boolean true if allowed or false
	 *
	 * Resharing is allowed by default if not configured
	 */
	public static function isResharingAllowed() {
		if (!isset(self::$isResharingAllowed)) {
			if (\OC::$server->getConfig()->getAppValue('core', 'shareapi_allow_resharing', 'yes') == 'yes') {
				self::$isResharingAllowed = true;
			} else {
				self::$isResharingAllowed = false;
			}
		}
		return self::$isResharingAllowed;
	}

	/**
	 * Get a list of collection item types for the specified item type
	 * @param string $itemType
	 * @return array
	 */
	private static function getCollectionItemTypes($itemType) {
		$collectionTypes = [$itemType];
		foreach (self::$backendTypes as $type => $backend) {
			if (in_array($backend['collectionOf'], $collectionTypes)) {
				$collectionTypes[] = $type;
			}
		}
		// TODO Add option for collections to be collection of themselves, only 'folder' does it now...
		if (isset(self::$backendTypes[$itemType]) && (!self::getBackend($itemType) instanceof \OCP\Share_Backend_Collection || $itemType != 'folder')) {
			unset($collectionTypes[0]);
		}
		// Return array if collections were found or the item type is a
		// collection itself - collections can be inside collections
		if (count($collectionTypes) > 0) {
			return $collectionTypes;
		}
		return false;
	}

	/**
	 * Get shared items from the database
	 * @param string $itemType
	 * @param string $item Item source or target (optional)
	 * @param int $shareType SHARE_TYPE_USER, SHARE_TYPE_GROUP, SHARE_TYPE_LINK, $shareTypeUserAndGroups, or $shareTypeGroupUserUnique
	 * @param string $shareWith User or group the item is being shared with
	 * @param string $uidOwner User that is the owner of shared items (optional)
	 * @param int $format Format to convert items to with formatItems() (optional)
	 * @param mixed $parameters to pass to formatItems() (optional)
	 * @param int $limit Number of items to return, -1 to return all matches (optional)
	 * @param boolean $includeCollections Include collection item types (optional)
	 * @param boolean $itemShareWithBySource (optional)
	 * @param boolean $checkExpireDate
	 * @return array
	 *
	 * See public functions getItem(s)... for parameter usage
	 *
	 * Refactoring notes:
	 *   * defacto $limit, $itemsShareWithBySource, $checkExpireDate, $parameters and $format is always the default and therefore is removed in the subsequent call
	 */
	public static function getItems($itemType, $item = null, $shareType = null, $shareWith = null,
									$uidOwner = null, $format = self::FORMAT_NONE, $parameters = null, $limit = -1,
									$includeCollections = false, $itemShareWithBySource = false, $checkExpireDate = true) {
		if (\OC::$server->getConfig()->getAppValue('core', 'shareapi_enabled', 'yes') != 'yes') {
			return [];
		}
		$backend = self::getBackend($itemType);
		$collectionTypes = false;
		// Get filesystem root to add it to the file target and remove from the
		// file source, match file_source with the file cache
		if ($itemType == 'file' || $itemType == 'folder') {
			if (!is_null($uidOwner)) {
				$root = \OC\Files\Filesystem::getRoot();
			} else {
				$root = '';
			}
			$where = 'INNER JOIN `*PREFIX*filecache` ON `file_source` = `*PREFIX*filecache`.`fileid` ';
			if (!isset($item)) {
				$where .= ' AND `file_target` IS NOT NULL ';
			}
			$where .= 'INNER JOIN `*PREFIX*storages` ON `numeric_id` = `*PREFIX*filecache`.`storage` ';
			$fileDependent = true;
			$queryArgs = [];
		} else {
			$fileDependent = false;
			$root = '';
			$collectionTypes = self::getCollectionItemTypes($itemType);
			if ($includeCollections && !isset($item) && $collectionTypes) {
				// If includeCollections is true, find collections of this item type, e.g. a music album contains songs
				if (!in_array($itemType, $collectionTypes)) {
					$itemTypes = array_merge([$itemType], $collectionTypes);
				} else {
					$itemTypes = $collectionTypes;
				}
				$placeholders = implode(',', array_fill(0, count($itemTypes), '?'));
				$where = ' WHERE `item_type` IN ('.$placeholders.'))';
				$queryArgs = $itemTypes;
			} else {
				$where = ' WHERE `item_type` = ?';
				$queryArgs = [$itemType];
			}
		}
		if (\OC::$server->getConfig()->getAppValue('core', 'shareapi_allow_links', 'yes') !== 'yes') {
			$where .= ' AND `share_type` != ?';
			$queryArgs[] = IShare::TYPE_LINK;
		}
		if (isset($shareType)) {
			// Include all user and group items
			if ($shareType == self::$shareTypeUserAndGroups && isset($shareWith)) {
				$where .= ' AND ((`share_type` in (?, ?) AND `share_with` = ?) ';
				$queryArgs[] = IShare::TYPE_USER;
				$queryArgs[] = self::$shareTypeGroupUserUnique;
				$queryArgs[] = $shareWith;

				$user = \OC::$server->getUserManager()->get($shareWith);
				$groups = [];
				if ($user) {
					$groups = \OC::$server->getGroupManager()->getUserGroupIds($user);
				}
				if (!empty($groups)) {
					$placeholders = implode(',', array_fill(0, count($groups), '?'));
					$where .= ' OR (`share_type` = ? AND `share_with` IN ('.$placeholders.')) ';
					$queryArgs[] = IShare::TYPE_GROUP;
					$queryArgs = array_merge($queryArgs, $groups);
				}
				$where .= ')';
				// Don't include own group shares
				$where .= ' AND `uid_owner` != ?';
				$queryArgs[] = $shareWith;
			} else {
				$where .= ' AND `share_type` = ?';
				$queryArgs[] = $shareType;
				if (isset($shareWith)) {
					$where .= ' AND `share_with` = ?';
					$queryArgs[] = $shareWith;
				}
			}
		}
		if (isset($uidOwner)) {
			$where .= ' AND `uid_owner` = ?';
			$queryArgs[] = $uidOwner;
			if (!isset($shareType)) {
				// Prevent unique user targets for group shares from being selected
				$where .= ' AND `share_type` != ?';
				$queryArgs[] = self::$shareTypeGroupUserUnique;
			}
			if ($fileDependent) {
				$column = 'file_source';
			} else {
				$column = 'item_source';
			}
		} else {
			if ($fileDependent) {
				$column = 'file_target';
			} else {
				$column = 'item_target';
			}
		}
		if (isset($item)) {
			$collectionTypes = self::getCollectionItemTypes($itemType);
			if ($includeCollections && $collectionTypes && !in_array('folder', $collectionTypes)) {
				$where .= ' AND (';
			} else {
				$where .= ' AND';
			}
			// If looking for own shared items, check item_source else check item_target
			if (isset($uidOwner)) {
				// If item type is a file, file source needs to be checked in case the item was converted
				if ($fileDependent) {
					$where .= ' `file_source` = ?';
					$column = 'file_source';
				} else {
					$where .= ' `item_source` = ?';
					$column = 'item_source';
				}
			} else {
				if ($fileDependent) {
					$where .= ' `file_target` = ?';
					$item = \OC\Files\Filesystem::normalizePath($item);
				} else {
					$where .= ' `item_target` = ?';
				}
			}
			$queryArgs[] = $item;
			if ($includeCollections && $collectionTypes && !in_array('folder', $collectionTypes)) {
				$placeholders = implode(',', array_fill(0, count($collectionTypes), '?'));
				$where .= ' OR `item_type` IN ('.$placeholders.'))';
				$queryArgs = array_merge($queryArgs, $collectionTypes);
			}
		}

		$where .= ' ORDER BY `*PREFIX*share`.`id` ASC';

		$queryLimit = null;
		$select = self::createSelectStatement(self::FORMAT_NONE, $fileDependent, $uidOwner);
		$root = strlen($root);
		$query = \OC_DB::prepare('SELECT '.$select.' FROM `*PREFIX*share` '.$where, $queryLimit);
		$result = $query->execute($queryArgs);
		if ($result === false) {
			\OCP\Util::writeLog('OCP\Share',
				\OC_DB::getErrorMessage() . ', select=' . $select . ' where=',
				ILogger::ERROR);
		}
		$items = [];
		$targets = [];
		$switchedItems = [];
		$mounts = [];
		while ($row = $result->fetchRow()) {
			self::transformDBResults($row);
			// Filter out duplicate group shares for users with unique targets
			if ($fileDependent && !self::isFileReachable($row['path'], $row['storage_id'])) {
				continue;
			}
			if ($row['share_type'] == self::$shareTypeGroupUserUnique && isset($items[$row['parent']])) {
				$row['share_type'] = IShare::TYPE_GROUP;
				$row['unique_name'] = true; // remember that we use a unique name for this user
				$row['share_with'] = $items[$row['parent']]['share_with'];
				// if the group share was unshared from the user we keep the permission, otherwise
				// we take the permission from the parent because this is always the up-to-date
				// permission for the group share
				if ($row['permissions'] > 0) {
					$row['permissions'] = $items[$row['parent']]['permissions'];
				}
				// Remove the parent group share
				unset($items[$row['parent']]);
				if ($row['permissions'] == 0) {
					continue;
				}
			} elseif (!isset($uidOwner)) {
				// Check if the same target already exists
				if (isset($targets[$row['id']])) {
					// Check if the same owner shared with the user twice
					// through a group and user share - this is allowed
					$id = $targets[$row['id']];
					if (isset($items[$id]) && $items[$id]['uid_owner'] == $row['uid_owner']) {
						// Switch to group share type to ensure resharing conditions aren't bypassed
						if ($items[$id]['share_type'] != IShare::TYPE_GROUP) {
							$items[$id]['share_type'] = IShare::TYPE_GROUP;
							$items[$id]['share_with'] = $row['share_with'];
						}
						// Switch ids if sharing permission is granted on only
						// one share to ensure correct parent is used if resharing
						if (~(int)$items[$id]['permissions'] & \OCP\Constants::PERMISSION_SHARE
							&& (int)$row['permissions'] & \OCP\Constants::PERMISSION_SHARE) {
							$items[$row['id']] = $items[$id];
							$switchedItems[$id] = $row['id'];
							unset($items[$id]);
							$id = $row['id'];
						}
						$items[$id]['permissions'] |= (int)$row['permissions'];
					}
					continue;
				} elseif (!empty($row['parent'])) {
					$targets[$row['parent']] = $row['id'];
				}
			}
			// Remove root from file source paths if retrieving own shared items
			if (isset($uidOwner) && isset($row['path'])) {
				if (isset($row['parent'])) {
					$query = \OC::$server->getDatabaseConnection()->getQueryBuilder();
					$query->select('file_target')
						->from('share')
						->where($query->expr()->eq('id', $query->createNamedParameter($row['parent'])));

					$parentResult = $query->execute();
					$parentRow = $parentResult->fetch();
					$parentResult->closeCursor();

					if ($parentRow === false) {
						\OCP\Util::writeLog('OCP\Share', 'Can\'t select parent: ' .
							\OC_DB::getErrorMessage() . ', select=' . $select . ' where=' . $where,
							ILogger::ERROR);
					} else {
						$tmpPath = $parentRow['file_target'];
						// find the right position where the row path continues from the target path
						$pos = strrpos($row['path'], $parentRow['file_target']);
						$subPath = substr($row['path'], $pos);
						$splitPath = explode('/', $subPath);
						foreach (array_slice($splitPath, 2) as $pathPart) {
							$tmpPath = $tmpPath . '/' . $pathPart;
						}
						$row['path'] = $tmpPath;
					}
				} else {
					if (!isset($mounts[$row['storage']])) {
						$mountPoints = \OC\Files\Filesystem::getMountByNumericId($row['storage']);
						if (is_array($mountPoints) && !empty($mountPoints)) {
							$mounts[$row['storage']] = current($mountPoints);
						}
					}
					if (!empty($mounts[$row['storage']])) {
						$path = $mounts[$row['storage']]->getMountPoint().$row['path'];
						$relPath = substr($path, $root); // path relative to data/user
						$row['path'] = rtrim($relPath, '/');
					}
				}
			}

			// Check if resharing is allowed, if not remove share permission
			if (isset($row['permissions']) && (!self::isResharingAllowed() | \OCP\Util::isSharingDisabledForUser())) {
				$row['permissions'] &= ~\OCP\Constants::PERMISSION_SHARE;
			}
			// Add display names to result
			$row['share_with_displayname'] = $row['share_with'];
			if (isset($row['share_with']) && $row['share_with'] != '' &&
				$row['share_type'] === IShare::TYPE_USER) {
				$shareWithUser = \OC::$server->getUserManager()->get($row['share_with']);
				$row['share_with_displayname'] = $shareWithUser === null ? $row['share_with'] : $shareWithUser->getDisplayName();
			} elseif (isset($row['share_with']) && $row['share_with'] != '' &&
				$row['share_type'] === IShare::TYPE_REMOTE) {
				$addressBookEntries = \OC::$server->getContactsManager()->search($row['share_with'], ['CLOUD']);
				foreach ($addressBookEntries as $entry) {
					foreach ($entry['CLOUD'] as $cloudID) {
						if ($cloudID === $row['share_with']) {
							$row['share_with_displayname'] = $entry['FN'];
						}
					}
				}
			}
			if (isset($row['uid_owner']) && $row['uid_owner'] != '') {
				$ownerUser = \OC::$server->getUserManager()->get($row['uid_owner']);
				$row['displayname_owner'] = $ownerUser === null ? $row['uid_owner'] : $ownerUser->getDisplayName();
			}

			if ($row['permissions'] > 0) {
				$items[$row['id']] = $row;
			}
		}

		// group items if we are looking for items shared with the current user
		if (isset($shareWith) && $shareWith === \OCP\User::getUser()) {
			$items = self::groupItems($items, $itemType);
		}

		if (!empty($items)) {
			$collectionItems = [];
			foreach ($items as &$row) {
				// Check if this is a collection of the requested item type
				if ($includeCollections && $collectionTypes && $row['item_type'] !== 'folder' && in_array($row['item_type'], $collectionTypes)) {
					if (($collectionBackend = self::getBackend($row['item_type']))
						&& $collectionBackend instanceof \OCP\Share_Backend_Collection) {
						// Collections can be inside collections, check if the item is a collection
						if (isset($item) && $row['item_type'] == $itemType && $row[$column] == $item) {
							$collectionItems[] = $row;
						} else {
							$collection = [];
							$collection['item_type'] = $row['item_type'];
							if ($row['item_type'] == 'file' || $row['item_type'] == 'folder') {
								$collection['path'] = basename($row['path']);
							}
							$row['collection'] = $collection;
							// Fetch all of the children sources
							$children = $collectionBackend->getChildren($row[$column]);
							foreach ($children as $child) {
								$childItem = $row;
								$childItem['item_type'] = $itemType;
								if ($row['item_type'] != 'file' && $row['item_type'] != 'folder') {
									$childItem['item_source'] = $child['source'];
									$childItem['item_target'] = $child['target'];
								}
								if ($backend instanceof \OCP\Share_Backend_File_Dependent) {
									if ($row['item_type'] == 'file' || $row['item_type'] == 'folder') {
										$childItem['file_source'] = $child['source'];
									} else { // TODO is this really needed if we already know that we use the file backend?
										$meta = \OC\Files\Filesystem::getFileInfo($child['file_path']);
										$childItem['file_source'] = $meta['fileid'];
									}
									$childItem['file_target'] =
										\OC\Files\Filesystem::normalizePath($child['file_path']);
								}
								if (isset($item)) {
									if ($childItem[$column] == $item) {
										$collectionItems[] = $childItem;
									}
								} else {
									$collectionItems[] = $childItem;
								}
							}
						}
					}
					// Remove collection item
					$toRemove = $row['id'];
					if (array_key_exists($toRemove, $switchedItems)) {
						$toRemove = $switchedItems[$toRemove];
					}
					unset($items[$toRemove]);
				} elseif ($includeCollections && $collectionTypes && in_array($row['item_type'], $collectionTypes)) {
					// FIXME: Thats a dirty hack to improve file sharing performance,
					// see github issue #10588 for more details
					// Need to find a solution which works for all back-ends
					$collectionBackend = self::getBackend($row['item_type']);
					$sharedParents = $collectionBackend->getParents($row['item_source']);
					foreach ($sharedParents as $parent) {
						$collectionItems[] = $parent;
					}
				}
			}
			if (!empty($collectionItems)) {
				$collectionItems = array_unique($collectionItems, SORT_REGULAR);
				$items = array_merge($items, $collectionItems);
			}

			// filter out invalid items, these can appear when subshare entries exist
			// for a group in which the requested user isn't a member any more
			$items = array_filter($items, function ($item) {
				return $item['share_type'] !== self::$shareTypeGroupUserUnique;
			});

			return self::formatResult($items, $column, $backend);
		} elseif ($includeCollections && $collectionTypes && in_array('folder', $collectionTypes)) {
			// FIXME: Thats a dirty hack to improve file sharing performance,
			// see github issue #10588 for more details
			// Need to find a solution which works for all back-ends
			$collectionItems = [];
			$collectionBackend = self::getBackend('folder');
			$sharedParents = $collectionBackend->getParents($item, $shareWith, $uidOwner);
			foreach ($sharedParents as $parent) {
				$collectionItems[] = $parent;
			}
			return self::formatResult($collectionItems, $column, $backend);
		}

		return [];
	}

	/**
	 * group items with link to the same source
	 *
	 * @param array $items
	 * @param string $itemType
	 * @return array of grouped items
	 */
	protected static function groupItems($items, $itemType) {
		$fileSharing = $itemType === 'file' || $itemType === 'folder';

		$result = [];

		foreach ($items as $item) {
			$grouped = false;
			foreach ($result as $key => $r) {
				// for file/folder shares we need to compare file_source, otherwise we compare item_source
				// only group shares if they already point to the same target, otherwise the file where shared
				// before grouping of shares was added. In this case we don't group them toi avoid confusions
				if (($fileSharing && $item['file_source'] === $r['file_source'] && $item['file_target'] === $r['file_target']) ||
					(!$fileSharing && $item['item_source'] === $r['item_source'] && $item['item_target'] === $r['item_target'])) {
					// add the first item to the list of grouped shares
					if (!isset($result[$key]['grouped'])) {
						$result[$key]['grouped'][] = $result[$key];
					}
					$result[$key]['permissions'] = (int) $item['permissions'] | (int) $r['permissions'];
					$result[$key]['grouped'][] = $item;
					$grouped = true;
					break;
				}
			}

			if (!$grouped) {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * construct select statement
	 * @param int $format
	 * @param boolean $fileDependent ist it a file/folder share or a generla share
	 * @param string $uidOwner
	 * @return string select statement
	 */
	private static function createSelectStatement($format, $fileDependent, $uidOwner = null) {
		$select = '*';
		if ($format == self::FORMAT_STATUSES) {
			if ($fileDependent) {
				$select = '`*PREFIX*share`.`id`, `*PREFIX*share`.`parent`, `share_type`, `path`, `storage`, '
					. '`share_with`, `uid_owner` , `file_source`, `stime`, `*PREFIX*share`.`permissions`, '
					. '`*PREFIX*storages`.`id` AS `storage_id`, `*PREFIX*filecache`.`parent` as `file_parent`, '
					. '`uid_initiator`';
			} else {
				$select = '`id`, `parent`, `share_type`, `share_with`, `uid_owner`, `item_source`, `stime`, `*PREFIX*share`.`permissions`';
			}
		} else {
			if (isset($uidOwner)) {
				if ($fileDependent) {
					$select = '`*PREFIX*share`.`id`, `item_type`, `item_source`, `*PREFIX*share`.`parent`,'
						. ' `share_type`, `share_with`, `file_source`, `file_target`, `path`, `*PREFIX*share`.`permissions`, `stime`,'
						. ' `expiration`, `token`, `storage`, `mail_send`, `uid_owner`, '
						. '`*PREFIX*storages`.`id` AS `storage_id`, `*PREFIX*filecache`.`parent` as `file_parent`';
				} else {
					$select = '`id`, `item_type`, `item_source`, `parent`, `share_type`, `share_with`, `*PREFIX*share`.`permissions`,'
						. ' `stime`, `file_source`, `expiration`, `token`, `mail_send`, `uid_owner`';
				}
			} else {
				if ($fileDependent) {
					if ($format == \OCA\Files_Sharing\ShareBackend\File::FORMAT_GET_FOLDER_CONTENTS || $format == \OCA\Files_Sharing\ShareBackend\File::FORMAT_FILE_APP_ROOT) {
						$select = '`*PREFIX*share`.`id`, `item_type`, `item_source`, `*PREFIX*share`.`parent`, `uid_owner`, '
							. '`share_type`, `share_with`, `file_source`, `path`, `file_target`, `stime`, '
							. '`*PREFIX*share`.`permissions`, `expiration`, `storage`, `*PREFIX*filecache`.`parent` as `file_parent`, '
							. '`name`, `mtime`, `mimetype`, `mimepart`, `size`, `encrypted`, `etag`, `mail_send`';
					} else {
						$select = '`*PREFIX*share`.`id`, `item_type`, `item_source`, `item_target`,'
							. '`*PREFIX*share`.`parent`, `share_type`, `share_with`, `uid_owner`,'
							. '`file_source`, `path`, `file_target`, `*PREFIX*share`.`permissions`,'
							. '`stime`, `expiration`, `token`, `storage`, `mail_send`,'
							. '`*PREFIX*storages`.`id` AS `storage_id`, `*PREFIX*filecache`.`parent` as `file_parent`';
					}
				}
			}
		}
		return $select;
	}


	/**
	 * transform db results
	 * @param array $row result
	 */
	private static function transformDBResults(&$row) {
		if (isset($row['id'])) {
			$row['id'] = (int) $row['id'];
		}
		if (isset($row['share_type'])) {
			$row['share_type'] = (int) $row['share_type'];
		}
		if (isset($row['parent'])) {
			$row['parent'] = (int) $row['parent'];
		}
		if (isset($row['file_parent'])) {
			$row['file_parent'] = (int) $row['file_parent'];
		}
		if (isset($row['file_source'])) {
			$row['file_source'] = (int) $row['file_source'];
		}
		if (isset($row['permissions'])) {
			$row['permissions'] = (int) $row['permissions'];
		}
		if (isset($row['storage'])) {
			$row['storage'] = (int) $row['storage'];
		}
		if (isset($row['stime'])) {
			$row['stime'] = (int) $row['stime'];
		}
		if (isset($row['expiration']) && $row['share_type'] !== IShare::TYPE_LINK) {
			// discard expiration date for non-link shares, which might have been
			// set by ancient bugs
			$row['expiration'] = null;
		}
	}

	/**
	 * format result
	 * @param array $items result
	 * @param string $column is it a file share or a general share ('file_target' or 'item_target')
	 * @param \OCP\Share_Backend $backend sharing backend
	 * @param int $format
	 * @param array $parameters additional format parameters
	 * @return array format result
	 */
	private static function formatResult($items, $column, $backend, $format = self::FORMAT_NONE , $parameters = null) {
		if ($format === self::FORMAT_NONE) {
			return $items;
		} elseif ($format === self::FORMAT_STATUSES) {
			$statuses = [];
			foreach ($items as $item) {
				if ($item['share_type'] === IShare::TYPE_LINK) {
					if ($item['uid_initiator'] !== \OC::$server->getUserSession()->getUser()->getUID()) {
						continue;
					}
					$statuses[$item[$column]]['link'] = true;
				} elseif (!isset($statuses[$item[$column]])) {
					$statuses[$item[$column]]['link'] = false;
				}
				if (!empty($item['file_target'])) {
					$statuses[$item[$column]]['path'] = $item['path'];
				}
			}
			return $statuses;
		} else {
			return $backend->formatItems($items, $format, $parameters);
		}
	}

	/**
	 * remove protocol from URL
	 *
	 * @param string $url
	 * @return string
	 */
	public static function removeProtocolFromUrl($url) {
		if (strpos($url, 'https://') === 0) {
			return substr($url, strlen('https://'));
		} elseif (strpos($url, 'http://') === 0) {
			return substr($url, strlen('http://'));
		}

		return $url;
	}


	/**
	 * @return int
	 */
	public static function getExpireInterval() {
		return (int)\OC::$server->getConfig()->getAppValue('core', 'shareapi_expire_after_n_days', '7');
	}

	/**
	 * Checks whether the given path is reachable for the given owner
	 *
	 * @param string $path path relative to files
	 * @param string $ownerStorageId storage id of the owner
	 *
	 * @return boolean true if file is reachable, false otherwise
	 */
	private static function isFileReachable($path, $ownerStorageId) {
		// if outside the home storage, file is always considered reachable
		if (!(substr($ownerStorageId, 0, 6) === 'home::' ||
			substr($ownerStorageId, 0, 13) === 'object::user:'
		)) {
			return true;
		}

		// if inside the home storage, the file has to be under "/files/"
		$path = ltrim($path, '/');
		if (substr($path, 0, 6) === 'files/') {
			return true;
		}

		return false;
	}
}
