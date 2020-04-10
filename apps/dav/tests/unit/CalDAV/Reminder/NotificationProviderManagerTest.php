<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\Tests\unit\CalDAV\Reminder;

use OCA\DAV\CalDAV\Reminder\NotificationProvider\EmailProvider;
use OCA\DAV\CalDAV\Reminder\NotificationProvider\ProviderNotAvailableException;
use OCA\DAV\CalDAV\Reminder\NotificationProvider\PushProvider;
use OCA\DAV\CalDAV\Reminder\NotificationProviderManager;
use OCA\DAV\CalDAV\Reminder\NotificationTypeDoesNotExistException;
use OCA\DAV\Capabilities;
use Test\TestCase;

class NotificationProviderManagerTest extends TestCase {

	/** @var NotificationProviderManager|\PHPUnit\Framework\MockObject\MockObject */
	private $providerManager;

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->providerManager = new NotificationProviderManager();
		$this->providerManager->registerProvider(EmailProvider::class);
	}

	/**
	 * @throws ProviderNotAvailableException
	 * @throws NotificationTypeDoesNotExistException
	 */
	public function testGetProviderForUnknownType(): void {
		$this->expectException(\OCA\DAV\CalDAV\Reminder\NotificationTypeDoesNotExistException::class);
		$this->expectExceptionMessage('Type NOT EXISTENT is not an accepted type of notification');

		$this->providerManager->getProvider('NOT EXISTENT');
	}

	/**
	 * @throws NotificationTypeDoesNotExistException
	 * @throws ProviderNotAvailableException
	 */
	public function testGetProviderForUnRegisteredType(): void {
		$this->expectException(\OCA\DAV\CalDAV\Reminder\NotificationProvider\ProviderNotAvailableException::class);
		$this->expectExceptionMessage('No notification provider for type AUDIO available');

		$this->providerManager->getProvider('AUDIO');
	}

	public function testGetProvider(): void {
		$provider = $this->providerManager->getProvider('EMAIL');
		$this->assertInstanceOf(EmailProvider::class, $provider);
	}

	public function testRegisterProvider(): void {
		$this->providerManager->registerProvider(PushProvider::class);
		$provider = $this->providerManager->getProvider('DISPLAY');
		$this->assertInstanceOf(PushProvider::class, $provider);
	}

	/**
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function testRegisterBadProvider(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid notification provider registered');

		$this->providerManager->registerProvider(Capabilities::class);
	}

	public function testHasProvider(): void {
		$this->assertTrue($this->providerManager->hasProvider('EMAIL'));
		$this->assertFalse($this->providerManager->hasProvider('EMAIL123'));
	}
}
