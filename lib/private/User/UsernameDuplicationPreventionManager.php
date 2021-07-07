<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\User;

use OCP\IDBConnection;

class UsernameDuplicationPreventionManager {
	/** @var IDBConnection */
	private $dbConnection;
	private const TABLE_NAME = 'previously_used_userids';
	private const HASHED_USER_ID_NAME = 'user_id_hash';

	public function __construct(IDBConnection $connection) {
		$this->dbConnection = $connection;
	}

	private function calculateUserNameHash(string $username) : string {
		return hash('sha512', $username);
	}

	public function markUsed(string $userName) : void {
		$queryBuilder = $this->dbConnection->getQueryBuilder();
		$queryBuilder->insert(self::TABLE_NAME)
			->values([
				self::HASHED_USER_ID_NAME => $queryBuilder->createNamedParameter($this->calculateUserNameHash($userName)),
			])
			->executeStatement();
	}

	public function wasUsed(string $userName) : bool {
		$queryBuilder = $this->dbConnection->getQueryBuilder();
		$result = $queryBuilder->select($queryBuilder->func()->count())
			->from(self::TABLE_NAME)
			->where(
				$queryBuilder->expr()->eq(
					self::HASHED_USER_ID_NAME,
					$queryBuilder->expr()->literal($this->calculateUserNameHash($userName))
				)
			)
			->executeQuery();
		return (int)$result->fetchOne() !== 0;
	}

	public function cleanUp(): void {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb
			->delete(self::TABLE_NAME)
			->executeStatement();
	}
}
