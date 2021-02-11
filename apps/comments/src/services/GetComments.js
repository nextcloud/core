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

import { parseXML, prepareFileFromProps } from 'webdav/dist/node/interface/dav'
import { processResponsePayload } from 'webdav/dist/node/response'
import client from './DavClient'

export const DEFAULT_LIMIT = 20
/**
 * Retrieve the comments list
 *
 * @param {Object} data destructuring object
 * @param {string} data.commentsType the ressource type
 * @param {number} data.ressourceId the ressource ID
 * @param {Object} [options] optional options for axios
 * @returns {Object[]} the comments list
 */
export default async function({ commentsType, ressourceId }, options = {}) {
	let response = null
	const ressourcePath = ['', commentsType, ressourceId].join('/')

	return await client.customRequest(ressourcePath, Object.assign({
		method: 'REPORT',
		data: `<?xml version="1.0"?>
			<oc:filter-comments
				xmlns:d="DAV:"
				xmlns:oc="http://owncloud.org/ns"
				xmlns:nc="http://nextcloud.org/ns"
				xmlns:ocs="http://open-collaboration-services.org/ns">
				<oc:limit>${DEFAULT_LIMIT}</oc:limit>
				<oc:offset>${options.offset || 0}</oc:offset>
			</oc:filter-comments>`,
	}, options))
		// See example on how it's done normaly
		// https://github.com/perry-mitchell/webdav-client/blob/9de2da4a2599e06bd86c2778145b7ade39fe0b3c/source/interface/stat.js#L19
		// Waiting for proper REPORT integration https://github.com/perry-mitchell/webdav-client/issues/207
		.then(res => {
			response = res
			return res.data
		})
		.then(parseXML)
		.then(xml => processMultistatus(xml, true))
		.then(comments => processResponsePayload(response, comments, true))
		.then(response => response.data)
}

// https://github.com/perry-mitchell/webdav-client/blob/9de2da4a2599e06bd86c2778145b7ade39fe0b3c/source/interface/directoryContents.js#L32
function processMultistatus(result, isDetailed = false) {
	// Extract the response items (directory contents)
	const {
		multistatus: { response: responseItems },
	} = result
	return responseItems.map(item => {
		// Each item should contain a stat object
		const {
			propstat: { prop: props },
		} = item
		return prepareFileFromProps(props, props.id.toString(), isDetailed)
	})
}
