<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\BackgroundJob;

use OCP\BackgroundJob\IJob;

/**
 * Class DummyJobList
 *
 * in memory job list for testing purposes
 */
class DummyJobList extends \OC\BackgroundJob\JobList {
	/**
	 * @var IJob[]
	 */
	private $jobs = [];

	private $last = 0;

	public function __construct() {
	}

	/**
	 * @param IJob|string $job
	 * @param mixed $argument
	 */
	public function add($job, $argument = null) {
		if (is_string($job)) {
			/** @var \OC\BackgroundJob\Job $job */
			$job = new $job;
		}
		$job->setArgument($argument);
		if (!$this->has($job, null)) {
			$this->jobs[] = $job;
		}
	}

	/**
	 * @param IJob|string $job
	 * @param mixed $argument
	 */
	public function remove($job, $argument = null) {
		$index = array_search($job, $this->jobs);
		if ($index !== false) {
			unset($this->jobs[$index]);
		}
	}

	/**
	 * check if a job is in the list
	 *
	 * @param $job
	 * @param mixed $argument
	 * @return bool
	 */
	public function has($job, $argument) {
		return array_search($job, $this->jobs) !== false;
	}

	/**
	 * get all jobs in the list
	 *
	 * @return IJob[]
	 */
	public function getAll() {
		return $this->jobs;
	}

	/**
	 * get the next job in the list
	 *
	 * @return IJob|null
	 */
	public function getNext() {
		if (count($this->jobs) > 0) {
			if ($this->last < (count($this->jobs) - 1)) {
				$i = $this->last + 1;
			} else {
				$i = 0;
			}
			return $this->jobs[$i];
		} else {
			return null;
		}
	}

	/**
	 * set the job that was last ran
	 *
	 * @param \OC\BackgroundJob\Job $job
	 */
	public function setLastJob(IJob $job) {
		$i = array_search($job, $this->jobs);
		if ($i !== false) {
			$this->last = $i;
		} else {
			$this->last = 0;
		}
	}

	/**
	 * @param int $id
	 * @return IJob
	 */
	public function getById($id) {
		foreach ($this->jobs as $job) {
			if ($job->getId() === $id) {
				return $job;
			}
		}
		return null;
	}

	/**
	 * get the id of the last ran job
	 *
	 * @return int
	 */
	public function getLastJob() {
		return $this->last;
	}

	/**
	 * set the lastRun of $job to now
	 *
	 * @param IJob $job
	 */
	public function setLastRun(IJob $job) {
		$job->setLastRun(time());
	}

	public function setExecutionTime(IJob $job, $timeTaken) {
	}
}
