<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roger Szabo <roger.szabo@web.de>
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

/** @var $this \OCP\Route\IRouter */
$this->create('user_ldap_ajax_clearMappings', 'ajax/clearMappings.php')
	->actionInclude('user_ldap/ajax/clearMappings.php');
$this->create('user_ldap_ajax_deleteConfiguration', 'ajax/deleteConfiguration.php')
	->actionInclude('user_ldap/ajax/deleteConfiguration.php');
$this->create('user_ldap_ajax_getConfiguration', 'ajax/getConfiguration.php')
	->actionInclude('user_ldap/ajax/getConfiguration.php');
$this->create('user_ldap_ajax_getNewServerConfigPrefix', 'ajax/getNewServerConfigPrefix.php')
	->actionInclude('user_ldap/ajax/getNewServerConfigPrefix.php');
$this->create('user_ldap_ajax_setConfiguration', 'ajax/setConfiguration.php')
	->actionInclude('user_ldap/ajax/setConfiguration.php');
$this->create('user_ldap_ajax_testConfiguration', 'ajax/testConfiguration.php')
	->actionInclude('user_ldap/ajax/testConfiguration.php');
$this->create('user_ldap_ajax_wizard', 'ajax/wizard.php')
	->actionInclude('user_ldap/ajax/wizard.php');

$application = new \OCP\AppFramework\App('user_ldap');
$application->registerRoutes($this, [
	'ocs' => [
		['name' => 'ConfigAPI#create', 'url' => '/api/v1/config', 'verb' => 'POST'],
		['name' => 'ConfigAPI#show',   'url' => '/api/v1/config/{configID}', 'verb' => 'GET'],
		['name' => 'ConfigAPI#modify', 'url' => '/api/v1/config/{configID}', 'verb' => 'PUT'],
		['name' => 'ConfigAPI#delete', 'url' => '/api/v1/config/{configID}', 'verb' => 'DELETE'],
	]
]);

/** @var \OCA\User_LDAP\AppInfo\Application $application */
$application = \OC::$server->query(\OCA\User_LDAP\AppInfo\Application::class);
$application->registerRoutes($this, [
	'routes' => [
		['name' => 'renewPassword#tryRenewPassword', 'url' => '/renewpassword', 'verb' => 'POST'],
		['name' => 'renewPassword#showRenewPasswordForm', 'url' => '/renewpassword/{user}', 'verb' => 'GET'],
		['name' => 'renewPassword#cancel', 'url' => '/renewpassword/cancel', 'verb' => 'GET'],
		['name' => 'renewPassword#showLoginFormInvalidPassword', 'url' => '/renewpassword/invalidlogin/{user}', 'verb' => 'GET'],
	]
]);
