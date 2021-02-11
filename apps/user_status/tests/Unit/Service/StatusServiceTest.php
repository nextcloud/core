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

namespace OCA\UserStatus\Tests\Service;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Db\UserStatusMapper;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidMessageIdException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCA\UserStatus\Service\EmojiService;
use OCA\UserStatus\Service\PredefinedStatusService;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use Test\TestCase;

class StatusServiceTest extends TestCase {

	/** @var UserStatusMapper|\PHPUnit\Framework\MockObject\MockObject */
	private $mapper;

	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;

	/** @var PredefinedStatusService|\PHPUnit\Framework\MockObject\MockObject */
	private $predefinedStatusService;

	/** @var EmojiService|\PHPUnit\Framework\MockObject\MockObject */
	private $emojiService;

	/** @var StatusService */
	private $service;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = $this->createMock(UserStatusMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->predefinedStatusService = $this->createMock(PredefinedStatusService::class);
		$this->emojiService = $this->createMock(EmojiService::class);
		$this->service = new StatusService($this->mapper,
			$this->timeFactory,
			$this->predefinedStatusService,
			$this->emojiService);
	}

	public function testFindAll(): void {
		$status1 = $this->createMock(UserStatus::class);
		$status2 = $this->createMock(UserStatus::class);

		$this->mapper->expects($this->once())
			->method('findAll')
			->with(20, 50)
			->willReturn([$status1, $status2]);

		$this->assertEquals([
			$status1,
			$status2,
		], $this->service->findAll(20, 50));
	}

	public function testFindAllRecentStatusChanges(): void {
		$status1 = $this->createMock(UserStatus::class);
		$status2 = $this->createMock(UserStatus::class);

		$this->mapper->expects($this->once())
			->method('findAllRecent')
			->with(20, 50)
			->willReturn([$status1, $status2]);

		$this->assertEquals([
			$status1,
			$status2,
		], $this->service->findAllRecentStatusChanges(20, 50));
	}

	public function testFindByUserId(): void {
		$status = $this->createMock(UserStatus::class);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->assertEquals($status, $this->service->findByUserId('john.doe'));
	}

