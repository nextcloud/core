<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

/**
 * Asynchronous command bus that uses the background job system as backend
 */
abstract class AsyncBus implements IBus {
	/**
	 * List of traits for command which require sync execution
	 *
	 * @var string[]
	 */
	private $syncTraits = [];

	/**
	 * Schedule a command to be fired
	 *
	 * @param \OCP\Command\ICommand | callable $command
	 */
	public function push($command) {
		if ($this->canRunAsync($command)) {
			$this->queueCommand($command);
		} else {
			$this->runCommand($command);
		}
	}

	/**
	 * Queue a command in the bus
	 *
	 * @param \OCP\Command\ICommand | callable $command
	 */
	abstract protected function queueCommand($command);

	/**
	 * Require all commands using a trait to be run synchronous
	 *
	 * @param string $trait
	 */
	public function requireSync($trait) {
		$this->syncTraits[] = trim($trait, '\\');
	}

	/**
	 * @param \OCP\Command\ICommand | callable $command
	 */
	private function runCommand($command) {
		if ($command instanceof ICommand) {
			$command->handle();
		} else {
			$command();
		}
	}

	/**
	 * @param \OCP\Command\ICommand | callable $command
	 * @return bool
	 */
	private function canRunAsync($command) {
		$traits = $this->getTraits($command);
		foreach ($traits as $trait) {
			if (array_search($trait, $this->syncTraits) !== false) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param \OCP\Command\ICommand | callable $command
	 * @return string[]
	 */
	private function getTraits($command) {
		if ($command instanceof ICommand) {
			return class_uses($command);
		} else {
			return [];
		}
	}
}
