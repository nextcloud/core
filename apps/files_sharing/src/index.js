/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

// register default shares types
Object.assign(OC, {
	Share: {
		SHARE_TYPE_USER: 0,
		SHARE_TYPE_GROUP: 1,
		SHARE_TYPE_LINK: 3,
		SHARE_TYPE_EMAIL: 4,
		SHARE_TYPE_REMOTE: 6,
		SHARE_TYPE_CIRCLE: 7,
		SHARE_TYPE_GUEST: 8,
		SHARE_TYPE_REMOTE_GROUP: 9,
		SHARE_TYPE_ROOM: 10,
	},
})
