<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\DAV\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class DirectMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'directlink', Direct::class);
	}

	/**
	 * @param string $token
	 * @return Direct
	 * @throws DoesNotExistException
	 */
	public function getByToken(string $token): Direct {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from('directlink')
			->where(
				$qb->expr()->eq('token', $qb->createNamedParameter($token))
			);

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new DoesNotExistException('Direct link with token does not exist');
		}

		return Direct::fromRow($data);
	}

	public function deleteExpired(int $expiration) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('directlink')
			->where(
				$qb->expr()->lt('expiration', $qb->createNamedParameter($expiration))
			);

		$qb->execute();
	}
}
