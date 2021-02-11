<?php
/**
 * @copyright 2019, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\CalDAV\ResourceBooking;

use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\Traits\PrincipalProxyTrait;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserSession;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\BackendInterface;

abstract class AbstractPrincipalBackend implements BackendInterface {

	/** @var IDBConnection */
	private $db;

	/** @var IUserSession */
	private $userSession;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ILogger */
	private $logger;

	/** @var ProxyMapper */
	private $proxyMapper;

	/** @var string */
	private $principalPrefix;

	/** @var string */
	private $dbTableName;

	/** @var string */
	private $dbMetaDataTableName;

	/** @var string */
	private $dbForeignKeyName;

	/** @var string */
	private $cuType;

	/**
	 * @param IDBConnection $dbConnection
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param ILogger $logger
	 * @param string $principalPrefix
	 * @param string $dbPrefix
	 * @param string $cuType
	 */
	public function __construct(IDBConnection $dbConnection,
								IUserSession $userSession,
								IGroupManager $groupManager,
								ILogger $logger,
								ProxyMapper $proxyMapper,
								string $principalPrefix,
								string $dbPrefix,
								string $cuType) {
		$this->db = $dbConnection;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->logger = $logger;
		$this->proxyMapper = $proxyMapper;
		$this->principalPrefix = $principalPrefix;
		$this->dbTableName = 'calendar_' . $dbPrefix . 's';
		$this->dbMetaDataTableName = $this->dbTableName . '_md';
		$this->dbForeignKeyName = $dbPrefix . '_id';
		$this->cuType = $cuType;
	}

	use PrincipalProxyTrait;

	/**
	 * Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are only
	 * expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties are:
	 *   {DAV:}displayname
	 *
	 * @param string $prefixPath
	 * @return string[]
	 */
	public function getPrincipalsByPrefix($prefixPath) {
		$principals = [];

		if ($prefixPath === $this->principalPrefix) {
			$query = $this->db->getQueryBuilder();
			$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname'])
				->from($this->dbTableName);
			$stmt = $query->execute();

			$metaDataQuery = $this->db->getQueryBuilder();
			$metaDataQuery->select([$this->dbForeignKeyName, 'key', 'value'])
				->from($this->dbMetaDataTableName);
			$metaDataStmt = $metaDataQuery->execute();
			$metaDataRows = $metaDataStmt->fetchAll(\PDO::FETCH_ASSOC);

			$metaDataById = [];
			foreach ($metaDataRows as $metaDataRow) {
				if (!isset($metaDataById[$metaDataRow[$this->dbForeignKeyName]])) {
					$metaDataById[$metaDataRow[$this->dbForeignKeyName]] = [];
				}

				$metaDataById[$metaDataRow[$this->dbForeignKeyName]][$metaDataRow['key']] =
					$metaDataRow['value'];
			}

			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$id = $row['id'];

				if (isset($metaDataById[$id])) {
					$principals[] = $this->rowToPrincipal($row, $metaDataById[$id]);
				} else {
					$principals[] = $this->rowToPrincipal($row);
				}
			}

			$stmt->closeCursor();
		}

		return $principals;
	}

	/**
	 * Returns a specific principal, specified by it's path.
	 * The returned structure should be the exact same as from
	 * getPrincipalsByPrefix.
	 *
	 * @param string $path
	 * @return array
	 */
	public function getPrincipalByPath($path) {
		if (strpos($path, $this->principalPrefix) !== 0) {
			return null;
		}
		list(, $name) = \Sabre\Uri\split($path);

		list($backendId, $resourceId) = explode('-',  $name, 2);

		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname'])
			->from($this->dbTableName)
			->where($query->expr()->eq('backend_id', $query->createNamedParameter($backendId)))
			->andWhere($query->expr()->eq('resource_id', $query->createNamedParameter($resourceId)));
		$stmt = $query->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$row) {
			return null;
		}

		$metaDataQuery = $this->db->getQueryBuilder();
		$metaDataQuery->select(['key', 'value'])
			->from($this->dbMetaDataTableName)
			->where($metaDataQuery->expr()->eq($this->dbForeignKeyName, $metaDataQuery->createNamedParameter($row['id'])));
		$metaDataStmt = $metaDataQuery->execute();
		$metaDataRows = $metaDataStmt->fetchAll(\PDO::FETCH_ASSOC);
		$metadata = [];

		foreach ($metaDataRows as $metaDataRow) {
			$metadata[$metaDataRow['key']] = $metaDataRow['value'];
		}

		return $this->rowToPrincipal($row, $metadata);
	}

	/**
	 * @param int $id
	 * @return array|null
	 */
	public function getPrincipalById($id):?array {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname'])
			->from($this->dbTableName)
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));
		$stmt = $query->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$row) {
			return null;
		}

		$metaDataQuery = $this->db->getQueryBuilder();
		$metaDataQuery->select(['key', 'value'])
			->from($this->dbMetaDataTableName)
			->where($metaDataQuery->expr()->eq($this->dbForeignKeyName, $metaDataQuery->createNamedParameter($row['id'])));
		$metaDataStmt = $metaDataQuery->execute();
		$metaDataRows = $metaDataStmt->fetchAll(\PDO::FETCH_ASSOC);
		$metadata = [];

		foreach ($metaDataRows as $metaDataRow) {
			$metadata[$metaDataRow['key']] = $metaDataRow['value'];
		}

		return $this->rowToPrincipal($row, $metadata);
	}

	/**
	 * @param string $path
	 * @param PropPatch $propPatch
	 * @return int
	 */
	public function updatePrincipal($path, PropPatch $propPatch) {
		return 0;
	}

	/**
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
		$results = [];
		if (\count($searchProperties) === 0) {
			return [];
		}
		if ($prefixPath !== $this->principalPrefix) {
			return [];
		}

		$user = $this->userSession->getUser();
		if (!$user) {
			return [];
		}
		$usersGroups = $this->groupManager->getUserGroupIds($user);

		foreach ($searchProperties as $prop => $value) {
			switch ($prop) {
				case '{http://sabredav.org/ns}email-address':
					$query = $this->db->getQueryBuilder();
					$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname', 'group_restrictions'])
						->from($this->dbTableName)
						->where($query->expr()->iLike('email', $query->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%')));

					$stmt = $query->execute();
					$principals = [];
					while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
						if (!$this->isAllowedToAccessResource($row, $usersGroups)) {
							continue;
						}
						$principals[] = $this->rowToPrincipal($row)['uri'];
					}
					$results[] = $principals;

					$stmt->closeCursor();
					break;

				case '{DAV:}displayname':
					$query = $this->db->getQueryBuilder();
					$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname', 'group_restrictions'])
						->from($this->dbTableName)
						->where($query->expr()->iLike('displayname', $query->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%')));

					$stmt = $query->execute();
					$principals = [];
					while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
						if (!$this->isAllowedToAccessResource($row, $usersGroups)) {
							continue;
						}
						$principals[] = $this->rowToPrincipal($row)['uri'];
					}
					$results[] = $principals;

					$stmt->closeCursor();
					break;

				case '{urn:ietf:params:xml:ns:caldav}calendar-user-address-set':
					// If you add support for more search properties that qualify as a user-address,
					// please also add them to the array below
					$results[] = $this->searchPrincipals($this->principalPrefix, [
						'{http://sabredav.org/ns}email-address' => $value,
					], 'anyof');
					break;

				default:
					$rowsByMetadata = $this->searchPrincipalsByMetadataKey($prop, $value);
					$filteredRows = array_filter($rowsByMetadata, function ($row) use ($usersGroups) {
						return $this->isAllowedToAccessResource($row, $usersGroups);
					});

					$results[] = array_map(function ($row) {
						return $row['uri'];
					}, $filteredRows);

					break;
			}
		}

		// results is an array of arrays, so this is not the first search result
		// but the results of the first searchProperty
		if (count($results) === 1) {
			return $results[0];
		}

		switch ($test) {
			case 'anyof':
				return array_values(array_unique(array_merge(...$results)));

			case 'allof':
			default:
				return array_values(array_intersect(...$results));
		}
	}

	/**
	 * Searches principals based on their metadata keys.
	 * This allows to search for all principals with a specific key.
	 * e.g.:
	 * '{http://nextcloud.com/ns}room-building-address' => 'ABC Street 123, ...'
	 *
	 * @param $key
	 * @param $value
	 * @return array
	 */
	private function searchPrincipalsByMetadataKey($key, $value):array {
		$query = $this->db->getQueryBuilder();
		$query->select([$this->dbForeignKeyName])
			->from($this->dbMetaDataTableName)
			->where($query->expr()->eq('key', $query->createNamedParameter($key)))
			->andWhere($query->expr()->iLike('value', $query->createNamedParameter('%' . $this->db->escapeLikeParameter($value) . '%')));
		$stmt = $query->execute();

		$rows = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$id = $row[$this->dbForeignKeyName];

			$principalRow = $this->getPrincipalById($id);
			if (!$principalRow) {
				continue;
			}

			$rows[] = $principalRow;
		}

		return $rows;
	}

	/**
	 * @param string $uri
	 * @param string $principalPrefix
	 * @return null|string
	 */
	public function findByUri($uri, $principalPrefix) {
		$user = $this->userSession->getUser();
		if (!$user) {
			return null;
		}
		$usersGroups = $this->groupManager->getUserGroupIds($user);

		if (strpos($uri, 'mailto:') === 0) {
			$email = substr($uri, 7);
			$query = $this->db->getQueryBuilder();
			$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname', 'group_restrictions'])
				->from($this->dbTableName)
				->where($query->expr()->eq('email', $query->createNamedParameter($email)));

			$stmt = $query->execute();
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);

			if (!$row) {
				return null;
			}
			if (!$this->isAllowedToAccessResource($row, $usersGroups)) {
				return null;
			}

			return $this->rowToPrincipal($row)['uri'];
		}

		if (strpos($uri, 'principal:') === 0) {
			$path = substr($uri, 10);
			if (strpos($path, $this->principalPrefix) !== 0) {
				return null;
			}

			list(, $name) = \Sabre\Uri\split($path);
			list($backendId, $resourceId) = explode('-',  $name, 2);

			$query = $this->db->getQueryBuilder();
			$query->select(['id', 'backend_id', 'resource_id', 'email', 'displayname', 'group_restrictions'])
				->from($this->dbTableName)
				->where($query->expr()->eq('backend_id', $query->createNamedParameter($backendId)))
				->andWhere($query->expr()->eq('resource_id', $query->createNamedParameter($resourceId)));
			$stmt = $query->execute();
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);

			if (!$row) {
				return null;
			}
			if (!$this->isAllowedToAccessResource($row, $usersGroups)) {
				return null;
			}

			return $this->rowToPrincipal($row)['uri'];
		}

		return null;
	}

	/**
	 * convert database row to principal
	 *
	 * @param String[] $row
	 * @param String[] $metadata
	 * @return Array
	 */
	private function rowToPrincipal(array $row, array $metadata = []):array {
		return array_merge([
			'uri' => $this->principalPrefix . '/' . $row['backend_id'] . '-' . $row['resource_id'],
			'{DAV:}displayname' => $row['displayname'],
			'{http://sabredav.org/ns}email-address' => $row['email'],
			'{urn:ietf:params:xml:ns:caldav}calendar-user-type' => $this->cuType,
		], $metadata);
	}

	/**
	 * @param $row
	 * @param $userGroups
	 * @return bool
	 */
	private function isAllowedToAccessResource(array $row, array $userGroups):bool {
		if (!isset($row['group_restrictions']) ||
			$row['group_restrictions'] === null ||
			$row['group_restrictions'] === '') {
			return true;
		}

		// group restrictions contains something, but not parsable, deny access and log warning
		$json = json_decode($row['group_restrictions']);
		if (!\is_array($json)) {
			$this->logger->info('group_restrictions field could not be parsed for ' . $this->dbTableName . '::' . $row['id'] . ', denying access to resource');
			return false;
		}

		// empty array => no group restrictions
		if (empty($json)) {
			return true;
		}

		return !empty(array_intersect($json, $userGroups));
	}
}
