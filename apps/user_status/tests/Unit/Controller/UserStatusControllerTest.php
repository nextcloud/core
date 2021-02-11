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

namespace OCA\UserStatus\Tests\Controller;

use OCA\UserStatus\Controller\UserStatusController;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Exception\InvalidClearAtException;
use OCA\UserStatus\Exception\InvalidMessageIdException;
use OCA\UserStatus\Exception\InvalidStatusIconException;
use OCA\UserStatus\Exception\InvalidStatusTypeException;
use OCA\UserStatus\Exception\StatusMessageTooLongException;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use Test\TestCase;
use Throwable;

class UserStatusControllerTest extends TestCase {

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	/** @var StatusService|\PHPUnit\Framework\MockObject\MockObject */
	private $service;

	/** @var UserStatusController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$request = $this->createMock(IRequest::class);
		$userId = 'john.doe';
		$this->logger = $this->createMock(ILogger::class);
		$this->service = $this->createMock(StatusService::class);

		$this->controller = new UserStatusController('user_status', $request, $userId, $this->logger, $this->service);
	}

	public function testGetStatus(): void {
		$userStatus = $this->getUserStatus();

		$this->service->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willReturn($userStatus);

		$response = $this->controller->getStatus();
		$this->assertEquals([
			'userId' => 'john.doe',
			'status' => 'invisible',
			'icon' => '🏝',
			'message' => 'On vacation',
			'clearAt' => 60000,
			'statusIsUserDefined' => true,
			'messageIsPredefined' => false,
			'messageId' => null,
		], $response->getData());
	}

	public function testGetStatusDoesNotExist(): void {
		$this->service->expects($this->once())
			->method('findByUserId')
			->with('john.doe')
			->willThrowException(new DoesNotExistException(''));

		$this->expectException(OCSNotFoundException::class);
		$this->expectExceptionMessage('No status for the current user');

		$this->controller->getStatus();
	}

	/**
	 * @param string $statusType
	 * @param string|null $statusIcon
	 * @param string|null $message
	 * @param int|null $clearAt
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param Throwable|null $exception
	 * @param bool $expectLogger
	 * @param string|null $expectedLogMessage
	 *
	 * @dataProvider setStatusDataProvider
	 */
	public function testSetStatus(string $statusType,
								  ?string $statusIcon,
								  ?string $message,
								  ?int $clearAt,
								  bool $expectSuccess,
								  bool $expectException,
								  ?Throwable $exception,
								  bool $expectLogger,
								  ?string $expectedLogMessage): void {
		$userStatus = $this->getUserStatus();

		if ($expectException) {
			$this->service->expects($this->once())
				->method('setStatus')
				->with('john.doe', $statusType, null, true)
				->willThrowException($exception);
		} else {
			$this->service->expects($this->once())
				->method('setStatus')
				->with('john.doe', $statusType, null, true)
				->willReturn($userStatus);
		}

		if ($expectLogger) {
			$this->logger->expects($this->once())
				->method('debug')
				->with($expectedLogMessage);
		}
		if ($expectException) {
			$this->expectException(OCSBadRequestException::class);
			$this->expectExceptionMessage('Original exception message');
		}

		$response = $this->controller->setStatus($statusType);

		if ($expectSuccess) {
			$this->assertEquals([
				'userId' => 'john.doe',
				'status' => 'invisible',
				'icon' => '🏝',
				'message' => 'On vacation',
				'clearAt' => 60000,
				'statusIsUserDefined' => true,
				'messageIsPredefined' => false,
				'messageId' => null,
			], $response->getData());
		}
	}

