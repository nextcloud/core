<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCA\DAV\Service;

use OCP\IDBConnection;

class CustomPropertiesService {

	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function delete(string $userId, string $path): void {
		$statement = $this->connection->prepare(
			'DELETE FROM `*PREFIX*properties` WHERE `userid` = ? AND `propertypath` = ?'
		);
		$result = $statement->execute([$userId, $this->formatPath($path)]);
		$result->closeCursor();
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
		}
		return $path;
	}
}
