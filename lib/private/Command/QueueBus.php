<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Command;

use OCP\Command\IBus;
use OCP\Command\ICommand;

class QueueBus implements IBus {
	/**
	 * @var ICommand[]|callable[]
	 */
	private $queue = [];

	/**
	 * Schedule a command to be fired
	 *
	 * @param \OCP\Command\ICommand | callable $command
	 */
	public function push($command) {
		$this->queue[] = $command;
	}

	/**
	 * Require all commands using a trait to be run synchronous
	 *
	 * @param string $trait
	 */
	public function requireSync($trait) {
	}

	/**
	 * @param \OCP\Command\ICommand | callable $command
	 */
	private function runCommand($command) {
		if ($command instanceof ICommand) {
			// ensure the command can be serialized
			$serialized = serialize($command);
			if(strlen($serialized) > 4000) {
				throw new \InvalidArgumentException('Trying to push a command which serialized form can not be stored in the database (>4000 character)');
			}
			$unserialized = unserialize($serialized);
			$unserialized->handle();
		} else {
			$command();
		}
	}

	public function run() {
		while ($command = array_shift($this->queue)) {
			$this->runCommand($command);
		}
	}
}
