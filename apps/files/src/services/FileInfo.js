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

import axios from '@nextcloud/axios'

export default async function(url) {
	const response = await axios({
		method: 'PROPFIND',
		url,
		data: `<?xml version="1.0"?>
			<d:propfind  xmlns:d="DAV:"
				xmlns:oc="http://owncloud.org/ns"
				xmlns:nc="http://nextcloud.org/ns"
				xmlns:ocs="http://open-collaboration-services.org/ns">
			<d:prop>
				<d:getlastmodified />
				<d:getetag />
				<d:getcontenttype />
				<d:resourcetype />
				<oc:fileid />
				<oc:permissions />
				<oc:size />
				<d:getcontentlength />
				<nc:has-preview />
				<nc:mount-type />
				<nc:is-encrypted />
				<ocs:share-permissions />
				<oc:tags />
				<oc:favorite />
				<oc:comments-unread />
				<oc:owner-id />
				<oc:owner-display-name />
				<oc:share-types />
			</d:prop>
			</d:propfind>`,
	})

	// TODO: create new parser or use cdav-lib when available
	const file = OCA.Files.App.fileList.filesClient._client.parseMultiStatus(response.data)
	// TODO: create new parser or use cdav-lib when available
	const fileInfo = OCA.Files.App.fileList.filesClient._parseFileInfo(file[0])

	// TODO remove when no more legacy backbone is used
	fileInfo.get = (key) => fileInfo[key]
	fileInfo.isDirectory = () => fileInfo.mimetype === 'httpd/unix-directory'

	return fileInfo
}
