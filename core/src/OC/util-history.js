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

/**
 * Utility class for the history API,
 * includes fallback to using the URL hash when
 * the browser doesn't support the history API.
 *
 * @namespace OC.Util.History
 */
export default {

	_handlers: [],

	/**
	 * Push the current URL parameters to the history stack
	 * and change the visible URL.
	 * Note: this includes a workaround for IE8/IE9 that uses
	 * the hash part instead of the search part.
	 *
	 * @param {Object|string} params to append to the URL, can be either a string
	 * or a map
	 * @param {string} [url] URL to be used, otherwise the current URL will be used,
	 * using the params as query string
	 * @param {boolean} [replace=false] whether to replace instead of pushing
	 */
	_pushState: function(params, url, replace) {
		let strParams
		if (typeof (params) === 'string') {
			strParams = params
		} else {
			strParams = OC.buildQueryString(params)
		}

		if (window.history.pushState) {
			url = url || location.pathname + '?' + strParams
			// Workaround for bug with SVG and window.history.pushState on Firefox < 51
			// https://bugzilla.mozilla.org/show_bug.cgi?id=652991
			const isFirefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1
			if (isFirefox && parseInt(navigator.userAgent.split('/').pop()) < 51) {
				const patterns = document.querySelectorAll('[fill^="url(#"], [stroke^="url(#"], [filter^="url(#invert"]')
				for (let i = 0, ii = patterns.length, pattern; i < ii; i++) {
					pattern = patterns[i]
					// eslint-disable-next-line no-self-assign
					pattern.style.fill = pattern.style.fill
					// eslint-disable-next-line no-self-assign
					pattern.style.stroke = pattern.style.stroke
					pattern.removeAttribute('filter')
					pattern.setAttribute('filter', 'url(#invert)')
				}
			}
			if (replace) {
				window.history.replaceState(params, '', url)
			} else {
				window.history.pushState(params, '', url)
			}
		} else {
			// use URL hash for IE8
			window.location.hash = '?' + strParams
			// inhibit next onhashchange that just added itself
			// to the event queue
			this._cancelPop = true
		}
	},

	/**
	 * Push the current URL parameters to the history stack
	 * and change the visible URL.
	 * Note: this includes a workaround for IE8/IE9 that uses
	 * the hash part instead of the search part.
	 *
	 * @param {Object|string} params to append to the URL, can be either a string or a map
	 * @param {string} [url] URL to be used, otherwise the current URL will be used, using the params as query string
	 */
	pushState: function(params, url) {
		this._pushState(params, url, false)
	},

	/**
	 * Push the current URL parameters to the history stack
	 * and change the visible URL.
	 * Note: this includes a workaround for IE8/IE9 that uses
	 * the hash part instead of the search part.
	 *
	 * @param {Object|string} params to append to the URL, can be either a string
	 * or a map
	 * @param {string} [url] URL to be used, otherwise the current URL will be used,
	 * using the params as query string
	 */
	replaceState: function(params, url) {
		this._pushState(params, url, true)
	},

	/**
	 * Add a popstate handler
	 *
	 * @param {Function} handler handler
	 */
	addOnPopStateHandler: function(handler) {
		this._handlers.push(handler)
	},

	/**
	 * Parse a query string from the hash part of the URL.
	 * (workaround for IE8 / IE9)
	 * @returns {string}
	 */
	_parseHashQuery: function() {
		const hash = window.location.hash
		const pos = hash.indexOf('?')
		if (pos >= 0) {
			return hash.substr(pos + 1)
		}
		if (hash.length) {
			// remove hash sign
			return hash.substr(1)
		}
		return ''
	},

	_decodeQuery: function(query) {
		return query.replace(/\+/g, ' ')
	},

	/**
	 * Parse the query/search part of the URL.
	 * Also try and parse it from the URL hash (for IE8)
	 *
	 * @returns {Object} map of parameters
	 */
	parseUrlQuery: function() {
		const query = this._parseHashQuery()
		let params
		// try and parse from URL hash first
		if (query) {
			params = OC.parseQueryString(this._decodeQuery(query))
		}
		// else read from query attributes
		params = _.extend(params || {}, OC.parseQueryString(this._decodeQuery(location.search)))
		return params || {}
	},

	_onPopState: function(e) {
		if (this._cancelPop) {
			this._cancelPop = false
			return
		}
		let params
		if (!this._handlers.length) {
			return
		}
		params = (e && e.state)
		if (_.isString(params)) {
			params = OC.parseQueryString(params)
		} else if (!params) {
			params = this.parseUrlQuery() || {}
		}
		for (let i = 0; i < this._handlers.length; i++) {
			this._handlers[i](params)
		}
	},
}
