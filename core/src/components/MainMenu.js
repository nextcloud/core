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

import $ from 'jquery'

import OC from '../OC'

/**
 * Set up the main menu toggle to react to media query changes.
 * If the screen is small enough, the main menu becomes a toggle.
 * If the screen is bigger, the main menu is not a toggle any more.
 */
export const setUp = () => {
	// init the more-apps menu
	OC.registerMenu($('#more-apps > a'), $('#navigation'))

	// toggle the navigation
	const $toggle = $('#header .header-appname-container')
	const $navigation = $('#navigation')
	const $appmenu = $('#appmenu')

	// init the menu
	OC.registerMenu($toggle, $navigation)
	$toggle.data('oldhref', $toggle.attr('href'))
	$toggle.attr('href', '#')
	$navigation.hide()

	// show loading feedback on more apps list
	$navigation.delegate('a', 'click', event => {
		let $app = $(event.target)
		if (!$app.is('a')) {
			$app = $app.closest('a')
		}
		if (event.which === 1 && !event.ctrlKey && !event.metaKey) {
			$app.find('svg').remove()
			$app.find('div').remove() // prevent odd double-clicks
			// no need for theming, loader is already inverted on dark mode
			// but we need it over the primary colour
			$app.prepend($('<div/>').addClass('icon-loading-small'))
		} else {
			// Close navigation when opening app in
			// a new tab
			OC.hideMenus(() => false)
		}
	})

	$navigation.delegate('a', 'mouseup', event => {
		if (event.which === 2) {
			// Close navigation when opening app in
			// a new tab via middle click
			OC.hideMenus(() => false)
		}
	})

	// show loading feedback on visible apps list
	$appmenu.delegate('li:not(#more-apps) > a', 'click', event => {
		let $app = $(event.target)
		if (!$app.is('a')) {
			$app = $app.closest('a')
		}

		if (event.which === 1 && !event.ctrlKey && !event.metaKey && $app.parent('#more-apps').length === 0) {
			$app.find('svg').remove()
			$app.find('div').remove() // prevent odd double-clicks
			$app.prepend($('<div/>').addClass(
				OCA.Theming && OCA.Theming.inverted
					? 'icon-loading-small'
					: 'icon-loading-small-dark'
			))
			// trigger redirect
			// needed for ie, but also works for every browser
			window.location = $app.attr('href')
		} else {
			// Close navigation when opening app in
			// a new tab
			OC.hideMenus(() => false)
		}
	})
}
