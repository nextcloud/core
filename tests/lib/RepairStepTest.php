<?php
/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OCP\Migration\IRepairStep;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RepairStepTest implements IRepairStep {
	private $warning;

	public function __construct($warning = false) {
		$this->warning = $warning;
	}

	public function getName() {
		return 'Test Name';
	}

	public function run(\OCP\Migration\IOutput $out) {
		if ($this->warning) {
			$out->warning('Simulated warning');
		}
		else {
			$out->info('Simulated info');
		}
	}
}

class RepairTest extends TestCase {
	/** @var \OC\Repair */
	private $repair;

	/** @var string[] */
	private $outputArray;

	protected function setUp(): void {
		parent::setUp();
		$dispatcher = new EventDispatcher();
		$this->repair = new \OC\Repair([], $dispatcher);

		$dispatcher->addListener('\OC\Repair::warning', function ($event) {
			/** @var \Symfony\Component\EventDispatcher\GenericEvent $event */
			$this->outputArray[] = 'warning: ' . $event->getArgument(0);
		});
		$dispatcher->addListener('\OC\Repair::info', function ($event) {
			/** @var \Symfony\Component\EventDispatcher\GenericEvent $event */
			$this->outputArray[] = 'info: ' . $event->getArgument(0);
		});
		$dispatcher->addListener('\OC\Repair::step', function ($event) {
			/** @var \Symfony\Component\EventDispatcher\GenericEvent $event */
			$this->outputArray[] = 'step: ' . $event->getArgument(0);
		});
	}

	public function testRunRepairStep() {

		$this->repair->addStep(new TestRepairStep(false));
		$this->repair->run();

		$this->assertEquals(
			[
				'step: Test Name',
				'info: Simulated info',
			],
			$this->outputArray
		);
	}

	public function testRunRepairStepThatFail() {

		$this->repair->addStep(new TestRepairStep(true));
		$this->repair->run();

		$this->assertEquals(
			[
				'step: Test Name',
				'warning: Simulated warning',
			],
			$this->outputArray
		);
	}

	public function testRunRepairStepsWithException() {
		$mock = $this->createMock(TestRepairStep::class);
		$mock->expects($this->any())
			->method('run')
			->will($this->throwException(new \Exception()));
		$mock->expects($this->any())
			->method('getName')
			->willReturn('Exception Test');

		$this->repair->addStep($mock);
		$this->repair->addStep(new TestRepairStep(false));

		$thrown = false;
		try {
			$this->repair->run();
		}
		catch (\Exception $e) {
			$thrown = true;
		}

		$this->assertTrue($thrown);
		// jump out after exception
		$this->assertEquals(
			[
				'step: Exception Test',
			],
			$this->outputArray
		);
	}

	public function testRunRepairStepsContinueAfterWarning() {
		$this->repair->addStep(new TestRepairStep(true));
		$this->repair->addStep(new TestRepairStep(false));
		$this->repair->run();

		$this->assertEquals(
			[
				'step: Test Name',
				'warning: Simulated warning',
				'step: Test Name',
				'info: Simulated info',
			],
			$this->outputArray
		);
	}
}
