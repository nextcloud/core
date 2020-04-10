<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\UpdateNotification\Tests;

use OCA\UpdateNotification\ResetTokenBackgroundJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Test\TestCase;

class ResetTokenBackgroundJobTest extends TestCase {
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;
	/** @var ResetTokenBackgroundJob */
	private $resetTokenBackgroundJob;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->resetTokenBackgroundJob = new ResetTokenBackgroundJob($this->config, $this->timeFactory);
	}

	public function testRunWithNotExpiredToken() {
		$this->timeFactory
			->expects($this->atLeastOnce())
			->method('getTime')
			->willReturn(123);
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'updater.secret.created', 123);
		$this->config
			->expects($this->never())
			->method('deleteSystemValue');

		static::invokePrivate($this->resetTokenBackgroundJob, 'run', [null]);
	}

	public function testRunWithExpiredToken() {
		$this->timeFactory
			->expects($this->at(0))
			->method('getTime')
			->willReturn(1455131633);
		$this->timeFactory
			->expects($this->at(1))
			->method('getTime')
			->willReturn(1455045234);
		$this->config
			->expects($this->once())
			->method('getAppValue')
			->with('core', 'updater.secret.created', 1455045234);
		$this->config
			->expects($this->once())
			->method('deleteSystemValue')
			->with('updater.secret');

		static::invokePrivate($this->resetTokenBackgroundJob, 'run', [null]);
	}
}
