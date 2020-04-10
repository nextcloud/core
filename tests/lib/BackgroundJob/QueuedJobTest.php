<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;

class TestQueuedJob extends \OC\BackgroundJob\QueuedJob {
	public $ran = false;


	public function run($argument) {
		$this->ran = true;
	}
}


class TestQueuedJobNew extends \OCP\BackgroundJob\QueuedJob {
	public $ran = false;


	public function run($argument) {
		$this->ran = true;
	}
}

class QueuedJobTest extends \Test\TestCase {
	/**
	 * @var DummyJobList $jobList
	 */
	private $jobList;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = new DummyJobList();
	}

	public function testJobShouldBeRemoved() {
		$job = new TestQueuedJob();
		$this->jobList->add($job);

		$this->assertTrue($this->jobList->has($job, null));
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
	}

	public function testJobShouldBeRemovedNew() {
		$job = new TestQueuedJobNew(\OC::$server->query(ITimeFactory::class));
		$job->setId(42);
		$this->jobList->add($job);

		$this->assertTrue($this->jobList->has($job, null));
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
	}
}
