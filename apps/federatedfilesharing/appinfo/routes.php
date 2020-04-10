<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

return [
	'routes' => [
		['name' => 'MountPublicLink#createFederatedShare', 'url' => '/createFederatedShare', 'verb' => 'POST'],
		['name' => 'MountPublicLink#askForFederatedShare', 'url' => '/askForFederatedShare', 'verb' => 'POST'],
	],
	'ocs' => [
		['root' => '/cloud', 'name' => 'RequestHandler#createShare', 'url' => '/shares', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#reShare', 'url' => '/shares/{id}/reshare', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#updatePermissions', 'url' => '/shares/{id}/permissions', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#acceptShare', 'url' => '/shares/{id}/accept', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#declineShare', 'url' => '/shares/{id}/decline', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#unshare', 'url' => '/shares/{id}/unshare', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#revoke', 'url' => '/shares/{id}/revoke', 'verb' => 'POST'],
		['root' => '/cloud', 'name' => 'RequestHandler#move', 'url' => '/shares/{id}/move', 'verb' => 'POST'],
	],
];
