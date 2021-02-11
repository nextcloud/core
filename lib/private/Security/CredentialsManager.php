<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Security;

use OCP\IDBConnection;
use OCP\Security\ICredentialsManager;
use OCP\Security\ICrypto;

/**
 * Store and retrieve credentials for external services
 *
 * @package OC\Security
 */
class CredentialsManager implements ICredentialsManager {
	public const DB_TABLE = 'storages_credentials';

	/** @var ICrypto */
	protected $crypto;

	/** @var IDBConnection */
	protected $dbConnection;

	/**
	 * @param ICrypto $crypto
	 * @param IDBConnection $dbConnection
	 */
	public function __construct(ICrypto $crypto, IDBConnection $dbConnection) {
		$this->crypto = $crypto;
		$this->dbConnection = $dbConnection;
	}

	/**
	 * Store a set of credentials
	 *
	 * @param string $userId empty string for system-wide credentials
	 * @param string $identifier
	 * @param mixed $credentials
	 */
	public function store($userId, $identifier, $credentials) {
		$value = $this->crypto->encrypt(json_encode($credentials));

		$this->dbConnection->setValues(self::DB_TABLE, [
			'user' => (string)$userId,
			'identifier' => $identifier,
		], [
			'credentials' => $value,
		]);
	}

	/**
	 * Retrieve a set of credentials
	 *
	 * @param string $userId empty string for system-wide credentials
	 * @param string $identifier
	 * @return mixed
	 */
	public function retrieve($userId, $identifier) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('credentials')
			->from(self::DB_TABLE)
			->where($qb->expr()->eq('identifier', $qb->createNamedParameter($identifier)));

		if ($userId === '') {
			$qb->andWhere($qb->expr()->emptyString('user'));
		} else {
			$qb->andWhere($qb->expr()->eq('user', $qb->createNamedParameter((string)$userId)));
		}

		$qResult = $qb->execute();
		$result = $qResult->fetch();
		$qResult->closeCursor();

		if (!$result) {
			return null;
		}
		$value = $result['credentials'];

		return json_decode($this->crypto->decrypt($value), true);
	}

	/**
	 * Delete a set of credentials
	 *
	 * @param string $userId empty string for system-wide credentials
	 * @param string $identifier
	 * @return int rows removed
	 */
	public function delete($userId, $identifier) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::DB_TABLE)
			->where($qb->expr()->eq('identifier', $qb->createNamedParameter($identifier)));

		if ($userId === '') {
			$qb->andWhere($qb->expr()->emptyString('user'));
		} else {
			$qb->andWhere($qb->expr()->eq('user', $qb->createNamedParameter((string)$userId)));
		}

		return $qb->execute();
	}

	/**
	 * Erase all credentials stored for a user
	 *
	 * @param string $userId
	 * @return int rows removed
	 */
	public function erase($userId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete(self::DB_TABLE)
			->where($qb->expr()->eq('user', $qb->createNamedParameter($userId)))
		;
		return $qb->execute();
	}
}
