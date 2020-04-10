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

export default class ExternalLinkActions {

	#state;

	constructor() {
		// init empty state
		this.#state = {}

		// init default values
		this.#state.actions = []
		console.debug('OCA.Sharing.ExternalLinkActions initialized')
	}

	/**
	 * Get the state
	 *
	 * @readonly
	 * @memberof ExternalLinkActions
	 * @returns {Object} the data state
	 */
	get state() {
		return this.#state
	}

	/**
	 * Register a new action for the link share
	 * Mostly used by the social sharing app.
	 *
	 * @param {Object} action new action component to register
	 * @returns {boolean}
	 */
	registerAction(action) {
		if (typeof action === 'object' && action.icon && action.name && action.url) {
			this.#state.actions.push(action)
			return true
		}
		console.error(`Invalid action provided`, action)
		return false
	}

}
