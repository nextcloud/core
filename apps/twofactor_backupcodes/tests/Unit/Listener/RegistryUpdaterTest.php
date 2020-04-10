<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Listener;

use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCA\TwoFactorBackupCodes\Listener\RegistryUpdater;
use OCA\TwoFactorBackupCodes\Provider\BackupCodesProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use Test\TestCase;

class RegistryUpdaterTest extends TestCase {

	/** @var IRegistry */
	private $registry;

	/** @var BackupCodesProvider */
	private $provider;

	/** @var RegistryUpdater */
	private $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->registry = $this->createMock(IRegistry::class);
		$this->provider = $this->createMock(BackupCodesProvider::class);

		$this->listener = new RegistryUpdater($this->registry, $this->provider);
	}

	public function testHandleGenericEvent() {
		$event = $this->createMock(Event::class);
		$this->registry->expects($this->never())
			->method('enableProviderFor');

		$this->listener->handle($event);
	}

	public function testHandleCodesGeneratedEvent() {
		$user = $this->createMock(IUser::class);
		$event = new CodesGenerated($user);
		$this->registry->expects($this->once())
			->method('enableProviderFor')
			->with(
				$this->provider,
				$user
			);

		$this->listener->handle($event);
	}
}
