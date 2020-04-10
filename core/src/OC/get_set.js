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

/**
 * Get a variable by name
 * @param {string} context context
 * @returns {Function} getter
 * @deprecated 19.0.0 use https://lodash.com/docs#get
 */
export const get = context => name => {
	const namespaces = name.split('.')
	const tail = namespaces.pop()

	for (let i = 0; i < namespaces.length; i++) {
		context = context[namespaces[i]]
		if (!context) {
			return false
		}
	}
	return context[tail]
}

/**
 * Set a variable by name
 * @param {string} context context
 * @returns {Function} setter
 * @deprecated 19.0.0 use https://lodash.com/docs#set
 */
export const set = context => (name, value) => {
	const namespaces = name.split('.')
	const tail = namespaces.pop()

	for (let i = 0; i < namespaces.length; i++) {
		if (!context[namespaces[i]]) {
			context[namespaces[i]] = {}
		}
		context = context[namespaces[i]]
	}
	context[tail] = value
	return value
}
