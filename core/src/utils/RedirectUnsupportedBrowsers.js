/**
 * @copyright 2021 John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import { generateUrl } from '@nextcloud/router'

import { supportedBrowsersRegExp } from '../services/BrowsersListService'
import browserStorage from '../services/BrowserStorageService'
import logger from '../services/LoggerService'

const redirectPath = '/unsupported'
export const browserStorageKey = 'unsupported-browser-ignore'

const isBrowserOverridden = browserStorage.getItem(browserStorageKey) === 'true'

/**
 * Test the current browser user agent against our official browserslist config
 * and redirect if unsupported
 */
export const testSupportedBrowser = function() {
	if (!supportedBrowsersRegExp.test(navigator.userAgent)
		&& window.location.pathname.indexOf(redirectPath) === -1
		&& !isBrowserOverridden) {
		window.location = generateUrl(redirectPath)
	} else if (isBrowserOverridden) {
		logger.debug('this browser is NOT supported but has been manually overridden ! ⚠️', { browserStorageKey: browserStorage.getItem(browserStorageKey) })
	} else {
		logger.debug('this browser is officially supported ! 🚀', { browserStorageKey: browserStorage.getItem(browserStorageKey) })
	}
}
