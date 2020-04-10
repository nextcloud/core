<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Noveen Sachdeva <noveen.sachdeva@research.iiit.ac.in>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OCP\BackgroundJob\IJob;
use OCP\ILogger;

abstract class Job implements IJob {
	/**
	 * @var int $id
	 */
	protected $id;

	/**
	 * @var int $lastRun
	 */
	protected $lastRun;

	/**
	 * @var mixed $argument
	 */
	protected $argument;

	/**
	 * @param JobList $jobList
	 * @param ILogger|null $logger
	 */
	public function execute($jobList, ILogger $logger = null) {
		$jobList->setLastRun($this);
		if ($logger === null) {
			$logger = \OC::$server->getLogger();
		}

		try {
			$jobStartTime = time();
			$logger->debug('Run ' . get_class($this) . ' job with ID ' . $this->getId(), ['app' => 'cron']);
			$this->run($this->argument);
			$timeTaken = time() - $jobStartTime;

			$logger->debug('Finished ' . get_class($this) . ' job with ID ' . $this->getId() . ' in ' . $timeTaken . ' seconds', ['app' => 'cron']);
			$jobList->setExecutionTime($this, $timeTaken);
		} catch (\Throwable $e) {
			if ($logger) {
				$logger->logException($e, [
					'app' => 'core',
					'message' => 'Error while running background job (class: ' . get_class($this) . ', arguments: ' . print_r($this->argument, true) . ')'
				]);
			}
		}
	}

	abstract protected function run($argument);

	public function setId($id) {
		$this->id = $id;
	}

	public function setLastRun($lastRun) {
		$this->lastRun = $lastRun;
	}

	public function setArgument($argument) {
		$this->argument = $argument;
	}

	public function getId() {
		return $this->id;
	}

	public function getLastRun() {
		return $this->lastRun;
	}

	public function getArgument() {
		return $this->argument;
	}
}
