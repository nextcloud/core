<?php
/**
 * @copyright 2019, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\Calendar\BackendTemporarilyUnavailableException;
use OCP\Calendar\IMetadataProvider;
use OCP\Calendar\Resource\IBackend as IResourceBackend;
use OCP\Calendar\Resource\IManager as IResourceManager;
use OCP\Calendar\Resource\IResource;
use OCP\Calendar\Room\IManager as IRoomManager;
use OCP\Calendar\Room\IRoom;
use OCP\IDBConnection;

class UpdateCalendarResourcesRoomsBackgroundJob extends TimedJob {

	/** @var IResourceManager */
	private $resourceManager;

	/** @var IRoomManager */
	private $roomManager;

	/** @var IDBConnection */
	private $dbConnection;

	/** @var CalDavBackend */
	private $calDavBackend;

	/**
	 * UpdateCalendarResourcesRoomsBackgroundJob constructor.
	 *
	 * @param IResourceManager $resourceManager
	 * @param IRoomManager $roomManager
	 * @param IDBConnection $dbConnection
	 * @param CalDavBackend $calDavBackend
	 */
	public function __construct(IResourceManager $resourceManager,
								IRoomManager $roomManager,
								IDBConnection $dbConnection,
								CalDavBackend $calDavBackend) {
		$this->resourceManager = $resourceManager;
		$this->roomManager = $roomManager;
		$this->dbConnection = $dbConnection;
		$this->calDavBackend = $calDavBackend;

		// run once an hour
		$this->setInterval(60 * 60);
	}

	/**
	 * @param $argument
	 */
	public function run($argument):void {
		$this->runForBackend(
			$this->resourceManager,
			'calendar_resources',
			'calendar_resources_md',
			'resource_id',
			'principals/calendar-resources'
		);
		$this->runForBackend(
			$this->roomManager,
			'calendar_rooms',
			'calendar_rooms_md',
			'room_id',
			'principals/calendar-rooms'
		);
	}

	/**
	 * Run background-job for one specific backendManager
	 * either ResourceManager or RoomManager
	 *
	 * @param IResourceManager|IRoomManager $backendManager
	 * @param string $dbTable
	 * @param string $dbTableMetadata
	 * @param string $foreignKey
	 * @param string $principalPrefix
	 */
	private function runForBackend($backendManager,
								   string $dbTable,
								   string $dbTableMetadata,
								   string $foreignKey,
								   string $principalPrefix):void {
		$backends = $backendManager->getBackends();

		foreach ($backends as $backend) {
			$backendId = $backend->getBackendIdentifier();

			try {
				if ($backend instanceof IResourceBackend) {
					$list = $backend->listAllResources();
				} else {
					$list = $backend->listAllRooms();
				}
			} catch (BackendTemporarilyUnavailableException $ex) {
				continue;
			}

			$cachedList = $this->getAllCachedByBackend($dbTable, $backendId);
			$newIds = array_diff($list, $cachedList);
			$deletedIds = array_diff($cachedList, $list);
			$editedIds = array_intersect($list, $cachedList);

			foreach ($newIds as $newId) {
				try {
					if ($backend instanceof IResourceBackend) {
						$resource = $backend->getResource($newId);
					} else {
						$resource = $backend->getRoom($newId);
					}

					$metadata = [];
					if ($resource instanceof IMetadataProvider) {
						$metadata = $this->getAllMetadataOfBackend($resource);
					}
				} catch (BackendTemporarilyUnavailableException $ex) {
					continue;
				}

				$id = $this->addToCache($dbTable, $backendId, $resource);
				$this->addMetadataToCache($dbTableMetadata, $foreignKey, $id, $metadata);
				// we don't create the calendar here, it is created lazily
				// when an event is actually scheduled with this resource / room
			}

			foreach ($deletedIds as $deletedId) {
				$id = $this->getIdForBackendAndResource($dbTable, $backendId, $deletedId);
				$this->deleteFromCache($dbTable, $id);
				$this->deleteMetadataFromCache($dbTableMetadata, $foreignKey, $id);

				$principalName = implode('-', [$backendId, $deletedId]);
				$this->deleteCalendarDataForResource($principalPrefix, $principalName);
			}

			foreach ($editedIds as $editedId) {
				$id = $this->getIdForBackendAndResource($dbTable, $backendId, $editedId);

				try {
					if ($backend instanceof IResourceBackend) {
						$resource = $backend->getResource($editedId);
					} else {
						$resource = $backend->getRoom($editedId);
					}

					$metadata = [];
					if ($resource instanceof IMetadataProvider) {
						$metadata = $this->getAllMetadataOfBackend($resource);
					}
				} catch (BackendTemporarilyUnavailableException $ex) {
					continue;
				}

				$this->updateCache($dbTable, $id, $resource);

				if ($resource instanceof IMetadataProvider) {
					$cachedMetadata = $this->getAllMetadataOfCache($dbTableMetadata, $foreignKey, $id);
					$this->updateMetadataCache($dbTableMetadata, $foreignKey, $id, $metadata, $cachedMetadata);
				}
			}
		}
	}

	/**
	 * add entry to cache that exists remotely but not yet in cache
	 *
	 * @param string $table
	 * @param string $backendId
	 * @param IResource|IRoom $remote
	 * @return int Insert id
	 */
	private function addToCache(string $table,
								string $backendId,
								$remote):int {
		$query = $this->dbConnection->getQueryBuilder();
		$query->insert($table)
			->values([
				'backend_id' => $query->createNamedParameter($backendId),
				'resource_id' => $query->createNamedParameter($remote->getId()),
				'email' => $query->createNamedParameter($remote->getEMail()),
				'displayname' => $query->createNamedParameter($remote->getDisplayName()),
				'group_restrictions' => $query->createNamedParameter(
					$this->serializeGroupRestrictions(
						$remote->getGroupRestrictions()
					))
			])
			->execute();
		return $query->getLastInsertId();
	}

	/**
	 * @param string $table
	 * @param string $foreignKey
	 * @param int $foreignId
	 * @param array $metadata
	 */
	private function addMetadataToCache(string $table,
										string $foreignKey,
										int $foreignId,
										array $metadata):void {
		foreach ($metadata as $key => $value) {
			$query = $this->dbConnection->getQueryBuilder();
			$query->insert($table)
				->values([
					$foreignKey => $query->createNamedParameter($foreignId),
					'key' => $query->createNamedParameter($key),
					'value' => $query->createNamedParameter($value),
				])
				->execute();
		}
	}

	/**
	 * delete entry from cache that does not exist anymore remotely
	 *
	 * @param string $table
	 * @param int $id
	 */
	private function deleteFromCache(string $table,
									 int $id):void {
		$query = $this->dbConnection->getQueryBuilder();
		$query->delete($table)
			->where($query->expr()->eq('id', $query->createNamedParameter($id)))
			->execute();
	}

	/**
	 * @param string $table
	 * @param string $foreignKey
	 * @param int $id
	 */
	private function deleteMetadataFromCache(string $table,
											 string $foreignKey,
											 int $id):void {
		$query = $this->dbConnection->getQueryBuilder();
		$query->delete($table)
			->where($query->expr()->eq($foreignKey, $query->createNamedParameter($id)))
			->execute();
	}

	/**
	 * update an existing entry in cache
	 *
	 * @param string $table
	 * @param int $id
	 * @param IResource|IRoom $remote
	 */
	private function updateCache(string $table,
								 int $id,
								 $remote):void {
		$query = $this->dbConnection->getQueryBuilder();
		$query->update($table)
			->set('email', $query->createNamedParameter($remote->getEMail()))
			->set('displayname', $query->createNamedParameter($remote->getDisplayName()))
			->set('group_restrictions', $query->createNamedParameter(
				$this->serializeGroupRestrictions(
					$remote->getGroupRestrictions()
				)))
			->where($query->expr()->eq('id', $query->createNamedParameter($id)))
			->execute();
	}

	/**
	 * @param string $dbTable
	 * @param string $foreignKey
	 * @param int $id
	 * @param array $metadata
	 * @param array $cachedMetadata
	 */
	private function updateMetadataCache(string $dbTable,
										 string $foreignKey,
										 int $id,
										 array $metadata,
										 array $cachedMetadata):void {
		$newMetadata = array_diff_key($metadata, $cachedMetadata);
		$deletedMetadata = array_diff_key($cachedMetadata, $metadata);

		foreach ($newMetadata as $key => $value) {
			$query = $this->dbConnection->getQueryBuilder();
			$query->insert($dbTable)
				->values([
					$foreignKey => $query->createNamedParameter($id),
					'key' => $query->createNamedParameter($key),
					'value' => $query->createNamedParameter($value),
				])
				->execute();
		}

		foreach ($deletedMetadata as $key => $value) {
			$query = $this->dbConnection->getQueryBuilder();
			$query->delete($dbTable)
				->where($query->expr()->eq($foreignKey, $query->createNamedParameter($id)))
				->andWhere($query->expr()->eq('key', $query->createNamedParameter($key)))
				->execute();
		}

		$existingKeys = array_keys(array_intersect_key($metadata, $cachedMetadata));
		foreach ($existingKeys as $existingKey) {
			if ($metadata[$existingKey] !== $cachedMetadata[$existingKey]) {
				$query = $this->dbConnection->getQueryBuilder();
				$query->update($dbTable)
					->set('value', $query->createNamedParameter($metadata[$existingKey]))
					->where($query->expr()->eq($foreignKey, $query->createNamedParameter($id)))
					->andWhere($query->expr()->eq('key', $query->createNamedParameter($existingKey)))
					->execute();
			}
		}
	}

	/**
	 * serialize array of group restrictions to store them in database
	 *
	 * @param array $groups
	 * @return string
	 */
	private function serializeGroupRestrictions(array $groups):string {
		return \json_encode($groups);
	}

	/**
	 * Gets all metadata of a backend
	 *
	 * @param IResource|IRoom $resource
	 * @return array
	 */
	private function getAllMetadataOfBackend($resource):array {
		if (!($resource instanceof IMetadataProvider)) {
			return [];
		}

		$keys = $resource->getAllAvailableMetadataKeys();
		$metadata = [];
		foreach ($keys as $key) {
			$metadata[$key] = $resource->getMetadataForKey($key);
		}

		return $metadata;
	}

	/**
	 * @param string $table
	 * @param string $foreignKey
	 * @param int $id
	 * @return array
	 */
	private function getAllMetadataOfCache(string $table,
										   string $foreignKey,
										   int $id):array {
		$query = $this->dbConnection->getQueryBuilder();
		$query->select(['key', 'value'])
			->from($table)
			->where($query->expr()->eq($foreignKey, $query->createNamedParameter($id)));
		$stmt = $query->execute();
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$metadata = [];
		foreach ($rows as $row) {
			$metadata[$row['key']] = $row['value'];
		}

		return $metadata;
	}

	/**
	 * Gets all cached rooms / resources by backend
	 *
	 * @param $tableName
	 * @param $backendId
	 * @return array
	 */
	private function getAllCachedByBackend(string $tableName,
										   string $backendId):array {
		$query = $this->dbConnection->getQueryBuilder();
		$query->select('resource_id')
			->from($tableName)
			->where($query->expr()->eq('backend_id', $query->createNamedParameter($backendId)));
		$stmt = $query->execute();

		return array_map(function ($row) {
			return $row['resource_id'];
		}, $stmt->fetchAll());
	}

	/**
	 * @param $principalPrefix
	 * @param $principalUri
	 */
	private function deleteCalendarDataForResource(string $principalPrefix,
												   string $principalUri):void {
		$calendar = $this->calDavBackend->getCalendarByUri(
			implode('/', [$principalPrefix, $principalUri]),
			CalDavBackend::RESOURCE_BOOKING_CALENDAR_URI);

		if ($calendar !== null) {
			$this->calDavBackend->deleteCalendar($calendar['id']);
		}
	}

	/**
	 * @param $table
	 * @param $backendId
	 * @param $resourceId
	 * @return int
	 */
	private function getIdForBackendAndResource(string $table,
												string $backendId,
												string $resourceId):int {
		$query = $this->dbConnection->getQueryBuilder();
		$query->select('id')
			->from($table)
			->where($query->expr()->eq('backend_id', $query->createNamedParameter($backendId)))
			->andWhere($query->expr()->eq('resource_id', $query->createNamedParameter($resourceId)));
		$stmt = $query->execute();

		return $stmt->fetch()['id'];
	}
}
