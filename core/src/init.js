/* globals Snap */
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
import moment from 'moment'
import cssVars from 'css-vars-ponyfill'

import { initSessionHeartBeat } from './session-heartbeat'
import OC from './OC/index'
import { setUp as setUpContactsMenu } from './components/ContactsMenu'
import { setUp as setUpMainMenu } from './components/MainMenu'
import { setUp as setUpUserMenu } from './components/UserMenu'
import PasswordConfirmation from './OC/password-confirmation'

// keep in sync with core/css/variables.scss
const breakpointMobileWidth = 1024

const resizeMenu = () => {
	const appList = $('#appmenu li')
	const rightHeaderWidth = $('.header-right').outerWidth()
	const headerWidth = $('header').outerWidth()
	const usePercentualAppMenuLimit = 0.33
	const minAppsDesktop = 8
	let availableWidth = headerWidth - $('#nextcloud').outerWidth() - (rightHeaderWidth > 210 ? rightHeaderWidth : 210)
	const isMobile = $(window).width() < breakpointMobileWidth
	if (!isMobile) {
		availableWidth = availableWidth * usePercentualAppMenuLimit
	}
	let appCount = Math.floor((availableWidth / $(appList).width()))
	if (isMobile && appCount > minAppsDesktop) {
		appCount = minAppsDesktop
	}
	if (!isMobile && appCount < minAppsDesktop) {
		appCount = minAppsDesktop
	}

	// show at least 2 apps in the popover
	if (appList.length - 1 - appCount >= 1) {
		appCount--
	}

	$('#more-apps a').removeClass('active')
	let lastShownApp
	for (let k = 0; k < appList.length - 1; k++) {
		const name = $(appList[k]).data('id')
		if (k < appCount) {
			$(appList[k]).removeClass('hidden')
			$('#apps li[data-id=' + name + ']').addClass('in-header')
			lastShownApp = appList[k]
		} else {
			$(appList[k]).addClass('hidden')
			$('#apps li[data-id=' + name + ']').removeClass('in-header')
			// move active app to last position if it is active
			if (appCount > 0 && $(appList[k]).children('a').hasClass('active')) {
				$(lastShownApp).addClass('hidden')
				$('#apps li[data-id=' + $(lastShownApp).data('id') + ']').removeClass('in-header')
				$(appList[k]).removeClass('hidden')
				$('#apps li[data-id=' + name + ']').addClass('in-header')
			}
		}
	}

	// show/hide more apps icon
	if ($('#apps li:not(.in-header)').length === 0) {
		$('#more-apps').hide()
		$('#navigation').hide()
	} else {
		$('#more-apps').show()
	}
}

const initLiveTimestamps = () => {
	// Update live timestamps every 30 seconds
	setInterval(() => {
		$('.live-relative-timestamp').each(function() {
			$(this).text(OC.Util.relativeModifiedDate(parseInt($(this).attr('data-timestamp'), 10)))
		})
	}, 30 * 1000)
}

/**
 * Initializes core
 */
