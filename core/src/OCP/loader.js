/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

const loadedScripts = {}
const loadedStylesheets = {}
/**
 * @namespace OCP
 * @class Loader
 */
export default {

	/**
	 * Load a script asynchronously
	 *
	 * @param {string} app the app name
	 * @param {string} file the script file name
	 * @returns {Promise}
	 */
	loadScript: function(app, file) {
		const key = app + file
		if (Object.prototype.hasOwnProperty.call(loadedScripts, key)) {
			return Promise.resolve()
		}
		loadedScripts[key] = true
		return new Promise(function(resolve, reject) {
			const scriptPath = OC.filePath(app, 'js', file)
			const script = document.createElement('script')
			script.src = scriptPath
			script.setAttribute('nonce', btoa(OC.requestToken))
			script.onload = () => resolve()
			script.onerror = () => reject(new Error(`Failed to load script from ${scriptPath}`))
			document.head.appendChild(script)
		})
	},

	/**
	 * Load a stylesheet file asynchronously
	 *
	 * @param {string} app the app name
	 * @param {string} file the script file name
	 * @returns {Promise}
	 */
	loadStylesheet: function(app, file) {
		const key = app + file
		if (Object.prototype.hasOwnProperty.call(loadedStylesheets, key)) {
			return Promise.resolve()
		}
		loadedStylesheets[key] = true
		return new Promise(function(resolve, reject) {
			const stylePath = OC.filePath(app, 'css', file)
			const link = document.createElement('link')
			link.href = stylePath
			link.type = 'text/css'
			link.rel = 'stylesheet'
			link.onload = () => resolve()
			link.onerror = () => reject(new Error(`Failed to load stylesheet from ${stylePath}`))
			document.head.appendChild(link)
		})
	},
}
