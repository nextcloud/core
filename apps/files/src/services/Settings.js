/**
 * @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

export default class Settings {

	#settings

	constructor() {
		this.#settings = []
		console.debug('OCA.Files.Settings initialized')
	}

	/**
	 * Register a new setting
	 *
	 * @since 19.0.0
	 * @param {OCA.Files.Settings.Setting} view element to add to settings
	 * @returns {boolean} whether registering was successful
	 */
	register(view) {
		if (this.#settings.filter(e => e.name === view.name).length > 0) {
			console.error('A setting with the same name is already registered')
			return false
		}
		this.#settings.push(view)
		return true
	}

	/**
	 * All settings elements
	 * @returns {OCA.Files.Settings.Setting[]} All currently registered settings
	 */
	get settings() {
		return this.#settings
	}

}
