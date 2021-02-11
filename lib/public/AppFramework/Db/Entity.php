<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\AppFramework\Db;

use function lcfirst;
use function substr;

/**
 * @method integer getId()
 * @method void setId(integer $id)
 * @since 7.0.0
 */
abstract class Entity {
	public $id;

	private $_updatedFields = [];
	private $_fieldTypes = ['id' => 'integer'];


	/**
	 * Simple alternative constructor for building entities from a request
	 * @param array $params the array which was obtained via $this->params('key')
	 * in the controller
	 * @return Entity
	 * @since 7.0.0
	 */
	public static function fromParams(array $params) {
		$instance = new static();

		foreach ($params as $key => $value) {
			$method = 'set' . ucfirst($key);
			$instance->$method($value);
		}

		return $instance;
	}


	/**
	 * Maps the keys of the row array to the attributes
	 * @param array $row the row to map onto the entity
	 * @since 7.0.0
	 */
	public static function fromRow(array $row) {
		$instance = new static();

		foreach ($row as $key => $value) {
			$prop = ucfirst($instance->columnToProperty($key));
			$setter = 'set' . $prop;
			$instance->$setter($value);
		}

		$instance->resetUpdatedFields();

		return $instance;
	}


	/**
	 * @return array with attribute and type
	 * @since 7.0.0
	 */
	public function getFieldTypes() {
		return $this->_fieldTypes;
	}


	/**
	 * Marks the entity as clean needed for setting the id after the insertion
	 * @since 7.0.0
	 */
	public function resetUpdatedFields() {
		$this->_updatedFields = [];
	}

	/**
	 * Generic setter for properties
	 * @since 7.0.0
	 */
	protected function setter($name, $args) {
		// setters should only work for existing attributes
		if (property_exists($this, $name)) {
			if ($this->$name === $args[0]) {
				return;
			}
			$this->markFieldUpdated($name);

			// if type definition exists, cast to correct type
			if ($args[0] !== null && array_key_exists($name, $this->_fieldTypes)) {
				$type = $this->_fieldTypes[$name];
				if ($type === 'blob') {
					// (B)LOB is treated as string when we read from the DB
					$type = 'string';
				}
				settype($args[0], $type);
			}
			$this->$name = $args[0];
		} else {
			throw new \BadFunctionCallException($name .
				' is not a valid attribute');
		}
	}

	/**
	 * Generic getter for properties
	 * @since 7.0.0
	 */
	protected function getter($name) {
		// getters should only work for existing attributes
		if (property_exists($this, $name)) {
			return $this->$name;
		} else {
			throw new \BadFunctionCallException($name .
				' is not a valid attribute');
		}
	}


	/**
	 * Each time a setter is called, push the part after set
	 * into an array: for instance setId will save Id in the
	 * updated fields array so it can be easily used to create the
	 * getter method
	 * @since 7.0.0
	 */
	public function __call($methodName, $args) {
		if (strpos($methodName, 'set') === 0) {
			$this->setter(lcfirst(substr($methodName, 3)), $args);
		} elseif (strpos($methodName, 'get') === 0) {
			return $this->getter(lcfirst(substr($methodName, 3)));
		} elseif ($this->isGetterForBoolProperty($methodName)) {
			return $this->getter(lcfirst(substr($methodName, 2)));
		} else {
			throw new \BadFunctionCallException($methodName .
				' does not exist');
		}
	}

	/**
	 * @param string $methodName
	 * @return bool
	 * @since 18.0.0
	 */
	protected function isGetterForBoolProperty(string $methodName): bool {
		if (strpos($methodName, 'is') === 0) {
			$fieldName = lcfirst(substr($methodName, 2));
			return isset($this->_fieldTypes[$fieldName]) && strpos($this->_fieldTypes[$fieldName], 'bool') === 0;
		}
		return false;
	}

	/**
	 * Mark am attribute as updated
	 * @param string $attribute the name of the attribute
	 * @since 7.0.0
	 */
	protected function markFieldUpdated($attribute) {
		$this->_updatedFields[$attribute] = true;
	}


	/**
	 * Transform a database columnname to a property
	 * @param string $columnName the name of the column
	 * @return string the property name
	 * @since 7.0.0
	 */
	public function columnToProperty($columnName) {
		$parts = explode('_', $columnName);
		$property = null;

		foreach ($parts as $part) {
			if ($property === null) {
				$property = $part;
			} else {
				$property .= ucfirst($part);
			}
		}

		return $property;
	}


	/**
	 * Transform a property to a database column name
	 * @param string $property the name of the property
	 * @return string the column name
	 * @since 7.0.0
	 */
	public function propertyToColumn($property) {
		$parts = preg_split('/(?=[A-Z])/', $property);
		$column = null;

		foreach ($parts as $part) {
			if ($column === null) {
				$column = $part;
			} else {
				$column .= '_' . lcfirst($part);
			}
		}

		return $column;
	}


	/**
	 * @return array array of updated fields for update query
	 * @since 7.0.0
	 */
	public function getUpdatedFields() {
		return $this->_updatedFields;
	}


	/**
	 * Adds type information for a field so that its automatically casted to
	 * that value once its being returned from the database
	 * @param string $fieldName the name of the attribute
	 * @param string $type the type which will be used to call settype()
	 * @since 7.0.0
	 */
	protected function addType($fieldName, $type) {
		$this->_fieldTypes[$fieldName] = $type;
	}


	/**
	 * Slugify the value of a given attribute
	 * Warning: This doesn't result in a unique value
	 * @param string $attributeName the name of the attribute, which value should be slugified
	 * @return string slugified value
	 * @since 7.0.0
	 */
	public function slugify($attributeName) {
		// toSlug should only work for existing attributes
		if (property_exists($this, $attributeName)) {
			$value = $this->$attributeName;
			// replace everything except alphanumeric with a single '-'
			$value = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
			$value = strtolower($value);
			// trim '-'
			return trim($value, '-');
		} else {
			throw new \BadFunctionCallException($attributeName .
				' is not a valid attribute');
		}
	}
}
