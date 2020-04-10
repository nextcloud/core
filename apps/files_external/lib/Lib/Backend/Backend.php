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

namespace OCA\Files_External\Lib\Backend;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\DependencyTrait;
use OCA\Files_External\Lib\FrontendDefinitionTrait;
use OCA\Files_External\Lib\IdentifierTrait;
use OCA\Files_External\Lib\PriorityTrait;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Lib\StorageModifierTrait;
use OCA\Files_External\Lib\VisibilityTrait;

/**
 * Storage backend
 *
 * A backend can have services injected during construction,
 * such as \OCP\IDB for database operations. This allows a backend
 * to perform advanced operations based on provided information.
 *
 * An authenication scheme defines the parameter interface, common to the
 * storage implementation, the backend and the authentication mechanism.
 * A storage implementation expects parameters according to the authentication
 * scheme, which are provided from the authentication mechanism.
 *
 * This class uses the following traits:
 *  - VisibilityTrait
 *      Restrict usage to admin-only/none
 *  - FrontendDefinitionTrait
 *      Specify configuration parameters and other definitions
 *  - PriorityTrait
 *      Allow objects to prioritize over others with the same mountpoint
 *  - DependencyTrait
 *      The object requires certain dependencies to be met
 *  - StorageModifierTrait
 *      Object can affect storage mounting
 */
class Backend implements \JsonSerializable {

	use VisibilityTrait;
	use FrontendDefinitionTrait;
	use PriorityTrait;
	use DependencyTrait;
	use StorageModifierTrait;
	use IdentifierTrait;

	/** @var string storage class */
	private $storageClass;

	/** @var array 'scheme' => true, supported authentication schemes */
	private $authSchemes = [];

	/** @var AuthMechanism|callable authentication mechanism fallback */
	private $legacyAuthMechanism;

	/**
	 * @return string
	 */
	public function getStorageClass() {
		return $this->storageClass;
	}

	/**
	 * @param string $class
	 * @return $this
	 */
	public function setStorageClass($class) {
		$this->storageClass = $class;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getAuthSchemes() {
		if (empty($this->authSchemes)) {
			return [AuthMechanism::SCHEME_NULL => true];
		}
		return $this->authSchemes;
	}

	/**
	 * @param string $scheme
	 * @return self
	 */
	public function addAuthScheme($scheme) {
		$this->authSchemes[$scheme] = true;
		return $this;
	}

	/**
	 * @param array $parameters storage parameters, for dynamic mechanism selection
	 * @return AuthMechanism
	 */
	public function getLegacyAuthMechanism(array $parameters = []) {
		if (is_callable($this->legacyAuthMechanism)) {
			return call_user_func($this->legacyAuthMechanism, $parameters);
		}
		return $this->legacyAuthMechanism;
	}

	/**
	 * @param AuthMechanism $authMechanism
	 * @return self
	 */
	public function setLegacyAuthMechanism(AuthMechanism $authMechanism) {
		$this->legacyAuthMechanism = $authMechanism;
		return $this;
	}

	/**
	 * @param callable $callback dynamic auth mechanism selection
	 * @return self
	 */
	public function setLegacyAuthMechanismCallback(callable $callback) {
		$this->legacyAuthMechanism = $callback;
	}

	/**
	 * Serialize into JSON for client-side JS
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		$data = $this->jsonSerializeDefinition();
		$data += $this->jsonSerializeIdentifier();

		$data['backend'] = $data['name']; // legacy compat
		$data['priority'] = $this->getPriority();
		$data['authSchemes'] = $this->getAuthSchemes();

		return $data;
	}

	/**
	 * Check if parameters are satisfied in a StorageConfig
	 *
	 * @param StorageConfig $storage
	 * @return bool
	 */
	public function validateStorage(StorageConfig $storage) {
		return $this->validateStorageDefinition($storage);
	}

}
