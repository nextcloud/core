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

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ILogger;

/**
 * Base class for background jobs
 *
 * This is here if you want to do advanced stuff in your background jobs.
 * For the most common use cases have a look at QueuedJob and TimedJob
 *
 * @since 15.0.0
 */
abstract class Job implements IJob {

	/** @var int $id */
	protected $id;

	/** @var int $lastRun */
	protected $lastRun;

	/** @var mixed $argument */
	protected $argument;

	/** @var ITimeFactory */
	protected $time;

	/**
	 * @since 15.0.0
	 */
	public function __construct(ITimeFactory $time) {
		$this->time = $time;
	}

	/**
	 * The function to prepare the execution of the job.
	 *
	 *
	 * @param IJobList $jobList
	 * @param ILogger|null $logger
	 *
	 * @since 15.0.0
	 */
	public function execute($jobList, ILogger $logger = null) {
		$jobList->setLastRun($this);
		if ($logger === null) {
			$logger = \OC::$server->getLogger();
		}

		try {
			$jobStartTime = $this->time->getTime();
			$logger->debug('Run ' . get_class($this) . ' job with ID ' . $this->getId(), ['app' => 'cron']);
			$this->run($this->argument);
			$timeTaken = $this->time->getTime() - $jobStartTime;

			$logger->debug('Finished ' . get_class($this) . ' job with ID ' . $this->getId() . ' in ' . $timeTaken . ' seconds', ['app' => 'cron']);
			$jobList->setExecutionTime($this, $timeTaken);
		} catch (\Exception $e) {
			if ($logger) {
				$logger->logException($e, [
					'app' => 'core',
					'message' => 'Error while running background job (class: ' . get_class($this) . ', arguments: ' . print_r($this->argument, true) . ')'
				]);
			}
		}
	}

	/**
	 * @since 15.0.0
	 */
	final public function setId($id) {
		$this->id = $id;
	}

	/**
	 * @since 15.0.0
	 */
	final public function setLastRun($lastRun) {
		$this->lastRun = $lastRun;
	}

	/**
	 * @since 15.0.0
	 */
	public function setArgument($argument) {
		$this->argument = $argument;
	}

	/**
	 * @since 15.0.0
	 */
	final public function getId(): int {
		return $this->id;
	}

	/**
	 * @since 15.0.0
	 */
	final public function getLastRun(): int {
		return $this->lastRun;
	}

	/**
	 * @since 15.0.0
	 */
	public function getArgument() {
		return $this->argument;
	}

	/**
	 * The actual function that is called to run the job
	 *
	 * @param $argument
	 * @return mixed
	 *
	 * @since 15.0.0
	 */
	abstract protected function run($argument);
}
