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
 * but WITHOUT ANY WARRANTY without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import $ from 'jquery'

const loadedScripts = {}
const loadedStyles = []

/**
 * Load a script for the server and load it. If the script is already loaded,
 * the event handler will be called directly
 * @param {string} app the app id to which the script belongs
 * @param {string} script the filename of the script
 * @param {Function} ready event handler to be called when the script is loaded
 * @returns {jQuery.Deferred}
 * @deprecated 16.0.0 Use OCP.Loader.loadScript
 */
export const addScript = (app, script, ready) => {
	console.warn('OC.addScript is deprecated, use OCP.Loader.loadScript instead')

	let deferred
	const path = OC.filePath(app, 'js', script + '.js')
	if (!loadedScripts[path]) {
		deferred = $.Deferred()
		$.getScript(path, () => deferred.resolve())
		loadedScripts[path] = deferred
	} else {
		if (ready) {
			ready()
		}
	}
	return loadedScripts[path]
}

/**
 * Loads a CSS file
 * @param {string} app the app id to which the css style belongs
 * @param {string} style the filename of the css file
 * @deprecated 16.0.0 Use OCP.Loader.loadStylesheet
 */
export const addStyle = (app, style) => {
	console.warn('OC.addStyle is deprecated, use OCP.Loader.loadStylesheet instead')

	const path = OC.filePath(app, 'css', style + '.css')
	if (loadedStyles.indexOf(path) === -1) {
		loadedStyles.push(path)
		if (document.createStyleSheet) {
			document.createStyleSheet(path)
		} else {
			style = $('<link rel="stylesheet" type="text/css" href="' + path + '"/>')
			$('head').append(style)
		}
	}
}
