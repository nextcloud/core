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

abstract class AbstractCheck implements ICheck {
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
		switch ($errorCode) {
			case CodeChecker::STATIC_CALL_NOT_ALLOWED:
				$functions = $this->getLocalFunctions();
				$functions = array_change_key_case($functions, CASE_LOWER);
				if (isset($functions[$errorObject])) {
					return $this->getLocalDescription();
				}
			// no break;
			case CodeChecker::CLASS_EXTENDS_NOT_ALLOWED:
			case CodeChecker::CLASS_IMPLEMENTS_NOT_ALLOWED:
			case CodeChecker::CLASS_NEW_NOT_ALLOWED:
			case CodeChecker::CLASS_USE_NOT_ALLOWED:
				$classes = $this->getLocalClasses();
				$classes = array_change_key_case($classes, CASE_LOWER);
				if (isset($classes[$errorObject])) {
					return $this->getLocalDescription();
				}
			break;

			case CodeChecker::CLASS_CONST_FETCH_NOT_ALLOWED:
				$constants = $this->getLocalConstants();
				$constants = array_change_key_case($constants, CASE_LOWER);
				if (isset($constants[$errorObject])) {
					return $this->getLocalDescription();
				}
			break;

			case CodeChecker::CLASS_METHOD_CALL_NOT_ALLOWED:
				$methods = $this->getLocalMethods();
				$methods = array_change_key_case($methods, CASE_LOWER);
				if (isset($methods[$errorObject])) {
					return $this->getLocalDescription();
				}
			break;
		}

		return $this->check->getDescription($errorCode, $errorObject);
	}

	/**
	 * @return string
	 */
	abstract protected function getLocalDescription();

	/**
	 * @return array
	 */
	abstract protected function getLocalClasses();

	/**
	 * @return array
	 */
	abstract protected function getLocalConstants();

	/**
	 * @return array
	 */
	abstract protected function getLocalFunctions();

	/**
	 * @return array
	 */
	abstract protected function getLocalMethods();

	/**
	 * @return array E.g.: `'ClassName' => 'oc version',`
	 */
	public function getClasses() {
		return array_merge($this->getLocalClasses(), $this->check->getClasses());
	}

	/**
	 * @return array E.g.: `'ClassName::CONSTANT_NAME' => 'oc version',`
	 */
	public function getConstants() {
		return array_merge($this->getLocalConstants(), $this->check->getConstants());
	}

	/**
	 * @return array E.g.: `'functionName' => 'oc version',`
	 */
	public function getFunctions() {
		return array_merge($this->getLocalFunctions(), $this->check->getFunctions());
	}

	/**
	 * @return array E.g.: `'ClassName::methodName' => 'oc version',`
	 */
	public function getMethods() {
		return array_merge($this->getLocalMethods(), $this->check->getMethods());
	}

	/**
	 * @return bool
	 */
	public function checkStrongComparisons() {
		return $this->check->checkStrongComparisons();
	}
}
