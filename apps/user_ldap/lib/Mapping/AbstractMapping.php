<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Aaron Wood <aaronjwood@gmail.com>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author blizzz <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Mapping;

use Doctrine\DBAL\Exception;
use OC\DB\QueryBuilder\QueryBuilder;
use OCP\DB\IPreparedStatement;
use OCP\DB\QueryBuilder\IQueryBuilder;

/**
 * Class AbstractMapping
 *
 * @package OCA\User_LDAP\Mapping
 */
abstract class AbstractMapping {
	/**
	 * @var \OCP\IDBConnection $dbc
	 */
	protected $dbc;

	/**
	 * returns the DB table name which holds the mappings
	 *
	 * @return string
	 */
	abstract protected function getTableName(bool $includePrefix = true);

	/**
	 * @param \OCP\IDBConnection $dbc
	 */
	public function __construct(\OCP\IDBConnection $dbc) {
		$this->dbc = $dbc;
	}

	/** @var array caches Names (value) by DN (key) */
	protected $cache = [];

	/**
	 * checks whether a provided string represents an existing table col
	 *
	 * @param string $col
	 * @return bool
	 */
	public function isColNameValid($col) {
		switch ($col) {
			case 'ldap_dn':
			case 'owncloud_name':
			case 'directory_uuid':
				return true;
			default:
				return false;
		}
	}

