<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\Diagnostics;

use OCP\Diagnostics\IEvent;

class Event implements IEvent {
	/**
	 * @var string
	 */
	protected $id;

	/**
	 * @var float
	 */
	protected $start;

	/**
	 * @var float
	 */
	protected $end;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @param string $id
	 * @param string $description
	 * @param float $start
	 */
	public function __construct($id, $description, $start) {
		$this->id = $id;
		$this->description = $description;
		$this->start = $start;
	}

	/**
	 * @param float $time
	 */
	public function end($time) {
		$this->end = $time;
	}

	/**
	 * @return float
	 */
	public function getStart() {
		return $this->start;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @return float
	 */
	public function getEnd() {
		return $this->end;
	}

	/**
	 * @return float
	 */
	public function getDuration() {
		if (!$this->end) {
			$this->end = microtime(true);
		}
		return $this->end - $this->start;
	}
}
