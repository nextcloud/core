<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author tbelau666 <thomas.belau@gmx.de>
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

use Doctrine\DBAL\Schema\AbstractAsset;
use OCP\IConfig;
use function preg_match;
use function preg_quote;

/**
 * Various PostgreSQL specific helper functions.
 */
class PgSqlTools {

	/** @var \OCP\IConfig */
	private $config;

	/**
	 * @param \OCP\IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @brief Resynchronizes all sequences of a database after using INSERTs
	 *        without leaving out the auto-incremented column.
	 * @param \OC\DB\Connection $conn
	 * @return null
	 */
	public function resynchronizeDatabaseSequences(Connection $conn) {
		$databaseName = $conn->getDatabase();
		$conn->getConfiguration()->setSchemaAssetsFilter(function ($asset) {
			/** @var string|AbstractAsset $asset */
			$filterExpression = '/^' . preg_quote($this->config->getSystemValue('dbtableprefix', 'oc_')) . '/';
			if ($asset instanceof AbstractAsset) {
				return preg_match($filterExpression, $asset->getName()) !== false;
			}
			return preg_match($filterExpression, $asset) !== false;
		});

		foreach ($conn->getSchemaManager()->listSequences() as $sequence) {
			$sequenceName = $sequence->getName();
			$sqlInfo = 'SELECT table_schema, table_name, column_name
				FROM information_schema.columns
				WHERE column_default = ? AND table_catalog = ?';
			$result = $conn->executeQuery($sqlInfo, [
				"nextval('$sequenceName'::regclass)",
				$databaseName
			]);
			$sequenceInfo = $result->fetchAssociative();
			$result->free();
			/** @var string $tableName */
			$tableName = $sequenceInfo['table_name'];
			/** @var string $columnName */
			$columnName = $sequenceInfo['column_name'];
			$sqlMaxId = "SELECT MAX($columnName) FROM $tableName";
			$sqlSetval = "SELECT setval('$sequenceName', ($sqlMaxId))";
			$conn->executeQuery($sqlSetval);
		}
	}
}
