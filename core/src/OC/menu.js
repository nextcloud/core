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
import $ from 'jquery'

import { menuSpeed } from './constants'

export let currentMenu = null
export let currentMenuToggle = null

/**
 * For menu toggling
 *
 * @param {jQuery} $toggle the toggle element
 * @param {jQuery} $menuEl the menu container element
 * @param {function|undefined} toggle callback invoked everytime the menu is opened
 * @param {boolean} headerMenu is this a top right header menu?
 * @returns {undefined}
 */
export const registerMenu = function($toggle, $menuEl, toggle, headerMenu) {
	$menuEl.addClass('menu')
	const isClickableElement = $toggle.prop('tagName') === 'A' || $toggle.prop('tagName') === 'BUTTON'

	// On link and button, the enter key trigger a click event
	// Only use the click to avoid two fired events
	$toggle.on(isClickableElement ? 'click.menu' : 'click.menu keyup.menu', function(event) {
		// prevent the link event (append anchor to URL)
		event.preventDefault()

		// allow enter key as a trigger
		if (event.key && event.key !== 'Enter') {
			return
		}

		if ($menuEl.is(currentMenu)) {
			hideMenus()
			return
		} else if (currentMenu) {
			// another menu was open?
			// close it
			hideMenus()
		}

		if (headerMenu === true) {
			$menuEl.parent().addClass('openedMenu')
		}

		// Set menu to expanded
		$toggle.attr('aria-expanded', true)

		$menuEl.slideToggle(menuSpeed, toggle)
		currentMenu = $menuEl
		currentMenuToggle = $toggle
	})
}

/**
 * Unregister a previously registered menu
 *
 * @param {jQuery} $toggle the toggle element
 * @param {jQuery} $menuEl the menu container element
 */
export const unregisterMenu = ($toggle, $menuEl) => {
	// close menu if opened
	if ($menuEl.is(currentMenu)) {
		hideMenus()
	}
	$toggle.off('click.menu').removeClass('menutoggle')
	$menuEl.removeClass('menu')
}

/**
 * Hides any open menus
 *
 * @param {Function} complete callback when the hiding animation is done
 */
export const hideMenus = function(complete) {
	if (currentMenu) {
		const lastMenu = currentMenu
		currentMenu.trigger(new $.Event('beforeHide'))
		currentMenu.slideUp(menuSpeed, function() {
			lastMenu.trigger(new $.Event('afterHide'))
			if (complete) {
				complete.apply(this, arguments)
			}
		})
	}

	// Set menu to closed
	$('.menutoggle').attr('aria-expanded', false)

	$('.openedMenu').removeClass('openedMenu')
	currentMenu = null
	currentMenuToggle = null
}

/**
 * Shows a given element as menu
 *
 * @param {Object} [$toggle=null] menu toggle
 * @param {Object} $menuEl menu element
 * @param {Function} complete callback when the showing animation is done
 */
export const showMenu = ($toggle, $menuEl, complete) => {
	if ($menuEl.is(currentMenu)) {
		return
	}
	hideMenus()
	currentMenu = $menuEl
	currentMenuToggle = $toggle
	$menuEl.trigger(new $.Event('beforeShow'))
	$menuEl.show()
	$menuEl.trigger(new $.Event('afterShow'))
	// no animation
	if (_.isFunction(complete)) {
		complete()
	}
}
