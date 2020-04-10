/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
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

import Vue from 'vue'
import Vuex from 'vuex'
import users from './users'
import apps from './apps'
import settings from './settings'
import oc from './oc'

Vue.use(Vuex)

const debug = process.env.NODE_ENV !== 'production'

const mutations = {
	API_FAILURE(state, error) {
		try {
			const message = error.error.response.data.ocs.meta.message
			OC.Notification.showHtml(t('settings', 'An error occured during the request. Unable to proceed.') + '<br>' + message, { timeout: 7 })
		} catch (e) {
			OC.Notification.showTemporary(t('settings', 'An error occured during the request. Unable to proceed.'))
		}
		console.error(state, error)
	},
}

export default new Vuex.Store({
	modules: {
		users,
		apps,
		settings,
		oc,
	},
	strict: debug,

	mutations,
})
