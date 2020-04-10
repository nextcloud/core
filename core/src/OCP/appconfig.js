/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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
 *
 */

import $ from 'jquery'

import OC from '../OC/index'

/**
 * @param {string} method 'post' or 'delete'
 * @param {string} endpoint endpoint
 * @param {Object} [options] destructuring object
 * @param {Object} [options.data] option data
 * @param {function} [options.success] success callback
 * @param {function} [options.error] error callback
 * @internal
 */
function call(method, endpoint, options) {
	if ((method === 'post' || method === 'delete') && OC.PasswordConfirmation.requiresPasswordConfirmation()) {
		OC.PasswordConfirmation.requirePasswordConfirmation(_.bind(call, this, method, endpoint, options))
		return
	}

	options = options || {}
	$.ajax({
		type: method.toUpperCase(),
		url: OC.linkToOCS('apps/provisioning_api/api/v1', 2) + 'config/apps' + endpoint,
		data: options.data || {},
		success: options.success,
		error: options.error,
	})
}

/**
 * @param {Object} [options] destructuring object
 * @param {function} [options.success] success callback
 * @since 11.0.0
 */
export function getApps(options) {
	call('get', '', options)
}

/**
 * @param {string} app app id
 * @param {Object} [options] destructuring object
 * @param {function} [options.success] success callback
 * @param {function} [options.error] error callback
 * @since 11.0.0
 */
export function getKeys(app, options) {
	call('get', '/' + app, options)
}

/**
 * @param {string} app app id
 * @param {string} key key
 * @param {string|function} defaultValue default value
 * @param {Object} [options] destructuring object
 * @param {function} [options.success] success callback
 * @param {function} [options.error] error callback
 * @since 11.0.0
 */
export function getValue(app, key, defaultValue, options) {
	options = options || {}
	options.data = {
		defaultValue: defaultValue,
	}

	call('get', '/' + app + '/' + key, options)
}

/**
 * @param {string} app app id
 * @param {string} key key
 * @param {string} value value
 * @param {Object} [options] destructuring object
 * @param {function} [options.success] success callback
 * @param {function} [options.error] error callback
 * @since 11.0.0
 */
export function setValue(app, key, value, options) {
	options = options || {}
	options.data = {
		value: value,
	}

	call('post', '/' + app + '/' + key, options)
}

/**
 * @param {string} app app id
 * @param {string} key key
 * @param {Object} [options] destructuring object
 * @param {function} [options.success] success callback
 * @param {function} [options.error] error callback
 * @since 11.0.0
 */
export function deleteKey(app, key, options) {
	call('delete', '/' + app + '/' + key, options)
}
