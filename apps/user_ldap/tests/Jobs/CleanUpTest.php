<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Tests\Jobs;

use Exception;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\Jobs\CleanUp;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User_Proxy;
use OCP\IConfig;
use OCP\IDBConnection;
use Test\TestCase;

class CleanUpTest extends TestCase {
	/** @var CleanUp */
	protected $bgJob;

	/** @var array */
	protected $mocks;

	public function setUp(): void {
		$this->createMocks();
		$this->bgJob = new CleanUp($this->mocks['userBackend'], $this->mocks['deletedUsersIndex']);
		$this->bgJob->setArguments($this->mocks);
	}

	protected function createMocks(): void {
		$this->mocks = [];
		$this->mocks['userBackend'] = $this->createMock(User_Proxy::class);
		$this->mocks['deletedUsersIndex'] = $this->createMock(DeletedUsersIndex::class);
		$this->mocks['ocConfig'] = $this->createMock(IConfig::class);
		$this->mocks['db'] = $this->createMock(IDBConnection::class);
		$this->mocks['helper'] = $this->createMock(Helper::class);
	}

	/**
	 * clean up job must not run when there are disabled configurations
	 */
	public function test_runNotAllowedByDisabledConfigurations() {
		$this->mocks['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(true);

		$this->mocks['ocConfig']->expects($this->never())
			->method('getSystemValue');

		$result = $this->bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job must not run when LDAP Helper is broken i.e.
	 * returning unexpected results
	 */
	public function test_runNotAllowedByBrokenHelper() {
		$this->mocks['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->will($this->throwException(new Exception()));

		$this->mocks['ocConfig']->expects($this->never())
			->method('getSystemValue');

		$result = $this->bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job must not run when it is not enabled
	 */
	public function test_runNotAllowedBySysConfig() {
		$this->mocks['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(false);

		$this->mocks['ocConfig']->expects($this->once())
			->method('getSystemValue')
			->willReturn(false);

		$result = $this->bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job is allowed to run
	 */
	public function test_runIsAllowed() {
		$this->mocks['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(false);

		$this->mocks['ocConfig']->expects($this->once())
			->method('getSystemValue')
			->willReturn(true);

		$result = $this->bgJob->isCleanUpAllowed();
		$this->assertSame(true, $result);
	}

	/**
	 * check whether offset will be reset when it needs to
	 */
	public function test_OffsetResetIsNecessary() {
		$result = $this->bgJob->isOffsetResetNecessary($this->bgJob->getChunkSize() - 1);
		$this->assertSame(true, $result);
	}

	/**
	 * make sure offset is not reset when it is not due
	 */
	public function test_OffsetResetIsNotNecessary() {
		$result = $this->bgJob->isOffsetResetNecessary($this->bgJob->getChunkSize());
		$this->assertSame(false, $result);
	}
}
