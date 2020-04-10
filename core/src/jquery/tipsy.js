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

/**
 * $ tipsy shim for the bootstrap tooltip
 * @param {Object} argument options
 * @returns {Object} this
 * @deprecated
 */
$.fn.tipsy = function(argument) {
	console.warn('Deprecation warning: tipsy is deprecated. Use tooltip instead.')
	if (typeof argument === 'object' && argument !== null) {

		// tipsy defaults
		const options = {
			placement: 'bottom',
			delay: { 'show': 0, 'hide': 0 },
			trigger: 'hover',
			html: false,
			container: 'body',
		}
		if (argument.gravity) {
			switch (argument.gravity) {
			case 'n':
			case 'nw':
			case 'ne':
				options.placement = 'bottom'
				break
			case 's':
			case 'sw':
			case 'se':
				options.placement = 'top'
				break
			case 'w':
				options.placement = 'right'
				break
			case 'e':
				options.placement = 'left'
				break
			}
		}
		if (argument.trigger) {
			options.trigger = argument.trigger
		}
		if (argument.delayIn) {
			options.delay.show = argument.delayIn
		}
		if (argument.delayOut) {
			options.delay.hide = argument.delayOut
		}
		if (argument.html) {
			options.html = true
		}
		if (argument.fallback) {
			options.title = argument.fallback
		}
		// destroy old tooltip in case the title has changed
		$.fn.tooltip.call(this, 'destroy')
		$.fn.tooltip.call(this, options)
	} else {
		this.tooltip(argument)
		$.fn.tooltip.call(this, argument)
	}
	return this
}
