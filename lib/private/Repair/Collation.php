<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin Müller <coder-hugo@users.noreply.github.com>
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

namespace OC\Repair;

use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class Collation implements IRepairStep {
	/**  @var IConfig */
	protected $config;

	/** @var ILogger */
	protected $logger;

	/** @var IDBConnection */
	protected $connection;

	/** @var bool */
	protected $ignoreFailures;

	/**
	 * @param IConfig $config
	 * @param ILogger $logger
	 * @param IDBConnection $connection
	 * @param bool $ignoreFailures
	 */
	public function __construct(IConfig $config, ILogger $logger, IDBConnection $connection, $ignoreFailures) {
		$this->connection = $connection;
		$this->config = $config;
		$this->logger = $logger;
		$this->ignoreFailures = $ignoreFailures;
	}

	public function getName() {
		return 'Repair MySQL collation';
	}

	/**
	 * Fix mime types
	 */
	public function run(IOutput $output) {
		if (!$this->connection->getDatabasePlatform() instanceof MySqlPlatform) {
			$output->info('Not a mysql database -> nothing to do');
			return;
		}

		$characterSet = $this->config->getSystemValue('mysql.utf8mb4', false) ? 'utf8mb4' : 'utf8';

		$tables = $this->getAllNonUTF8BinTables($this->connection);
		foreach ($tables as $table) {
			$output->info("Change row format for $table ...");
			$query = $this->connection->prepare('ALTER TABLE `' . $table . '` ROW_FORMAT = DYNAMIC;');
			try {
				$query->execute();
			} catch (DriverException $e) {
				// Just log this
				$this->logger->logException($e);
				if (!$this->ignoreFailures) {
					throw $e;
				}
			}

			$output->info("Change collation for $table ...");
			if ($characterSet === 'utf8mb4') {
				// need to set row compression first
				$query = $this->connection->prepare('ALTER TABLE `' . $table . '` ROW_FORMAT=COMPRESSED;');
				$query->execute();
			}
			$query = $this->connection->prepare('ALTER TABLE `' . $table . '` CONVERT TO CHARACTER SET ' . $characterSet . ' COLLATE ' . $characterSet . '_bin;');
			try {
				$query->execute();
			} catch (DriverException $e) {
				// Just log this
				$this->logger->logException($e);
				if (!$this->ignoreFailures) {
					throw $e;
				}
			}
		}
		if (empty($tables)) {
			$output->info('All tables already have the correct collation -> nothing to do');
		}
	}

	/**
	 * @param IDBConnection $connection
	 * @return string[]
	 */
	protected function getAllNonUTF8BinTables(IDBConnection $connection) {
		$dbName = $this->config->getSystemValue("dbname");
		$characterSet = $this->config->getSystemValue('mysql.utf8mb4', false) ? 'utf8mb4' : 'utf8';

		// fetch tables by columns
		$statement = $connection->executeQuery(
			"SELECT DISTINCT(TABLE_NAME) AS `table`" .
			"	FROM INFORMATION_SCHEMA . COLUMNS" .
			"	WHERE TABLE_SCHEMA = ?" .
			"	AND (COLLATION_NAME <> '" . $characterSet . "_bin' OR CHARACTER_SET_NAME <> '" . $characterSet . "')" .
			"	AND TABLE_NAME LIKE '*PREFIX*%'",
			[$dbName]
		);
		$rows = $statement->fetchAll();
		$result = [];
		foreach ($rows as $row) {
			$result[$row['table']] = true;
		}

		// fetch tables by collation
		$statement = $connection->executeQuery(
			"SELECT DISTINCT(TABLE_NAME) AS `table`" .
			"	FROM INFORMATION_SCHEMA . TABLES" .
			"	WHERE TABLE_SCHEMA = ?" .
			"	AND TABLE_COLLATION <> '" . $characterSet . "_bin'" .
			"	AND TABLE_NAME LIKE '*PREFIX*%'",
			[$dbName]
		);
		$rows = $statement->fetchAll();
		foreach ($rows as $row) {
			$result[$row['table']] = true;
		}

		return array_keys($result);
	}
}
