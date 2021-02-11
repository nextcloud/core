<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Jan C. Borchardt <hey@jancborchardt.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

return [
	'resources' => [
		'AuthSettings' => ['url' => '/settings/personal/authtokens' , 'root' => ''],
	],
	'routes' => [
		['name' => 'AuthSettings#wipe', 'url' => '/settings/personal/authtokens/wipe/{id}', 'verb' => 'POST' , 'root' => ''],

		['name' => 'MailSettings#setMailSettings', 'url' => '/settings/admin/mailsettings', 'verb' => 'POST' , 'root' => ''],
		['name' => 'MailSettings#storeCredentials', 'url' => '/settings/admin/mailsettings/credentials', 'verb' => 'POST' , 'root' => ''],
		['name' => 'MailSettings#sendTestMail', 'url' => '/settings/admin/mailtest', 'verb' => 'POST' , 'root' => ''],
		['name' => 'Encryption#startMigration', 'url' => '/settings/admin/startmigration', 'verb' => 'POST' , 'root' => ''],

		['name' => 'AppSettings#listCategories', 'url' => '/settings/apps/categories', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#listApps', 'url' => '/settings/apps/list', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#enableApp', 'url' => '/settings/apps/enable/{appId}', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#enableApp', 'url' => '/settings/apps/enable/{appId}', 'verb' => 'POST' , 'root' => ''],
		['name' => 'AppSettings#enableApps', 'url' => '/settings/apps/enable', 'verb' => 'POST' , 'root' => ''],
		['name' => 'AppSettings#disableApp', 'url' => '/settings/apps/disable/{appId}', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#disableApps', 'url' => '/settings/apps/disable', 'verb' => 'POST' , 'root' => ''],
		['name' => 'AppSettings#updateApp', 'url' => '/settings/apps/update/{appId}', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#uninstallApp', 'url' => '/settings/apps/uninstall/{appId}', 'verb' => 'GET' , 'root' => ''],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps/{category}', 'verb' => 'GET', 'defaults' => ['category' => ''] , 'root' => ''],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps/{category}/{id}', 'verb' => 'GET', 'defaults' => ['category' => '', 'id' => ''] , 'root' => ''],
		['name' => 'AppSettings#force', 'url' => '/settings/apps/force', 'verb' => 'POST' , 'root' => ''],

		['name' => 'Users#setDisplayName', 'url' => '/settings/users/{username}/displayName', 'verb' => 'POST' , 'root' => ''],
		['name' => 'Users#setEMailAddress', 'url' => '/settings/users/{id}/mailAddress', 'verb' => 'PUT' , 'root' => ''],
		['name' => 'Users#setUserSettings', 'url' => '/settings/users/{username}/settings', 'verb' => 'PUT' , 'root' => ''],
		['name' => 'Users#getVerificationCode', 'url' => '/settings/users/{account}/verify', 'verb' => 'GET' , 'root' => ''],
		['name' => 'Users#usersList', 'url' => '/settings/users', 'verb' => 'GET' , 'root' => ''],
		['name' => 'Users#usersListByGroup', 'url' => '/settings/users/{group}', 'verb' => 'GET', 'requirements' => ['group' => '.+'] , 'root' => ''],
		['name' => 'Users#setPreference', 'url' => '/settings/users/preferences/{key}', 'verb' => 'POST' , 'root' => ''],
		['name' => 'LogSettings#setLogLevel', 'url' => '/settings/admin/log/level', 'verb' => 'POST' , 'root' => ''],
		['name' => 'LogSettings#getEntries', 'url' => '/settings/admin/log/entries', 'verb' => 'GET' , 'root' => ''],
		['name' => 'LogSettings#download', 'url' => '/settings/admin/log/download', 'verb' => 'GET' , 'root' => ''],
		['name' => 'CheckSetup#check', 'url' => '/settings/ajax/checksetup', 'verb' => 'GET' , 'root' => ''],
		['name' => 'CheckSetup#getFailedIntegrityCheckFiles', 'url' => '/settings/integrity/failed', 'verb' => 'GET' , 'root' => ''],
		['name' => 'CheckSetup#rescanFailedIntegrityCheck', 'url' => '/settings/integrity/rescan', 'verb' => 'GET' , 'root' => ''],
		['name' => 'PersonalSettings#index', 'url' => '/settings/user/{section}', 'verb' => 'GET', 'defaults' => ['section' => 'personal-info'] , 'root' => ''],
		['name' => 'AdminSettings#index', 'url' => '/settings/admin/{section}', 'verb' => 'GET', 'defaults' => ['section' => 'server'] , 'root' => ''],
		['name' => 'AdminSettings#form', 'url' => '/settings/admin/{section}', 'verb' => 'GET' , 'root' => ''],
		['name' => 'ChangePassword#changePersonalPassword', 'url' => '/settings/personal/changepassword', 'verb' => 'POST' , 'root' => ''],
		['name' => 'ChangePassword#changeUserPassword', 'url' => '/settings/users/changepassword', 'verb' => 'POST' , 'root' => ''],
		['name' => 'TwoFactorSettings#index', 'url' => '/settings/api/admin/twofactorauth', 'verb' => 'GET' , 'root' => ''],
		['name' => 'TwoFactorSettings#update', 'url' => '/settings/api/admin/twofactorauth', 'verb' => 'PUT' , 'root' => ''],

		['name' => 'Help#help', 'url' => '/settings/help/{mode}', 'verb' => 'GET', 'defaults' => ['mode' => ''] , 'root' => ''],

		['name' => 'WebAuthn#startRegistration', 'url' => '/settings/api/personal/webauthn/registration', 'verb' => 'GET' , 'root' => ''],
		['name' => 'WebAuthn#finishRegistration', 'url' => '/settings/api/personal/webauthn/registration', 'verb' => 'POST' , 'root' => ''],
		['name' => 'WebAuthn#deleteRegistration', 'url' => '/settings/api/personal/webauthn/registration/{id}', 'verb' => 'DELETE' , 'root' => ''],

		['name' => 'Reasons#getPdf', 'url' => '/settings/download/reasons', 'verb' => 'GET', 'root' => ''],
	]
];
