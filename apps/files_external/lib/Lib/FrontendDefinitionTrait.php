<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Lib;

/**
 * Trait for objects that have a frontend representation
 */
trait FrontendDefinitionTrait {

	/** @var string human-readable mechanism name */
	private $text;

	/** @var DefinitionParameter[] parameters for mechanism */
	private $parameters = [];

	/** @var string[] custom JS */
	private $customJs = [];

	/**
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}

	/**
	 * @param string $text
	 * @return $this
	 */
	public function setText($text) {
		$this->text = $text;
		return $this;
	}

	/**
	 * @param FrontendDefinitionTrait $a
	 * @param FrontendDefinitionTrait $b
	 * @return int
	 */
	public static function lexicalCompare(FrontendDefinitionTrait $a, FrontendDefinitionTrait $b) {
		return strcmp($a->getText(), $b->getText());
	}

	/**
	 * @return DefinitionParameter[]
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @param DefinitionParameter[] $parameters
	 * @return self
	 */
	public function addParameters(array $parameters) {
		foreach ($parameters as $parameter) {
			$this->addParameter($parameter);
		}
		return $this;
	}

	/**
	 * @param DefinitionParameter $parameter
	 * @return self
	 */
	public function addParameter(DefinitionParameter $parameter) {
		$this->parameters[$parameter->getName()] = $parameter;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getCustomJs() {
		return $this->customJs;
	}

	/**
	 * @param string $custom
	 * @return self
	 */
	public function addCustomJs($custom) {
		$this->customJs[] = $custom;
		return $this;
	}

	/**
	 * Serialize into JSON for client-side JS
	 *
	 * @return array
	 */
	public function jsonSerializeDefinition() {
		$configuration = [];
		foreach ($this->getParameters() as $parameter) {
			$configuration[$parameter->getName()] = $parameter;
		}

		$data = [
			'name' => $this->getText(),
			'configuration' => $configuration,
			'custom' => $this->getCustomJs(),
		];
		return $data;
	}

	/**
	 * Check if parameters are satisfied in a StorageConfig
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	public function validateStorageDefinition(StorageConfig $storage) {
		foreach ($this->getParameters() as $name => $parameter) {
			$value = $storage->getBackendOption($name);
			if (!is_null($value) || !$parameter->isOptional()) {
				if (!$parameter->validateValue($value)) {
					return false;
				}
				$storage->setBackendOption($name, $value);
			}
		}
		return true;
	}
}