	public function setStatusDataProvider(): array {
		return [
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, true, false, null, false, null],
			['busy', '👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidStatusTypeException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid status type "busy"'],
		];
	}

	/**
	 * @param string $messageId
	 * @param int|null $clearAt
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param Throwable|null $exception
	 * @param bool $expectLogger
	 * @param string|null $expectedLogMessage
	 *
	 * @dataProvider setPredefinedMessageDataProvider
	 */
	public function testSetPredefinedMessage(string $messageId,
											 ?int $clearAt,
											 bool $expectSuccess,
											 bool $expectException,
											 ?Throwable $exception,
											 bool $expectLogger,
											 ?string $expectedLogMessage): void {
		$userStatus = $this->getUserStatus();

		if ($expectException) {
			$this->service->expects($this->once())
				->method('setPredefinedMessage')
				->with('john.doe', $messageId, $clearAt)
				->willThrowException($exception);
		} else {
			$this->service->expects($this->once())
				->method('setPredefinedMessage')
				->with('john.doe', $messageId, $clearAt)
				->willReturn($userStatus);
		}

		if ($expectLogger) {
			$this->logger->expects($this->once())
				->method('debug')
				->with($expectedLogMessage);
		}
		if ($expectException) {
			$this->expectException(OCSBadRequestException::class);
			$this->expectExceptionMessage('Original exception message');
		}

		$response = $this->controller->setPredefinedMessage($messageId, $clearAt);

		if ($expectSuccess) {
			$this->assertEquals([
				'userId' => 'john.doe',
				'status' => 'invisible',
				'icon' => '🏝',
				'message' => 'On vacation',
				'clearAt' => 60000,
				'statusIsUserDefined' => true,
				'messageIsPredefined' => false,
				'messageId' => null,
			], $response->getData());
		}
	}

	public function setPredefinedMessageDataProvider(): array {
		return [
			['messageId-42', 500, true, false, null, false, null],
			['messageId-42', 500, false, true, new InvalidClearAtException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid clearAt value "500"'],
			['messageId-42', 500, false, true, new InvalidMessageIdException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid message-id "messageId-42"'],
		];
	}

	/**
	 * @param string|null $statusIcon
	 * @param string $message
	 * @param int|null $clearAt
	 * @param bool $expectSuccess
	 * @param bool $expectException
	 * @param Throwable|null $exception
	 * @param bool $expectLogger
	 * @param string|null $expectedLogMessage
	 * @param bool $expectSuccessAsReset
	 *
	 * @dataProvider setCustomMessageDataProvider
	 */
	public function testSetCustomMessage(?string $statusIcon,
										 string $message,
										 ?int $clearAt,
										 bool $expectSuccess,
										 bool $expectException,
										 ?Throwable $exception,
										 bool $expectLogger,
										 ?string $expectedLogMessage,
										 bool $expectSuccessAsReset = false): void {
		$userStatus = $this->getUserStatus();

		if ($expectException) {
			$this->service->expects($this->once())
				->method('setCustomMessage')
				->with('john.doe', $statusIcon, $message, $clearAt)
				->willThrowException($exception);
		} else {
			if ($expectSuccessAsReset) {
				$this->service->expects($this->never())
					->method('setCustomMessage');
				$this->service->expects($this->once())
					->method('clearMessage')
					->with('john.doe');
				$this->service->expects($this->once())
					->method('findByUserId')
					->with('john.doe')
					->willReturn($userStatus);
			} else {
				$this->service->expects($this->once())
					->method('setCustomMessage')
					->with('john.doe', $statusIcon, $message, $clearAt)
					->willReturn($userStatus);

				$this->service->expects($this->never())
					->method('clearMessage');
			}
		}

		if ($expectLogger) {
			$this->logger->expects($this->once())
				->method('debug')
				->with($expectedLogMessage);
		}
		if ($expectException) {
			$this->expectException(OCSBadRequestException::class);
			$this->expectExceptionMessage('Original exception message');
		}

		$response = $this->controller->setCustomMessage($statusIcon, $message, $clearAt);

		if ($expectSuccess) {
			$this->assertEquals([
				'userId' => 'john.doe',
				'status' => 'invisible',
				'icon' => '🏝',
				'message' => 'On vacation',
				'clearAt' => 60000,
				'statusIsUserDefined' => true,
				'messageIsPredefined' => false,
				'messageId' => null,
			], $response->getData());
		}
	}

	public function setCustomMessageDataProvider(): array {
		return [
			['👨🏽‍💻', 'Busy developing the status feature', 500, true, false, null, false, null],
			['👨🏽‍💻', '', 500, true, false, null, false, null, true],
			['👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidClearAtException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid clearAt value "500"'],
			['👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new InvalidStatusIconException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to an invalid icon value "👨🏽‍💻"'],
			['👨🏽‍💻', 'Busy developing the status feature', 500, false, true, new StatusMessageTooLongException('Original exception message'), true,
				'New user-status for "john.doe" was rejected due to a too long status message.'],
		];
	}

	public function testClearStatus(): void {
		$this->service->expects($this->once())
			->method('clearStatus')
			->with('john.doe');

		$response = $this->controller->clearStatus();
		$this->assertEquals([], $response->getData());
	}

	public function testClearMessage(): void {
		$this->service->expects($this->once())
			->method('clearMessage')
			->with('john.doe');

		$response = $this->controller->clearMessage();
		$this->assertEquals([], $response->getData());
	}

	private function getUserStatus(): UserStatus {
		$userStatus = new UserStatus();
		$userStatus->setId(1337);
		$userStatus->setUserId('john.doe');
		$userStatus->setStatus('invisible');
		$userStatus->setStatusTimestamp(5000);
		$userStatus->setIsUserDefined(true);
		$userStatus->setCustomIcon('🏝');
		$userStatus->setCustomMessage('On vacation');
		$userStatus->setClearAt(60000);

		return $userStatus;
	}
}
