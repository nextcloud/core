<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OCA\DAV\AppInfo\PluginManager;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarRoot;
use OCA\DAV\CalDAV\Principal\Collection;
use OCA\DAV\CalDAV\Proxy\ProxyMapper;
use OCA\DAV\CalDAV\PublicCalendarRoot;
use OCA\DAV\CalDAV\ResourceBooking\ResourcePrincipalBackend;
use OCA\DAV\CalDAV\ResourceBooking\RoomPrincipalBackend;
use OCA\DAV\CardDAV\AddressBookRoot;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\DAV\GroupPrincipalBackend;
use OCA\DAV\DAV\SystemPrincipalBackend;
use OCA\DAV\Provisioning\Apple\AppleProvisioningNode;
use OCA\DAV\Upload\CleanupService;
use OCP\App\IAppManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use Sabre\DAV\SimpleCollection;

class RootCollection extends SimpleCollection {
	public function __construct() {
		$config = \OC::$server->getConfig();
		$l10n = \OC::$server->getL10N('dav');
		$random = \OC::$server->getSecureRandom();
		$logger = \OC::$server->getLogger();
		$userManager = \OC::$server->getUserManager();
		$userSession = \OC::$server->getUserSession();
		$groupManager = \OC::$server->getGroupManager();
		$shareManager = \OC::$server->getShareManager();
		$db = \OC::$server->getDatabaseConnection();
		$dispatcher = \OC::$server->get(IEventDispatcher::class);
		$legacyDispatcher = \OC::$server->getEventDispatcher();
		$proxyMapper = \OC::$server->query(ProxyMapper::class);

		$userPrincipalBackend = new Principal(
			$userManager,
			$groupManager,
			$shareManager,
			\OC::$server->getUserSession(),
			\OC::$server->getAppManager(),
			$proxyMapper,
			\OC::$server->getConfig()
		);
		$groupPrincipalBackend = new GroupPrincipalBackend($groupManager, $userSession, $shareManager, $config);
		$calendarResourcePrincipalBackend = new ResourcePrincipalBackend($db, $userSession, $groupManager, $logger, $proxyMapper);
		$calendarRoomPrincipalBackend = new RoomPrincipalBackend($db, $userSession, $groupManager, $logger, $proxyMapper);
		// as soon as debug mode is enabled we allow listing of principals
		$disableListing = !$config->getSystemValue('debug', false);

		// setup the first level of the dav tree
		$userPrincipals = new Collection($userPrincipalBackend, 'principals/users');
		$userPrincipals->disableListing = $disableListing;
		$groupPrincipals = new Collection($groupPrincipalBackend, 'principals/groups');
		$groupPrincipals->disableListing = $disableListing;
		$systemPrincipals = new Collection(new SystemPrincipalBackend(), 'principals/system');
		$systemPrincipals->disableListing = $disableListing;
		$calendarResourcePrincipals = new Collection($calendarResourcePrincipalBackend, 'principals/calendar-resources');
		$calendarResourcePrincipals->disableListing = $disableListing;
		$calendarRoomPrincipals = new Collection($calendarRoomPrincipalBackend, 'principals/calendar-rooms');
		$calendarRoomPrincipals->disableListing = $disableListing;


		$filesCollection = new Files\RootCollection($userPrincipalBackend, 'principals/users');
		$filesCollection->disableListing = $disableListing;
		$caldavBackend = new CalDavBackend($db, $userPrincipalBackend, $userManager, $groupManager, $random, $logger, $dispatcher, $legacyDispatcher);
		$userCalendarRoot = new CalendarRoot($userPrincipalBackend, $caldavBackend, 'principals/users');
		$userCalendarRoot->disableListing = $disableListing;

		$resourceCalendarCaldavBackend = new CalDavBackend($db, $userPrincipalBackend, $userManager, $groupManager, $random, $logger, $dispatcher, $legacyDispatcher);
		$resourceCalendarRoot = new CalendarRoot($calendarResourcePrincipalBackend, $caldavBackend, 'principals/calendar-resources');
		$resourceCalendarRoot->disableListing = $disableListing;
		$roomCalendarCaldavBackend = new CalDavBackend($db, $userPrincipalBackend, $userManager, $groupManager, $random, $logger, $dispatcher, $legacyDispatcher);
		$roomCalendarRoot = new CalendarRoot($calendarRoomPrincipalBackend, $roomCalendarCaldavBackend, 'principals/calendar-rooms');
		$roomCalendarRoot->disableListing = $disableListing;

		$publicCalendarRoot = new PublicCalendarRoot($caldavBackend, $l10n, $config);
		$publicCalendarRoot->disableListing = $disableListing;

		$systemTagCollection = new SystemTag\SystemTagsByIdCollection(
			\OC::$server->getSystemTagManager(),
			\OC::$server->getUserSession(),
			$groupManager
		);
		$systemTagRelationsCollection = new SystemTag\SystemTagsRelationsCollection(
			\OC::$server->getSystemTagManager(),
			\OC::$server->getSystemTagObjectMapper(),
			\OC::$server->getUserSession(),
			$groupManager,
			\OC::$server->getEventDispatcher()
		);
		$commentsCollection = new Comments\RootCollection(
			\OC::$server->getCommentsManager(),
			$userManager,
			\OC::$server->getUserSession(),
			\OC::$server->getEventDispatcher(),
			\OC::$server->getLogger()
		);

		$pluginManager = new PluginManager(\OC::$server, \OC::$server->query(IAppManager::class));
		$usersCardDavBackend = new CardDavBackend($db, $userPrincipalBackend, $userManager, $groupManager, $dispatcher, $legacyDispatcher);
		$usersAddressBookRoot = new AddressBookRoot($userPrincipalBackend, $usersCardDavBackend, $pluginManager, 'principals/users');
		$usersAddressBookRoot->disableListing = $disableListing;

		$systemCardDavBackend = new CardDavBackend($db, $userPrincipalBackend, $userManager, $groupManager, $dispatcher, $legacyDispatcher);
		$systemAddressBookRoot = new AddressBookRoot(new SystemPrincipalBackend(), $systemCardDavBackend, $pluginManager, 'principals/system');
		$systemAddressBookRoot->disableListing = $disableListing;

		$uploadCollection = new Upload\RootCollection(
			$userPrincipalBackend,
			'principals/users',
			\OC::$server->query(CleanupService::class));
		$uploadCollection->disableListing = $disableListing;

		$avatarCollection = new Avatars\RootCollection($userPrincipalBackend, 'principals/users');
		$avatarCollection->disableListing = $disableListing;

		$appleProvisioning = new AppleProvisioningNode(
			\OC::$server->query(ITimeFactory::class));

		$children = [
			new SimpleCollection('principals', [
				$userPrincipals,
				$groupPrincipals,
				$systemPrincipals,
				$calendarResourcePrincipals,
				$calendarRoomPrincipals]),
			$filesCollection,
			$userCalendarRoot,
			new SimpleCollection('system-calendars', [
				$resourceCalendarRoot,
				$roomCalendarRoot,
			]),
			$publicCalendarRoot,
			new SimpleCollection('addressbooks', [
				$usersAddressBookRoot,
				$systemAddressBookRoot]),
			$systemTagCollection,
			$systemTagRelationsCollection,
			$commentsCollection,
			$uploadCollection,
			$avatarCollection,
			new SimpleCollection('provisioning', [
				$appleProvisioning
			])
		];

		parent::__construct('root', $children);
	}
}
