<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace Test\Authentication\Listeners;

use Exception;
use OC\Authentication\Listeners\UserDeletedTokenCleanupListener;
use OC\Authentication\Token\IToken;
use OC\Authentication\Token\Manager;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class UserDeletedTokenCleanupListenerTest extends TestCase {


	/** @var Manager|MockObject */
	private $manager;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var UserDeletedTokenCleanupListener */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->manager = $this->createMock(Manager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->listener = new UserDeletedTokenCleanupListener(
			$this->manager,
			$this->logger
		);
	}

	public function testHandleUnrelated(): void {
		$event = new Event();
		$this->manager->expects($this->never())->method('getTokenByUser');
		$this->logger->expects($this->never())->method('error');

		$this->listener->handle($event);
	}

	public function testHandleWithErrors(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$event = new UserDeletedEvent($user);
		$exception = new Exception('nope');
		$this->manager->expects($this->once())
			->method('getTokenByUser')
			->with('user123')
			->willThrowException($exception);
		$this->logger->expects($this->once())
			->method('error');

		$this->listener->handle($event);
	}

	public function testHandle(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$event = new UserDeletedEvent($user);
		$token1 = $this->createMock(IToken::class);
		$token1->method('getId')->willReturn(1);
		$token2 = $this->createMock(IToken::class);
		$token2->method('getId')->willReturn(2);
		$token3 = $this->createMock(IToken::class);
		$token3->method('getId')->willReturn(3);
		$this->manager->expects($this->once())
			->method('getTokenByUser')
			->with('user123')
			->willReturn([
				$token1,
				$token2,
				$token3,
			]);
		$this->manager->expects($this->exactly(3))
			->method('invalidateTokenById')
			->withConsecutive(
				['user123', 1],
				['user123', 2],
				['user123', 3]
			);
		$this->logger->expects($this->never())
			->method('error');

		$this->listener->handle($event);
	}
}
