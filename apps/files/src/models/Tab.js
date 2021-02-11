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

export default class Tab {

	#id
	#name
	#icon
	#mount
	#update
	#destroy
	#enabled
	#scrollBottomReached

	/**
	 * Create a new tab instance
	 *
	 * @param {Object} options destructuring object
	 * @param {string} options.id the unique id of this tab
	 * @param {string} options.name the translated tab name
	 * @param {string} options.icon the vue component
	 * @param {Function} options.mount function to mount the tab
	 * @param {Function} options.update function to update the tab
	 * @param {Function} options.destroy function to destroy the tab
	 * @param {Function} [options.enabled] define conditions whether this tab is active. Must returns a boolean
	 * @param {Function} [options.scrollBottomReached] executed when the tab is scrolled to the bottom
	 */
	constructor({ id, name, icon, mount, update, destroy, enabled, scrollBottomReached } = {}) {
		if (enabled === undefined) {
			enabled = () => true
		}
		if (scrollBottomReached === undefined) {
			scrollBottomReached = () => {}
		}

		// Sanity checks
		if (typeof id !== 'string' || id.trim() === '') {
			throw new Error('The id argument is not a valid string')
		}
		if (typeof name !== 'string' || name.trim() === '') {
			throw new Error('The name argument is not a valid string')
		}
		if (typeof icon !== 'string' || icon.trim() === '') {
			throw new Error('The icon argument is not a valid string')
		}
		if (typeof mount !== 'function') {
			throw new Error('The mount argument should be a function')
		}
		if (typeof update !== 'function') {
			throw new Error('The update argument should be a function')
		}
		if (typeof destroy !== 'function') {
			throw new Error('The destroy argument should be a function')
		}
		if (typeof enabled !== 'function') {
			throw new Error('The enabled argument should be a function')
		}
		if (typeof scrollBottomReached !== 'function') {
			throw new Error('The scrollBottomReached argument should be a function')
		}

		this.#id = id
		this.#name = name
		this.#icon = icon
		this.#mount = mount
		this.#update = update
		this.#destroy = destroy
		this.#enabled = enabled
		this.#scrollBottomReached = scrollBottomReached

	}

	get id() {
		return this.#id
	}

	get name() {
		return this.#name
	}

	get icon() {
		return this.#icon
	}

	get mount() {
		return this.#mount
	}

	get update() {
		return this.#update
	}

	get destroy() {
		return this.#destroy
	}

	get enabled() {
		return this.#enabled
	}

	get scrollBottomReached() {
		return this.#scrollBottomReached
	}

}
