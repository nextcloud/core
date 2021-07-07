<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\User;

use OC\User\UsernameDuplicationPreventionManager;
use Test\TestCase;

/**
 * @group DB
 */
class UsernameDuplicationPreventionManagerTest extends TestCase {
	/** @var UsernameDuplicationPreventionManager */
	private $usernameDuplicationPreventionManager;

	protected function setUp(): void {
		parent::setUp();

		$this->usernameDuplicationPreventionManager = \OC::$server->get(UsernameDuplicationPreventionManager::class);
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->usernameDuplicationPreventionManager->cleanUp();
	}

	public function testNotMarkedAsDeleted() {
		$return = $this->usernameDuplicationPreventionManager->wasUsed('not_deleted_user');
		$this->assertFalse($return);
	}

	public function testMarkedAsDeleted() {
		$this->usernameDuplicationPreventionManager->markUsed('deleted_user');
		$return = $this->usernameDuplicationPreventionManager->wasUsed('deleted_user');
		$this->assertTrue($return);
	}
}
