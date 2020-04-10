<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
			'name' => 'RateLimitTest#userAndAnonProtected',
			'url' => '/userAndAnonProtected',
			'verb' => 'GET',
		],
		[
			'name' => 'RateLimitTest#onlyAnonProtected',
			'url' => '/anonProtected',
			'verb' => 'GET',
		],
	],

	'ocs' => [
		[
			'name' => 'Config#setAppValue',
			'url'  => '/api/v1/app/{appid}/{configkey}',
			'verb' => 'POST',
		],
		[
			'name' => 'Config#deleteAppValue',
			'url'  => '/api/v1/app/{appid}/{configkey}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'Locking#isLockingEnabled',
			'url'  => '/api/v1/lockprovisioning',
			'verb' => 'GET',
		],
		[
			'name' => 'Locking#isLocked',
			'url'  => '/api/v1/lockprovisioning/{type}/{user}',
			'verb' => 'GET',
		],
		[
			'name' => 'Locking#acquireLock',
			'url'  => '/api/v1/lockprovisioning/{type}/{user}',
			'verb' => 'POST',
		],
		[
			'name' => 'Locking#changeLock',
			'url'  => '/api/v1/lockprovisioning/{type}/{user}',
			'verb' => 'PUT',
		],
		[
			'name' => 'Locking#releaseLock',
			'url'  => '/api/v1/lockprovisioning/{type}/{user}',
			'verb' => 'DELETE',
		],
		[
			'name' => 'Locking#releaseAll',
			'url'  => '/api/v1/lockprovisioning/{type}',
			'verb' => 'DELETE',
			'defaults' => [
				'type' => null
			]
		],
	],
];
