<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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
namespace OC\DB;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;
use Doctrine\DBAL\Event\Listeners\SQLSessionInit;
use OC\SystemConfig;

/**
 * Takes care of creating and configuring Doctrine connections.
 */
class ConnectionFactory {
	/** @var string default database name */
	public const DEFAULT_DBNAME = 'owncloud';

	/** @var string default database table prefix */
	public const DEFAULT_DBTABLEPREFIX = 'oc_';

	/**
	 * @var array
	 *
	 * Array mapping DBMS type to default connection parameters passed to
	 * \Doctrine\DBAL\DriverManager::getConnection().
	 */
	protected $defaultConnectionParams = [
		'mysql' => [
			'adapter' => AdapterMySQL::class,
			'charset' => 'UTF8',
			'driver' => 'pdo_mysql',
			'wrapperClass' => Connection::class,
		],
		'oci' => [
			'adapter' => AdapterOCI8::class,
			'charset' => 'AL32UTF8',
			'driver' => 'oci8',
			'wrapperClass' => OracleConnection::class,
		],
		'pgsql' => [
			'adapter' => AdapterPgSql::class,
			'driver' => 'pdo_pgsql',
			'wrapperClass' => Connection::class,
		],
		'sqlite3' => [
			'adapter' => AdapterSqlite::class,
			'driver' => 'pdo_sqlite',
			'wrapperClass' => Connection::class,
		],
	];

	/** @var SystemConfig */
	private $config;

	/**
	 * ConnectionFactory constructor.
	 *
	 * @param SystemConfig $systemConfig
	 */
	public function __construct(SystemConfig $systemConfig) {
		$this->config = $systemConfig;
		if ($this->config->getValue('mysql.utf8mb4', false)) {
			$this->defaultConnectionParams['mysql']['charset'] = 'utf8mb4';
		}
	}

	/**
	 * @brief Get default connection parameters for a given DBMS.
	 * @param string $type DBMS type
	 * @throws \InvalidArgumentException If $type is invalid
	 * @return array Default connection parameters.
	 */
	public function getDefaultConnectionParams($type) {
		$normalizedType = $this->normalizeType($type);
		if (!isset($this->defaultConnectionParams[$normalizedType])) {
			throw new \InvalidArgumentException("Unsupported type: $type");
		}
		$result = $this->defaultConnectionParams[$normalizedType];
		// \PDO::MYSQL_ATTR_FOUND_ROWS may not be defined, e.g. when the MySQL
		// driver is missing. In this case, we won't be able to connect anyway.
		if ($normalizedType === 'mysql' && defined('\PDO::MYSQL_ATTR_FOUND_ROWS')) {
			$result['driverOptions'] = [
				\PDO::MYSQL_ATTR_FOUND_ROWS => true,
			];
		}
		return $result;
	}

	/**
	 * @brief Get default connection parameters for a given DBMS.
	 * @param string $type DBMS type
	 * @param array $additionalConnectionParams Additional connection parameters
	 * @return \OC\DB\Connection
	 */
	public function getConnection($type, $additionalConnectionParams) {
		$normalizedType = $this->normalizeType($type);
		$eventManager = new EventManager();
		$eventManager->addEventSubscriber(new SetTransactionIsolationLevel());
		switch ($normalizedType) {
			case 'mysql':
				$eventManager->addEventSubscriber(
					new SQLSessionInit("SET SESSION AUTOCOMMIT=1"));
				break;
			case 'oci':
				$eventManager->addEventSubscriber(new OracleSessionInit);
				// the driverOptions are unused in dbal and need to be mapped to the parameters
				if (isset($additionalConnectionParams['driverOptions'])) {
					$additionalConnectionParams = array_merge($additionalConnectionParams, $additionalConnectionParams['driverOptions']);
				}
				$host = $additionalConnectionParams['host'];
				$port = isset($additionalConnectionParams['port']) ? $additionalConnectionParams['port'] : null;
				$dbName = $additionalConnectionParams['dbname'];

				// we set the connect string as dbname and unset the host to coerce doctrine into using it as connect string
				if ($host === '') {
					$additionalConnectionParams['dbname'] = $dbName; // use dbname as easy connect name
				} else {
					$additionalConnectionParams['dbname'] = '//' . $host . (!empty($port) ? ":{$port}" : "") . '/' . $dbName;
				}
				unset($additionalConnectionParams['host']);
				break;

			case 'sqlite3':
				$journalMode = $additionalConnectionParams['sqlite.journal_mode'];
				$additionalConnectionParams['platform'] = new OCSqlitePlatform();
				$eventManager->addEventSubscriber(new SQLiteSessionInit(true, $journalMode));
				break;
		}
		/** @var Connection $connection */
		$connection = DriverManager::getConnection(
			array_merge($this->getDefaultConnectionParams($type), $additionalConnectionParams),
			new Configuration(),
			$eventManager
		);
		return $connection;
	}

