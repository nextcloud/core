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
import moment from 'moment'

import OC from './index'

/**
 * @namespace OC.PasswordConfirmation
 */
export default {
	callback: null,

	pageLoadTime: null,

	init: function() {
		$('.password-confirm-required').on('click', _.bind(this.requirePasswordConfirmation, this))
		this.pageLoadTime = moment.now()
	},

	requiresPasswordConfirmation: function() {
		const serverTimeDiff = this.pageLoadTime - (window.nc_pageLoad * 1000)
		const timeSinceLogin = moment.now() - (serverTimeDiff + (window.nc_lastLogin * 1000))

		// if timeSinceLogin > 30 minutes and user backend allows password confirmation
		return (window.backendAllowsPasswordConfirmation && timeSinceLogin > 30 * 60 * 1000)
	},

	/**
	 * @param {Function} callback success callback function
	 * @param {Object} options options
	 * @param {Function} rejectCallback error callback function
	 */
	requirePasswordConfirmation: function(callback, options, rejectCallback) {
		options = typeof options !== 'undefined' ? options : {}
		const defaults = {
			title: t('core', 'Authentication required'),
			text: t(
				'core',
				'This action requires you to confirm your password'
			),
			confirm: t('core', 'Confirm'),
			label: t('core', 'Password'),
			error: '',
		}

		const config = _.extend(defaults, options)

		const self = this

		if (this.requiresPasswordConfirmation()) {
			OC.dialogs.prompt(
				config.text,
				config.title,
				function(result, password) {
					if (result && password !== '') {
						self._confirmPassword(password, config)
					} else if (_.isFunction(rejectCallback)) {
						rejectCallback()
					}
				},
				true,
				config.label,
				true
			).then(function() {
				const $dialog = $('.oc-dialog:visible')
				$dialog.find('.ui-icon').remove()
				$dialog.addClass('password-confirmation')
				if (config.error !== '') {
					const $error = $('<p></p>').addClass('msg warning').text(config.error)
					$dialog.find('.oc-dialog-content').append($error)
				}
				$dialog.find('.oc-dialog-buttonrow').addClass('aside')

				const $buttons = $dialog.find('button')
				$buttons.eq(0).hide()
				$buttons.eq(1).text(config.confirm)
			})
		}

		this.callback = callback
	},

	_confirmPassword: function(password, config) {
		const self = this

		$.ajax({
			url: OC.generateUrl('/login/confirm'),
			data: {
				password: password,
			},
			type: 'POST',
			success: function(response) {
				window.nc_lastLogin = response.lastLogin

				if (_.isFunction(self.callback)) {
					self.callback()
				}
			},
			error: function() {
				config.error = t('core', 'Failed to authenticate, try again')
				OC.PasswordConfirmation.requirePasswordConfirmation(self.callback, config)
			},
		})
	},
}
