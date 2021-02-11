<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\Authentication\Token;

use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Exceptions\WipeTokenException;
use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IProvider as ITokenProvider;
use OC\Authentication\Token\IToken;
use OC\Authentication\Token\IWipeableToken;
use OC\Authentication\Token\RemoteWipe;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RemoteWipeTest extends TestCase {

	/** @var ITokenProvider|MockObject */
	private $tokenProvider;

	/** @var IEventDispatcher|MockObject */
	private $eventDispatcher;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var RemoteWipe */
	private $remoteWipe;

	protected function setUp(): void {
		parent::setUp();

		$this->tokenProvider = $this->createMock(IProvider::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->remoteWipe = new RemoteWipe(
			$this->tokenProvider,
			$this->eventDispatcher,
			$this->logger
		);
	}

	public function testMarkNonWipableTokenForWipe(): void {
		$token = $this->createMock(IToken::class);
		$result = $this->remoteWipe->markTokenForWipe($token);
		$this->assertFalse($result);
	}

	public function testMarkTokenForWipe(): void {
		$token = $this->createMock(IWipeableToken::class);
		$token->expects($this->once())
			->method('wipe');

		$this->tokenProvider->expects($this->once())
			->method('updateToken')
			->with($token);

		$result = $this->remoteWipe->markTokenForWipe($token);
		$this->assertTrue($result);
	}

	public function testMarkAllTokensForWipeNoWipeableToken(): void {
		/** @var IUser|MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$token1 = $this->createMock(IToken::class);
		$token2 = $this->createMock(IToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getTokenByUser')
			->with('user123')
			->willReturn([$token1, $token2]);

		$result = $this->remoteWipe->markAllTokensForWipe($user);

		$this->assertFalse($result);
	}

	public function testMarkAllTokensForWipe(): void {
		/** @var IUser|MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user123');
		$token1 = $this->createMock(IToken::class);
		$token2 = $this->createMock(IWipeableToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getTokenByUser')
			->with('user123')
			->willReturn([$token1, $token2]);
		$token2->expects($this->once())
			->method('wipe');
		$this->tokenProvider->expects($this->once())
			->method('updateToken')
			->with($token2);

		$result = $this->remoteWipe->markAllTokensForWipe($user);

		$this->assertTrue($result);
	}

	public function testStartWipingNotAWipeToken() {
		$token = $this->createMock(IToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getToken')
			->with('tk1')
			->willReturn($token);
		$this->eventDispatcher->expects($this->never())
			->method('dispatch');

		$result = $this->remoteWipe->start('tk1');

		$this->assertFalse($result);
	}

	public function testStartWiping() {
		$token = $this->createMock(IToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getToken')
			->with('tk1')
			->willThrowException(new WipeTokenException($token));
		$this->eventDispatcher->expects($this->once())
			->method('dispatch');
		$this->eventDispatcher->expects($this->once())
			->method('dispatch')
			->with(RemoteWipeStarted::class, $this->equalTo(new RemoteWipeStarted($token)));

		$result = $this->remoteWipe->start('tk1');

		$this->assertTrue($result);
	}

	public function testFinishWipingNotAWipeToken() {
		$token = $this->createMock(IToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getToken')
			->with('tk1')
			->willReturn($token);
		$this->eventDispatcher->expects($this->never())
			->method('dispatch');

		$result = $this->remoteWipe->finish('tk1');

		$this->assertFalse($result);
	}

	public function startFinishWiping() {
		$token = $this->createMock(IToken::class);
		$this->tokenProvider->expects($this->once())
			->method('getToken')
			->with('tk1')
			->willThrowException(new WipeTokenException($token));
		$this->eventDispatcher->expects($this->once())
			->method('dispatch');
		$this->tokenProvider->expects($this->once())
			->method('invalidateToken')
			->with($token);
		$this->eventDispatcher->expects($this->once())
			->method('dispatch')
			->with(RemoteWipeFinished::class, $this->equalTo(new RemoteWipeFinished($token)));

		$result = $this->remoteWipe->finish('tk1');

		$this->assertTrue($result);
	}
}
