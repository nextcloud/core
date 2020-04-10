/* eslint-disable */
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

 import { getValue, setValue, getApps, getKeys, deleteKey } from '../OCP/appconfig'

export const appConfig = window.oc_appconfig || {}

/**
 * @namespace
 * @deprecated 16.0.0 Use OCP.AppConfig instead
 */
export const AppConfig = {
	/**
	 * @deprecated Use OCP.AppConfig.getValue() instead
	 */
	getValue: function(app, key, defaultValue, callback) {
		getValue(app, key, defaultValue, {
			success: callback
		})
	},

	/**
	 * @deprecated Use OCP.AppConfig.setValue() instead
	 */
	setValue: function(app, key, value) {
		setValue(app, key, value)
	},

	/**
	 * @deprecated Use OCP.AppConfig.getApps() instead
	 */
	getApps: function(callback) {
		getApps({
			success: callback
		})
	},

	/**
	 * @deprecated Use OCP.AppConfig.getKeys() instead
	 */
	getKeys: function(app, callback) {
		getKeys(app, {
			success: callback
		})
	},

	/**
	 * @deprecated Use OCP.AppConfig.deleteKey() instead
	 */
	deleteKey: function(app, key) {
		deleteKey(app, key)
	}

}
