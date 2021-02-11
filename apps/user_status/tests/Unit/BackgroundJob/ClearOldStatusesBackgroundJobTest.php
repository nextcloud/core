<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\UserStatus\Tests\BackgroundJob;

use OCA\UserStatus\BackgroundJob\ClearOldStatusesBackgroundJob;
use OCA\UserStatus\Db\UserStatusMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class ClearOldStatusesBackgroundJobTest extends TestCase {

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $time;

	/** @var UserStatusMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $mapper;

	/** @var ClearOldStatusesBackgroundJob */
	private $job;

	protected function setUp(): void {
		parent::setUp();

		$this->time = $this->createMock(ITimeFactory::class);
		$this->mapper = $this->createMock(UserStatusMapper::class);

		$this->job = new ClearOldStatusesBackgroundJob($this->time, $this->mapper);
	}

	public function testRun() {
		$this->mapper->expects($this->once())
			->method('clearMessagesOlderThan')
			->with(1337);
		$this->mapper->expects($this->once())
			->method('clearStatusesOlderThan')
			->with(437, 1337);

		$this->time->method('getTime')
			->willReturn(1337);

		self::invokePrivate($this->job, 'run', [[]]);
	}
}
