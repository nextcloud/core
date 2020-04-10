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

/**
 * Returns a human readable file size
 * @param {number} size Size in bytes
 * @param {boolean} skipSmallSizes return '< 1 kB' for small files
 * @returns {string}
 */
export default function humanFileSize(size, skipSmallSizes) {
	const humanList = ['B', 'KB', 'MB', 'GB', 'TB']
	// Calculate Log with base 1024: size = 1024 ** order
	let order = size > 0 ? Math.floor(Math.log(size) / Math.log(1024)) : 0
	// Stay in range of the byte sizes that are defined
	order = Math.min(humanList.length - 1, order)
	const readableFormat = humanList[order]
	let relativeSize = (size / Math.pow(1024, order)).toFixed(1)
	if (skipSmallSizes === true && order === 0) {
		if (relativeSize !== '0.0') {
			return '< 1 KB'
		} else {
			return '0 KB'
		}
	}
	if (order < 2) {
		relativeSize = parseFloat(relativeSize).toFixed(0)
	} else if (relativeSize.substr(relativeSize.length - 2, 2) === '.0') {
		relativeSize = relativeSize.substr(0, relativeSize.length - 2)
	} else {
		relativeSize = parseFloat(relativeSize).toLocaleString(OC.getCanonicalLocale())
	}
	return relativeSize + ' ' + readableFormat
}
