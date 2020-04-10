<?php
/**
 * @copyright 2017, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
		['name' => 'birthday_calendar#enable', 'url' => '/enableBirthdayCalendar', 'verb' => 'POST'],
		['name' => 'birthday_calendar#disable', 'url' => '/disableBirthdayCalendar', 'verb' => 'POST'],
		['name' => 'invitation_response#accept', 'url' => '/invitation/accept/{token}', 'verb' => 'GET'],
		['name' => 'invitation_response#decline', 'url' => '/invitation/decline/{token}', 'verb' => 'GET'],
		['name' => 'invitation_response#options', 'url' => '/invitation/moreOptions/{token}', 'verb' => 'GET'],
		['name' => 'invitation_response#processMoreOptionsResult', 'url' => '/invitation/moreOptions/{token}', 'verb' => 'POST']
	],
	'ocs' => [
		['name' => 'direct#getUrl', 'url' => '/api/v1/direct', 'verb' => 'POST'],
	],
];