	/**
	 * Gets the value of one column based on a provided value of another column
	 *
	 * @param string $fetchCol
	 * @param string $compareCol
	 * @param string $search
	 * @return string|false
	 * @throws \Exception
	 */
	protected function getXbyY($fetchCol, $compareCol, $search) {
		if (!$this->isColNameValid($fetchCol)) {
			//this is used internally only, but we don't want to risk
			//having SQL injection at all.
			throw new \Exception('Invalid Column Name');
		}
		$query = $this->dbc->prepare('
			SELECT `' . $fetchCol . '`
			FROM `' . $this->getTableName() . '`
			WHERE `' . $compareCol . '` = ?
		');

		try {
			$res = $query->execute([$search]);
			$data = $res->fetchOne();
			$res->closeCursor();
			return $data;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Performs a DELETE or UPDATE query to the database.
	 *
	 * @param IPreparedStatement $statement
	 * @param array $parameters
	 * @return bool true if at least one row was modified, false otherwise
	 */
	protected function modify(IPreparedStatement $statement, $parameters) {
		try {
			$result = $statement->execute($parameters);
			$updated = $result->rowCount() > 0;
			$result->closeCursor();
			return $updated;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * Gets the LDAP DN based on the provided name.
	 * Replaces Access::ocname2dn
	 *
	 * @param string $name
	 * @return string|false
	 */
	public function getDNByName($name) {
		$dn = array_search($name, $this->cache);
		if ($dn === false && ($dn = $this->getXbyY('ldap_dn', 'owncloud_name', $name)) !== false) {
			$this->cache[$dn] = $name;
		}
		return $dn;
	}

	/**
	 * Updates the DN based on the given UUID
	 *
	 * @param string $fdn
	 * @param string $uuid
	 * @return bool
	 */
	public function setDNbyUUID($fdn, $uuid) {
		$oldDn = $this->getDnByUUID($uuid);
		$statement = $this->dbc->prepare('
			UPDATE `' . $this->getTableName() . '`
			SET `ldap_dn` = ?
			WHERE `directory_uuid` = ?
		');

		$r = $this->modify($statement, [$fdn, $uuid]);

		if ($r && is_string($oldDn) && isset($this->cache[$oldDn])) {
			$this->cache[$fdn] = $this->cache[$oldDn];
			unset($this->cache[$oldDn]);
		}

		return $r;
	}

	/**
	 * Updates the UUID based on the given DN
	 *
	 * required by Migration/UUIDFix
	 *
	 * @param $uuid
	 * @param $fdn
	 * @return bool
	 */
	public function setUUIDbyDN($uuid, $fdn) {
		$statement = $this->dbc->prepare('
			UPDATE `' . $this->getTableName() . '`
			SET `directory_uuid` = ?
			WHERE `ldap_dn` = ?
		');

		unset($this->cache[$fdn]);

		return $this->modify($statement, [$uuid, $fdn]);
	}

	/**
	 * Gets the name based on the provided LDAP DN.
	 *
	 * @param string $fdn
	 * @return string|false
	 */
	public function getNameByDN($fdn) {
		if (!isset($this->cache[$fdn])) {
			$this->cache[$fdn] = $this->getXbyY('owncloud_name', 'ldap_dn', $fdn);
		}
		return $this->cache[$fdn];
	}

	protected function prepareListOfIdsQuery(array $dnList): IQueryBuilder {
		$qb = $this->dbc->getQueryBuilder();
		$qb->select('owncloud_name', 'ldap_dn')
			->from($this->getTableName(false))
			->where($qb->expr()->in('ldap_dn', $qb->createNamedParameter($dnList, QueryBuilder::PARAM_STR_ARRAY)));
		return $qb;
	}

	protected function collectResultsFromListOfIdsQuery(IQueryBuilder $qb, array &$results): void {
		$stmt = $qb->execute();
		while ($entry = $stmt->fetch(\Doctrine\DBAL\FetchMode::ASSOCIATIVE)) {
			$results[$entry['ldap_dn']] = $entry['owncloud_name'];
			$this->cache[$entry['ldap_dn']] = $entry['owncloud_name'];
		}
		$stmt->closeCursor();
	}

	public function getListOfIdsByDn(array $fdns): array {
		$totalDBParamLimit = 65000;
		$sliceSize = 1000;
		$maxSlices = $totalDBParamLimit / $sliceSize;
		$results = [];

		$slice = 1;
		$fdnsSlice = count($fdns) > $sliceSize ? array_slice($fdns, 0, $sliceSize) : $fdns;
		$qb = $this->prepareListOfIdsQuery($fdnsSlice);

		while (isset($fdnsSlice[999])) {
			// Oracle does not allow more than 1000 values in the IN list,
			// but allows slicing
			$slice++;
			$fdnsSlice = array_slice($fdns, $sliceSize * ($slice - 1), $sliceSize);

			/** @see https://github.com/vimeo/psalm/issues/4995 */
			/** @psalm-suppress TypeDoesNotContainType */
			if (!isset($qb)) {
				$qb = $this->prepareListOfIdsQuery($fdnsSlice);
				continue;
			}

			if (!empty($fdnsSlice)) {
				$qb->orWhere($qb->expr()->in('ldap_dn', $qb->createNamedParameter($fdnsSlice, QueryBuilder::PARAM_STR_ARRAY)));
			}

			if ($slice % $maxSlices === 0) {
				$this->collectResultsFromListOfIdsQuery($qb, $results);
				unset($qb);
			}
		}

		if (isset($qb)) {
			$this->collectResultsFromListOfIdsQuery($qb, $results);
		}

		return $results;
	}

	/**
	 * Searches mapped names by the giving string in the name column
	 *
	 * @param string $search
	 * @param string $prefixMatch
	 * @param string $postfixMatch
	 * @return string[]
	 */
	public function getNamesBySearch($search, $prefixMatch = "", $postfixMatch = "") {
		$statement = $this->dbc->prepare('
			SELECT `owncloud_name`
			FROM `' . $this->getTableName() . '`
			WHERE `owncloud_name` LIKE ?
		');

		try {
			$res = $statement->execute([$prefixMatch . $this->dbc->escapeLikeParameter($search) . $postfixMatch]);
		} catch (Exception $e) {
			return [];
		}
		$names = [];
		while ($row = $res->fetch()) {
			$names[] = $row['owncloud_name'];
		}
		return $names;
	}

	/**
	 * Gets the name based on the provided LDAP UUID.
	 *
	 * @param string $uuid
	 * @return string|false
	 */
	public function getNameByUUID($uuid) {
		return $this->getXbyY('owncloud_name', 'directory_uuid', $uuid);
	}

	public function getDnByUUID($uuid) {
		return $this->getXbyY('ldap_dn', 'directory_uuid', $uuid);
	}

	/**
	 * Gets the UUID based on the provided LDAP DN
	 *
	 * @param string $dn
	 * @return false|string
	 * @throws \Exception
	 */
	public function getUUIDByDN($dn) {
		return $this->getXbyY('directory_uuid', 'ldap_dn', $dn);
	}

	/**
	 * gets a piece of the mapping list
	 *
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function getList($offset = null, $limit = null) {
		$query = $this->dbc->prepare('
			SELECT
				`ldap_dn` AS `dn`,
				`owncloud_name` AS `name`,
				`directory_uuid` AS `uuid`
			FROM `' . $this->getTableName() . '`',
			$limit,
			$offset
		);

		$query->execute();
		return $query->fetchAll();
	}

	/**
	 * attempts to map the given entry
	 *
	 * @param string $fdn fully distinguished name (from LDAP)
	 * @param string $name
	 * @param string $uuid a unique identifier as used in LDAP
	 * @return bool
	 */
	public function map($fdn, $name, $uuid) {
		if (mb_strlen($fdn) > 255) {
			\OC::$server->getLogger()->error(
				'Cannot map, because the DN exceeds 255 characters: {dn}',
				[
					'app' => 'user_ldap',
					'dn' => $fdn,
				]
			);
			return false;
		}

		$row = [
			'ldap_dn' => $fdn,
			'owncloud_name' => $name,
			'directory_uuid' => $uuid
		];

		try {
			$result = $this->dbc->insertIfNotExist($this->getTableName(), $row);
			if ((bool)$result === true) {
				$this->cache[$fdn] = $name;
			}
			// insertIfNotExist returns values as int
			return (bool)$result;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * removes a mapping based on the owncloud_name of the entry
	 *
	 * @param string $name
	 * @return bool
	 */
	public function unmap($name) {
		$statement = $this->dbc->prepare('
			DELETE FROM `' . $this->getTableName() . '`
			WHERE `owncloud_name` = ?');

		return $this->modify($statement, [$name]);
	}

	/**
	 * Truncates the mapping table
	 *
	 * @return bool
	 */
	public function clear() {
		$sql = $this->dbc
			->getDatabasePlatform()
			->getTruncateTableSQL('`' . $this->getTableName() . '`');
		try {
			$this->dbc->executeQuery($sql);

			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/**
	 * clears the mapping table one by one and executing a callback with
	 * each row's id (=owncloud_name col)
	 *
	 * @param callable $preCallback
	 * @param callable $postCallback
	 * @return bool true on success, false when at least one row was not
	 * deleted
	 */
	public function clearCb(callable $preCallback, callable $postCallback): bool {
		$picker = $this->dbc->getQueryBuilder();
		$picker->select('owncloud_name')
			->from($this->getTableName());
		$cursor = $picker->execute();
		$result = true;
		while ($id = $cursor->fetchOne()) {
			$preCallback($id);
			if ($isUnmapped = $this->unmap($id)) {
				$postCallback($id);
			}
			$result &= $isUnmapped;
		}
		$cursor->closeCursor();
		return $result;
	}

	/**
	 * returns the number of entries in the mappings table
	 *
	 * @return int
	 */
	public function count() {
		$qb = $this->dbc->getQueryBuilder();
		$query = $qb->select($qb->func()->count('ldap_dn'))
			->from($this->getTableName());
		$res = $query->execute();
		$count = $res->fetchOne();
		$res->closeCursor();
		return (int)$count;
	}
}
