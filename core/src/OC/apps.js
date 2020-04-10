/**
 * ownCloud - core
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Bernhard Posselt 2014
 */

import $ from 'jquery'

let dynamicSlideToggleEnabled = false

const Apps = {
	enableDynamicSlideToggle: function() {
		dynamicSlideToggleEnabled = true
	},
}

/**
 * Shows the #app-sidebar and add .with-app-sidebar to subsequent siblings
 *
 * @param {Object} [$el] sidebar element to show, defaults to $('#app-sidebar')
 */
Apps.showAppSidebar = function($el) {
	const $appSidebar = $el || $('#app-sidebar')
	$appSidebar.removeClass('disappear').show()
	$('#app-content').trigger(new $.Event('appresized'))
}

/**
 * Shows the #app-sidebar and removes .with-app-sidebar from subsequent
 * siblings
 *
 * @param {Object} [$el] sidebar element to hide, defaults to $('#app-sidebar')
 */
Apps.hideAppSidebar = function($el) {
	const $appSidebar = $el || $('#app-sidebar')
	$appSidebar.hide().addClass('disappear')
	$('#app-content').trigger(new $.Event('appresized'))
}

/**
 * Provides a way to slide down a target area through a button and slide it
 * up if the user clicks somewhere else. Used for the news app settings and
 * add new field.
 *
 * Usage:
 * <button data-apps-slide-toggle=".slide-area">slide</button>
 * <div class=".slide-area" class="hidden">I'm sliding up</div>
 */
export const registerAppsSlideToggle = () => {
	let buttons = $('[data-apps-slide-toggle]')

	if (buttons.length === 0) {
		$('#app-navigation').addClass('without-app-settings')
	}

	$(document).click(function(event) {

		if (dynamicSlideToggleEnabled) {
			buttons = $('[data-apps-slide-toggle]')
		}

		buttons.each(function(index, button) {

			const areaSelector = $(button).data('apps-slide-toggle')
			const area = $(areaSelector)

			function hideArea() {
				area.slideUp(OC.menuSpeed * 4, function() {
					area.trigger(new $.Event('hide'))
				})
				area.removeClass('opened')
				$(button).removeClass('opened')
			}

			function showArea() {
				area.slideDown(OC.menuSpeed * 4, function() {
					area.trigger(new $.Event('show'))
				})
				area.addClass('opened')
				$(button).addClass('opened')
				const input = $(areaSelector + ' [autofocus]')
				if (input.length === 1) {
					input.focus()
				}
			}

			// do nothing if the area is animated
			if (!area.is(':animated')) {

				// button toggles the area
				if ($(button).is($(event.target).closest('[data-apps-slide-toggle]'))) {
					if (area.is(':visible')) {
						hideArea()
					} else {
						showArea()
					}

					// all other areas that have not been clicked but are open
					// should be slid up
				} else {
					const closest = $(event.target).closest(areaSelector)
					if (area.is(':visible') && closest[0] !== area[0]) {
						hideArea()
					}
				}
			}
		})

	})
}

export default Apps
