<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Settings;

use OCA\Settings\AppInfo\Application;

/** @var Application $application */
$application = \OC::$server->query(Application::class);
$this->useCollection('root');
$application->registerRoutes($this, [
	'resources' => [
		'AuthSettings' => ['url' => '/settings/personal/authtokens'],
	],
	'routes' => [
		['name' => 'AuthSettings#wipe', 'url' => '/settings/personal/authtokens/wipe/{id}', 'verb' => 'POST'],

		['name' => 'MailSettings#setMailSettings', 'url' => '/settings/admin/mailsettings', 'verb' => 'POST'],
		['name' => 'MailSettings#storeCredentials', 'url' => '/settings/admin/mailsettings/credentials', 'verb' => 'POST'],
		['name' => 'MailSettings#sendTestMail', 'url' => '/settings/admin/mailtest', 'verb' => 'POST'],
		['name' => 'Encryption#startMigration', 'url' => '/settings/admin/startmigration', 'verb' => 'POST'],

		['name' => 'AppSettings#listCategories', 'url' => '/settings/apps/categories', 'verb' => 'GET'],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps', 'verb' => 'GET'],
		['name' => 'AppSettings#listApps', 'url' => '/settings/apps/list', 'verb' => 'GET'],
		['name' => 'AppSettings#enableApp', 'url' => '/settings/apps/enable/{appId}', 'verb' => 'GET'],
		['name' => 'AppSettings#enableApp', 'url' => '/settings/apps/enable/{appId}', 'verb' => 'POST'],
		['name' => 'AppSettings#enableApps', 'url' => '/settings/apps/enable', 'verb' => 'POST'],
		['name' => 'AppSettings#disableApp', 'url' => '/settings/apps/disable/{appId}', 'verb' => 'GET'],
		['name' => 'AppSettings#disableApps', 'url' => '/settings/apps/disable', 'verb' => 'POST'],
		['name' => 'AppSettings#updateApp', 'url' => '/settings/apps/update/{appId}', 'verb' => 'GET'],
		['name' => 'AppSettings#uninstallApp', 'url' => '/settings/apps/uninstall/{appId}', 'verb' => 'GET'],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps/{category}', 'verb' => 'GET', 'defaults' => ['category' => '']],
		['name' => 'AppSettings#viewApps', 'url' => '/settings/apps/{category}/{id}', 'verb' => 'GET', 'defaults' => ['category' => '', 'id' => '']],
		['name' => 'AppSettings#force', 'url' => '/settings/apps/force', 'verb' => 'POST'],

		['name' => 'Users#setDisplayName', 'url' => '/settings/users/{username}/displayName', 'verb' => 'POST'],
		['name' => 'Users#setEMailAddress', 'url' => '/settings/users/{id}/mailAddress', 'verb' => 'PUT'],
		['name' => 'Users#setUserSettings', 'url' => '/settings/users/{username}/settings', 'verb' => 'PUT'],
		['name' => 'Users#getVerificationCode', 'url' => '/settings/users/{account}/verify', 'verb' => 'GET'],
		['name' => 'Users#usersList', 'url' => '/settings/users', 'verb' => 'GET'],
		['name' => 'Users#usersListByGroup', 'url' => '/settings/users/{group}', 'verb' => 'GET', 'requirements' => ['group' => '.+']],
		['name' => 'Users#setPreference', 'url' => '/settings/users/preferences/{key}', 'verb' => 'POST'],
		['name' => 'LogSettings#setLogLevel', 'url' => '/settings/admin/log/level', 'verb' => 'POST'],
		['name' => 'LogSettings#getEntries', 'url' => '/settings/admin/log/entries', 'verb' => 'GET'],
		['name' => 'LogSettings#download', 'url' => '/settings/admin/log/download', 'verb' => 'GET'],
		['name' => 'CheckSetup#check', 'url' => '/settings/ajax/checksetup', 'verb' => 'GET'],
		['name' => 'CheckSetup#getFailedIntegrityCheckFiles', 'url' => '/settings/integrity/failed', 'verb' => 'GET'],
		['name' => 'CheckSetup#rescanFailedIntegrityCheck', 'url' => '/settings/integrity/rescan', 'verb' => 'GET'],
		['name' => 'Certificate#addPersonalRootCertificate', 'url' => '/settings/personal/certificate', 'verb' => 'POST'],
		['name' => 'Certificate#removePersonalRootCertificate', 'url' => '/settings/personal/certificate/{certificateIdentifier}', 'verb' => 'DELETE'],
		['name' => 'Certificate#addSystemRootCertificate', 'url' => '/settings/admin/certificate', 'verb' => 'POST'],
		['name' => 'Certificate#removeSystemRootCertificate', 'url' => '/settings/admin/certificate/{certificateIdentifier}', 'verb' => 'DELETE'],
		['name' => 'PersonalSettings#index', 'url' => '/settings/user/{section}', 'verb' => 'GET', 'defaults' => ['section' => 'personal-info']],
		['name' => 'AdminSettings#index', 'url' => '/settings/admin/{section}', 'verb' => 'GET', 'defaults' => ['section' => 'server']],
		['name' => 'AdminSettings#form', 'url' => '/settings/admin/{section}', 'verb' => 'GET'],
		['name' => 'ChangePassword#changePersonalPassword', 'url' => '/settings/personal/changepassword', 'verb' => 'POST'],
		['name' => 'ChangePassword#changeUserPassword', 'url' => '/settings/users/changepassword', 'verb' => 'POST'],
		['name' => 'TwoFactorSettings#index', 'url' => '/settings/api/admin/twofactorauth', 'verb' => 'GET'],
		['name' => 'TwoFactorSettings#update', 'url' => '/settings/api/admin/twofactorauth', 'verb' => 'PUT'],

		['name' => 'Help#help', 'url' => '/settings/help/{mode}', 'verb' => 'GET', 'defaults' => ['mode' => '']],

		['name' => 'WebAuthn#startRegistration', 'url' => '/settings/api/personal/webauthn/registration', 'verb' => 'GET'],
		['name' => 'WebAuthn#finishRegistration', 'url' => '/settings/api/personal/webauthn/registration', 'verb' => 'POST'],
		['name' => 'WebAuthn#deleteRegistration', 'url' => '/settings/api/personal/webauthn/registration/{id}', 'verb' => 'DELETE'],
	]
]);
