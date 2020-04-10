<?php

/**
 * @author Christoph Wurst <christoph@owncloud.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test\Authentication\Token;

use OC\Authentication\Token\DefaultTokenCleanupJob;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\Manager;
use Test\TestCase;

class DefaultTokenCleanupJobTest extends TestCase {

	/** @var DefaultTokenCleanupJob */
	private $job;
	private $tokenProvider;

	protected function setUp(): void {
		parent::setUp();

		$this->tokenProvider = $this->getMockBuilder(Manager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->overwriteService(IProvider::class, $this->tokenProvider);
		$this->job = new DefaultTokenCleanupJob();
	}

	public function testRun() {
		$this->tokenProvider->expects($this->once())
			->method('invalidateOldTokens')
			->with();
		$this->invokePrivate($this->job, 'run', [null]);
	}

}
