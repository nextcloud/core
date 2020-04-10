<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OC\Setup;

use OC\DB\ConnectionFactory;

class Sqlite extends AbstractDatabase {
	public $dbprettyname = 'Sqlite';

	public function validate($config) {
		return [];
	}

	public function initialize($config) {
		/*
		 * Web: When using web based installer its not possible to set dbname
		 * or dbtableprefix. Defaults used from ConnectionFactory and dbtype = 'sqlite'
		 * is written to config.php.
		 *
		 * Cli: When --database-name or --database-table-prefix empty or default
		 * dbtype = 'sqlite' is written to config.php. If you choose a value different
		 * from default these values are written to config.php. This is required because
		 * in connection factory configuration is obtained from config.php.
		 */

		$this->dbName = empty($config['dbname'])
			? ConnectionFactory::DEFAULT_DBNAME
			: $config['dbname'];

		$this->tablePrefix = empty($config['dbtableprefix'])
			? ConnectionFactory::DEFAULT_DBTABLEPREFIX
			: $config['dbtableprefix'];

		if ($this->dbName !== ConnectionFactory::DEFAULT_DBNAME) {
			$this->config->setValue('dbname', $this->dbName);
		}

		if ($this->tablePrefix !== ConnectionFactory::DEFAULT_DBTABLEPREFIX) {
			$this->config->setValue('dbtableprefix', $this->tablePrefix);
		}
	}

	public function setupDatabase($username) {
		$datadir = $this->config->getValue(
			'datadirectory',
			\OC::$SERVERROOT . '/data'
		);

		$sqliteFile = $datadir . '/' . $this->dbName . 'db';
		if (file_exists($sqliteFile)) {
			unlink($sqliteFile);
		}
	}
}
