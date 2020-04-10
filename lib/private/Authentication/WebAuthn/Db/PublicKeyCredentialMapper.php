<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Authentication\WebAuthn\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PublicKeyCredentialMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'webauthn', PublicKeyCredentialEntity::class);
	}

	public function findOneByCredentialId(string $publicKeyCredentialId): PublicKeyCredentialEntity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('public_key_credential_id', $qb->createNamedParameter(base64_encode($publicKeyCredentialId)))
			);

		return $this->findEntity($qb);
	}

	/**
	 * @return PublicKeyCredentialEntity[]
	 */
	public function findAllForUid(string $uid): array {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($uid))
			);

		return $this->findEntities($qb);
	}

	/**
	 * @param string $uid
	 * @param int $id
	 *
	 * @return PublicKeyCredentialEntity
	 * @throws DoesNotExistException
	 */
	public function findById(string $uid, int $id): PublicKeyCredentialEntity {
		$qb = $this->db->getQueryBuilder();

		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->andX(
				$qb->expr()->eq('id', $qb->createNamedParameter($id)),
				$qb->expr()->eq('uid', $qb->createNamedParameter($uid))
			));

		return $this->findEntity($qb);
	}

}
