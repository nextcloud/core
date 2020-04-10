<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
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
	'ocs' => [
		// Apps
		['root' => '/cloud', 'name' => 'Apps#getApps', 'url' => '/apps', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Apps#getAppInfo', 'url' => '/apps/{app}', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Apps#enable', 'url' => '/apps/{app}', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Apps#disable', 'url' => '/apps/{app}', 'verb' => 'DELETE'],

		// Groups
		['root' => '/cloud', 'name' => 'Groups#getGroups', 'url' => '/groups', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Groups#getGroupsDetails', 'url' => '/groups/details', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Groups#getGroupUsers', 'url' => '/groups/{groupId}/users', 'verb' => 'GET', 'requirements' => ['groupId' => '.+']],
		['root' => '/cloud', 'name' => 'Groups#getGroupUsersDetails', 'url' => '/groups/{groupId}/users/details', 'verb' => 'GET', 'requirements' => ['groupId' => '.+']],
		['root' => '/cloud', 'name' => 'Groups#getSubAdminsOfGroup', 'url' => '/groups/{groupId}/subadmins', 'verb' => 'GET', 'requirements' => ['groupId' => '.+']],
		['root' => '/cloud', 'name' => 'Groups#addGroup', 'url' => '/groups', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Groups#getGroup', 'url' => '/groups/{groupId}', 'verb' => 'GET', 'requirements' => ['groupId' => '.+']],
		['root' => '/cloud', 'name' => 'Groups#updateGroup', 'url' => '/groups/{groupId}', 'verb' => 'PUT', 'requirements' => ['groupId' => '.+']],
		['root' => '/cloud', 'name' => 'Groups#deleteGroup', 'url' => '/groups/{groupId}', 'verb' => 'DELETE', 'requirements' => ['groupId' => '.+']],

		// Users
		['root' => '/cloud', 'name' => 'Users#getUsers', 'url' => '/users', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#getUsersDetails', 'url' => '/users/details', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#addUser', 'url' => '/users', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Users#getUser', 'url' => '/users/{userId}', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#getCurrentUser', 'url' => '/user', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#getEditableFields', 'url' => '/user/fields', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#editUser', 'url' => '/users/{userId}', 'verb' => 'PUT'],
		['root' => '/cloud', 'name' => 'Users#wipeUserDevices', 'url' => '/users/{userId}/wipe', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Users#deleteUser', 'url' => '/users/{userId}', 'verb' => 'DELETE'],
		['root' => '/cloud', 'name' => 'Users#enableUser', 'url' => '/users/{userId}/enable', 'verb' => 'PUT'],
		['root' => '/cloud', 'name' => 'Users#disableUser', 'url' => '/users/{userId}/disable', 'verb' => 'PUT'],
		['root' => '/cloud', 'name' => 'Users#getUsersGroups', 'url' => '/users/{userId}/groups', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#addToGroup', 'url' => '/users/{userId}/groups', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Users#removeFromGroup', 'url' => '/users/{userId}/groups', 'verb' => 'DELETE'],
		['root' => '/cloud', 'name' => 'Users#getUserSubAdminGroups', 'url' => '/users/{userId}/subadmins', 'verb' => 'GET'],
		['root' => '/cloud', 'name' => 'Users#addSubAdmin', 'url' => '/users/{userId}/subadmins', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'Users#removeSubAdmin', 'url' => '/users/{userId}/subadmins', 'verb' => 'DELETE'],
		['root' => '/cloud', 'name' => 'Users#resendWelcomeMessage', 'url' => '/users/{userId}/welcome', 'verb' => 'POST'],

		// Config
		['name' => 'AppConfig#getApps', 'url' => '/api/v1/config/apps', 'verb' => 'GET'],
		['name' => 'AppConfig#getKeys', 'url' => '/api/v1/config/apps/{app}', 'verb' => 'GET'],
		['name' => 'AppConfig#getValue', 'url' => '/api/v1/config/apps/{app}/{key}', 'verb' => 'GET'],
		['name' => 'AppConfig#setValue', 'url' => '/api/v1/config/apps/{app}/{key}', 'verb' => 'POST'],
		['name' => 'AppConfig#deleteKey', 'url' => '/api/v1/config/apps/{app}/{key}', 'verb' => 'DELETE'],
	],
];
