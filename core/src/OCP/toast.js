/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
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

import {
	showError,
	showInfo, showMessage,
	showSuccess,
	showWarning,
} from '@nextcloud/dialogs'

export default {
	/**
	 * @deprecated 19.0.0 use `showSuccess` from the `@nextcloud/dialogs` package instead
	 *
	 * @param {string} text the toast text
	 * @param {object} options options
	 * @returns {Toast}
	 */
	success(text, options) {
		return showSuccess(text, options)
	},
	/**
	 * @deprecated 19.0.0 use `showWarning` from the `@nextcloud/dialogs` package instead
	 *
	 * @param {string} text the toast text
	 * @param {object} options options
	 * @returns {Toast}
	 */
	warning(text, options) {
		return showWarning(text, options)
	},
	/**
	 * @deprecated 19.0.0 use `showError` from the `@nextcloud/dialogs` package instead
	 *
	 * @param {string} text the toast text
	 * @param {object} options options
	 * @returns {Toast}
	 */
	error(text, options) {
		return showError(text, options)
	},
	/**
	 * @deprecated 19.0.0 use `showInfo` from the `@nextcloud/dialogs` package instead
	 *
	 * @param {string} text the toast text
	 * @param {object} options options
	 * @returns {Toast}
	 */
	info(text, options) {
		return showInfo(text, options)
	},
	/**
	 * @deprecated 19.0.0 use `showMessage` from the `@nextcloud/dialogs` package instead
	 *
	 * @param {string} text the toast text
	 * @param {object} options options
	 * @returns {Toast}
	 */
	message(text, options) {
		return showMessage(text, options)
	},

}
