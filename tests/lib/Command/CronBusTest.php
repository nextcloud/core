<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Command;

use OC\Command\CronBus;
use Test\BackgroundJob\DummyJobList;

class CronBusTest extends AsyncBusTest {
	/**
	 * @var \OCP\BackgroundJob\IJobList
	 */
	private $jobList;


	protected function setUp(): void {
		parent::setUp();

		$this->jobList = new DummyJobList();
	}

	protected function createBus() {
		return new CronBus($this->jobList);
	}

	protected function runJobs() {
		$jobs = $this->jobList->getAll();
		foreach ($jobs as $job) {
			$job->execute($this->jobList);
		}
	}
}
