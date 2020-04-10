<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Clark Tomlinson <fallen013@gmail.com>
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
			'name' => 'Recovery#adminRecovery',
			'url' => '/ajax/adminRecovery',
			'verb' => 'POST'
		],
		[
			'name' => 'Settings#updatePrivateKeyPassword',
			'url' => '/ajax/updatePrivateKeyPassword',
			'verb' => 'POST'
		],
		[
			'name' => 'Settings#setEncryptHomeStorage',
			'url' => '/ajax/setEncryptHomeStorage',
			'verb' => 'POST'
		],
		[
			'name' => 'Recovery#changeRecoveryPassword',
			'url' => '/ajax/changeRecoveryPassword',
			'verb' => 'POST'
		],
		[
			'name' => 'Recovery#userSetRecovery',
			'url' => '/ajax/userSetRecovery',
			'verb' => 'POST'
		],
		[
			'name' => 'Status#getStatus',
			'url' => '/ajax/getStatus',
			'verb' => 'GET'
		],
	]
];
