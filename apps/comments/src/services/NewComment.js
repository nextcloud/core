/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
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

import { getCurrentUser } from '@nextcloud/auth'
import { getRootPath } from '../utils/davUtils'
import axios from '@nextcloud/axios'
import client from './DavClient'

/**
 * Retrieve the comments list
 *
 * @param {string} commentsType the ressource type
 * @param {number} ressourceId the ressource ID
 * @param {string} message the message
 * @returns {Object} the new comment
 */
export default async function(commentsType, ressourceId, message) {
	const ressourcePath = ['', commentsType, ressourceId].join('/')

	const response = await axios.post(getRootPath() + ressourcePath, {
		actorDisplayName: getCurrentUser().displayName,
		actorId: getCurrentUser().uid,
		actorType: 'users',
		creationDateTime: (new Date()).toUTCString(),
		message,
		objectType: 'files',
		verb: 'comment',
	})

	// Retrieve comment id from ressource location
	const commentId = parseInt(response.headers['content-location'].split('/').pop())
	const commentPath = ressourcePath + '/' + commentId

	// Fetch newly created comment data
	const comment = await client.stat(commentPath, {
		details: true,
	})

	return comment.data
}
