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

// TODO: remove when ie not supported
import 'url-search-params-polyfill'

import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Share from '../models/Share'

const shareUrl = generateOcsUrl('apps/files_sharing/api/v1', 2) + 'shares'
const headers = {
	'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
}

export default {
	methods: {
		/**
		 * Create a new share
		 *
		 * @param {Object} data destructuring object
		 * @param {string} data.path  path to the file/folder which should be shared
		 * @param {number} data.shareType  0 = user; 1 = group; 3 = public link; 6 = federated cloud share
		 * @param {string} data.shareWith  user/group id with which the file should be shared (optional for shareType > 1)
		 * @param {boolean} [data.publicUpload=false]  allow public upload to a public shared folder
		 * @param {string} [data.password]  password to protect public link Share with
		 * @param {number} [data.permissions=31]  1 = read; 2 = update; 4 = create; 8 = delete; 16 = share; 31 = all (default: 31, for public shares: 1)
		 * @param {boolean} [data.sendPasswordByTalk=false] send the password via a talk conversation
		 * @param {string} [data.expireDate=''] expire the shareautomatically after
		 * @param {string} [data.label=''] custom label
		 * @returns {Share} the new share
		 * @throws {Error}
		 */
		async createShare({ path, permissions, shareType, shareWith, publicUpload, password, sendPasswordByTalk, expireDate, label }) {
			try {
				const request = await axios.post(shareUrl, { path, permissions, shareType, shareWith, publicUpload, password, sendPasswordByTalk, expireDate, label })
				if (!('ocs' in request.data)) {
					throw request
				}
				return new Share(request.data.ocs.data)
			} catch (error) {
				console.error('Error while creating share', error)
				OC.Notification.showTemporary(t('files_sharing', 'Error creating the share'), { type: 'error' })
				throw error
			}
		},

		/**
		 * Delete a share
		 *
		 * @param {number} id share id
		 * @throws {Error}
		 */
		async deleteShare(id) {
			try {
				const request = await axios.delete(shareUrl + `/${id}`)
				if (!('ocs' in request.data)) {
					throw request
				}
				return true
			} catch (error) {
				console.error('Error while deleting share', error)
				OC.Notification.showTemporary(t('files_sharing', 'Error deleting the share'), { type: 'error' })
				throw error
			}
		},

		/**
		 * Update a share
		 *
		 * @param {number} id share id
		 * @param {Object} data destructuring object
		 * @param {string} data.property property to update
		 * @param {any} data.value value to set
		 */
		async updateShare(id, { property, value }) {
			try {
				// ocs api requires x-www-form-urlencoded
				const data = new URLSearchParams()
				data.append(property, value)

				const request = await axios.put(shareUrl + `/${id}`, { [property]: value }, headers)
				if (!('ocs' in request.data)) {
					throw request
				}
				return true
			} catch (error) {
				console.error('Error while updating share', error)
				OC.Notification.showTemporary(t('files_sharing', 'Error updating the share'), { type: 'error' })
				const message = error.response.data.ocs.meta.message
				throw new Error(`${property}, ${message}`)
			}
		},
	},
}
