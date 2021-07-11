<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OCA\Files_External\Tests\Listener;

use OCA\Files_External\Lib\Auth\Password\LoginCredentials;
use OCA\Files_External\Listener\StorePasswordListener;
use OCP\IUser;
use OCP\Security\ICredentialsManager;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedOutEvent;
use Test\TestCase;

class StorePasswordListenerTest extends TestCase {

	/** @var ICredentialsManager */
	private $credentialsManager;

	/** @var IUser */
	private $user;

	/** @var StorePasswordListener */
	private $storePasswordListener;

	protected function setUp(): void {
		parent::setUp();

		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->storePasswordListener = new StorePasswordListener($this->credentialsManager);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')
			->willReturn('admin');
	}


	public function testIgnoreOtherEvent(): void {
		$this->credentialsManager->expects($this->never())
			->method('retrieve');
		$this->credentialsManager->expects($this->never())
			->method('store');

		$this->storePasswordListener->handle(new UserLoggedOutEvent());
	}

	public function testIgnoreTokenLogin(): void {
		$this->credentialsManager->expects($this->never())
			->method('retrieve');
		$this->credentialsManager->expects($this->never())
			->method('store');

		$event = new UserLoggedInEvent($this->user, 'admin', 'password', true);
		$this->storePasswordListener->handle($event);
	}

	public function testUserLoggedInNoCredentials(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(null);

		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('admin', LoginCredentials::CREDENTIALS_IDENTIFIER, ['user' => 'admin', 'password' => 'password']);

		$event = new UserLoggedInEvent($this->user, 'admin', 'password', false);
		$this->storePasswordListener->handle($event);
	}

	public function testUserLoggedInUpdateCredentials(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(['user' => 'admin2', 'password' => 'password2']);

		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('admin', LoginCredentials::CREDENTIALS_IDENTIFIER, ['user' => 'admin', 'password' => 'password']);

		$event = new UserLoggedInEvent($this->user, 'admin', 'password', false);
		$this->storePasswordListener->handle($event);
	}

	public function testUserLoggedInSameCredentials(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(['user' => 'admin', 'password' => 'password']);

		$this->credentialsManager->expects($this->never())
			->method('store');

		$event = new UserLoggedInEvent($this->user, 'admin', 'password', false);
		$this->storePasswordListener->handle($event);
	}

	public function testPasswordUpdatedNoCredentials(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(null);

		$this->credentialsManager->expects($this->never())
			->method('store');

		$event = new PasswordUpdatedEvent($this->user, 'password', 'recoveryPassword');
		$this->storePasswordListener->handle($event);
	}

	public function testPasswordUpdatedUpdatePassword(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(['user' => 'admin', 'password' => 'password2']);

		$this->credentialsManager->expects($this->once())
			->method('store')
			->with('admin', LoginCredentials::CREDENTIALS_IDENTIFIER, ['user' => 'admin', 'password' => 'password']);

		$event = new PasswordUpdatedEvent($this->user, 'password', 'recoveryPassword');
		$this->storePasswordListener->handle($event);
	}


	public function testPasswordUpdatedSamePassword(): void {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(['user' => 'admin', 'password' => 'password']);

		$this->credentialsManager->expects($this->never())
			->method('store');

		$event = new PasswordUpdatedEvent($this->user, 'password', 'recoveryPassword');
		$this->storePasswordListener->handle($event);
	}
}
