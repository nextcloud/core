/* eslint-disable @nextcloud/no-deprecations */
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

import { initCore } from './init'

import _ from 'underscore'
import $ from 'jquery'
import 'jquery-migrate/dist/jquery-migrate.min'
// TODO: switch to `jquery-ui` package and import widgets and effects individually
//       `jquery-ui-dist` is used as a workaround for the issue of missing effects
import 'jquery-ui-dist/jquery-ui'
import 'jquery-ui-dist/jquery-ui.css'
import 'jquery-ui-dist/jquery-ui.theme.css'
// END TODO
import autosize from 'autosize'
import Backbone from 'backbone'
import 'bootstrap/js/dist/tooltip'
import './Polyfill/tooltip'
import ClipboardJS from 'clipboard'
import dav from 'davclient.js'
import DOMPurify from 'dompurify'
import Handlebars from 'handlebars'
import 'jcrop/js/jquery.Jcrop'
import 'jcrop/css/jquery.Jcrop.css'
import jstimezonedetect from 'jstimezonedetect'
import md5 from 'blueimp-md5'
import moment from 'moment'
import 'select2'
import 'select2/select2.css'
import 'snap.js/dist/snap'
import 'strengthify'
import 'strengthify/strengthify.css'

import OC from './OC/index'
import OCP from './OCP/index'
import OCA from './OCA/index'
import escapeHTML from 'escape-html'
import { getToken as getRequestToken } from './OC/requesttoken'

const warnIfNotTesting = function() {
	if (window.TESTING === undefined) {
		console.warn.apply(console, arguments)
	}
}

/**
 * Mark a function as deprecated and automatically
 * warn if used!
 *
 * @param {Function} func the library to deprecate
 * @param {String} funcName the name of the library
 * @param {Int} version the version this gets removed
 * @returns {function}
 */
const deprecate = (func, funcName, version) => {
	const oldFunc = func
	const newFunc = function() {
		warnIfNotTesting(`The ${funcName} library is deprecated! It will be removed in nextcloud ${version}.`)
		return oldFunc.apply(this, arguments)
	}
	Object.assign(newFunc, oldFunc)
	return newFunc
}

const setDeprecatedProp = (global, cb, msg) => {
	(Array.isArray(global) ? global : [global]).map(global => {
		if (window[global] !== undefined) {
			delete window[global]
		}
		Object.defineProperty(window, global, {
			get: () => {
				if (msg) {
					warnIfNotTesting(`${global} is deprecated: ${msg}`)
				} else {
					warnIfNotTesting(`${global} is deprecated`)
				}

				return cb()
			},
		})
	})
}

window['_'] = _
setDeprecatedProp(['$', 'jQuery'], () => $, 'The global jQuery is deprecated. It will be updated to v2.4 in Nextcloud 20 and v3.x in Nextcloud 21. In later versions of Nextcloud it might be removed completely. Please ship your own.')
setDeprecatedProp('autosize', () => autosize, 'please ship your own, this will be removed in Nextcloud 20')
setDeprecatedProp('Backbone', () => Backbone, 'please ship your own, this will be removed in Nextcloud 20')
setDeprecatedProp(['Clipboard', 'ClipboardJS'], () => ClipboardJS, 'please ship your own, this will be removed in Nextcloud 20')
window['dav'] = dav
setDeprecatedProp('DOMPurify', () => DOMPurify, 'The global DOMPurify is deprecated, this will be removed in Nextcloud 21')
setDeprecatedProp('Handlebars', () => Handlebars, 'please ship your own, this will be removed in Nextcloud 20')
setDeprecatedProp(['jstz', 'jstimezonedetect'], () => jstimezonedetect, 'please ship your own, this will be removed in Nextcloud 20')
setDeprecatedProp('md5', () => md5, 'please ship your own, this will be removed in Nextcloud 20')
setDeprecatedProp('moment', () => moment, 'please ship your own, this will be removed in Nextcloud 20')

window['OC'] = OC
setDeprecatedProp('initCore', () => initCore, 'this is an internal function')
setDeprecatedProp('oc_appswebroots', () => OC.appswebroots, 'use OC.appswebroots instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_capabilities', OC.getCapabilities, 'use OC.getCapabilities instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_config', () => OC.config, 'use OC.config instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_current_user', () => OC.getCurrentUser().uid, 'use OC.getCurrentUser().uid instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_debug', () => OC.debug, 'use OC.debug instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_defaults', () => OC.theme, 'use OC.theme instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_isadmin', OC.isUserAdmin, 'use OC.isUserAdmin() instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_requesttoken', () => getRequestToken(), 'use OC.requestToken instead, this will be removed in Nextcloud 20')
setDeprecatedProp('oc_webroot', () => OC.webroot, 'use OC.getRootPath() instead, this will be removed in Nextcloud 20')
setDeprecatedProp('OCDialogs', () => OC.dialogs, 'use OC.dialogs instead, this will be removed in Nextcloud 20')
window['OCP'] = OCP
window['OCA'] = OCA
window['escapeHTML'] = deprecate(escapeHTML, 'escapeHTML', 19)
$.fn.select2 = deprecate($.fn.select2, 'select2', 19)

/**
 * translate a string
 * @param {string} app the id of the app for which to translate the string
 * @param {string} text the string to translate
 * @param [vars] map of placeholder key to value
 * @param {number} [count] number to replace %n with
 * @return {string}
 */
window.t = _.bind(OC.L10N.translate, OC.L10N)

/**
 * translate a string
 * @param {string} app the id of the app for which to translate the string
 * @param {string} text_singular the string to translate for exactly one object
 * @param {string} text_plural the string to translate for n objects
 * @param {number} count number to determine whether to use singular or plural
 * @param [vars] map of placeholder key to value
 * @return {string} Translated string
 */
window.n = _.bind(OC.L10N.translatePlural, OC.L10N)
