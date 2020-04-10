<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Core\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

class LoginFlowV2Mapper extends QBMapper {
	private const lifetime = 1200;

	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IDBConnection $db, ITimeFactory $timeFactory) {
		parent::__construct($db, 'login_flow_v2', LoginFlowV2::class);
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @param string $pollToken
	 * @return LoginFlowV2
	 * @throws DoesNotExistException
	 */
	public function getByPollToken(string $pollToken): LoginFlowV2 {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('poll_token', $qb->createNamedParameter($pollToken))
			);

		$entity = $this->findEntity($qb);
		return $this->validateTimestamp($entity);
	}

	/**
	 * @param string $loginToken
	 * @return LoginFlowV2
	 * @throws DoesNotExistException
	 */
	public function getByLoginToken(string $loginToken): LoginFlowV2 {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('login_token', $qb->createNamedParameter($loginToken))
			);

		$entity = $this->findEntity($qb);
		return $this->validateTimestamp($entity);
	}

	public function cleanup(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->lt('timestamp', $qb->createNamedParameter($this->timeFactory->getTime() - self::lifetime))
			);

		$qb->execute();
	}

	/**
	 * @param LoginFlowV2 $flowV2
	 * @return LoginFlowV2
	 * @throws DoesNotExistException
	 */
	private function validateTimestamp(LoginFlowV2 $flowV2): LoginFlowV2 {
		if ($flowV2->getTimestamp() < ($this->timeFactory->getTime() - self::lifetime)) {
			$this->delete($flowV2);
			throw new DoesNotExistException('Token expired');
		}

		return $flowV2;
	}
}
