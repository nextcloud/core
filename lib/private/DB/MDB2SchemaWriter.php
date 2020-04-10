<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
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

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;

class MDB2SchemaWriter {

	/**
	 * @param string $file
	 * @param \OC\DB\Connection $conn
	 * @return bool
	 */
	static public function saveSchemaToFile($file, \OC\DB\Connection $conn) {
		$config = \OC::$server->getConfig();

		$xml = new \SimpleXMLElement('<database/>');
		$xml->addChild('name', $config->getSystemValue('dbname', 'owncloud'));
		$xml->addChild('create', 'true');
		$xml->addChild('overwrite', 'false');
		if($config->getSystemValue('dbtype', 'sqlite') === 'mysql' && $config->getSystemValue('mysql.utf8mb4', false)) {
			$xml->addChild('charset', 'utf8mb4');
		} else {
			$xml->addChild('charset', 'utf8');
		}

		// FIX ME: bloody work around
		if ($config->getSystemValue('dbtype', 'sqlite') === 'oci') {
			$filterExpression = '/^"' . preg_quote($conn->getPrefix()) . '/';
		} else {
			$filterExpression = '/^' . preg_quote($conn->getPrefix()) . '/';
		}
		$conn->getConfiguration()->setFilterSchemaAssetsExpression($filterExpression);

		foreach ($conn->getSchemaManager()->listTables() as $table) {
			self::saveTable($table, $xml->addChild('table'));
		}
		file_put_contents($file, $xml->asXML());
		return true;
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 * @param \SimpleXMLElement $xml
	 */
	private static function saveTable($table, $xml) {
		$xml->addChild('name', $table->getName());
		$declaration = $xml->addChild('declaration');
		foreach($table->getColumns() as $column) {
			self::saveColumn($column, $declaration->addChild('field'));
		}
		foreach($table->getIndexes() as $index) {
			if ($index->getName() == 'PRIMARY') {
				$autoincrement = false;
				foreach($index->getColumns() as $column) {
					if ($table->getColumn($column)->getAutoincrement()) {
						$autoincrement = true;
					}
				}
				if ($autoincrement) {
					continue;
				}
			}
			self::saveIndex($index, $declaration->addChild('index'));
		}
	}

	/**
	 * @param Column $column
	 * @param \SimpleXMLElement $xml
	 */
	private static function saveColumn($column, $xml) {
		$xml->addChild('name', $column->getName());
		switch($column->getType()) {
			case 'SmallInt':
			case 'Integer':
			case 'BigInt':
				$xml->addChild('type', 'integer');
				$default = $column->getDefault();
				if (is_null($default) && $column->getAutoincrement()) {
					$default = '0';
				}
				$xml->addChild('default', $default);
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				if ($column->getAutoincrement()) {
					$xml->addChild('autoincrement', '1');
				}
				if ($column->getUnsigned()) {
					$xml->addChild('unsigned', 'true');
				}
				$length = '4';
				if ($column->getType() == 'SmallInt') {
					$length = '2';
				}
				elseif ($column->getType() == 'BigInt') {
					$length = '8';
				}
				$xml->addChild('length', $length);
				break;
			case 'String':
				$xml->addChild('type', 'text');
				$default = trim($column->getDefault());
				if ($default === '') {
					$default = false;
				}
				$xml->addChild('default', $default);
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				$xml->addChild('length', $column->getLength());
				break;
			case 'Text':
				$xml->addChild('type', 'clob');
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				break;
			case 'Decimal':
				$xml->addChild('type', 'decimal');
				$xml->addChild('default', $column->getDefault());
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				$xml->addChild('length', '15');
				break;
			case 'Boolean':
				$xml->addChild('type', 'integer');
				$xml->addChild('default', $column->getDefault());
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				$xml->addChild('length', '1');
				break;
			case 'DateTime':
				$xml->addChild('type', 'timestamp');
				$xml->addChild('default', $column->getDefault());
				$xml->addChild('notnull', self::toBool($column->getNotnull()));
				break;

		}
	}

	/**
	 * @param Index $index
	 * @param \SimpleXMLElement $xml
	 */
	private static function saveIndex($index, $xml) {
		$xml->addChild('name', $index->getName());
		if ($index->isPrimary()) {
			$xml->addChild('primary', 'true');
		}
		elseif ($index->isUnique()) {
			$xml->addChild('unique', 'true');
		}
		foreach($index->getColumns() as $column) {
			$field = $xml->addChild('field');
			$field->addChild('name', $column);
			$field->addChild('sorting', 'ascending');
			
		}
	}

	private static function toBool($bool) {
		return $bool ? 'true' : 'false';
	}
}
