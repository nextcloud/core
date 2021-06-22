/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
import 'core-js/stable'
import 'regenerator-runtime/runtime'
import './Polyfill/index'

// If you remove the line below, tests won't pass
// eslint-disable-next-line no-unused-vars
import OC from './OC/index'

import './globals'
import './jquery/index'
import { initCore } from './init'
import { registerAppsSlideToggle } from './OC/apps'
import { testSupportedBrowser } from './utils/RedirectUnsupportedBrowsers'

testSupportedBrowser()

window.addEventListener('DOMContentLoaded', function() {
	initCore()
	registerAppsSlideToggle()

	// fallback to hashchange when no history support
	if (window.history.pushState) {
		window.onpopstate = _.bind(OC.Util.History._onPopState, OC.Util.History)
	} else {
		$(window).on('hashchange', _.bind(OC.Util.History._onPopState, OC.Util.History))
	}
})
