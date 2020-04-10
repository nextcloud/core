<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\BackgroundJob;

use OC\BackgroundJob\JobList;
use OCP\ILogger;

/**
 * Simple base class to extend to run periodic background jobs.
 * Call setInterval with your desired interval in seconds from the constructor.
 *
 * @since 15.0.0
 */
abstract class TimedJob extends Job {
	/** @var int */
	protected $interval = 0;

	/**
	 * set the interval for the job
	 *
	 * @since 15.0.0
	 */
	public function setInterval(int $interval) {
		$this->interval = $interval;
	}

	/**
	 * run the job if the last run is is more than the interval ago
	 *
	 * @param JobList $jobList
	 * @param ILogger|null $logger
	 *
	 * @since 15.0.0
	 */
	final public function execute($jobList, ILogger $logger = null) {
		if (($this->time->getTime() - $this->lastRun) > $this->interval) {
			parent::execute($jobList, $logger);
		}
	}
}
