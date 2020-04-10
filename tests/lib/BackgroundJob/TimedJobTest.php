<?php
/**
 * Copyright (c) 2013 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;

class TestTimedJob extends \OC\BackgroundJob\TimedJob {
	/** @var bool */
	public $ran = false;

	public function __construct() {
		$this->setInterval(10);
	}

	public function run($argument) {
		$this->ran = true;
	}
}

class TestTimedJobNew extends \OCP\BackgroundJob\TimedJob {
	/** @var bool */
	public $ran = false;

	public function __construct(ITimeFactory $timeFactory) {
		parent::__construct($timeFactory);
		$this->setInterval(10);
	}

	public function run($argument) {
		$this->ran = true;
	}
}

class TimedJobTest extends \Test\TestCase {
	/** @var DummyJobList $jobList */
	private $jobList;

	/** @var ITimeFactory */
	private $time;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = new DummyJobList();
		$this->time = \OC::$server->query(ITimeFactory::class);
	}

	public function testShouldRunAfterInterval() {
		$job = new TestTimedJob();
		$this->jobList->add($job);

		$job->setLastRun(time() - 12);
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
	}

	public function testShouldNotRunWithinInterval() {
		$job = new TestTimedJob();
		$this->jobList->add($job);

		$job->setLastRun(time() - 5);
		$job->execute($this->jobList);
		$this->assertFalse($job->ran);
	}

	public function testShouldNotTwice() {
		$job = new TestTimedJob();
		$this->jobList->add($job);

		$job->setLastRun(time() - 15);
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
		$job->ran = false;
		$job->execute($this->jobList);
		$this->assertFalse($job->ran);
	}


	public function testShouldRunAfterIntervalNew() {
		$job = new TestTimedJobNew($this->time);
		$job->setId(42);
		$this->jobList->add($job);

		$job->setLastRun(time() - 12);
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
	}

	public function testShouldNotRunWithinIntervalNew() {
		$job = new TestTimedJobNew($this->time);
		$job->setId(42);
		$this->jobList->add($job);

		$job->setLastRun(time() - 5);
		$job->execute($this->jobList);
		$this->assertFalse($job->ran);
	}

	public function testShouldNotTwiceNew() {
		$job = new TestTimedJobNew($this->time);
		$job->setId(42);
		$this->jobList->add($job);

		$job->setLastRun(time() - 15);
		$job->execute($this->jobList);
		$this->assertTrue($job->ran);
		$job->ran = false;
		$job->execute($this->jobList);
		$this->assertFalse($job->ran);
	}
}