	/**
	 * @brief Normalize DBMS type
	 * @param string $type DBMS type
	 * @return string Normalized DBMS type
	 */
	public function normalizeType($type) {
		return $type === 'sqlite' ? 'sqlite3' : $type;
	}

	/**
	 * Checks whether the specified DBMS type is valid.
	 *
	 * @param string $type
	 * @return bool
	 */
	public function isValidType($type) {
		$normalizedType = $this->normalizeType($type);
		return isset($this->defaultConnectionParams[$normalizedType]);
	}

	/**
	 * Create the connection parameters for the config
	 *
	 * @return array
	 */
	public function createConnectionParams() {
		$type = $this->config->getValue('dbtype', 'sqlite');

		$connectionParams = [
			'user' => $this->config->getValue('dbuser', ''),
			'password' => $this->config->getValue('dbpassword', ''),
		];
		$name = $this->config->getValue('dbname', self::DEFAULT_DBNAME);

		if ($this->normalizeType($type) === 'sqlite3') {
			$dataDir = $this->config->getValue("datadirectory", \OC::$SERVERROOT . '/data');
			$connectionParams['path'] = $dataDir . '/' . $name . '.db';
		} else {
			$host = $this->config->getValue('dbhost', '');
			$connectionParams = array_merge($connectionParams, $this->splitHostFromPortAndSocket($host));
			$connectionParams['dbname'] = $name;
		}

		$connectionParams['tablePrefix'] = $this->config->getValue('dbtableprefix', self::DEFAULT_DBTABLEPREFIX);
		$connectionParams['sqlite.journal_mode'] = $this->config->getValue('sqlite.journal_mode', 'WAL');

		//additional driver options, eg. for mysql ssl
		$driverOptions = $this->config->getValue('dbdriveroptions', null);
		if ($driverOptions) {
			$connectionParams['driverOptions'] = $driverOptions;
		}

		// set default table creation options
		$defaultTableOptions = [
			'collate' => 'utf8_bin',
			'tablePrefix' => $connectionParams['tablePrefix']
		];

		if ($this->config->getValue('mysql.utf8mb4', false)) {
			$defaultTableOptions = [
				'collate' => 'utf8mb4_bin',
				'charset' => 'utf8mb4',
				'row_format' => 'compressed',
				'tablePrefix' => $connectionParams['tablePrefix']
			];
		}

		$configTableOptions = $this->config->getValue('dbdefaulttableoptions', []);

		// prevent user from overriding collation / charset and tablePrefix here (previous behavior.)
		if (array_key_exists('collate', $configTableOptions)) {
			unset($configTableOptions['collate']);
		}
		if (array_key_exists('charset', $configTableOptions)) {
			unset($configTableOptions['charset']);
		}
		if (array_key_exists('tablePrefix', $configTableOptions)) {
			unset($configTableOptions['tablePrefix']);
		}

		$connectionParams['defaultTableOptions'] = array_merge($defaultTableOptions, $configTableOptions);

		return $connectionParams;
	}

	/**
	 * @param string $host
	 * @return array
	 */
	protected function splitHostFromPortAndSocket($host): array {
		$params = [
			'host' => $host,
		];

		$matches = [];
		if (preg_match('/^(.*):([^\]:]+)$/', $host, $matches)) {
			// Host variable carries a port or socket.
			$params['host'] = $matches[1];
			if (is_numeric($matches[2])) {
				$params['port'] = (int) $matches[2];
			} else {
				$params['unix_socket'] = $matches[2];
			}
		}

		return $params;
	}
}
