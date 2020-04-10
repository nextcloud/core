<?php

declare(strict_types=1);

/**
 *
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Migration;

use OCA\TwoFactorBackupCodes\Migration\CheckBackupCodes;
use OCP\BackgroundJob\IJobList;
use OCP\Migration\IOutput;
use Test\TestCase;

class CheckBackupCodeTest extends TestCase {

	/** @var IJobList|\PHPunit\Framework\MockObject\MockObject */
	private $jobList;

	/** @var CheckBackupCodes */
	private $checkBackupsCodes;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = $this->createMock(IJobList::class);
		$this->checkBackupsCodes = new CheckBackupCodes($this->jobList);
	}

	public function testGetName() {
		$this->assertSame('Add background job to check for backup codes', $this->checkBackupsCodes->getName());
	}

	public function testRun() {
		$this->jobList->expects($this->once())
			->method('add')
			->with(
				$this->equalTo(\OCA\TwoFactorBackupCodes\BackgroundJob\CheckBackupCodes::class)
			);

		$this->checkBackupsCodes->run($this->createMock(IOutput::class));
	}
}
