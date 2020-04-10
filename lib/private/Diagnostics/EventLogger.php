<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
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

namespace OC\Diagnostics;

use OCP\Diagnostics\IEventLogger;

class EventLogger implements IEventLogger {
	/**
	 * @var \OC\Diagnostics\Event[]
	 */
	private $events = [];
	
	/**
	 * @var bool - Module needs to be activated by some app
	 */
	private $activated = false;

	/**
	 * @inheritdoc
	 */
	public function start($id, $description) {
		if ($this->activated){
			$this->events[$id] = new Event($id, $description, microtime(true));
		}
	}

	/**
	 * @inheritdoc
	 */
	public function end($id) {
		if ($this->activated && isset($this->events[$id])) {
			$timing = $this->events[$id];
			$timing->end(microtime(true));
		}
	}

	/**
	 * @inheritdoc
	 */
	public function log($id, $description, $start, $end) {
		if ($this->activated) {
			$this->events[$id] = new Event($id, $description, $start);
			$this->events[$id]->end($end);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function getEvents() {
		return $this->events;
	}
	
	/**
	 * @inheritdoc
	 */
	public function activate() {
		$this->activated = true;
	}
}
