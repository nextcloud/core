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
import $ from 'jquery'

import OC from './index'
import Notification from './notification'

/**
 * Warn users that the connection to the server was lost temporarily
 *
 * This function is throttled to prevent stacked notfications.
 * After 7sec the first notification is gone, then we can show another one
 * if necessary.
 */
export const ajaxConnectionLostHandler = _.throttle(() => {
	Notification.showTemporary(t('core', 'Connection to server lost'))
}, 7 * 1000, { trailing: false })

/**
 * Process ajax error, redirects to main page
 * if an error/auth error status was returned.
 * @param {XMLHttpRequest} xhr xhr request
 */
export const processAjaxError = xhr => {
	// purposefully aborted request ?
	// OC._userIsNavigatingAway needed to distinguish ajax calls cancelled by navigating away
	// from calls cancelled by failed cross-domain ajax due to SSO redirect
	if (xhr.status === 0 && (xhr.statusText === 'abort' || xhr.statusText === 'timeout' || OC._reloadCalled)) {
		return
	}

	if (_.contains([302, 303, 307, 401], xhr.status) && OC.currentUser) {
		// sometimes "beforeunload" happens later, so need to defer the reload a bit
		setTimeout(function() {
			if (!OC._userIsNavigatingAway && !OC._reloadCalled) {
				let timer = 0
				const seconds = 5
				const interval = setInterval(function() {
					Notification.showUpdate(n('core', 'Problem loading page, reloading in %n second', 'Problem loading page, reloading in %n seconds', seconds - timer))
					if (timer >= seconds) {
						clearInterval(interval)
						OC.reload()
					}
					timer++
				}, 1000 // 1 second interval
				)

				// only call reload once
				OC._reloadCalled = true
			}
		}, 100)
	} else if (xhr.status === 0) {
		// Connection lost (e.g. WiFi disconnected or server is down)
		setTimeout(function() {
			if (!OC._userIsNavigatingAway && !OC._reloadCalled) {
				// TODO: call method above directly
				OC._ajaxConnectionLostHandler()
			}
		}, 100)
	}
}

/**
 * Registers XmlHttpRequest object for global error processing.
 *
 * This means that if this XHR object returns 401 or session timeout errors,
 * the current page will automatically be reloaded.
 *
 * @param {XMLHttpRequest} xhr xhr request
 */
export const registerXHRForErrorProcessing = xhr => {
	const loadCallback = () => {
		if (xhr.readyState !== 4) {
			return
		}

		if ((xhr.status >= 200 && xhr.status < 300) || xhr.status === 304) {
			return
		}

		// fire jquery global ajax error handler
		$(document).trigger(new $.Event('ajaxError'), xhr)
	}

	const errorCallback = () => {
		// fire jquery global ajax error handler
		$(document).trigger(new $.Event('ajaxError'), xhr)
	}

	if (xhr.addEventListener) {
		xhr.addEventListener('load', loadCallback)
		xhr.addEventListener('error', errorCallback)
	}

}
