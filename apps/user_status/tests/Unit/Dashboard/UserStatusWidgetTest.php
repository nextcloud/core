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

namespace OCA\UserStatus\Tests\Dashboard;

use OCA\UserStatus\Dashboard\UserStatusWidget;
use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Service\StatusService;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

class UserStatusWidgetTest extends TestCase {

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var IInitialStateService|\PHPUnit\Framework\MockObject\MockObject */
	private $initialState;

	/** @var IUserManager|\PHPUnit\Framework\MockObject\MockObject */
	private $userManager;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var StatusService|\PHPUnit\Framework\MockObject\MockObject */
	private $service;

	/** @var UserStatusWidget */
	private $widget;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->initialState = $this->createMock(IInitialStateService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->service = $this->createMock(StatusService::class);

		$this->widget = new UserStatusWidget($this->l10n, $this->initialState, $this->userManager, $this->userSession, $this->service);
	}

	public function testGetId(): void {
		$this->assertEquals('user_status', $this->widget->getId());
	}

	public function testGetTitle(): void {
		$this->l10n->expects($this->exactly(1))
			->method('t')
			->willReturnArgument(0);

		$this->assertEquals('Recent statuses', $this->widget->getTitle());
	}

	public function testGetOrder(): void {
		$this->assertEquals(5, $this->widget->getOrder());
	}

	public function testGetIconClass(): void {
		$this->assertEquals('icon-user-status', $this->widget->getIconClass());
	}

	public function testGetUrl(): void {
		$this->assertNull($this->widget->getUrl());
	}

	public function testLoadNoUserSession(): void {
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn(null);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('user_status', 'dashboard_data', []);

		$this->service->expects($this->never())
			->method('findAllRecentStatusChanges');

		$this->widget->load();
	}

	public function testLoadWithCurrentUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUid')->willReturn('john.doe');
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$user1 = $this->createMock(IUser::class);
		$user1->method('getDisplayName')->willReturn('User No. 1');

		$this->userManager
			->method('get')
			->willReturnMap([
				['user_1', $user1],
				['user_2', null],
				['user_3', null],
				['user_4', null],
				['user_5', null],
				['user_6', null],
				['user_7', null],
			]);

		$userStatuses = [
			UserStatus::fromParams([
				'userId' => 'user_1',
				'status' => 'online',
				'customIcon' => '💻',
				'customMessage' => 'Working',
				'statusTimestamp' => 5000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_2',
				'status' => 'away',
				'customIcon' => '☕️',
				'customMessage' => 'Office Hangout',
				'statusTimestamp' => 6000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_3',
				'status' => 'dnd',
				'customIcon' => null,
				'customMessage' => null,
				'statusTimestamp' => 7000,
			]),
			UserStatus::fromParams([
				'userId' => 'john.doe',
				'status' => 'away',
				'customIcon' => '☕️',
				'customMessage' => 'Office Hangout',
				'statusTimestamp' => 90000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_4',
				'status' => 'dnd',
				'customIcon' => null,
				'customMessage' => null,
				'statusTimestamp' => 7000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_5',
				'status' => 'invisible',
				'customIcon' => '🏝',
				'customMessage' => 'On vacation',
				'statusTimestamp' => 7000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_6',
				'status' => 'offline',
				'customIcon' => null,
				'customMessage' => null,
				'statusTimestamp' => 7000,
			]),
			UserStatus::fromParams([
				'userId' => 'user_7',
				'status' => 'invisible',
				'customIcon' => null,
				'customMessage' => null,
				'statusTimestamp' => 7000,
			]),
		];

		$this->service->expects($this->once())
			->method('findAllRecentStatusChanges')
			->with(8, 0)
			->willReturn($userStatuses);

		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->with('user_status', 'dashboard_data', $this->callback(function ($data): bool {
				$this->assertEquals([
					[
						'userId' => 'user_1',
						'displayName' => 'User No. 1',
						'status' => 'online',
						'icon' => '💻',
						'message' => 'Working',
						'timestamp' => 5000,
					],
					[
						'userId' => 'user_2',
						'displayName' => 'user_2',
						'status' => 'away',
						'icon' => '☕️',
						'message' => 'Office Hangout',
						'timestamp' => 6000,
					],
					[
						'userId' => 'user_3',
						'displayName' => 'user_3',
						'status' => 'dnd',
						'icon' => null,
						'message' => null,
						'timestamp' => 7000,
					],
					[
						'userId' => 'user_4',
						'displayName' => 'user_4',
						'status' => 'dnd',
						'icon' => null,
						'message' => null,
						'timestamp' => 7000,
					],
					[
						'userId' => 'user_5',
						'displayName' => 'user_5',
						'status' => 'offline',
						'icon' => '🏝',
						'message' => 'On vacation',
						'timestamp' => 7000,
					],
					[
						'userId' => 'user_6',
						'displayName' => 'user_6',
						'status' => 'offline',
						'icon' => null,
						'message' => null,
						'timestamp' => 7000,
					],
					[
						'userId' => 'user_7',
						'displayName' => 'user_7',
						'status' => 'offline',
						'icon' => null,
						'message' => null,
						'timestamp' => 7000,
					],
				], $data);
				return true;
			}));

		$this->widget->load();
	}
}
