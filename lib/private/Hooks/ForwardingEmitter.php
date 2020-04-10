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

/**
 * Class ForwardingEmitter
 *
 * allows forwarding all listen calls to other emitters
 *
 * @package OC\Hooks
 */
abstract class ForwardingEmitter extends BasicEmitter {
	/**
	 * @var \OC\Hooks\Emitter[] array
	 */
	private $forwardEmitters = [];

	/**
	 * @param string $scope
	 * @param string $method
	 * @param callable $callback
	 */
	public function listen($scope, $method, callable $callback) {
		parent::listen($scope, $method, $callback);
		foreach ($this->forwardEmitters as $emitter) {
			$emitter->listen($scope, $method, $callback);
		}
	}

	/**
	 * @param \OC\Hooks\Emitter $emitter
	 */
	protected function forward(Emitter $emitter) {
		$this->forwardEmitters[] = $emitter;

		//forward all previously connected hooks
		foreach ($this->listeners as $key => $listeners) {
			list($scope, $method) = explode('::', $key, 2);
			foreach ($listeners as $listener) {
				$emitter->listen($scope, $method, $listener);
			}
		}
	}
}
