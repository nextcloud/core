/*
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { generateUrl } from '@nextcloud/router'
import prefixWithBaseUrl from './prefixWithBaseUrl'

export default (background, time = 0, themingDefaultBackground = '') => {
	if (background === 'default') {
		if (themingDefaultBackground && themingDefaultBackground !== 'backgroundColor') {
			return generateUrl('/apps/theming/image/background') + '?v=' + window.OCA.Theming.cacheBuster
		}
		if (window.OCA.Accessibility && window.OCA.Accessibility.theme === 'dark') {
			return prefixWithBaseUrl('eduardo-neves-pedra-azul.jpg')
		}
		return prefixWithBaseUrl('kamil-porembinski-clouds.jpg')
	} else if (background === 'custom') {
		return generateUrl('/apps/dashboard/background') + '?v=' + time
	}
	return prefixWithBaseUrl(background)
}
