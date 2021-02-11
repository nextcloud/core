<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Federation;

use OC\Files\Filesystem;
use OC\HintException;
use OCP\IDBConnection;
use OCP\IL10N;

/**
 * Class DbHandler
 *
 * handles all database calls for the federation app
 *
 * @group DB
 * @package OCA\Federation
 */
class DbHandler {

	/** @var  IDBConnection */
	private $connection;

	/** @var  IL10N */
	private $IL10N;

	/** @var string  */
	private $dbTable = 'trusted_servers';

	/**
	 * @param IDBConnection $connection
	 * @param IL10N $il10n
	 */
	public function __construct(
		IDBConnection $connection,
		IL10N $il10n
	) {
		$this->connection = $connection;
		$this->IL10N = $il10n;
	}

	/**
	 * add server to the list of trusted servers
	 *
	 * @param string $url
	 * @return int
	 * @throws HintException
	 */
	public function addServer($url) {
		$hash = $this->hash($url);
		$url = rtrim($url, '/');
		$query = $this->connection->getQueryBuilder();
		$query->insert($this->dbTable)
			->values(
				[
					'url' => $query->createParameter('url'),
					'url_hash' => $query->createParameter('url_hash'),
				]
			)
			->setParameter('url', $url)
			->setParameter('url_hash', $hash);

		$result = $query->execute();

		if ($result) {
			return (int)$this->connection->lastInsertId('*PREFIX*'.$this->dbTable);
		}

		$message = 'Internal failure, Could not add trusted server: ' . $url;
		$message_t = $this->IL10N->t('Could not add server');
		throw new HintException($message, $message_t);
	}

	/**
	 * remove server from the list of trusted servers
	 *
	 * @param int $id
	 */
	public function removeServer($id) {
		$query = $this->connection->getQueryBuilder();
		$query->delete($this->dbTable)
			->where($query->expr()->eq('id', $query->createParameter('id')))
			->setParameter('id', $id);
		$query->execute();
	}

	/**
	 * get trusted server with given ID
	 *
	 * @param int $id
	 * @return array
	 * @throws \Exception
	 */
	public function getServerById($id) {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')->from($this->dbTable)
			->where($query->expr()->eq('id', $query->createParameter('id')))
			->setParameter('id', $id);

		$qResult = $query->execute();
		$result = $qResult->fetchAll();
		$qResult->closeCursor();

		if (empty($result)) {
			throw new \Exception('No Server found with ID: ' . $id);
		}

		return $result[0];
	}

	/**
	 * get all trusted servers
	 *
	 * @return array
	 */
	public function getAllServer() {
		$query = $this->connection->getQueryBuilder();
		$query->select(['url', 'url_hash', 'id', 'status', 'shared_secret', 'sync_token'])
			->from($this->dbTable);
		$statement = $query->execute();
		$result = $statement->fetchAll();
		$statement->closeCursor();
		return $result;
	}

	/**
	 * check if server already exists in the database table
	 *
	 * @param string $url
	 * @return bool
	 */
	public function serverExists($url) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->select('url')
			->from($this->dbTable)
			->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
			->setParameter('url_hash', $hash);
		$statement = $query->execute();
		$result = $statement->fetchAll();
		$statement->closeCursor();

		return !empty($result);
	}

	/**
	 * write token to database. Token is used to exchange the secret
	 *
	 * @param string $url
	 * @param string $token
	 */
	public function addToken($url, $token) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->update($this->dbTable)
			->set('token', $query->createParameter('token'))
			->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
			->setParameter('url_hash', $hash)
			->setParameter('token', $token);
		$query->execute();
	}

	/**
	 * get token stored in database
	 *
	 * @param string $url
	 * @return string
	 * @throws \Exception
	 */
	public function getToken($url) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->select('token')->from($this->dbTable)
			->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
			->setParameter('url_hash', $hash);

		$statement = $query->execute();
		$result = $statement->fetch();
		$statement->closeCursor();

		if (!isset($result['token'])) {
			throw new \Exception('No token found for: ' . $url);
		}

		return $result['token'];
	}

	/**
	 * add shared Secret to database
	 *
	 * @param string $url
	 * @param string $sharedSecret
	 */
	public function addSharedSecret($url, $sharedSecret) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->update($this->dbTable)
			->set('shared_secret', $query->createParameter('sharedSecret'))
			->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
			->setParameter('url_hash', $hash)
			->setParameter('sharedSecret', $sharedSecret);
		$query->execute();
	}

	/**
	 * get shared secret from database
	 *
	 * @param string $url
	 * @return string
	 */
	public function getSharedSecret($url) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->select('shared_secret')->from($this->dbTable)
			->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
			->setParameter('url_hash', $hash);

		$statement = $query->execute();
		$result = $statement->fetch();
		$statement->closeCursor();
		return $result['shared_secret'];
	}

	/**
	 * set server status
	 *
	 * @param string $url
	 * @param int $status
	 * @param string|null $token
	 */
	public function setServerStatus($url, $status, $token = null) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->update($this->dbTable)
				->set('status', $query->createNamedParameter($status))
				->where($query->expr()->eq('url_hash', $query->createNamedParameter($hash)));
		if (!is_null($token)) {
			$query->set('sync_token', $query->createNamedParameter($token));
		}
		$query->execute();
	}

	/**
	 * get server status
	 *
	 * @param string $url
	 * @return int
	 */
	public function getServerStatus($url) {
		$hash = $this->hash($url);
		$query = $this->connection->getQueryBuilder();
		$query->select('status')->from($this->dbTable)
				->where($query->expr()->eq('url_hash', $query->createParameter('url_hash')))
				->setParameter('url_hash', $hash);

		$statement = $query->execute();
		$result = $statement->fetch();
		$statement->closeCursor();
		return (int)$result['status'];
	}

	/**
	 * create hash from URL
	 *
	 * @param string $url
	 * @return string
	 */
	protected function hash($url) {
		$normalized = $this->normalizeUrl($url);
		return sha1($normalized);
	}

	/**
	 * normalize URL, used to create the sha1 hash
	 *
	 * @param string $url
	 * @return string
	 */
	protected function normalizeUrl($url) {
		$normalized = $url;

		if (strpos($url, 'https://') === 0) {
			$normalized = substr($url, strlen('https://'));
		} elseif (strpos($url, 'http://') === 0) {
			$normalized = substr($url, strlen('http://'));
		}

		$normalized = Filesystem::normalizePath($normalized);
		$normalized = trim($normalized, '/');

		return $normalized;
	}

	/**
	 * @param $username
	 * @param $password
	 * @return bool
	 */
	public function auth($username, $password) {
		if ($username !== 'system') {
			return false;
		}
		$query = $this->connection->getQueryBuilder();
		$query->select('url')->from($this->dbTable)
				->where($query->expr()->eq('shared_secret', $query->createNamedParameter($password)));

		$statement = $query->execute();
		$result = $statement->fetch();
		$statement->closeCursor();
		return !empty($result);
	}
}
