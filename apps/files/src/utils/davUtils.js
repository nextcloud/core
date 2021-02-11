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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'

export const getRootPath = function() {
	if (getCurrentUser()) {
		return generateRemoteUrl(`dav/files/${getCurrentUser().uid}`)
	} else {
		return generateRemoteUrl('webdav').replace('/remote.php', '/public.php')
	}
}

export const isPublic = function() {
	return !getCurrentUser()
}

export const getToken = function() {
	return document.getElementById('sharingToken') && document.getElementById('sharingToken').value
}

/**
 * Return the current directory, fallback to root
 * @returns {string}
 */
export const getCurrentDirectory = function() {
	const currentDirInfo = OCA?.Files?.App?.currentFileList?.dirInfo
		|| { path: '/', name: '' }

	// Make sure we don't have double slashes
	return `${currentDirInfo.path}/${currentDirInfo.name}`.replace(/\/\//gi, '/')
}
