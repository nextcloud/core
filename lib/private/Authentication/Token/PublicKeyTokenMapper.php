<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OC\Authentication\Token;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class PublicKeyTokenMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'authtoken');
	}

	/**
	 * Invalidate (delete) a given token
	 *
	 * @param string $token
	 */
	public function invalidate(string $token) {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->delete('authtoken')
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
			->execute();
	}

	/**
	 * @param int $olderThan
	 * @param int $remember
	 */
	public function invalidateOld(int $olderThan, int $remember = IToken::DO_NOT_REMEMBER) {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->delete('authtoken')
			->where($qb->expr()->lt('last_activity', $qb->createNamedParameter($olderThan, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(IToken::TEMPORARY_TOKEN, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('remember', $qb->createNamedParameter($remember, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
			->execute();
	}

	/**
	 * Get the user UID for the given token
	 *
	 * @throws DoesNotExistException
	 */
	public function getToken(string $token): PublicKeyToken {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from('authtoken')
			->where($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
			->execute();

		$data = $result->fetch();
		$result->closeCursor();
		if ($data === false) {
			throw new DoesNotExistException('token does not exist');
		}
		return PublicKeyToken::fromRow($data);
	}

	/**
	 * Get the token for $id
	 *
	 * @throws DoesNotExistException
	 */
	public function getTokenById(int $id): PublicKeyToken {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')
			->from('authtoken')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
			->execute();

		$data = $result->fetch();
		$result->closeCursor();
		if ($data === false) {
			throw new DoesNotExistException('token does not exist');
		}
		return PublicKeyToken::fromRow($data);
	}

	/**
	 * Get all tokens of a user
	 *
	 * The provider may limit the number of result rows in case of an abuse
	 * where a high number of (session) tokens is generated
	 *
	 * @param string $uid
	 * @return PublicKeyToken[]
	 */
	public function getTokenByUser(string $uid): array {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('authtoken')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1000);
		$result = $qb->execute();
		$data = $result->fetchAll();
		$result->closeCursor();

		$entities = array_map(function ($row) {
			return PublicKeyToken::fromRow($row);
		}, $data);

		return $entities;
	}

	public function deleteById(string $uid, int $id) {
		/* @var $qb IQueryBuilder */
		$qb = $this->db->getQueryBuilder();
		$qb->delete('authtoken')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	/**
	 * delete all auth token which belong to a specific client if the client was deleted
	 *
	 * @param string $name
	 */
	public function deleteByName(string $name) {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('authtoken')
			->where($qb->expr()->eq('name', $qb->createNamedParameter($name), IQueryBuilder::PARAM_STR))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)));
		$qb->execute();
	}

	public function deleteTempToken(PublicKeyToken $except) {
		$qb = $this->db->getQueryBuilder();

		$qb->delete('authtoken')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($except->getUID())))
			->andWhere($qb->expr()->eq('type', $qb->createNamedParameter(IToken::TEMPORARY_TOKEN)))
			->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($except->getId())))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter(PublicKeyToken::VERSION, IQueryBuilder::PARAM_INT)));

		$qb->execute();
	}

	public function hasExpiredTokens(string $uid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('authtoken')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('password_invalid', $qb->createNamedParameter(true), IQueryBuilder::PARAM_BOOL))
			->setMaxResults(1);

		$cursor = $qb->execute();
		$data = $cursor->fetchAll();
		$cursor->closeCursor();

		return count($data) === 1;
	}
}
