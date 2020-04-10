<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Julius Härtl <jus@bitgrid.net>
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
	'routes' => [
		[
			'name' => 'Settings#addServer',
			'url' => '/trusted-servers',
			'verb' => 'POST'
		],
		[
			'name' => 'Settings#removeServer',
			'url' => '/trusted-servers/{id}',
			'verb' => 'DELETE'
		],
		[
			'name' => 'Settings#autoAddServers',
			'url' => '/auto-add-servers',
			'verb' => 'POST'
		],
	],
	'ocs' => [
		// old endpoints, only used by Nextcloud and ownCloud
		[
			'name' => 'OCSAuthAPI#getSharedSecretLegacy',
			'url' => '/api/v1/shared-secret',
			'verb' => 'GET',
		],
		[
			'name' => 'OCSAuthAPI#requestSharedSecretLegacy',
			'url' => '/api/v1/request-shared-secret',
			'verb' => 'POST',
		],
		// new endpoints, published as public api
		[
			'name' => 'OCSAuthAPI#getSharedSecret',
			'root' => '/cloud',
			'url' => '/shared-secret',
			'verb' => 'GET',
		],
		[
			'name' => 'OCSAuthAPI#requestSharedSecret',
			'root' => '/cloud',
			'url' => '/shared-secret',
			'verb' => 'POST',
		],
	],
];
