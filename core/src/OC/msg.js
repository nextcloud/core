/*
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

/**
 * A little class to manage a status field for a "saving" process.
 * It can be used to display a starting message (e.g. "Saving...") and then
 * replace it with a green success message or a red error message.
 *
 * @namespace OC.msg
 */
export default {
	/**
	 * Displayes a "Saving..." message in the given message placeholder
	 *
	 * @param {Object} selector    Placeholder to display the message in
	 */
	startSaving: function(selector) {
		this.startAction(selector, t('core', 'Saving …'))
	},

	/**
	 * Displayes a custom message in the given message placeholder
	 *
	 * @param {Object} selector    Placeholder to display the message in
	 * @param {string} message    Plain text message to display (no HTML allowed)
	 */
	startAction: function(selector, message) {
		$(selector).text(message)
			.removeClass('success')
			.removeClass('error')
			.stop(true, true)
			.show()
	},

	/**
	 * Displayes an success/error message in the given selector
	 *
	 * @param {Object} selector    Placeholder to display the message in
	 * @param {Object} response    Response of the server
	 * @param {Object} response.data    Data of the servers response
	 * @param {string} response.data.message    Plain text message to display (no HTML allowed)
	 * @param {string} response.status    is being used to decide whether the message
	 * is displayed as an error/success
	 */
	finishedSaving: function(selector, response) {
		this.finishedAction(selector, response)
	},

	/**
	 * Displayes an success/error message in the given selector
	 *
	 * @param {Object} selector    Placeholder to display the message in
	 * @param {Object} response    Response of the server
	 * @param {Object} response.data Data of the servers response
	 * @param {string} response.data.message Plain text message to display (no HTML allowed)
	 * @param {string} response.status is being used to decide whether the message
	 * is displayed as an error/success
	 */
	finishedAction: function(selector, response) {
		if (response.status === 'success') {
			this.finishedSuccess(selector, response.data.message)
		} else {
			this.finishedError(selector, response.data.message)
		}
	},

	/**
	 * Displayes an success message in the given selector
	 *
	 * @param {Object} selector Placeholder to display the message in
	 * @param {string} message Plain text success message to display (no HTML allowed)
	 */
	finishedSuccess: function(selector, message) {
		$(selector).text(message)
			.addClass('success')
			.removeClass('error')
			.stop(true, true)
			.delay(3000)
			.fadeOut(900)
			.show()
	},

	/**
	 * Displayes an error message in the given selector
	 *
	 * @param {Object} selector Placeholder to display the message in
	 * @param {string} message Plain text error message to display (no HTML allowed)
	 */
	finishedError: function(selector, message) {
		$(selector).text(message)
			.addClass('error')
			.removeClass('success')
			.show()
	},
}
