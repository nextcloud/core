<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Hooks;

trait EmitterTrait {

	/**
	 * @var callable[][] $listeners
	 */
	protected $listeners = [];

	/**
	 * @param string $scope
	 * @param string $method
	 * @param callable $callback
	 */
	public function listen($scope, $method, callable $callback) {
		$eventName = $scope . '::' . $method;
		if (!isset($this->listeners[$eventName])) {
			$this->listeners[$eventName] = [];
		}
		if (array_search($callback, $this->listeners[$eventName], true) === false) {
			$this->listeners[$eventName][] = $callback;
		}
	}

	/**
	 * @param string $scope optional
	 * @param string $method optional
	 * @param callable $callback optional
	 */
	public function removeListener($scope = null, $method = null, callable $callback = null) {
		$names = [];
		$allNames = array_keys($this->listeners);
		if ($scope and $method) {
			$name = $scope . '::' . $method;
			if (isset($this->listeners[$name])) {
				$names[] = $name;
			}
		} elseif ($scope) {
			foreach ($allNames as $name) {
				$parts = explode('::', $name, 2);
				if ($parts[0] == $scope) {
					$names[] = $name;
				}
			}
		} elseif ($method) {
			foreach ($allNames as $name) {
				$parts = explode('::', $name, 2);
				if ($parts[1] == $method) {
					$names[] = $name;
				}
			}
		} else {
			$names = $allNames;
		}

		foreach ($names as $name) {
			if ($callback) {
				$index = array_search($callback, $this->listeners[$name], true);
				if ($index !== false) {
					unset($this->listeners[$name][$index]);
				}
			} else {
				$this->listeners[$name] = [];
			}
		}
	}

	/**
	 * @param string $scope
	 * @param string $method
	 * @param array $arguments optional
	 */
	protected function emit($scope, $method, array $arguments = []) {
		$eventName = $scope . '::' . $method;
		if (isset($this->listeners[$eventName])) {
			foreach ($this->listeners[$eventName] as $callback) {
				call_user_func_array($callback, $arguments);
			}
		}
	}
}
