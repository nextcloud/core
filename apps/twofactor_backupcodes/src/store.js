/**
 * @copyright 2019 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author 2019 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

import Vue from 'vue'
import Vuex from 'vuex'
import { generateCodes } from './service/BackupCodesService'

Vue.use(Vuex)

const state = {
	enabled: false,
	total: 0,
	used: 0,
	codes: [],
}

const mutations = {
	setEnabled(state, enabled) {
		Vue.set(state, 'enabled', enabled)
	},
	setTotal(state, total) {
		Vue.set(state, 'total', total)
	},
	setUsed(state, used) {
		Vue.set(state, 'used', used)
	},
	setCodes(state, codes) {
		Vue.set(state, 'codes', codes)
	},
}

const actions = {
	generate({ commit }) {
		commit('setEnabled', false)

		return generateCodes().then(({ codes, state }) => {
			commit('setEnabled', state.enabled)
			commit('setTotal', state.total)
			commit('setUsed', state.used)
			commit('setCodes', codes)
			return true
		})
	},
}

export default new Vuex.Store({
	strict: process.env.NODE_ENV !== 'production',
	state,
	mutations,
	actions,
})
