<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

use OCA\DAV\AppInfo\Application;
use OCA\DAV\CalDAV\WebcalCaching\RefreshWebcalService;
use OCA\DAV\CardDAV\CardDavBackend;
use Symfony\Component\EventDispatcher\GenericEvent;

\OC_App::loadApps(['dav']);

/** @var Application $app */
$app = \OC::$server->query(Application::class);
$app->registerHooks();

\OC::$server->registerService('CardDAVSyncService', function () use ($app) {
	return $app->getSyncService();
});

$eventDispatcher = \OC::$server->getEventDispatcher();

$eventDispatcher->addListener('OCP\Federation\TrustedServerEvent::remove',
	function (GenericEvent $event) use ($app) {
		/** @var CardDavBackend $cardDavBackend */
		$cardDavBackend = $app->getContainer()->query(CardDavBackend::class);
		$addressBookUri = $event->getSubject();
		$addressBook = $cardDavBackend->getAddressBooksByUri('principals/system/system', $addressBookUri);
		if (!is_null($addressBook)) {
			$cardDavBackend->deleteAddressBook($addressBook['id']);
		}
	}
);

$eventDispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::createSubscription',
	function (GenericEvent $event) use ($app) {
		$jobList = $app->getContainer()->getServer()->getJobList();
		$subscriptionData = $event->getArgument('subscriptionData');

		/**
		 * Initial subscription refetch
		 * @var RefreshWebcalService $refreshWebcalService
		 */
		$refreshWebcalService = $app->getContainer()->query(RefreshWebcalService::class);
		$refreshWebcalService->refreshSubscription($subscriptionData['principaluri'], $subscriptionData['uri']);

		$jobList->add(\OCA\DAV\BackgroundJob\RefreshWebcalJob::class, [
			'principaluri' => $subscriptionData['principaluri'],
			'uri' => $subscriptionData['uri']
		]);
	}
);

$eventDispatcher->addListener('\OCA\DAV\CalDAV\CalDavBackend::deleteSubscription',
	function (GenericEvent $event) use ($app) {
		$jobList = $app->getContainer()->getServer()->getJobList();
		$subscriptionData = $event->getArgument('subscriptionData');

		$jobList->remove(\OCA\DAV\BackgroundJob\RefreshWebcalJob::class, [
			'principaluri' => $subscriptionData['principaluri'],
			'uri' => $subscriptionData['uri']
		]);

		/** @var \OCA\DAV\CalDAV\CalDavBackend $calDavBackend */
		$calDavBackend = $app->getContainer()->query(\OCA\DAV\CalDAV\CalDavBackend::class);
		$calDavBackend->purgeAllCachedEventsForSubscription($subscriptionData['id']);
	}
);

$eventHandler = function () use ($app) {
	try {
		$job = $app->getContainer()->query(\OCA\DAV\BackgroundJob\UpdateCalendarResourcesRoomsBackgroundJob::class);
		$job->run([]);
		$app->getContainer()->getServer()->getJobList()->setLastRun($job);
	} catch(\Exception $ex) {
		$app->getContainer()->getServer()->getLogger()->logException($ex);
	}
};

$eventDispatcher->addListener('\OCP\Calendar\Resource\ForceRefreshEvent', $eventHandler);
$eventDispatcher->addListener('\OCP\Calendar\Room\ForceRefreshEvent', $eventHandler);

$cm = \OC::$server->getContactsManager();
$cm->register(function () use ($cm, $app) {
	$user = \OC::$server->getUserSession()->getUser();
	if (!is_null($user)) {
		$app->setupContactsProvider($cm, $user->getUID());
	} else {
		$app->setupSystemContactsProvider($cm);
	}
});

$calendarManager = \OC::$server->getCalendarManager();
$calendarManager->register(function () use ($calendarManager, $app) {
	$user = \OC::$server->getUserSession()->getUser();
	if ($user !== null) {
		$app->setupCalendarProvider($calendarManager, $user->getUID());
	}
});

$app->registerNotifier();
$app->registerCalendarReminders();
