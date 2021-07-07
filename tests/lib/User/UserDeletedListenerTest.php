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

use OC\User\UserDeletedListener;
use OC\User\UsernameDuplicationPreventionManager;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserDeletedListenerTest extends TestCase {
	public function testHandle() {
		/** @var UsernameDuplicationPreventionManager|MockObject $usernameDuplicationPreventionManager */
		$usernameDuplicationPreventionManager = $this->createMock(UsernameDuplicationPreventionManager::class);
		$usernameDuplicationPreventionManager
			->expects($this->once())
			->method('markUsed')
			->with($this->equalTo('ThisIsTheUsername'));
		/** @var IUser|MockObject $mockUser */
		$mockUser = $this->createMock(IUser::class);
		$mockUser
			->expects($this->once())
			->method('getUID')
			->willReturn('ThisIsTheUsername');

		$listener = new UserDeletedListener($usernameDuplicationPreventionManager);
		$listener->handle(new UserDeletedEvent($mockUser));
	}
}