export const initCore = () => {
	/**
	 * Set users locale to moment.js as soon as possible
	 */
	moment.locale(OC.getLocale())

	const userAgent = window.navigator.userAgent
	const msie = userAgent.indexOf('MSIE ')
	const trident = userAgent.indexOf('Trident/')
	const edge = userAgent.indexOf('Edge/')

	if (msie > 0 || trident > 0) {
		// (IE 10 or older) || IE 11
		$('html').addClass('ie')
	} else if (edge > 0) {
		// for edge
		$('html').addClass('edge')
	}

	// css variables fallback for IE
	if (msie > 0 || trident > 0 || edge > 0) {
		console.info('Legacy browser detected, applying css vars polyfill')
		cssVars({
			watch: true,
			//  set edge < 16 as incompatible
			onlyLegacy: !(/Edge\/([0-9]{2})\./i.test(navigator.userAgent)
				&& parseInt(/Edge\/([0-9]{2})\./i.exec(navigator.userAgent)[1]) < 16),
		})
	}

	$(window).on('unload.main', () => { OC._unloadCalled = true })
	$(window).on('beforeunload.main', () => {
		// super-trick thanks to http://stackoverflow.com/a/4651049
		// in case another handler displays a confirmation dialog (ex: navigating away
		// during an upload), there are two possible outcomes: user clicked "ok" or
		// "cancel"

		// first timeout handler is called after unload dialog is closed
		setTimeout(() => {
			OC._userIsNavigatingAway = true

			// second timeout event is only called if user cancelled (Chrome),
			// but in other browsers it might still be triggered, so need to
			// set a higher delay...
			setTimeout(() => {
				if (!OC._unloadCalled) {
					OC._userIsNavigatingAway = false
				}
			}, 10000)
		}, 1)
	})
	$(document).on('ajaxError.main', function(event, request, settings) {
		if (settings && settings.allowAuthErrors) {
			return
		}
		OC._processAjaxError(request)
	})

	initSessionHeartBeat()

	OC.registerMenu($('#expand'), $('#expanddiv'), false, true)

	// toggle for menus
	$(document).on('mouseup.closemenus', event => {
		const $el = $(event.target)
		if ($el.closest('.menu').length || $el.closest('.menutoggle').length) {
			// don't close when clicking on the menu directly or a menu toggle
			return false
		}

		OC.hideMenus()
	})

	setUpMainMenu()
	setUpUserMenu()
	setUpContactsMenu()

	// move triangle of apps dropdown to align with app name triangle
	// 2 is the additional offset between the triangles
	if ($('#navigation').length) {
		$('#header #nextcloud + .menutoggle').on('click', () => {
			$('#menu-css-helper').remove()
			const caretPosition = $('.header-appname + .icon-caret').offset().left - 2
			if (caretPosition > 255) {
				// if the app name is longer than the menu, just put the triangle in the middle

			} else {
				$('head').append('<style id="menu-css-helper">#navigation:after { left: ' + caretPosition + 'px }</style>')
			}
		})
		$('#header #appmenu .menutoggle').on('click', () => {
			$('#appmenu').toggleClass('menu-open')
			if ($('#appmenu').is(':visible')) {
				$('#menu-css-helper').remove()
			}
		})
	}

	$(window).resize(resizeMenu)
	setTimeout(resizeMenu, 0)

	// just add snapper for logged in users
	// and if the app doesn't handle the nav slider itself
	if ($('#app-navigation').length && !$('html').hasClass('lte9')
		&& !$('#app-content').hasClass('no-snapper')) {

		// App sidebar on mobile
		const snapper = new Snap({
			element: document.getElementById('app-content'),
			disable: 'right',
			maxPosition: 300, // $navigation-width
			minDragDistance: 100,
		})

		$('#app-content').prepend('<div id="app-navigation-toggle" class="icon-menu" style="display:none" tabindex="0"></div>')

		const toggleSnapperOnButton = () => {
			if (snapper.state().state === 'left') {
				snapper.close()
			} else {
				snapper.open('left')
			}
		}

		$('#app-navigation-toggle').click(toggleSnapperOnButton)
		$('#app-navigation-toggle').keypress(e => {
			if (e.which === 13) {
				toggleSnapperOnButton()
			}
		})

		// close sidebar when switching navigation entry
		const $appNavigation = $('#app-navigation')
		$appNavigation.delegate('a, :button', 'click', event => {
			const $target = $(event.target)
			// don't hide navigation when changing settings or adding things
			if ($target.is('.app-navigation-noclose')
				|| $target.closest('.app-navigation-noclose').length) {
				return
			}
			if ($target.is('.app-navigation-entry-utils-menu-button')
				|| $target.closest('.app-navigation-entry-utils-menu-button').length) {
				return
			}
			if ($target.is('.add-new')
				|| $target.closest('.add-new').length) {
				return
			}
			if ($target.is('#app-settings')
				|| $target.closest('#app-settings').length) {
				return
			}
			snapper.close()
		})

		let navigationBarSlideGestureEnabled = false
		let navigationBarSlideGestureAllowed = true
		let navigationBarSlideGestureEnablePending = false

		OC.allowNavigationBarSlideGesture = () => {
			navigationBarSlideGestureAllowed = true

			if (navigationBarSlideGestureEnablePending) {
				snapper.enable()

				navigationBarSlideGestureEnabled = true
				navigationBarSlideGestureEnablePending = false
			}
		}

		OC.disallowNavigationBarSlideGesture = () => {
			navigationBarSlideGestureAllowed = false

			if (navigationBarSlideGestureEnabled) {
				const endCurrentDrag = true
				snapper.disable(endCurrentDrag)

				navigationBarSlideGestureEnabled = false
				navigationBarSlideGestureEnablePending = true
			}
		}

		const toggleSnapperOnSize = () => {
			if ($(window).width() > breakpointMobileWidth) {
				snapper.close()
				snapper.disable()

				navigationBarSlideGestureEnabled = false
				navigationBarSlideGestureEnablePending = false
			} else if (navigationBarSlideGestureAllowed) {
				snapper.enable()

				navigationBarSlideGestureEnabled = true
				navigationBarSlideGestureEnablePending = false
			} else {
				navigationBarSlideGestureEnablePending = true
			}
		}

		$(window).resize(_.debounce(toggleSnapperOnSize, 250))

		// initial call
		toggleSnapperOnSize()

	}

	initLiveTimestamps()
	PasswordConfirmation.init()
}
