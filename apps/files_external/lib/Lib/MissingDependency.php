<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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
 * External storage backend dependency
 */
class MissingDependency {

	/** @var string */
	private $dependency;

	/** @var string|null Custom message */
	private $message = null;

	/**
	 * @param string $dependency
	 */
	public function __construct($dependency) {
		$this->dependency = $dependency;
	}

	/**
	 * @return string
	 */
	public function getDependency() {
		return $this->dependency;
	}

	/**
	 * @return string|null
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @param string $message
	 * @return self
	 */
	public function setMessage($message) {
		$this->message = $message;
		return $this;
	}
}
