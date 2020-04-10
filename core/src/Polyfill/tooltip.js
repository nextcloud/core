/*
 * @copyright 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author 2019 Julius Härtl <jus@bitgrid.net>
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

$.prototype.tooltip = (function(tooltip) {
	return function(config) {
		try {
			return tooltip.call(this, config)
		} catch (ex) {
			if (ex instanceof TypeError && config === 'destroy') {
				console.error('Deprecated call $.tooltip(\'destroy\') has been deprecated and should be removed')
				return tooltip.call(this, 'dispose')
			}
			if (ex instanceof TypeError && config === 'fixTitle') {
				console.error('Deprecated call $.tooltip(\'fixTitle\') has been deprecated and should be removed')
				return tooltip.call(this, '_fixTitle')
			}
		}
	}
})($.prototype.tooltip)
