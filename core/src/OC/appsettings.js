/* eslint-disable */
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
import $ from 'jquery'
import { filePath } from './routing'

/**
 * Opens a popup with the setting for an app.
 * @param {string} appid The ID of the app e.g. 'calendar', 'contacts' or 'files'.
 * @param {boolean|string} loadJS If true 'js/settings.js' is loaded. If it's a string
 * it will attempt to load a script by that name in the 'js' directory.
 * @param {boolean} [cache] If true the javascript file won't be forced refreshed. Defaults to true.
 * @param {string} [scriptName] The name of the PHP file to load. Defaults to 'settings.php' in
 * the root of the app directory hierarchy.
 *
 * @deprecated 17.0.0 this method is unused and will be removed with Nextcloud 18
 */
export const appSettings = args => {
	console.warn('OC.appSettings is deprecated and will be removed with Nextcloud 18')

	if (typeof args === 'undefined' || typeof args.appid === 'undefined') {
		throw {
			name: 'MissingParameter',
			message: 'The parameter appid is missing'
		}
	}
	var props = { scriptName: 'settings.php', cache: true }
	$.extend(props, args)
	var settings = $('#appsettings')
	if (settings.length === 0) {
		throw {
			name: 'MissingDOMElement',
			message: 'There has be be an element with id "appsettings" for the popup to show.'
		}
	}
	var popup = $('#appsettings_popup')
	if (popup.length === 0) {
		$('body').prepend('<div class="popup hidden" id="appsettings_popup"></div>')
		popup = $('#appsettings_popup')
		popup.addClass(settings.hasClass('topright') ? 'topright' : 'bottomleft')
	}
	if (popup.is(':visible')) {
		popup.hide().remove()
	} else {
		const arrowclass = settings.hasClass('topright') ? 'up' : 'left'
		$.get(filePath(props.appid, '', props.scriptName), function(data) {
			popup.html(data).ready(function() {
				popup.prepend('<span class="arrow ' + arrowclass + '"></span><h2>' + t('core', 'Settings') + '</h2><a class="close"></a>').show()
				popup.find('.close').bind('click', function() {
					popup.remove()
				})
				if (typeof props.loadJS !== 'undefined') {
					var scriptname
					if (props.loadJS === true) {
						scriptname = 'settings.js'
					} else if (typeof props.loadJS === 'string') {
						scriptname = props.loadJS
					} else {
						throw {
							name: 'InvalidParameter',
							message: 'The "loadJS" parameter must be either boolean or a string.'
						}
					}
					if (props.cache) {
						$.ajaxSetup({ cache: true })
					}
					$.getScript(filePath(props.appid, 'js', scriptname))
						.fail(function(jqxhr, settings, e) {
							throw e
						})
				}
			}).show()
		}, 'html')
	}
}
