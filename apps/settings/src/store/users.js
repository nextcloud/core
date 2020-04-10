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

const orderGroups = function(groups, orderBy) {
	/* const SORT_USERCOUNT = 1;
	 * const SORT_GROUPNAME = 2;
	 * https://github.com/nextcloud/server/blob/208e38e84e1a07a49699aa90dc5b7272d24489f0/lib/private/Group/MetaData.php#L34
	 */
	if (orderBy === 1) {
		return groups.sort((a, b) => a.usercount - a.disabled < b.usercount - b.disabled)
	} else {
		return groups.sort((a, b) => a.name.localeCompare(b.name))
	}
}

const defaults = {
	group: {
		id: '',
		name: '',
		usercount: 0,
		disabled: 0,
		canAdd: true,
		canRemove: true,
	},
}

const state = {
	users: [],
	groups: [],
	orderBy: 1,
	minPasswordLength: 0,
	usersOffset: 0,
	usersLimit: 25,
	userCount: 0,
}

const mutations = {
	appendUsers(state, usersObj) {
		// convert obj to array
		const users = state.users.concat(Object.keys(usersObj).map(userid => usersObj[userid]))
		state.usersOffset += state.usersLimit
		state.users = users
	},
	setPasswordPolicyMinLength(state, length) {
		state.minPasswordLength = length !== '' ? length : 0
	},
	initGroups(state, { groups, orderBy, userCount }) {
		state.groups = groups.map(group => Object.assign({}, defaults.group, group))
		state.orderBy = orderBy
		state.userCount = userCount
		state.groups = orderGroups(state.groups, state.orderBy)

	},
	addGroup(state, { gid, displayName }) {
		try {
			if (typeof state.groups.find((group) => group.id === gid) !== 'undefined') {
				return
			}
			// extend group to default values
			const group = Object.assign({}, defaults.group, {
				id: gid,
				name: displayName,
			})
			state.groups.push(group)
			state.groups = orderGroups(state.groups, state.orderBy)
		} catch (e) {
			console.error('Can\'t create group', e)
		}
	},
	removeGroup(state, gid) {
		const groupIndex = state.groups.findIndex(groupSearch => groupSearch.id === gid)
		if (groupIndex >= 0) {
			state.groups.splice(groupIndex, 1)
		}
	},
	addUserGroup(state, { userid, gid }) {
		const group = state.groups.find(groupSearch => groupSearch.id === gid)
		const user = state.users.find(user => user.id === userid)
		// increase count if user is enabled
		if (group && user.enabled && state.userCount > 0) {
			group.usercount++
		}
		const groups = user.groups
		groups.push(gid)
		state.groups = orderGroups(state.groups, state.orderBy)
	},
	removeUserGroup(state, { userid, gid }) {
		const group = state.groups.find(groupSearch => groupSearch.id === gid)
		const user = state.users.find(user => user.id === userid)
		// lower count if user is enabled
		if (group && user.enabled && state.userCount > 0) {
			group.usercount--
		}
		const groups = user.groups
		groups.splice(groups.indexOf(gid), 1)
		state.groups = orderGroups(state.groups, state.orderBy)
	},
	addUserSubAdmin(state, { userid, gid }) {
		const groups = state.users.find(user => user.id === userid).subadmin
		groups.push(gid)
	},
	removeUserSubAdmin(state, { userid, gid }) {
		const groups = state.users.find(user => user.id === userid).subadmin
		groups.splice(groups.indexOf(gid), 1)
	},
	deleteUser(state, userid) {
		const userIndex = state.users.findIndex(user => user.id === userid)
		state.users.splice(userIndex, 1)
	},
	addUserData(state, response) {
		state.users.push(response.data.ocs.data)
	},
	enableDisableUser(state, { userid, enabled }) {
		const user = state.users.find(user => user.id === userid)
		user.enabled = enabled
		// increment or not
		if (state.userCount > 0) {
			state.groups.find(group => group.id === 'disabled').usercount += enabled ? -1 : 1
			state.userCount += enabled ? 1 : -1
			user.groups.forEach(group => {
				// Increment disabled count
				state.groups.find(groupSearch => groupSearch.id === group).disabled += enabled ? -1 : 1
			})
		}
	},
	setUserData(state, { userid, key, value }) {
		if (key === 'quota') {
			const humanValue = OC.Util.computerFileSize(value)
			state.users.find(user => user.id === userid)[key][key] = humanValue !== null ? humanValue : value
		} else {
			state.users.find(user => user.id === userid)[key] = value
		}
	},

	/**
	 * Reset users list
	 * @param {Object} state the store state
	 */
	resetUsers(state) {
		state.users = []
		state.usersOffset = 0
	},
}

const getters = {
	getUsers(state) {
		return state.users
	},
	getGroups(state) {
		return state.groups
	},
	getSubadminGroups(state) {
		// Can't be subadmin of admin or disabled
		return state.groups.filter(group => group.id !== 'admin' && group.id !== 'disabled')
	},
	getPasswordPolicyMinLength(state) {
		return state.minPasswordLength
	},
	getUsersOffset(state) {
		return state.usersOffset
	},
	getUsersLimit(state) {
		return state.usersLimit
	},
	getUserCount(state) {
		return state.userCount
	},
}

