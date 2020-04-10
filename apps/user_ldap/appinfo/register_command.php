<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\User_Proxy;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\DeletedUsersIndex;

$dbConnection = \OC::$server->getDatabaseConnection();
$userMapping = new UserMapping($dbConnection);
$helper = new Helper(\OC::$server->getConfig());
$ocConfig = \OC::$server->getConfig();
$uBackend = new User_Proxy(
	$helper->getServerConfigurationPrefixes(true),
	new LDAP(),
	$ocConfig,
	\OC::$server->getNotificationManager(),
	\OC::$server->getUserSession(),
	\OC::$server->query('LDAPUserPluginManager')
);
$deletedUsersIndex = new DeletedUsersIndex(
	$ocConfig, $dbConnection, $userMapping
);

$application->add(new OCA\User_LDAP\Command\ShowConfig($helper));
$application->add(new OCA\User_LDAP\Command\SetConfig());
$application->add(new OCA\User_LDAP\Command\TestConfig());
$application->add(new OCA\User_LDAP\Command\CreateEmptyConfig($helper));
$application->add(new OCA\User_LDAP\Command\DeleteConfig($helper));
$application->add(new OCA\User_LDAP\Command\Search($ocConfig));
$application->add(new OCA\User_LDAP\Command\ShowRemnants(
	$deletedUsersIndex, \OC::$server->getDateTimeFormatter())
);
$application->add(new OCA\User_LDAP\Command\CheckUser(
	$uBackend, $helper, $deletedUsersIndex, $userMapping)
);
