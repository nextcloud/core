<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\App\CodeChecker;

class StrongComparisonCheck implements ICheck {
	/** @var ICheck */
	protected $check;

	/**
	 * @param ICheck $check
	 */
	public function __construct(ICheck $check) {
		$this->check = $check;
	}

	/**
	 * @param int $errorCode
	 * @param string $errorObject
	 * @return string
	 */
	public function getDescription($errorCode, $errorObject) {
		return $this->check->getDescription($errorCode, $errorObject);
	}

	/**
	 * @return array
	 */
	public function getClasses() {
		return $this->check->getClasses();
	}

	/**
	 * @return array
	 */
	public function getConstants() {
		return $this->check->getConstants();
	}

	/**
	 * @return array
	 */
	public function getFunctions() {
		return $this->check->getFunctions();
	}

	/**
	 * @return array
	 */
	public function getMethods() {
		return $this->check->getMethods();
	}

	/**
	 * @return bool
	 */
	public function checkStrongComparisons() {
		return true;
	}
}
