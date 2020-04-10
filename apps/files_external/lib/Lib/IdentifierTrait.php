<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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
 * Trait for objects requiring an identifier (and/or identifier aliases)
 * Also supports deprecation to a different object, linking the objects
 */
trait IdentifierTrait {

	/** @var string */
	protected $identifier;

	/** @var string[] */
	protected $identifierAliases = [];

	/** @var IdentifierTrait */
	protected $deprecateTo = null;

	/**
	 * @return string
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * @param string $identifier
	 * @return $this
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
		$this->identifierAliases[] = $identifier;
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getIdentifierAliases() {
		return $this->identifierAliases;
	}

	/**
	 * @param string $alias
	 * @return $this
	 */
	public function addIdentifierAlias($alias) {
		$this->identifierAliases[] = $alias;
		return $this;
	}

	/**
	 * @return object|null
	 */
	public function getDeprecateTo() {
		return $this->deprecateTo;
	}

	/**
	 * @param object $destinationObject
	 * @return self
	 */
	public function deprecateTo($destinationObject) {
		$this->deprecateTo = $destinationObject;
		return $this;
	}

	/**
	 * @return array
	 */
	public function jsonSerializeIdentifier() {
		$data = [
			'identifier' => $this->identifier,
			'identifierAliases' => $this->identifierAliases,
		];
		if ($this->deprecateTo) {
			$data['deprecateTo'] = $this->deprecateTo->getIdentifier();
		}
		return $data;
	}

}
