/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

import api from './api'

const state = {}
const mutations = {}
const getters = {}
const actions = {
	/**
     * Set application config in database
     *
	 * @param {Object} context store context
     * @param {Object} options destructuring object
	 * @param {string} options.app Application name
	 * @param {boolean} options.key Config key
	 * @param {boolean} options.value Value to set
	 * @returns{Promise}
	 */
	setAppConfig(context, { app, key, value }) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`apps/provisioning_api/api/v1/config/apps/${app}/${key}`, 2), { value: value })
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { app, key, value, error }))
	},
}

export default { state, mutations, getters, actions }
