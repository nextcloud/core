/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

import { emit } from '@nextcloud/event-bus'

/**
 * @private
 * @param {Document} global the document to read the initial value from
 * @param {Function} emit the function to invoke for every new token
 * @returns {Object}
 */
export const manageToken = (global, emit) => {
	let token = global.getElementsByTagName('head')[0].getAttribute('data-requesttoken')

	return {
		getToken: () => token,
		setToken: newToken => {
			token = newToken

			emit('csrf-token-update', {
				token,
			})
		},
	}
}

const manageFromDocument = manageToken(document, emit)

/**
 * @returns {string}
 */
export const getToken = manageFromDocument.getToken

/**
 * @param {String} newToken new token
 */
export const setToken = manageFromDocument.setToken
