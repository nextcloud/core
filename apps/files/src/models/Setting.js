/**
 * @copyright Copyright (c) 2019 Gary Kim <gary@garykim.dev>
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
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

export default class Setting {

	#close
	#el
	#name
	#open

	/**
	 * Create a new files app setting
	 *
	 * @since 19.0.0
	 * @param {string} name the name of this setting
	 * @param {Function} component.el function that returns an unmounted dom element to be added
	 * @param {Function} [component.open] callback for when setting is added
	 * @param {Function} [component.close] callback for when setting is closed
	 */
	constructor(name, { el, open, close }) {
		this.#name = name
		this.#el = el
		this.#open = open
		this.#close = close
		if (typeof this.#open !== 'function') {
			this.#open = () => {}
		}
		if (typeof this.#close !== 'function') {
			this.#close = () => {}
		}
	}

	get name() {
		return this.#name
	}

	get el() {
		return this.#el
	}

	get open() {
		return this.#open
	}

	get close() {
		return this.#close
	}

}
