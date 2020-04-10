<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\App\CodeChecker;

interface ICheck {
	/**
	 * @param int $errorCode
	 * @param string $errorObject
	 * @return string
	 */
	public function getDescription($errorCode, $errorObject);

	/**
	 * @return array E.g.: `'ClassName' => 'oc version',`
	 */
	public function getClasses();

	/**
	 * @return array E.g.: `'ClassName::CONSTANT_NAME' => 'oc version',`
	 */
	public function getConstants();

	/**
	 * @return array E.g.: `'functionName' => 'oc version',`
	 */
	public function getFunctions();

	/**
	 * @return array E.g.: `'ClassName::methodName' => 'oc version',`
	 */
	public function getMethods();

	/**
	 * @return bool
	 */
	public function checkStrongComparisons();
}
