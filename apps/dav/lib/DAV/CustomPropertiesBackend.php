<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\DAV;

use OCA\DAV\Connector\Sabre\Node;
use OCP\IDBConnection;
use OCP\IUser;
use Sabre\DAV\PropertyStorage\Backend\BackendInterface;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Tree;

class CustomPropertiesBackend implements BackendInterface {

	/**
	 * Ignored properties
	 *
	 * @var array
	 */
	private $ignoredProperties = [
		'{DAV:}getcontentlength',
		'{DAV:}getcontenttype',
		'{DAV:}getetag',
		'{DAV:}quota-used-bytes',
		'{DAV:}quota-available-bytes',
		'{http://owncloud.org/ns}permissions',
		'{http://owncloud.org/ns}downloadURL',
		'{http://owncloud.org/ns}dDC',
		'{http://owncloud.org/ns}size',
		'{http://nextcloud.org/ns}is-encrypted',
	];

	/**
	 * @var Tree
	 */
	private $tree;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var IUser
	 */
	private $user;

	/**
	 * Properties cache
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * @param Tree $tree node tree
	 * @param IDBConnection $connection database connection
	 * @param IUser $user owner of the tree and properties
	 */
	public function __construct(
		Tree $tree,
		IDBConnection $connection,
		IUser $user) {
		$this->tree = $tree;
		$this->connection = $connection;
		$this->user = $user;
	}

	/**
	 * Fetches properties for a path.
	 *
	 * @param string $path
	 * @param PropFind $propFind
	 * @return void
	 */
	public function propFind($path, PropFind $propFind) {
		$requestedProps = $propFind->get404Properties();

		// these might appear
		$requestedProps = array_diff(
			$requestedProps,
			$this->ignoredProperties
		);

		// substr of calendars/ => path is inside the CalDAV component
		// two '/' => this a calendar (no calendar-home nor calendar object)
		if (substr($path, 0, 10) === 'calendars/' && substr_count($path, '/') === 2) {
			$allRequestedProps = $propFind->getRequestedProperties();
			$customPropertiesForShares = [
				'{DAV:}displayname',
				'{urn:ietf:params:xml:ns:caldav}calendar-description',
				'{urn:ietf:params:xml:ns:caldav}calendar-timezone',
				'{http://apple.com/ns/ical/}calendar-order',
				'{http://apple.com/ns/ical/}calendar-color',
				'{urn:ietf:params:xml:ns:caldav}schedule-calendar-transp',
			];

			foreach ($customPropertiesForShares as $customPropertyForShares) {
				if (in_array($customPropertyForShares, $allRequestedProps)) {
					$requestedProps[] = $customPropertyForShares;
				}
			}
		}

		if (empty($requestedProps)) {
			return;
		}

		$props = $this->getProperties($path, $requestedProps);
		foreach ($props as $propName => $propValue) {
			$propFind->set($propName, $propValue);
		}
	}

	/**
	 * Updates properties for a path
	 *
	 * @param string $path
	 * @param PropPatch $propPatch
	 *
	 * @return void
	 */
	public function propPatch($path, PropPatch $propPatch) {
		$propPatch->handleRemaining(function ($changedProps) use ($path) {
			return $this->updateProperties($path, $changedProps);
		});
	}

	/**
	 * This method is called after a node is deleted.
	 *
	 * @param string $path path of node for which to delete properties
	 */
	public function delete($path) {
		$statement = $this->connection->prepare(
			'DELETE FROM `*PREFIX*properties` WHERE `userid` = ? AND `propertypath` = ?'
		);
		$statement->execute([$this->user->getUID(), $this->formatPath($path)]);
		$statement->closeCursor();

		unset($this->cache[$path]);
	}

	/**
	 * This method is called after a successful MOVE
	 *
	 * @param string $source
	 * @param string $destination
	 *
	 * @return void
	 */
	public function move($source, $destination) {
		$statement = $this->connection->prepare(
			'UPDATE `*PREFIX*properties` SET `propertypath` = ?' .
			' WHERE `userid` = ? AND `propertypath` = ?'
		);
		$statement->execute([$this->formatPath($destination), $this->user->getUID(), $this->formatPath($source)]);
		$statement->closeCursor();
	}

	/**
	 * Returns a list of properties for this nodes.;
	 *
	 * @param string $path
	 * @param array $requestedProperties requested properties or empty array for "all"
	 * @return array
	 * @note The properties list is a list of propertynames the client
	 * requested, encoded as xmlnamespace#tagName, for example:
	 * http://www.example.org/namespace#author If the array is empty, all
	 * properties should be returned
	 */
	private function getProperties(string $path, array $requestedProperties) {
		if (isset($this->cache[$path])) {
			return $this->cache[$path];
		}

		// TODO: chunking if more than 1000 properties
		$sql = 'SELECT * FROM `*PREFIX*properties` WHERE `userid` = ? AND `propertypath` = ?';

		$whereValues = [$this->user->getUID(), $this->formatPath($path)];
		$whereTypes = [null, null];

		if (!empty($requestedProperties)) {
			// request only a subset
			$sql .= ' AND `propertyname` in (?)';
			$whereValues[] = $requestedProperties;
			$whereTypes[] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
		}

		$result = $this->connection->executeQuery(
			$sql,
			$whereValues,
			$whereTypes
		);

		$props = [];
		while ($row = $result->fetch()) {
			$props[$row['propertyname']] = $row['propertyvalue'];
		}

		$result->closeCursor();

		$this->cache[$path] = $props;
		return $props;
	}

	/**
	 * Update properties
	 *
	 * @param string $path path for which to update properties
	 * @param array $properties array of properties to update
	 *
	 * @return bool
	 */
	private function updateProperties(string $path, array $properties) {

		$deleteStatement = 'DELETE FROM `*PREFIX*properties`' .
			' WHERE `userid` = ? AND `propertypath` = ? AND `propertyname` = ?';

		$insertStatement = 'INSERT INTO `*PREFIX*properties`' .
			' (`userid`,`propertypath`,`propertyname`,`propertyvalue`) VALUES(?,?,?,?)';

		$updateStatement = 'UPDATE `*PREFIX*properties` SET `propertyvalue` = ?' .
			' WHERE `userid` = ? AND `propertypath` = ? AND `propertyname` = ?';

		// TODO: use "insert or update" strategy ?
		$existing = $this->getProperties($path, []);
		$this->connection->beginTransaction();
		foreach ($properties as $propertyName => $propertyValue) {
			// If it was null, we need to delete the property
			if (is_null($propertyValue)) {
				if (array_key_exists($propertyName, $existing)) {
					$this->connection->executeUpdate($deleteStatement,
						[
							$this->user->getUID(),
							$this->formatPath($path),
							$propertyName,
						]
					);
				}
			} else {
				if (!array_key_exists($propertyName, $existing)) {
					$this->connection->executeUpdate($insertStatement,
						[
							$this->user->getUID(),
							$this->formatPath($path),
							$propertyName,
							$propertyValue,
						]
					);
				} else {
					$this->connection->executeUpdate($updateStatement,
						[
							$propertyValue,
							$this->user->getUID(),
							$this->formatPath($path),
							$propertyName,
						]
					);
				}
			}
		}

		$this->connection->commit();
		unset($this->cache[$path]);

		return true;
	}

	/**
	 * long paths are hashed to ensure they fit in the database
	 *
	 * @param string $path
	 * @return string
	 */
	private function formatPath(string $path): string {
		if (strlen($path) > 250) {
			return sha1($path);
		} else {
			return $path;
		}
	}
}