	public function testFindByUserIdDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(DoesNotExistException::class);
		$this->service->findByUserId('john.doe');
	}

	public function testFindAllAddDefaultMessage(): void {
		$status = new UserStatus();
		$status->setMessageId('commuting');

		$this->predefinedStatusService->expects($this->once())
			->method('getDefaultStatusById')
			->with('commuting')
			->willReturn([
				'id' => 'commuting',
				'icon' => '🚌',
				'message' => 'Commuting',
				'clearAt' => [
					'type' => 'period',
					'time' => 1800,
				],
			]);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->assertEquals($status, $this->service->findByUserId('john.doe'));
		$this->assertEquals('🚌', $status->getCustomIcon());
		$this->assertEquals('Commuting', $status->getCustomMessage());
	}

	public function testFindAllClearStatus(): void {
		$status = new UserStatus();
		$status->setStatus('online');
		$status->setStatusTimestamp(1000);
		$status->setIsUserDefined(true);

		$this->timeFactory->method('getTime')
			->willReturn(2600);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->assertEquals($status, $this->service->findByUserId('john.doe'));
		$this->assertEquals('offline', $status->getStatus());
		$this->assertEquals(2600, $status->getStatusTimestamp());
		$this->assertFalse($status->getIsUserDefined());
	}

	public function testFindAllClearMessage(): void {
		$status = new UserStatus();
		$status->setClearAt(50);
		$status->setMessageId('commuting');
		$status->setStatusTimestamp(60);

		$this->timeFactory->method('getTime')
			->willReturn(60);
		$this->predefinedStatusService->expects($this->never())
			->method('getDefaultStatusById');
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);
		$this->assertEquals($status, $this->service->findByUserId('john.doe'));
		$this->assertNull($status->getClearAt());
		$this->assertNull($status->getMessageId());
	}

	/**
	 * @param string $userId
	 * @param string $status
	 * @param int|null $statusTimestamp
	 * @param bool $isUserDefined
	 * @param bool $expectExisting
	 * @param bool $expectSuccess
	 * @param bool $expectTimeFactory
	 * @param bool $expectException
	 * @param string|null $expectedExceptionClass
	 * @param string|null $expectedExceptionMessage
	 *
	 * @dataProvider setStatusDataProvider
	 */
	public function testSetStatus(string $userId,
								  string $status,
								  ?int $statusTimestamp,
								  bool $isUserDefined,
								  bool $expectExisting,
								  bool $expectSuccess,
								  bool $expectTimeFactory,
								  bool $expectException,
								  ?string $expectedExceptionClass,
								  ?string $expectedExceptionMessage): void {
		$userStatus = new UserStatus();

		if ($expectExisting) {
			$userStatus->setId(42);
			$userStatus->setUserId($userId);

			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willReturn($userStatus);
		} else {
			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willThrowException(new DoesNotExistException(''));
		}

		if ($expectTimeFactory) {
			$this->timeFactory
				->method('getTime')
				->willReturn(40);
		}

		if ($expectException) {
			$this->expectException($expectedExceptionClass);
			$this->expectExceptionMessage($expectedExceptionMessage);

			$this->service->setStatus($userId, $status, $statusTimestamp, $isUserDefined);
		}

		if ($expectSuccess) {
			if ($expectExisting) {
				$this->mapper->expects($this->once())
					->method('update')
					->willReturnArgument(0);
			} else {
				$this->mapper->expects($this->once())
					->method('insert')
					->willReturnArgument(0);
			}

			$actual = $this->service->setStatus($userId, $status, $statusTimestamp, $isUserDefined);

			$this->assertEquals('john.doe', $actual->getUserId());
			$this->assertEquals($status, $actual->getStatus());
			$this->assertEquals($statusTimestamp ?? 40, $actual->getStatusTimestamp());
			$this->assertEquals($isUserDefined, $actual->getIsUserDefined());
		}
	}

	public function setStatusDataProvider(): array {
		return [
			['john.doe', 'online', 50,   true,  true,  true, false, false, null, null],
			['john.doe', 'online', 50,   true,  false, true, false, false, null, null],
			['john.doe', 'online', 50,   false, true,  true, false, false, null, null],
			['john.doe', 'online', 50,   false, false, true, false, false, null, null],
			['john.doe', 'online', null, true,  true,  true, true,  false, null, null],
			['john.doe', 'online', null, true,  false, true, true,  false, null, null],
			['john.doe', 'online', null, false, true,  true, true,  false, null, null],
			['john.doe', 'online', null, false, false, true, true,  false, null, null],

			['john.doe', 'away', 50,   true,  true,  true, false, false, null, null],
			['john.doe', 'away', 50,   true,  false, true, false, false, null, null],
			['john.doe', 'away', 50,   false, true,  true, false, false, null, null],
			['john.doe', 'away', 50,   false, false, true, false, false, null, null],
			['john.doe', 'away', null, true,  true,  true, true,  false, null, null],
			['john.doe', 'away', null, true,  false, true, true,  false, null, null],
			['john.doe', 'away', null, false, true,  true, true,  false, null, null],
			['john.doe', 'away', null, false, false, true, true,  false, null, null],

			['john.doe', 'dnd', 50,   true,  true,  true, false, false, null, null],
			['john.doe', 'dnd', 50,   true,  false, true, false, false, null, null],
			['john.doe', 'dnd', 50,   false, true,  true, false, false, null, null],
			['john.doe', 'dnd', 50,   false, false, true, false, false, null, null],
			['john.doe', 'dnd', null, true,  true,  true, true,  false, null, null],
			['john.doe', 'dnd', null, true,  false, true, true,  false, null, null],
			['john.doe', 'dnd', null, false, true,  true, true,  false, null, null],
			['john.doe', 'dnd', null, false, false, true, true,  false, null, null],

			['john.doe', 'invisible', 50,   true,  true,  true, false, false, null, null],
			['john.doe', 'invisible', 50,   true,  false, true, false, false, null, null],
			['john.doe', 'invisible', 50,   false, true,  true, false, false, null, null],
			['john.doe', 'invisible', 50,   false, false, true, false, false, null, null],
			['john.doe', 'invisible', null, true,  true,  true, true,  false, null, null],
			['john.doe', 'invisible', null, true,  false, true, true,  false, null, null],
			['john.doe', 'invisible', null, false, true,  true, true,  false, null, null],
			['john.doe', 'invisible', null, false, false, true, true,  false, null, null],

			['john.doe', 'offline', 50,   true,  true,  true, false, false, null, null],
			['john.doe', 'offline', 50,   true,  false, true, false, false, null, null],
			['john.doe', 'offline', 50,   false, true,  true, false, false, null, null],
			['john.doe', 'offline', 50,   false, false, true, false, false, null, null],
			['john.doe', 'offline', null, true,  true,  true, true,  false, null, null],
			['john.doe', 'offline', null, true,  false, true, true,  false, null, null],
			['john.doe', 'offline', null, false, true,  true, true,  false, null, null],
			['john.doe', 'offline', null, false, false, true, true,  false, null, null],

			['john.doe', 'illegal-status', 50,   true,  true,  false, false, true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', 50,   true,  false, false, false, true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', 50,   false, true,  false, false, true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', 50,   false, false, false, false, true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', null, true,  true,  false, true,  true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', null, true,  false, false, true,  true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', null, false, true,  false, true,  true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
			['john.doe', 'illegal-status', null, false, false, false, true,  true, InvalidStatusTypeException::class, 'Status-type "illegal-status" is not supported'],
		];
	}

	/**
	 * @param string $userId
	 * @param string $messageId
	 * @param bool $isValidMessageId
	 * @param int|null $clearAt
	 * @param bool $expectExisting
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param string|null $expectedExceptionClass
	 * @param string|null $expectedExceptionMessage
	 *
	 * @dataProvider setPredefinedMessageDataProvider
	 */
	public function testSetPredefinedMessage(string $userId,
											 string $messageId,
											 bool $isValidMessageId,
											 ?int $clearAt,
											 bool $expectExisting,
											 bool $expectSuccess,
											 bool $expectException,
											 ?string $expectedExceptionClass,
											 ?string $expectedExceptionMessage): void {
		$userStatus = new UserStatus();

		if ($expectExisting) {
			$userStatus->setId(42);
			$userStatus->setUserId($userId);
			$userStatus->setStatus('offline');
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
			$userStatus->setCustomIcon('😀');
			$userStatus->setCustomMessage('Foo');

			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willReturn($userStatus);
		} else {
			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willThrowException(new DoesNotExistException(''));
		}

		$this->predefinedStatusService->expects($this->once())
			->method('isValidId')
			->with($messageId)
			->willReturn($isValidMessageId);

		$this->timeFactory
			->method('getTime')
			->willReturn(40);

		if ($expectException) {
			$this->expectException($expectedExceptionClass);
			$this->expectExceptionMessage($expectedExceptionMessage);

			$this->service->setPredefinedMessage($userId, $messageId, $clearAt);
		}

		if ($expectSuccess) {
			if ($expectExisting) {
				$this->mapper->expects($this->once())
					->method('update')
					->willReturnArgument(0);
			} else {
				$this->mapper->expects($this->once())
					->method('insert')
					->willReturnArgument(0);
			}

			$actual = $this->service->setPredefinedMessage($userId, $messageId, $clearAt);

			$this->assertEquals('john.doe', $actual->getUserId());
			$this->assertEquals('offline', $actual->getStatus());
			$this->assertEquals(0, $actual->getStatusTimestamp());
			$this->assertEquals(false, $actual->getIsUserDefined());
			$this->assertEquals($messageId, $actual->getMessageId());
			$this->assertNull($actual->getCustomIcon());
			$this->assertNull($actual->getCustomMessage());
			$this->assertEquals($clearAt, $actual->getClearAt());
		}
	}

	public function setPredefinedMessageDataProvider(): array {
		return [
			['john.doe', 'sick-leave', true, null, true,  true,  false, null, null],
			['john.doe', 'sick-leave', true, null, false, true,  false, null, null],
			['john.doe', 'sick-leave', true, 20,   true,  false, true,  InvalidClearAtException::class, 'ClearAt is in the past'],
			['john.doe', 'sick-leave', true, 20,   false, false, true,  InvalidClearAtException::class, 'ClearAt is in the past'],
			['john.doe', 'sick-leave', true, 60,   true,  true,  false, null, null],
			['john.doe', 'sick-leave', true, 60,   false, true,  false, null, null],
			['john.doe', 'illegal-message-id', false, null, true, false, true, InvalidMessageIdException::class, 'Message-Id "illegal-message-id" is not supported'],
			['john.doe', 'illegal-message-id', false, null, false, false, true, InvalidMessageIdException::class, 'Message-Id "illegal-message-id" is not supported'],
		];
	}

	/**
	 * @param string $userId
	 * @param string|null $statusIcon
	 * @param bool $supportsEmoji
	 * @param string $message
	 * @param int|null $clearAt
	 * @param bool $expectExisting
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param string|null $expectedExceptionClass
	 * @param string|null $expectedExceptionMessage
	 *
	 * @dataProvider setCustomMessageDataProvider
	 */
	public function testSetCustomMessage(string $userId,
										 ?string $statusIcon,
										 bool $supportsEmoji,
										 string $message,
										 ?int $clearAt,
										 bool $expectExisting,
										 bool $expectSuccess,
										 bool $expectException,
										 ?string $expectedExceptionClass,
										 ?string $expectedExceptionMessage): void {
		$userStatus = new UserStatus();

		if ($expectExisting) {
			$userStatus->setId(42);
			$userStatus->setUserId($userId);
			$userStatus->setStatus('offline');
			$userStatus->setStatusTimestamp(0);
			$userStatus->setIsUserDefined(false);
			$userStatus->setMessageId('messageId-42');

			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willReturn($userStatus);
		} else {
			$this->mapper->expects($this->once())
				->method('findByUserId')
				->with($userId)
				->willThrowException(new DoesNotExistException(''));
		}

		$this->emojiService->method('isValidEmoji')
			->with($statusIcon)
			->willReturn($supportsEmoji);

		$this->timeFactory
			->method('getTime')
			->willReturn(40);

		if ($expectException) {
			$this->expectException($expectedExceptionClass);
			$this->expectExceptionMessage($expectedExceptionMessage);

			$this->service->setCustomMessage($userId, $statusIcon, $message, $clearAt);
		}

		if ($expectSuccess) {
			if ($expectExisting) {
				$this->mapper->expects($this->once())
					->method('update')
					->willReturnArgument(0);
			} else {
				$this->mapper->expects($this->once())
					->method('insert')
					->willReturnArgument(0);
			}

			$actual = $this->service->setCustomMessage($userId, $statusIcon, $message, $clearAt);

			$this->assertEquals('john.doe', $actual->getUserId());
			$this->assertEquals('offline', $actual->getStatus());
			$this->assertEquals(0, $actual->getStatusTimestamp());
			$this->assertEquals(false, $actual->getIsUserDefined());
			$this->assertNull($actual->getMessageId());
			$this->assertEquals($statusIcon, $actual->getCustomIcon());
			$this->assertEquals($message, $actual->getCustomMessage());
			$this->assertEquals($clearAt, $actual->getClearAt());
		}
	}

	public function setCustomMessageDataProvider(): array {
		return [
			['john.doe', '😁', true, 'Custom message', null, true,  true, false, null, null],
			['john.doe', '😁', true, 'Custom message', null, false, true, false, null, null],
			['john.doe', null, false, 'Custom message', null, true,  true, false, null, null],
			['john.doe', null, false, 'Custom message', null, false, true, false, null, null],
			['john.doe', '😁', false, 'Custom message', null, true,  false, true, InvalidStatusIconException::class, 'Status-Icon is longer than one character'],
			['john.doe', '😁', false, 'Custom message', null, false, false, true, InvalidStatusIconException::class, 'Status-Icon is longer than one character'],
			['john.doe', null, false, 'Custom message that is way too long and violates the maximum length and hence should be rejected', null, true,  false, true, StatusMessageTooLongException::class, 'Message is longer than supported length of 80 characters'],
			['john.doe', null, false, 'Custom message that is way too long and violates the maximum length and hence should be rejected', null, false, false, true, StatusMessageTooLongException::class, 'Message is longer than supported length of 80 characters'],
			['john.doe', '😁', true, 'Custom message', 80, true,  true, false, null, null],
			['john.doe', '😁', true, 'Custom message', 80, false, true, false, null, null],
			['john.doe', '😁', true, 'Custom message', 20, true,  false, true, InvalidClearAtException::class, 'ClearAt is in the past'],
			['john.doe', '😁', true, 'Custom message', 20, false, false, true, InvalidClearAtException::class, 'ClearAt is in the past'],
		];
	}

	public function testClearStatus(): void {
		$status = new UserStatus();
		$status->setId(1);
		$status->setUserId('john.doe');
		$status->setStatus('dnd');
		$status->setStatusTimestamp(1337);
		$status->setIsUserDefined(true);
		$status->setMessageId('messageId-42');
		$status->setCustomIcon('🙊');
		$status->setCustomMessage('My custom status message');
		$status->setClearAt(42);

		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->mapper->expects($this->once())
			->method('update')
			->with($status);

		$actual = $this->service->clearStatus('john.doe');
		$this->assertTrue($actual);
		$this->assertEquals('offline', $status->getStatus());
		$this->assertEquals(0, $status->getStatusTimestamp());
		$this->assertFalse($status->getIsUserDefined());
	}

	public function testClearStatusDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->mapper->expects($this->never())
			->method('update');

		$actual = $this->service->clearStatus('john.doe');
		$this->assertFalse($actual);
	}

	public function testClearMessage(): void {
		$status = new UserStatus();
		$status->setId(1);
		$status->setUserId('john.doe');
		$status->setStatus('dnd');
		$status->setStatusTimestamp(1337);
		$status->setIsUserDefined(true);
		$status->setMessageId('messageId-42');
		$status->setCustomIcon('🙊');
		$status->setCustomMessage('My custom status message');
		$status->setClearAt(42);

		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->mapper->expects($this->once())
			->method('update')
			->with($status);

		$actual = $this->service->clearMessage('john.doe');
		$this->assertTrue($actual);
		$this->assertNull($status->getMessageId());
		$this->assertNull($status->getCustomMessage());
		$this->assertNull($status->getCustomIcon());
		$this->assertNull($status->getClearAt());
	}

	public function testClearMessageDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->mapper->expects($this->never())
			->method('update');

		$actual = $this->service->clearMessage('john.doe');
		$this->assertFalse($actual);
	}

	public function testRemoveUserStatus(): void {
		$status = $this->createMock(UserStatus::class);
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($status);

		$this->mapper->expects($this->once())
			->method('delete')
			->with($status);

		$actual = $this->service->removeUserStatus('john.doe');
		$this->assertTrue($actual);
	}

	public function testRemoveUserStatusDoesNotExist(): void {
		$this->mapper->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->mapper->expects($this->never())
			->method('delete');

		$actual = $this->service->removeUserStatus('john.doe');
		$this->assertFalse($actual);
	}
}
