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
 * Polyfill for checking dependencies using legacy Storage::checkDependencies()
 */
trait LegacyDependencyCheckPolyfill {

	/**
	 * @return string
	 */
	abstract public function getStorageClass();

	/**
	 * Check if object is valid for use
	 *
	 * @return MissingDependency[] Unsatisfied dependencies
	 */
	public function checkDependencies() {
		$ret = [];

		$result = call_user_func([$this->getStorageClass(), 'checkDependencies']);
		if ($result !== true) {
			if (!is_array($result)) {
				$result = [$result];
			}
			foreach ($result as $key => $value) {
				if (!($value instanceof MissingDependency)) {
					$module = null;
					$message = null;
					if (is_numeric($key)) {
						$module = $value;
					} else {
						$module = $key;
						$message = $value;
					}
					$value = new MissingDependency($module, $this);
					$value->setMessage($message);
				}
				$ret[] = $value;
			}
		}

		return $ret;
	}

}
