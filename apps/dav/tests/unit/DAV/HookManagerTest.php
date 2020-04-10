<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\Tests\unit\DAV;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\SyncService;
use OCA\DAV\HookManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

class HookManagerTest extends TestCase {
	/** @var IL10N */
	private $l10n;

	/** @var  EventDispatcherInterface | \PHPUnit_Framework_MockObject_MockObject  */
	private $eventDispatcher;

	protected function setUp(): void {
		parent::setUp();
		$this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n
			->expects($this->any())
			->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
	}

	public function test() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->expects($this->once())->method('getUID')->willReturn('newUser');

		/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var SyncService | \PHPUnit_Framework_MockObject_MockObject $syncService */
		$syncService = $this->getMockBuilder(SyncService::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject $cal */
		$cal = $this->getMockBuilder(CalDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$cal->expects($this->once())->method('getCalendarsForUserCount')->willReturn(0);
		$cal->expects($this->once())->method('createCalendar')->with(
			'principals/users/newUser',
			'personal', ['{DAV:}displayname' => 'Personal']);

		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $card */
		$card = $this->getMockBuilder(CardDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$card->expects($this->once())->method('getAddressBooksForUserCount')->willReturn(0);
		$card->expects($this->once())->method('createAddressBook')->with(
			'principals/users/newUser',
			'contacts', ['{DAV:}displayname' => 'Contacts']);

		$hm = new HookManager($userManager, $syncService, $cal, $card, $this->eventDispatcher);
		$hm->firstLogin($user);
	}

	public function testWithExisting() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->expects($this->once())->method('getUID')->willReturn('newUser');

		/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var SyncService | \PHPUnit_Framework_MockObject_MockObject $syncService */
		$syncService = $this->getMockBuilder(SyncService::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject $cal */
		$cal = $this->getMockBuilder(CalDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$cal->expects($this->once())->method('getCalendarsForUserCount')->willReturn(1);
		$cal->expects($this->never())->method('createCalendar');

		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $card */
		$card = $this->getMockBuilder(CardDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$card->expects($this->once())->method('getAddressBooksForUserCount')->willReturn(1);
		$card->expects($this->never())->method('createAddressBook');

		$hm = new HookManager($userManager, $syncService, $cal, $card, $this->eventDispatcher);
		$hm->firstLogin($user);
	}

	public function testWithBirthdayCalendar() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();
		$user->expects($this->once())->method('getUID')->willReturn('newUser');

		/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var SyncService | \PHPUnit_Framework_MockObject_MockObject $syncService */
		$syncService = $this->getMockBuilder(SyncService::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject $cal */
		$cal = $this->getMockBuilder(CalDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$cal->expects($this->once())->method('getCalendarsForUserCount')->willReturn(0);
		$cal->expects($this->once())->method('createCalendar')->with(
			'principals/users/newUser',
			'personal', ['{DAV:}displayname' => 'Personal']);

		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $card */
		$card = $this->getMockBuilder(CardDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$card->expects($this->once())->method('getAddressBooksForUserCount')->willReturn(0);
		$card->expects($this->once())->method('createAddressBook')->with(
			'principals/users/newUser',
			'contacts', ['{DAV:}displayname' => 'Contacts']);

		$hm = new HookManager($userManager, $syncService, $cal, $card, $this->eventDispatcher);
		$hm->firstLogin($user);
	}

	public function testDeleteCalendar() {
		$user = $this->getMockBuilder(IUser::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var IUserManager | \PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$userManager->expects($this->once())->method('get')->willReturn($user);

		/** @var SyncService | \PHPUnit_Framework_MockObject_MockObject $syncService */
		$syncService = $this->getMockBuilder(SyncService::class)
			->disableOriginalConstructor()
			->getMock();
		$syncService->expects($this->once())
			->method('deleteUser');

		/** @var CalDavBackend | \PHPUnit_Framework_MockObject_MockObject $cal */
		$cal = $this->getMockBuilder(CalDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$cal->expects($this->once())->method('getUsersOwnCalendars')->willReturn([
			['id' => 'personal']
		]);
		$cal->expects($this->once())->method('deleteCalendar');
		$cal->expects($this->once())->method('deleteAllSharesByUser');

		/** @var CardDavBackend | \PHPUnit_Framework_MockObject_MockObject $card */
		$card = $this->getMockBuilder(CardDavBackend::class)
			->disableOriginalConstructor()
			->getMock();
		$card->expects($this->once())->method('getUsersOwnAddressBooks')->willReturn([
			['id' => 'personal']
		]);
		$card->expects($this->once())->method('deleteAddressBook');

		$hm = new HookManager($userManager, $syncService, $cal, $card, $this->eventDispatcher);
		$hm->preDeleteUser(['uid' => 'newUser']);
		$hm->postDeleteUser(['uid' => 'newUser']);
	}
}
