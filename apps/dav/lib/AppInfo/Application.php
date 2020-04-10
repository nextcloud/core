<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobia De Koninck <tobia@ledfan.be>
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

namespace OCA\DAV\AppInfo;

use OC\AppFramework\Utility\SimpleContainer;
use OCA\DAV\CalDAV\Activity\Backend;
use OCA\DAV\CalDAV\Activity\Provider\Event;
use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CalDAV\CalendarManager;
use OCA\DAV\CalDAV\Reminder\Backend as ReminderBackend;
use OCA\DAV\CalDAV\Reminder\NotificationProvider\AudioProvider;
use OCA\DAV\CalDAV\Reminder\NotificationProvider\EmailProvider;
use OCA\DAV\CalDAV\Reminder\NotificationProvider\PushProvider;
use OCA\DAV\CalDAV\Reminder\NotificationProviderManager;
use OCA\DAV\CalDAV\Reminder\Notifier;
use OCA\DAV\CalDAV\Reminder\ReminderService;
use OCA\DAV\Capabilities;
use OCA\DAV\CardDAV\ContactsManager;
use OCA\DAV\CardDAV\PhotoCache;
use OCA\DAV\CardDAV\SyncService;
use OCA\DAV\HookManager;
use OCP\AppFramework\App;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\Contacts\IManager as IContactsManager;
use OCP\IUser;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	const APP_ID = 'dav';

	/**
	 * Application constructor.
	 */
	public function __construct() {
		parent::__construct('dav');

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService(PhotoCache::class, function (SimpleContainer $s) use ($server) {
			return new PhotoCache(
				$server->getAppDataDir('dav-photocache'),
				$server->getLogger()
			);
		});

		/*
		 * Register capabilities
		 */
		$container->registerCapability(Capabilities::class);
	}

	/**
	 * @param IContactsManager $contactsManager
	 * @param string $userID
	 */
	public function setupContactsProvider(IContactsManager $contactsManager, $userID) {
		/** @var ContactsManager $cm */
		$cm = $this->getContainer()->query(ContactsManager::class);
		$urlGenerator = $this->getContainer()->getServer()->getURLGenerator();
		$cm->setupContactsProvider($contactsManager, $userID, $urlGenerator);
	}

	/**
	 * @param IContactsManager $contactsManager
	 */
	public function setupSystemContactsProvider(IContactsManager $contactsManager) {
		/** @var ContactsManager $cm */
		$cm = $this->getContainer()->query(ContactsManager::class);
		$urlGenerator = $this->getContainer()->getServer()->getURLGenerator();
		$cm->setupSystemContactsProvider($contactsManager, $urlGenerator);
	}

	/**
	 * @param ICalendarManager $calendarManager
	 * @param string $userId
	 */
	public function setupCalendarProvider(ICalendarManager $calendarManager, $userId) {
		$cm = $this->getContainer()->query(CalendarManager::class);
		$cm->setupCalendarProvider($calendarManager, $userId);
	}

	public function registerHooks() {
		/** @var HookManager $hm */
		$hm = $this->getContainer()->query(HookManager::class);
		$hm->setup();

		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();

		// first time login event setup
		$dispatcher->addListener(IUser::class . '::firstLogin', function ($event) use ($hm) {
			if ($event instanceof GenericEvent) {
				$hm->firstLogin($event->getSubject());
			}
		});

		$birthdayListener = function ($event) {
			if ($event instanceof GenericEvent) {
				/** @var BirthdayService $b */
				$b = $this->getContainer()->query(BirthdayService::class);
				$b->onCardChanged(
					$event->getArgument('addressBookId'),
					$event->getArgument('cardUri'),
					$event->getArgument('cardData')
				);
			}
		};

		$dispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::createCard', $birthdayListener);
		$dispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::updateCard', $birthdayListener);
		$dispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::deleteCard', function ($event) {
			if ($event instanceof GenericEvent) {
				/** @var BirthdayService $b */
				$b = $this->getContainer()->query(BirthdayService::class);
				$b->onCardDeleted(
					$event->getArgument('addressBookId'),
					$event->getArgument('cardUri')
				);
			}
		});

		$clearPhotoCache = function ($event) {
			if ($event instanceof GenericEvent) {
				/** @var PhotoCache $p */
				$p = $this->getContainer()->query(PhotoCache::class);
				$p->delete(
					$event->getArgument('addressBookId'),
					$event->getArgument('cardUri')
				);
			}
		};
		$dispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::updateCard', $clearPhotoCache);
		$dispatcher->addListener('\OCA\DAV\CardDAV\CardDavBackend::deleteCard', $clearPhotoCache);

		$dispatcher->addListener('OC\AccountManager::userUpdated', function (GenericEvent $event) {
			$user = $event->getSubject();
			$syncService = $this->getContainer()->query(SyncService::class);
			$syncService->updateUser($user);
		});

		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::createCalendar', function (GenericEvent $event) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);
			$backend->onCalendarAdd(
				$event->getArgument('calendarData')
			);
		});
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::updateCalendar', function (GenericEvent $event) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);
			$backend->onCalendarUpdate(
				$event->getArgument('calendarData'),
				$event->getArgument('shares'),
				$event->getArgument('propertyMutations')
			);
		});
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendar', function (GenericEvent $event) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);
			$backend->onCalendarDelete(
				$event->getArgument('calendarData'),
				$event->getArgument('shares')
			);

			$reminderBackend = $this->getContainer()->query(ReminderBackend::class);
			$reminderBackend->cleanRemindersForCalendar(
				$event->getArgument('calendarId')
			);
		});
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::updateShares', function (GenericEvent $event) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);
			$backend->onCalendarUpdateShares(
				$event->getArgument('calendarData'),
				$event->getArgument('shares'),
				$event->getArgument('add'),
				$event->getArgument('remove')
			);

			// Here we should recalculate if reminders should be sent to new or old sharees
		});

		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::publishCalendar', function (GenericEvent $event) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);
			$backend->onCalendarPublication(
				$event->getArgument('calendarData'),
				$event->getArgument('public')
			);
		});

		$listener = function (GenericEvent $event, $eventName) {
			/** @var Backend $backend */
			$backend = $this->getContainer()->query(Backend::class);

			$subject = Event::SUBJECT_OBJECT_ADD;
			if ($eventName === '\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject') {
				$subject = Event::SUBJECT_OBJECT_UPDATE;
			} else if ($eventName === '\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject') {
				$subject = Event::SUBJECT_OBJECT_DELETE;
			}
			$backend->onTouchCalendarObject(
				$subject,
				$event->getArgument('calendarData'),
				$event->getArgument('shares'),
				$event->getArgument('objectData')
			);

			/** @var ReminderService $reminderBackend */
			$reminderService = $this->getContainer()->query(ReminderService::class);

			$reminderService->onTouchCalendarObject(
				$eventName,
				$event->getArgument('objectData')
			);
		};
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject', $listener);
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject', $listener);
		$dispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject', $listener);
	}

	public function getSyncService() {
		return $this->getContainer()->query(SyncService::class);
	}

	public function registerNotifier():void {
		$this->getContainer()
			->getServer()
			->getNotificationManager()
			->registerNotifierService(Notifier::class);
	}

	public function registerCalendarReminders():void {
		try {
			/** @var NotificationProviderManager $notificationProviderManager */
			$notificationProviderManager = $this->getContainer()->query(NotificationProviderManager::class);
			$notificationProviderManager->registerProvider(AudioProvider::class);
			$notificationProviderManager->registerProvider(EmailProvider::class);
			$notificationProviderManager->registerProvider(PushProvider::class);
		} catch(\Exception $ex) {
			$this->getContainer()->getServer()->getLogger()->logException($ex);
		}
	}

}
