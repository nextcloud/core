<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Oliver Gasser <oliver.gasser@gmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use OCP\IConfig;

class MDB2SchemaReader {

	/**
	 * @var string $DBTABLEPREFIX
	 */
	protected $DBTABLEPREFIX;

	/**
	 * @var \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 */
	protected $platform;

	/** @var IConfig */
	protected $config;

	/**
	 * @param \OCP\IConfig $config
	 * @param \Doctrine\DBAL\Platforms\AbstractPlatform $platform
	 */
	public function __construct(IConfig $config, AbstractPlatform $platform) {
		$this->platform = $platform;
		$this->config = $config;
		$this->DBTABLEPREFIX = $config->getSystemValue('dbtableprefix', 'oc_');
	}

	/**
	 * @param string $file
	 * @param Schema $schema
	 * @return Schema
	 * @throws \DomainException
	 */
	public function loadSchemaFromFile($file, Schema $schema) {
		$loadEntities = libxml_disable_entity_loader(false);
		$xml = simplexml_load_file($file);
		libxml_disable_entity_loader($loadEntities);
		foreach ($xml->children() as $child) {
			/**
			 * @var \SimpleXMLElement $child
			 */
			switch ($child->getName()) {
				case 'name':
				case 'create':
				case 'overwrite':
				case 'charset':
					break;
				case 'table':
					$this->loadTable($schema, $child);
					break;
				default:
					throw new \DomainException('Unknown element: ' . $child->getName());

			}
		}
		return $schema;
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Schema $schema
	 * @param \SimpleXMLElement $xml
	 * @throws \DomainException
	 */
	private function loadTable($schema, $xml) {
		$table = null;
		foreach ($xml->children() as $child) {
			/**
			 * @var \SimpleXMLElement $child
			 */
			switch ($child->getName()) {
				case 'name':
					$name = (string)$child;
					$name = str_replace('*dbprefix*', $this->DBTABLEPREFIX, $name);
					$name = $this->platform->quoteIdentifier($name);
					$table = $schema->createTable($name);
					break;
				case 'create':
				case 'overwrite':
				case 'charset':
					break;
				case 'declaration':
					if (is_null($table)) {
						throw new \DomainException('Table declaration before table name');
					}
					$this->loadDeclaration($table, $child);
					break;
				default:
					throw new \DomainException('Unknown element: ' . $child->getName());

			}
		}
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 * @param \SimpleXMLElement $xml
	 * @throws \DomainException
	 */
	private function loadDeclaration($table, $xml) {
		foreach ($xml->children() as $child) {
			/**
			 * @var \SimpleXMLElement $child
			 */
			switch ($child->getName()) {
				case 'field':
					$this->loadField($table, $child);
					break;
				case 'index':
					$this->loadIndex($table, $child);
					break;
				default:
					throw new \DomainException('Unknown element: ' . $child->getName());

			}
		}
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 * @param \SimpleXMLElement $xml
	 * @throws \DomainException
	 */
	private function loadField($table, $xml) {
		$options = [ 'notnull' => false ];
		foreach ($xml->children() as $child) {
			/**
			 * @var \SimpleXMLElement $child
			 */
			switch ($child->getName()) {
				case 'name':
					$name = (string)$child;
					$name = $this->platform->quoteIdentifier($name);
					break;
				case 'type':
					$type = (string)$child;
					switch ($type) {
						case 'text':
							$type = 'string';
							break;
						case 'clob':
							$type = 'text';
							break;
						case 'timestamp':
							$type = 'datetime';
							break;
						case 'numeric':
							$type = 'decimal';
							break;
					}
					break;
				case 'length':
					$length = (string)$child;
					$options['length'] = $length;
					break;
				case 'unsigned':
					$unsigned = $this->asBool($child);
					$options['unsigned'] = $unsigned;
					break;
				case 'notnull':
					$notnull = $this->asBool($child);
					$options['notnull'] = $notnull;
					break;
				case 'autoincrement':
					$autoincrement = $this->asBool($child);
					$options['autoincrement'] = $autoincrement;
					break;
				case 'default':
					$default = (string)$child;
					$options['default'] = $default;
					break;
				case 'comments':
					$comment = (string)$child;
					$options['comment'] = $comment;
					break;
				case 'primary':
					$primary = $this->asBool($child);
					$options['primary'] = $primary;
					break;
				case 'precision':
					$precision = (string)$child;
					$options['precision'] = $precision;
					break;
				case 'scale':
					$scale = (string)$child;
					$options['scale'] = $scale;
					break;
				default:
					throw new \DomainException('Unknown element: ' . $child->getName());

			}
		}
		if (isset($name) && isset($type)) {
			if (isset($options['default']) && empty($options['default'])) {
				if (empty($options['notnull']) || !$options['notnull']) {
					unset($options['default']);
					$options['notnull'] = false;
				} else {
					$options['default'] = '';
				}
				if ($type == 'integer' || $type == 'decimal') {
					$options['default'] = 0;
				} elseif ($type == 'boolean') {
					$options['default'] = false;
				}
				if (!empty($options['autoincrement']) && $options['autoincrement']) {
					unset($options['default']);
				}
			}
			if ($type === 'integer' && isset($options['default'])) {
				$options['default'] = (int)$options['default'];
			}
			if ($type === 'integer' && isset($options['length'])) {
				$length = $options['length'];
				if ($length < 4) {
					$type = 'smallint';
				} elseif ($length > 4) {
					$type = 'bigint';
				}
			}
			if ($type === 'boolean' && isset($options['default'])) {
				$options['default'] = $this->asBool($options['default']);
			}
			if (!empty($options['autoincrement'])
				&& !empty($options['notnull'])
			) {
				$options['primary'] = true;
			}

			# not used anymore in the options argument
			# see https://github.com/doctrine/dbal/commit/138eb85234a1faeaa2e6a32cd7bcc66bb51c64e8#diff-300f55366adb50a32a40882ebdc95c163b141f64cba5f45f20bda04a907b3eb3L82
			# therefore it's read before and then unset right before the addColumn call
			$setPrimaryKey = false;
			if (!empty($options['primary']) && $options['primary']) {
				$setPrimaryKey = true;
			}
			unset($options['primary']);
			$table->addColumn($name, $type, $options);
			if ($setPrimaryKey) {
				$table->setPrimaryKey([$name]);
			}
		}
	}

	/**
	 * @param \Doctrine\DBAL\Schema\Table $table
	 * @param \SimpleXMLElement $xml
	 * @throws \DomainException
	 */
	private function loadIndex($table, $xml) {
		$name = null;
		$fields = [];
		foreach ($xml->children() as $child) {
			/**
			 * @var \SimpleXMLElement $child
			 */
			switch ($child->getName()) {
				case 'name':
					$name = (string)$child;
					break;
				case 'primary':
					$primary = $this->asBool($child);
					break;
				case 'unique':
					$unique = $this->asBool($child);
					break;
				case 'field':
					foreach ($child->children() as $field) {
						/**
						 * @var \SimpleXMLElement $field
						 */
						switch ($field->getName()) {
							case 'name':
								$field_name = (string)$field;
								$field_name = $this->platform->quoteIdentifier($field_name);
								$fields[] = $field_name;
								break;
							case 'sorting':
								break;
							default:
								throw new \DomainException('Unknown element: ' . $field->getName());

						}
					}
					break;
				default:
					throw new \DomainException('Unknown element: ' . $child->getName());

			}
		}
		if (!empty($fields)) {
			if (isset($primary) && $primary) {
				if ($table->hasPrimaryKey()) {
					return;
				}
				$table->setPrimaryKey($fields, $name);
			} else {
				if (isset($unique) && $unique) {
					$table->addUniqueIndex($fields, $name);
				} else {
					$table->addIndex($fields, $name);
				}
			}
		} else {
			throw new \DomainException('Empty index definition: ' . $name . ' options:' . print_r($fields, true));
		}
	}

	/**
	 * @param \SimpleXMLElement|string $xml
	 * @return bool
	 */
	private function asBool($xml) {
		$result = (string)$xml;
		if ($result == 'true') {
			$result = true;
		} elseif ($result == 'false') {
			$result = false;
		}
		return (bool)$result;
	}
}
