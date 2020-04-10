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

import _ from 'underscore'

import OC from './index'
import { coreApps } from './constants'

/**
 * Get an absolute url to a file in an app
 * @param {string} app the id of the app the file belongs to
 * @param {string} file the file path relative to the app folder
 * @returns {string} Absolute URL to a file
 */
export const linkTo = (app, file) => filePath(app, '', file)

/**
 * Creates a relative url for remote use
 * @param {string} service id
 * @returns {string} the url
 */
export const linkToRemoteBase = service => getRootPath() + '/remote.php/' + service

/**
 * @brief Creates an absolute url for remote use
 * @param {string} service id
 * @returns {string} the url
 */
export const linkToRemote = service => window.location.protocol + '//' + window.location.host + linkToRemoteBase(service)

/**
 * Gets the base path for the given OCS API service.
 * @param {string} service name
 * @param {int} version OCS API version
 * @returns {string} OCS API base path
 */
export const linkToOCS = (service, version) => {
	version = (version !== 2) ? 1 : 2
	return window.location.protocol + '//' + window.location.host + getRootPath() + '/ocs/v' + version + '.php/' + service + '/'
}

/**
 * Generates the absolute url for the given relative url, which can contain parameters.
 * Parameters will be URL encoded automatically.
 * @param {string} url the url
 * @param {Object} [params] params
 * @param {Object} [options] destructuring object
 * @param {bool} [options.escape=true] enable/disable auto escape of placeholders (by default enabled)
 * @returns {string} Absolute URL for the given relative URL
 */
export const generateUrl = (url, params, options) => {
	const defaultOptions = {
		escape: true,
	}
	const allOptions = options || {}
	_.defaults(allOptions, defaultOptions)

	const _build = function(text, vars) {
		vars = vars || []
		return text.replace(/{([^{}]*)}/g,
			function(a, b) {
				const r = (vars[b])
				if (allOptions.escape) {
					return (typeof r === 'string' || typeof r === 'number') ? encodeURIComponent(r) : encodeURIComponent(a)
				} else {
					return (typeof r === 'string' || typeof r === 'number') ? r : a
				}
			}
		)
	}
	if (url.charAt(0) !== '/') {
		url = '/' + url

	}

	if (OC.config.modRewriteWorking === true) {
		return getRootPath() + _build(url, params)
	}

	return getRootPath() + '/index.php' + _build(url, params)
}

/**
 * get the absolute path to an image file
 * if no extension is given for the image, it will automatically decide
 * between .png and .svg based on what the browser supports
 *
 * @param {string} app the app id to which the image belongs
 * @param {string} file the name of the image file
 * @returns {string}
 * @deprecated 19.0.0 use `imagePath` from https://www.npmjs.com/package/@nextcloud/router
 */
export const imagePath = (app, file) => {
	if (file.indexOf('.') === -1) {
		// if no extension is given, use svg
		return filePath(app, 'img', file + '.svg')
	}

	return filePath(app, 'img', file)
}

/**
 * Get the absolute url for a file in an app
 * @param {string} app the id of the app
 * @param {string} type the type of the file to link to (e.g. css,img,ajax.template)
 * @param {string} file the filename
 * @returns {string} Absolute URL for a file in an app
 * @deprecated 19.0.0 use `generateFilePath` from https://www.npmjs.com/package/@nextcloud/router
 */
export const filePath = (app, type, file) => {
	const isCore = coreApps.indexOf(app) !== -1
	let link = getRootPath()
	if (file.substring(file.length - 3) === 'php' && !isCore) {
		link += '/index.php/apps/' + app
		if (file !== 'index.php') {
			link += '/'
			if (type) {
				link += encodeURI(type + '/')
			}
			link += file
		}
	} else if (file.substring(file.length - 3) !== 'php' && !isCore) {
		link = OC.appswebroots[app]
		if (type) {
			link += '/' + type + '/'
		}
		if (link.substring(link.length - 1) !== '/') {
			link += '/'
		}
		link += file
	} else {
		if ((app === 'core' || app === 'search') && type === 'ajax') {
			link += '/index.php/'
		} else {
			link += '/'
		}
		if (!isCore) {
			link += 'apps/'
		}
		if (app !== '') {
			app += '/'
			link += app
		}
		if (type) {
			link += type + '/'
		}
		link += file
	}
	return link
}

/**
 * Returns the web root path where this Nextcloud instance
 * is accessible, with a leading slash.
 * For example "/nextcloud".
 *
 * @returns {string} web root path
 *
 * @deprecated 19.0.0 use `getRootUrl` from https://www.npmjs.com/package/@nextcloud/router
 * @since 8.2
 */
export const getRootPath = () => OC.webroot