const actions = {

	/**
	 * Get all users with full details
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {int} options.offset List offset to request
	 * @param {int} options.limit List number to return from offset
	 * @param {string} options.search Search amongst users
	 * @param {string} options.group Get users from group
	 * @returns {Promise}
	 */
	getUsers(context, { offset, limit, search, group }) {
		search = typeof search === 'string' ? search : ''
		group = typeof group === 'string' ? group : ''
		if (group !== '') {
			return api.get(OC.linkToOCS(`cloud/groups/${encodeURIComponent(group)}/users/details?offset=${offset}&limit=${limit}&search=${search}`, 2))
				.then((response) => {
					if (Object.keys(response.data.ocs.data.users).length > 0) {
						context.commit('appendUsers', response.data.ocs.data.users)
						return true
					}
					return false
				})
				.catch((error) => context.commit('API_FAILURE', error))
		}

		return api.get(OC.linkToOCS(`cloud/users/details?offset=${offset}&limit=${limit}&search=${search}`, 2))
			.then((response) => {
				if (Object.keys(response.data.ocs.data.users).length > 0) {
					context.commit('appendUsers', response.data.ocs.data.users)
					return true
				}
				return false
			})
			.catch((error) => context.commit('API_FAILURE', error))
	},

	getGroups(context, { offset, limit, search }) {
		search = typeof search === 'string' ? search : ''
		const limitParam = limit === -1 ? '' : `&limit=${limit}`
		return api.get(OC.linkToOCS(`cloud/groups?offset=${offset}&search=${search}${limitParam}`, 2))
			.then((response) => {
				if (Object.keys(response.data.ocs.data.groups).length > 0) {
					response.data.ocs.data.groups.forEach(function(group) {
						context.commit('addGroup', { gid: group, displayName: group })
					})
					return true
				}
				return false
			})
			.catch((error) => context.commit('API_FAILURE', error))
	},

	/**
	 * Get all users with full details
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {int} options.offset List offset to request
	 * @param {int} options.limit List number to return from offset
	 * @returns {Promise}
	 */
	getUsersFromList(context, { offset, limit, search }) {
		search = typeof search === 'string' ? search : ''
		return api.get(OC.linkToOCS(`cloud/users/details?offset=${offset}&limit=${limit}&search=${search}`, 2))
			.then((response) => {
				if (Object.keys(response.data.ocs.data.users).length > 0) {
					context.commit('appendUsers', response.data.ocs.data.users)
					return true
				}
				return false
			})
			.catch((error) => context.commit('API_FAILURE', error))
	},

	/**
	 * Get all users with full details from a groupid
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {int} options.offset List offset to request
	 * @param {int} options.limit List number to return from offset
	 * @returns {Promise}
	 */
	getUsersFromGroup(context, { groupid, offset, limit }) {
		return api.get(OC.linkToOCS(`cloud/users/${encodeURIComponent(groupid)}/details?offset=${offset}&limit=${limit}`, 2))
			.then((response) => context.commit('getUsersFromList', response.data.ocs.data.users))
			.catch((error) => context.commit('API_FAILURE', error))
	},

	getPasswordPolicyMinLength(context) {
		if (OC.getCapabilities().password_policy && OC.getCapabilities().password_policy.minLength) {
			context.commit('setPasswordPolicyMinLength', OC.getCapabilities().password_policy.minLength)
			return OC.getCapabilities().password_policy.minLength
		}
		return false
	},

	/**
	 * Add group
	 *
	 * @param {Object} context store context
	 * @param {string} gid Group id
	 * @returns {Promise}
	 */
	addGroup(context, gid) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/groups`, 2), { groupid: gid })
				.then((response) => {
					context.commit('addGroup', { gid: gid, displayName: gid })
					return { gid: gid, displayName: gid }
				})
				.catch((error) => { throw error })
		}).catch((error) => {
			context.commit('API_FAILURE', { gid, error })
			// let's throw one more time to prevent the view
			// from adding the user to a group that doesn't exists
			throw error
		})
	},

	/**
	 * Remove group
	 *
	 * @param {Object} context store context
	 * @param {string} gid Group id
	 * @returns {Promise}
	 */
	removeGroup(context, gid) {
		return api.requireAdmin().then((response) => {
			return api.delete(OC.linkToOCS(`cloud/groups/${encodeURIComponent(gid)}`, 2))
				.then((response) => context.commit('removeGroup', gid))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { gid, error }))
	},

	/**
	 * Add user to group
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.gid Group id
	 * @returns {Promise}
	 */
	addUserGroup(context, { userid, gid }) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/users/${userid}/groups`, 2), { groupid: gid })
				.then((response) => context.commit('addUserGroup', { userid, gid }))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Remove user from group
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.gid Group id
	 * @returns {Promise}
	 */
	removeUserGroup(context, { userid, gid }) {
		return api.requireAdmin().then((response) => {
			return api.delete(OC.linkToOCS(`cloud/users/${userid}/groups`, 2), { groupid: gid })
				.then((response) => context.commit('removeUserGroup', { userid, gid }))
				.catch((error) => { throw error })
		}).catch((error) => {
			context.commit('API_FAILURE', { userid, error })
			// let's throw one more time to prevent
			// the view from removing the user row on failure
			throw error
		})
	},

	/**
	 * Add user to group admin
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.gid Group id
	 * @returns {Promise}
	 */
	addUserSubAdmin(context, { userid, gid }) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/users/${userid}/subadmins`, 2), { groupid: gid })
				.then((response) => context.commit('addUserSubAdmin', { userid, gid }))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Remove user from group admin
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.gid Group id
	 * @returns {Promise}
	 */
	removeUserSubAdmin(context, { userid, gid }) {
		return api.requireAdmin().then((response) => {
			return api.delete(OC.linkToOCS(`cloud/users/${userid}/subadmins`, 2), { groupid: gid })
				.then((response) => context.commit('removeUserSubAdmin', { userid, gid }))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Mark all user devices for remote wipe
	 *
	 * @param {Object} context store context
	 * @param {string} userid User id
	 * @returns {Promise}
	 */
	wipeUserDevices(context, userid) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/users/${userid}/wipe`, 2))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Delete a user
	 *
	 * @param {Object} context store context
	 * @param {string} userid User id
	 * @returns {Promise}
	 */
	deleteUser(context, userid) {
		return api.requireAdmin().then((response) => {
			return api.delete(OC.linkToOCS(`cloud/users/${userid}`, 2))
				.then((response) => context.commit('deleteUser', userid))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Add a user
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.password User password
	 * @param {string} options.displayName User display name
	 * @param {string} options.email User email
	 * @param {string} options.groups User groups
	 * @param {string} options.subadmin User subadmin groups
	 * @param {string} options.quota User email
	 * @returns {Promise}
	 */
	addUser({ commit, dispatch }, { userid, password, displayName, email, groups, subadmin, quota, language }) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/users`, 2), { userid, password, displayName, email, groups, subadmin, quota, language })
				.then((response) => dispatch('addUserData', userid || response.data.ocs.data.id))
				.catch((error) => { throw error })
		}).catch((error) => {
			commit('API_FAILURE', { userid, error })
			throw error
		})
	},

	/**
	 * Get user data and commit addition
	 *
	 * @param {Object} context store context
	 * @param {string} userid User id
	 * @returns {Promise}
	 */
	addUserData(context, userid) {
		return api.requireAdmin().then((response) => {
			return api.get(OC.linkToOCS(`cloud/users/${userid}`, 2))
				.then((response) => context.commit('addUserData', response))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/** Enable or disable user
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {boolean} options.enabled User enablement status
	 * @returns {Promise}
	 */
	enableDisableUser(context, { userid, enabled = true }) {
		const userStatus = enabled ? 'enable' : 'disable'
		return api.requireAdmin().then((response) => {
			return api.put(OC.linkToOCS(`cloud/users/${userid}/${userStatus}`, 2))
				.then((response) => context.commit('enableDisableUser', { userid, enabled }))
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},

	/**
	 * Edit user data
	 *
	 * @param {Object} context store context
	 * @param {Object} options destructuring object
	 * @param {string} options.userid User id
	 * @param {string} options.key User field to edit
	 * @param {string} options.value Value of the change
	 * @returns {Promise}
	 */
	setUserData(context, { userid, key, value }) {
		const allowedEmpty = ['email', 'displayname']
		if (['email', 'language', 'quota', 'displayname', 'password'].indexOf(key) !== -1) {
			// We allow empty email or displayname
			if (typeof value === 'string'
				&& (
					(allowedEmpty.indexOf(key) === -1 && value.length > 0)
					|| allowedEmpty.indexOf(key) !== -1
				)
			) {
				return api.requireAdmin().then((response) => {
					return api.put(OC.linkToOCS(`cloud/users/${userid}`, 2), { key: key, value: value })
						.then((response) => context.commit('setUserData', { userid, key, value }))
						.catch((error) => { throw error })
				}).catch((error) => context.commit('API_FAILURE', { userid, error }))
			}
		}
		return Promise.reject(new Error('Invalid request data'))
	},

	/**
	 * Send welcome mail
	 *
	 * @param {Object} context store context
	 * @param {string} userid User id
	 * @returns {Promise}
	 */
	sendWelcomeMail(context, userid) {
		return api.requireAdmin().then((response) => {
			return api.post(OC.linkToOCS(`cloud/users/${userid}/welcome`, 2))
				.then(response => true)
				.catch((error) => { throw error })
		}).catch((error) => context.commit('API_FAILURE', { userid, error }))
	},
}

export default { state, mutations, getters, actions }
