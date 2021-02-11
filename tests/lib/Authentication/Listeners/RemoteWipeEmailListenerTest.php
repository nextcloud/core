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

namespace lib\Authentication\Listeners;

use Exception;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Listeners\RemoteWipeEmailListener;
use OC\Authentication\Token\IToken;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RemoteWipeEmailListenerTest extends TestCase {

	/** @var IMailer|MockObject */
	private $mailer;

	/** @var IUserManager|MockObject */
	private $userManager;

	/** @var IFactory|MockObject */
	private $l10nFactory;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var IEventListener */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->mailer = $this->createMock(IMailer::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->l10nFactory->method('get')->with('core')->willReturn($this->l10n);
		$this->l10n->method('t')->willReturnArgument(0);

		$this->listener = new RemoteWipeEmailListener(
			$this->mailer,
			$this->userManager,
			$this->l10nFactory,
			$this->logger
		);
	}


	public function testHandleUnrelated() {
		$event = new Event();
		$this->mailer->expects($this->never())->method('send');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeStartedInvalidUser() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$token->method('getUID')->willReturn('nope');
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn(null);
		$this->mailer->expects($this->never())->method('send');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeStartedNoEmailSet() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn(null);
		$this->mailer->expects($this->never())->method('send');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeStartedTransmissionError() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn('user@domain.org');
		$this->mailer->expects($this->once())
			->method('send')
			->willThrowException(new Exception());
		$this->logger->expects($this->once())
			->method('error');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeStarted() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeStarted($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn('user@domain.org');
		$message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$message->expects($this->once())
			->method('setTo')
			->with($this->equalTo(['user@domain.org']));
		$this->mailer->expects($this->once())
			->method('send')
			->with($message);

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinishedInvalidUser() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$token->method('getUID')->willReturn('nope');
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn(null);
		$this->mailer->expects($this->never())->method('send');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinishedNoEmailSet() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn(null);
		$this->mailer->expects($this->never())->method('send');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinishedTransmissionError() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn('user@domain.org');
		$this->mailer->expects($this->once())
			->method('send')
			->willThrowException(new Exception());
		$this->logger->expects($this->once())
			->method('error');

		$this->listener->handle($event);
	}

	public function testHandleRemoteWipeFinished() {
		/** @var IToken|MockObject $token */
		$token = $this->createMock(IToken::class);
		$event = new RemoteWipeFinished($token);
		$token->method('getUID')->willReturn('nope');
		$user = $this->createMock(IUser::class);
		$this->userManager->expects($this->once())
			->method('get')
			->with('nope')
			->willReturn($user);
		$user->method('getEMailAddress')->willReturn('user@domain.org');
		$message = $this->createMock(IMessage::class);
		$this->mailer->expects($this->once())
			->method('createMessage')
			->willReturn($message);
		$message->expects($this->once())
			->method('setTo')
			->with($this->equalTo(['user@domain.org']));
		$this->mailer->expects($this->once())
			->method('send')
			->with($message);

		$this->listener->handle($event);
	}
}
