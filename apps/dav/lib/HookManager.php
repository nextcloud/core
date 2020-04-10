<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\DAV;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\SyncService;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class HookManager {

	/** @var IUserManager */
	private $userManager;

	/** @var SyncService */
	private $syncService;

	/** @var IUser[] */
	private $usersToDelete = [];

	/** @var CalDavBackend */
	private $calDav;

	/** @var CardDavBackend */
	private $cardDav;

	/** @var array */
	private $calendarsToDelete = [];

	/** @var array */
	private $addressBooksToDelete = [];

	/** @var EventDispatcherInterface */
	private $eventDispatcher;

	public function __construct(IUserManager $userManager,
								SyncService $syncService,
								CalDavBackend $calDav,
								CardDavBackend $cardDav,
								EventDispatcherInterface $eventDispatcher) {
		$this->userManager = $userManager;
		$this->syncService = $syncService;
		$this->calDav = $calDav;
		$this->cardDav = $cardDav;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function setup() {
		Util::connectHook('OC_User',
			'post_createUser',
			$this,
			'postCreateUser');
		\OC::$server->getUserManager()->listen('\OC\User', 'assignedUserId', function ($uid) {
			$this->postCreateUser(['uid' => $uid]);
		});
		Util::connectHook('OC_User',
			'pre_deleteUser',
			$this,
			'preDeleteUser');
		\OC::$server->getUserManager()->listen('\OC\User', 'preUnassignedUserId', [$this, 'preUnassignedUserId']);
		Util::connectHook('OC_User',
			'post_deleteUser',
			$this,
			'postDeleteUser');
		\OC::$server->getUserManager()->listen('\OC\User', 'postUnassignedUserId', function ($uid) {
			$this->postDeleteUser(['uid' => $uid]);
		});
		\OC::$server->getUserManager()->listen('\OC\User', 'postUnassignedUserId', [$this, 'postUnassignedUserId']);
		Util::connectHook('OC_User',
			'changeUser',
			$this,
			'changeUser');
	}

	public function postCreateUser($params) {
		$user = $this->userManager->get($params['uid']);
		if ($user instanceof IUser) {
			$this->syncService->updateUser($user);
		}
	}

	public function preDeleteUser($params) {
		$uid = $params['uid'];
		$this->usersToDelete[$uid] = $this->userManager->get($uid);
		$this->calendarsToDelete = $this->calDav->getUsersOwnCalendars('principals/users/' . $uid);
		$this->addressBooksToDelete = $this->cardDav->getUsersOwnAddressBooks('principals/users/' . $uid);
	}

	public function preUnassignedUserId($uid) {
		$this->usersToDelete[$uid] = $this->userManager->get($uid);
	}

	public function postDeleteUser($params) {
		$uid = $params['uid'];
		if (isset($this->usersToDelete[$uid])){
			$this->syncService->deleteUser($this->usersToDelete[$uid]);
		}

		foreach ($this->calendarsToDelete as $calendar) {
			$this->calDav->deleteCalendar($calendar['id']);
		}
		$this->calDav->deleteAllSharesByUser('principals/users/' . $uid);

		foreach ($this->addressBooksToDelete as $addressBook) {
			$this->cardDav->deleteAddressBook($addressBook['id']);
		}
	}

	public function postUnassignedUserId($uid) {
		if (isset($this->usersToDelete[$uid])){
			$this->syncService->deleteUser($this->usersToDelete[$uid]);
		}
	}

	public function changeUser($params) {
		$user = $params['user'];
		$this->syncService->updateUser($user);
	}

	public function firstLogin(IUser $user = null) {
		if (!is_null($user)) {
			$principal = 'principals/users/' . $user->getUID();
			if ($this->calDav->getCalendarsForUserCount($principal) === 0) {
				try {
					$this->calDav->createCalendar($principal, CalDavBackend::PERSONAL_CALENDAR_URI, [
						'{DAV:}displayname' => CalDavBackend::PERSONAL_CALENDAR_NAME,
					]);
				} catch (\Exception $ex) {
					\OC::$server->getLogger()->logException($ex);
				}
			}
			if ($this->cardDav->getAddressBooksForUserCount($principal) === 0) {
				try {
					$this->cardDav->createAddressBook($principal, CardDavBackend::PERSONAL_ADDRESSBOOK_URI, [
						'{DAV:}displayname' => CardDavBackend::PERSONAL_ADDRESSBOOK_NAME,
					]);
				} catch (\Exception $ex) {
					\OC::$server->getLogger()->logException($ex);
				}
			}
		}
	}
}
