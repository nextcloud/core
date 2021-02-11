<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Alexey Pyltsyn <lex61rus@gmail.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
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
		['name' => 'accessibility#getCss', 'url' => '/css/user-{md5}', 'verb' => 'GET'],
	],
	'ocs' => [
		[
			'name' => 'Config#getConfig',
			'url' => '/api/v1/config',
			'verb' => 'GET',
		],
		[
			'name' => 'Config#setConfig',
			'url' => '/api/v1/config/{key}',
			'verb' => 'PUT',
		],
		[
			'name' => 'Config#deleteConfig',
			'url' => '/api/v1/config/{key}',
			'verb' => 'DELETE',
		],
	]
];
