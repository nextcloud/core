<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\OAuth2\Db;

use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\AppFramework\Db\IMapperException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ClientMapper extends QBMapper {

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'oauth2_clients');
	}

	/**
	 * @param string $clientIdentifier
	 * @return Client
	 * @throws ClientNotFoundException
	 */
	public function getByIdentifier(string $clientIdentifier): Client {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('client_identifier', $qb->createNamedParameter($clientIdentifier)));

		try {
			$client = $this->findEntity($qb);
		} catch (IMapperException $e) {
			throw new ClientNotFoundException('could not find client '.$clientIdentifier, 0, $e);
		}
		return $client;
	}

	/**
	 * @param int $id internal id of the client
	 * @return Client
	 * @throws ClientNotFoundException
	 */
	public function getByUid(int $id): Client {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			$client = $this->findEntity($qb);
		} catch (IMapperException $e) {
			throw new ClientNotFoundException('could not find client with id '.$id, 0, $e);
		}
		return $client;
	}

	/**
	 * @return Client[]
	 */
	public function getClients(): array {
		$qb = $this->db->getQueryBuilder();
		$qb
			->select('*')
			->from($this->tableName);

		return $this->findEntities($qb);
	}
}
