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

use OCA\User_LDAP\Helper;
use OCP\IConfig;
use OCP\IDBConnection;

class CleanUpTest extends \Test\TestCase {
	public function getMocks() {
		$mocks = [];
		$mocks['userBackend'] =
			$this->getMockBuilder('\OCA\User_LDAP\User_Proxy')
				->disableOriginalConstructor()
				->getMock();
		$mocks['deletedUsersIndex'] =
			$this->getMockBuilder('\OCA\User_LDAP\User\DeletedUsersIndex')
				->disableOriginalConstructor()
				->getMock();
		$mocks['ocConfig']    = $this->createMock(IConfig::class);
		$mocks['db']          = $this->createMock(IDBConnection::class);
		$mocks['helper']      = $this->createMock(Helper::class);

		return $mocks;
	}

	/**
	 * clean up job must not run when there are disabled configurations
	 */
	public function test_runNotAllowedByDisabledConfigurations() {
		$args = $this->getMocks();
		$args['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(true);

		$args['ocConfig']->expects($this->never())
			->method('getSystemValue');

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job must not run when LDAP Helper is broken i.e.
	 * returning unexpected results
	 */
	public function test_runNotAllowedByBrokenHelper() {
		$args = $this->getMocks();
		$args['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->will($this->throwException(new \Exception()));

		$args['ocConfig']->expects($this->never())
			->method('getSystemValue');

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job must not run when it is not enabled
	 */
	public function test_runNotAllowedBySysConfig() {
		$args = $this->getMocks();
		$args['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(false);

		$args['ocConfig']->expects($this->once())
			->method('getSystemValue')
			->willReturn(false);

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isCleanUpAllowed();
		$this->assertSame(false, $result);
	}

	/**
	 * clean up job is allowed to run
	 */
	public function test_runIsAllowed() {
		$args = $this->getMocks();
		$args['helper']->expects($this->once())
			->method('haveDisabledConfigurations')
			->willReturn(false);

		$args['ocConfig']->expects($this->once())
			->method('getSystemValue')
			->willReturn(true);

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isCleanUpAllowed();
		$this->assertSame(true, $result);
	}

	/**
	 * check whether offset will be reset when it needs to
	 */
	public function test_OffsetResetIsNecessary() {
		$args = $this->getMocks();

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isOffsetResetNecessary($bgJob->getChunkSize() - 1);
		$this->assertSame(true, $result);
	}

	/**
	 * make sure offset is not reset when it is not due
	 */
	public function test_OffsetResetIsNotNecessary() {
		$args = $this->getMocks();

		$bgJob = new \OCA\User_LDAP\Jobs\CleanUp();
		$bgJob->setArguments($args);

		$result = $bgJob->isOffsetResetNecessary($bgJob->getChunkSize());
		$this->assertSame(false, $result);
	}

}
