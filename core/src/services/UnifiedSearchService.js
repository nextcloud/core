/**
 * @copyright 2020, John Molakvoæ <skjnldsv@protonmail.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import { generateOcsUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'

export const defaultLimit = loadState('unified-search', 'limit-default')
export const minSearchLength = 2
export const regexFilterIn = /[^-]in:([a-z_-]+)/ig
export const regexFilterNot = /-in:([a-z_-]+)/ig

/**
 * Create a cancel token
 * @returns {CancelTokenSource}
 */
const createCancelToken = () => axios.CancelToken.source()

/**
 * Get the list of available search providers
 *
 * @returns {Array}
 */
export async function getTypes() {
	try {
		const { data } = await axios.get(generateOcsUrl('search', 2) + 'providers', {
			params: {
				// Sending which location we're currently at
				from: window.location.pathname.replace('/index.php', '') + window.location.search,
			},
		})
		if ('ocs' in data && 'data' in data.ocs && Array.isArray(data.ocs.data) && data.ocs.data.length > 0) {
			// Providers are sorted by the api based on their order key
			return data.ocs.data
		}
	} catch (error) {
		console.error(error)
	}
	return []
}

/**
 * Get the list of available search providers
 *
 * @param {Object} options destructuring object
 * @param {string} options.type the type to search
 * @param {string} options.query the search
 * @param {int|string|undefined} options.cursor the offset for paginated searches
 * @returns {Object} {request: Promise, cancel: Promise}
 */
export function search({ type, query, cursor }) {
	/**
	 * Generate an axios cancel token
	 */
	const cancelToken = createCancelToken()

	const request = async() => axios.get(generateOcsUrl('search', 2) + `providers/${type}/search`, {
		cancelToken: cancelToken.token,
		params: {
			term: query,
			cursor,
			// Sending which location we're currently at
			from: window.location.pathname.replace('/index.php', '') + window.location.search,
		},
	})

	return {
		request,
		cancel: cancelToken.cancel,
	}
}
