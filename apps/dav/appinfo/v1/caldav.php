<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

// Backends
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\Connector\LegacyDAVACL;
use OCA\DAV\CalDAV\CalendarRoot;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin;
use OCA\DAV\Connector\Sabre\MaintenancePlugin;
use OCA\DAV\Connector\Sabre\Principal;

$authBackend = new Auth(
	\OC::$server->getSession(),
	\OC::$server->getUserSession(),
	\OC::$server->getRequest(),
	\OC::$server->getTwoFactorAuthManager(),
	\OC::$server->getBruteForceThrottler()
);
$principalBackend = new Principal(
	\OC::$server->getUserManager(),
	\OC::$server->getGroupManager()
);
$db = \OC::$server->getDatabaseConnection();
$userManager = \OC::$server->getUserManager();
$random = \OC::$server->getSecureRandom();
$dispatcher = \OC::$server->getEventDispatcher();
$calDavBackend = new CalDavBackend($db, $principalBackend, $userManager, $random, $dispatcher);

$debugging = \OC::$server->getConfig()->getSystemValue('debug', false);

// Root nodes
$principalCollection = new \Sabre\CalDAV\Principal\Collection($principalBackend, 'principals/users/');
$principalCollection->disableListing = !$debugging; // Disable listing

$addressBookRoot = new CalendarRoot($principalBackend, $calDavBackend, 'principals/users/');
$addressBookRoot->disableListing = !$debugging; // Disable listing

$principals = new \Sabre\DAV\SimpleCollection('principals', [$principalCollection]);

$nodes = array(
	$principals,
	$addressBookRoot,
);

// Fire up server
$server = new \Sabre\DAV\Server($nodes);
$server->httpRequest->setUrl(\OC::$server->getRequest()->getRequestUri());
$server->setBaseUri($baseuri);

// Add plugins
$server->addPlugin(new MaintenancePlugin());
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, 'ownCloud'));
$server->addPlugin(new \Sabre\CalDAV\Plugin());

$server->addPlugin(new LegacyDAVACL());
if ($debugging) {
	$server->addPlugin(new Sabre\DAV\Browser\Plugin());
}

$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
$server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());
$server->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
$server->addPlugin(new \OCA\DAV\CalDAV\Schedule\IMipPlugin( \OC::$server->getMailer(), \OC::$server->getLogger()));
$server->addPlugin(new ExceptionLoggerPlugin('caldav', \OC::$server->getLogger()));

// And off we go!
$server->exec();
