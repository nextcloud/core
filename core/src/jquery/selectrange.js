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
 * select a range in an input field
 * @link http://stackoverflow.com/questions/499126/jquery-set-cursor-position-in-text-area
 * @param {int} start start selection from
 * @param {int} end number of char from start
 * @returns {Void}
 */
$.fn.selectRange = function(start, end) {
	return this.each(function() {
		if (this.setSelectionRange) {
			this.focus()
			this.setSelectionRange(start, end)
		} else if (this.createTextRange) {
			const range = this.createTextRange()
			range.collapse(true)
			range.moveEnd('character', end)
			range.moveStart('character', start)
			range.select()
		}
	})
}
