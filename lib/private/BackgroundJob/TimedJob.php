<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC\BackgroundJob;

use OCP\BackgroundJob\IJobList;
use OCP\ILogger;

/**
 * Class QueuedJob
 *
 * create a background job that is to be executed at an interval
 *
 * @package OC\BackgroundJob
 */
abstract class TimedJob extends Job {
	protected $interval = 0;

	/**
	 * set the interval for the job
	 *
	 * @param int $interval
	 */
	public function setInterval($interval) {
		$this->interval = $interval;
	}

	/**
	 * run the job if
	 *
	 * @param IJobList $jobList
	 * @param ILogger|null $logger
	 */
	public function execute($jobList, ILogger $logger = null) {
		if ((time() - $this->lastRun) > $this->interval) {
			parent::execute($jobList, $logger);
		}
	}
}
