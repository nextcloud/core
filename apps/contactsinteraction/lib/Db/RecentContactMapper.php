<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\ContactsInteraction\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\IUser;

class RecentContactMapper extends QBMapper {

	public const TABLE_NAME = 'recent_contact';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME);
	}

	/**
	 * @return RecentContact[]
	 */
	public function findAll(string $uid): array {
		$qb = $this->db->getQueryBuilder();

		$select = $qb
			->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('actor_uid', $qb->createNamedParameter($uid)));

		return $this->findEntities($select);
	}

	/**
	 * @param string $uid
	 * @param int $id
	 *
	 * @return RecentContact
	 * @throws DoesNotExistException
	 */
	public function find(string $uid, int $id): RecentContact {
		$qb = $this->db->getQueryBuilder();

		$select = $qb
			->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, $qb::PARAM_INT)))
			->andWhere($qb->expr()->eq('actor_uid', $qb->createNamedParameter($uid)));

		return $this->findEntity($select);
	}

	/**
	 * @param IUser $user
	 * @param string|null $uid
	 * @param string|null $email
	 * @param string|null $cloudId
	 *
	 * @return RecentContact[]
	 */
	public function findMatch(IUser $user,
							  ?string $uid,
							  ?string $email,
							  ?string $cloudId): array {
		$qb = $this->db->getQueryBuilder();

		$or = $qb->expr()->orX();
		if ($uid !== null) {
			$or->add($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		}
		if ($email !== null) {
			$or->add($qb->expr()->eq('email', $qb->createNamedParameter($email)));
		}
		if ($cloudId !== null) {
			$or->add($qb->expr()->eq('federated_cloud_id', $qb->createNamedParameter($cloudId)));
		}

		$select = $qb
			->select('*')
			->from($this->getTableName())
			->where($or)
			->andWhere($qb->expr()->eq('actor_uid', $qb->createNamedParameter($user->getUID())));

		return $this->findEntities($select);
	}

	public function cleanUp(int $olderThan): void {
		$qb = $this->db->getQueryBuilder();

		$delete = $qb
			->delete($this->getTableName())
			->where($qb->expr()->lt('last_contact', $qb->createNamedParameter($olderThan)));

		$delete->execute();
	}

}
