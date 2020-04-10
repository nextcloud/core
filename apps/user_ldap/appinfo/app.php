<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Dominik Schmidt <dev@dominik-schmidt.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo VillafÃ¡Ã±ez <jvillafanez@solidgear.es>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

\OC::$server->registerService('LDAPUserPluginManager', function () {
	return new OCA\User_LDAP\UserPluginManager();
});
\OC::$server->registerService('LDAPGroupPluginManager', function () {
	return new OCA\User_LDAP\GroupPluginManager();
});

$app = \OC::$server->query(\OCA\User_LDAP\AppInfo\Application::class);

$helper = new \OCA\User_LDAP\Helper(\OC::$server->getConfig());
$configPrefixes = $helper->getServerConfigurationPrefixes(true);
if(count($configPrefixes) > 0) {
	$ldapWrapper = new OCA\User_LDAP\LDAP();
	$ocConfig = \OC::$server->getConfig();
	$notificationManager = \OC::$server->getNotificationManager();
	$notificationManager->registerNotifierService(\OCA\User_LDAP\Notification\Notifier::class);
	$userSession = \OC::$server->getUserSession();

	$userPluginManager = \OC::$server->query('LDAPUserPluginManager');
	$groupPluginManager = \OC::$server->query('LDAPGroupPluginManager');

	$userBackend  = new OCA\User_LDAP\User_Proxy(
		$configPrefixes, $ldapWrapper, $ocConfig, $notificationManager, $userSession, $userPluginManager
	);
	$groupBackend  = new OCA\User_LDAP\Group_Proxy($configPrefixes, $ldapWrapper, $groupPluginManager);
	// register user backend
	OC_User::useBackend($userBackend);

	// Hook to allow plugins to work on registered backends
	OC::$server->getEventDispatcher()->dispatch('OCA\\User_LDAP\\User\\User::postLDAPBackendAdded');

	\OC::$server->getGroupManager()->addBackend($groupBackend);

	$app->registerBackendDependents();
}

\OCP\Util::connectHook(
	'\OCA\Files_Sharing\API\Server2Server',
	'preLoginNameUsedAsUserName',
	'\OCA\User_LDAP\Helper',
	'loginName2UserName'
);
