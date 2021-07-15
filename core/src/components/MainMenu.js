/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import $ from 'jquery'

import OC from '../OC'

/**
 * Set up the main menu toggle to react to media query changes.
 * If the screen is small enough, the main menu becomes a toggle.
 * If the screen is bigger, the main menu is not a toggle any more.
 */
export const setUp = () => {

	Object.assign(OC, {
		setNavigationCounter(id, counter) {
			const appmenuElement = document.getElementById('appmenu').querySelector('[data-id="' + id + '"] svg')
			const appsElement = document.getElementById('apps').querySelector('[data-id="' + id + '"] svg')
			if (counter === 0) {
				appmenuElement.classList.remove('has-unread')
				appsElement.classList.remove('has-unread')
				appmenuElement.getElementsByTagName('image')[0].style.mask = ''
				appsElement.getElementsByTagName('image')[0].style.mask = ''
			} else {
				appmenuElement.classList.add('has-unread')
				appsElement.classList.add('has-unread')
				appmenuElement.getElementsByTagName('image')[0].style.mask = 'url(#hole)'
				appsElement.getElementsByTagName('image')[0].style.mask = 'url(#hole)'
			}
			document.getElementById('appmenu').querySelector('[data-id="' + id + '"] .unread-counter').textContent = counter
			document.getElementById('apps').querySelector('[data-id="' + id + '"] .unread-counter').textContent = counter
		},
	})
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
		if (event.which === 1 && !event.ctrlKey && !event.metaKey && $app.attr('target') !== '_blank') {
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

		if (event.which === 1 && !event.ctrlKey && !event.metaKey && $app.parent('#more-apps').length === 0 && $app.attr('target') !== '_blank') {
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
