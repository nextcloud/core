<!--
  - @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Content app-name="settings" :navigation-class="{ 'icon-loading': loadingAddGroup }">
		<AppNavigation>
			<AppNavigationNew button-id="new-user-button"
				:text="t('settings','New user')"
				button-class="icon-add"
				@click="toggleNewUserMenu" />
			<template #list>
				<AppNavigationItem
					id="addgroup"
					ref="addGroup"
					:edit-placeholder="t('settings', 'Enter group name')"
					:editable="true"
					:loading="loadingAddGroup"
					:title="t('settings', 'Add group')"
					icon="icon-add"
					@click="toggleAddGroupEntry(true)"
					@update:title="createGroup" />
				<AppNavigationItem
					id="everyone"
					:exact="true"
					:title="t('settings', 'Everyone')"
					:to="{ name: 'users' }"
					icon="icon-contacts-dark">
					<AppNavigationCounter v-if="userCount > 0" slot="counter">
						{{ userCount }}
					</AppNavigationCounter>
				</AppNavigationItem>
				<AppNavigationItem
					v-if="settings.isAdmin"
					id="admin"
					:exact="true"
					:title="t('settings', 'Admins')"
					:to="{ name: 'group', params: { selectedGroup: 'admin' } }"
					icon="icon-user-admin">
					<AppNavigationCounter v-if="adminGroupMenu.count" slot="counter">
						{{ adminGroupMenu.count }}
					</AppNavigationCounter>
				</AppNavigationItem>

				<!-- Hide the disabled if none, if we don't have the data (-1) show it -->
				<AppNavigationItem
					v-if="disabledGroupMenu.usercount > 0 || disabledGroupMenu.usercount === -1"
					id="disabled"
					:exact="true"
					:title="t('settings', 'Disabled users')"
					:to="{ name: 'group', params: { selectedGroup: 'disabled' } }"
					icon="icon-disabled-users">
					<AppNavigationCounter v-if="disabledGroupMenu.usercount > 0" slot="counter">
						{{ disabledGroupMenu.usercount }}
					</AppNavigationCounter>
				</AppNavigationItem>

				<AppNavigationCaption v-if="groupList.length > 0" :title="t('settings', 'Groups')" />
				<AppNavigationItem
					v-for="group in groupList"
					:key="group.id"
					:exact="true"
					:title="group.title"
					:to="{ name: 'group', params: { selectedGroup: encodeURIComponent(group.id) } }">
					<AppNavigationCounter v-if="group.count" slot="counter">
						{{ group.count }}
					</AppNavigationCounter>
					<template slot="actions">
						<ActionButton
							v-if="group.id !== 'admin' && group.id !== 'disabled' && settings.isAdmin"
							icon="icon-delete"
							@click="removeGroup(group.id)">
							{{ t('settings', 'Remove group') }}
						</ActionButton>
					</template>
				</AppNavigationItem>
			</template>
			<template #footer>
				<AppNavigationSettings>
					<div>
						<p>{{ t('settings', 'Default quota:') }}</p>
						<Multiselect :value="defaultQuota"
							:options="quotaOptions"
							tag-placeholder="create"
							:placeholder="t('settings', 'Select default quota')"
							label="label"
							track-by="id"
							:allow-empty="false"
							:taggable="true"
							@tag="validateQuota"
							@input="setDefaultQuota" />
					</div>
					<div>
						<input id="showLanguages"
							v-model="showLanguages"
							type="checkbox"
							class="checkbox">
						<label for="showLanguages">{{ t('settings', 'Show Languages') }}</label>
					</div>
					<div>
						<input id="showLastLogin"
							v-model="showLastLogin"
							type="checkbox"
							class="checkbox">
						<label for="showLastLogin">{{ t('settings', 'Show last login') }}</label>
					</div>
					<div>
						<input id="showUserBackend"
							v-model="showUserBackend"
							type="checkbox"
							class="checkbox">
						<label for="showUserBackend">{{ t('settings', 'Show user backend') }}</label>
					</div>
					<div>
						<input id="showStoragePath"
							v-model="showStoragePath"
							type="checkbox"
							class="checkbox">
						<label for="showStoragePath">{{ t('settings', 'Show storage path') }}</label>
					</div>
					<div>
						<input id="sendWelcomeMail"
							v-model="sendWelcomeMail"
							:disabled="loadingSendMail"
							type="checkbox"
							class="checkbox">
						<label for="sendWelcomeMail">{{ t('settings', 'Send email to new user') }}</label>
					</div>
				</AppNavigationSettings>
			</template>
		</AppNavigation>
		<AppContent>
			<UserList slot="content"
				:users="users"
				:show-config="showConfig"
				:selected-group="selectedGroupDecoded"
				:external-actions="externalActions" />
		</AppContent>
	</Content>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import AppNavigationSettings from '@nextcloud/vue/dist/Components/AppNavigationSettings'
import axios from '@nextcloud/axios'
import Content from '@nextcloud/vue/dist/Components/Content'
import { generateUrl } from '@nextcloud/router'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Vue from 'vue'
import VueLocalStorage from 'vue-localstorage'

import UserList from '../components/UserList'

Vue.use(VueLocalStorage)

export default {
	name: 'Users',
	components: {
		ActionButton,
		AppContent,
		AppNavigation,
		AppNavigationCaption,
		AppNavigationCounter,
		AppNavigationItem,
		AppNavigationNew,
		AppNavigationSettings,
		Content,
		Multiselect,
		UserList,
	},
	props: {
		selectedGroup: {
			type: String,
			default: null,
		},
	},
	data() {
		return {
			// default quota is set to unlimited
			unlimitedQuota: { id: 'none', label: t('settings', 'Unlimited') },
			// temporary value used for multiselect change
			selectedQuota: false,
			externalActions: [],
			loadingAddGroup: false,
			loadingSendMail: false,
			showConfig: {
				showStoragePath: false,
				showUserBackend: false,
				showLastLogin: false,
				showNewUserForm: false,
				showLanguages: false,
			},
		}
	},
	computed: {
		selectedGroupDecoded() {
			return this.selectedGroup ? decodeURIComponent(this.selectedGroup) : null
		},
		users() {
			return this.$store.getters.getUsers
		},
		groups() {
			return this.$store.getters.getGroups
		},
		usersOffset() {
			return this.$store.getters.getUsersOffset
		},
		usersLimit() {
			return this.$store.getters.getUsersLimit
		},

		// Local settings
		showLanguages: {
			get() { return this.getLocalstorage('showLanguages') },
			set(status) {
				this.setLocalStorage('showLanguages', status)
			},
		},
		showLastLogin: {
			get() { return this.getLocalstorage('showLastLogin') },
			set(status) {
				this.setLocalStorage('showLastLogin', status)
			},
		},
		showUserBackend: {
			get() { return this.getLocalstorage('showUserBackend') },
			set(status) {
				this.setLocalStorage('showUserBackend', status)
			},
		},
		showStoragePath: {
			get() { return this.getLocalstorage('showStoragePath') },
			set(status) {
				this.setLocalStorage('showStoragePath', status)
			},
		},

		userCount() {
			return this.$store.getters.getUserCount
		},
		settings() {
			return this.$store.getters.getServerData
		},

		// default quota
		quotaOptions() {
			// convert the preset array into objects
			const quotaPreset = this.settings.quotaPreset.reduce((acc, cur) => acc.concat({ id: cur, label: cur }), [])
			// add default presets
			quotaPreset.unshift(this.unlimitedQuota)
			return quotaPreset
		},
		// mapping saved values to objects
		defaultQuota: {
			get() {
				if (this.selectedQuota !== false) {
					return this.selectedQuota
				}
				if (this.settings.defaultQuota !== this.unlimitedQuota.id && OC.Util.computerFileSize(this.settings.defaultQuota) >= 0) {
					// if value is valid, let's map the quotaOptions or return custom quota
					return { id: this.settings.defaultQuota, label: this.settings.defaultQuota }
				}
				return this.unlimitedQuota // unlimited
			},
			set(quota) {
				this.selectedQuota = quota
			},

		},

		sendWelcomeMail: {
			get() {
				return this.settings.newUserSendEmail
			},
			async set(value) {
				try {
					this.loadingSendMail = true
					this.$store.commit('setServerData', {
						...this.settings,
						newUserSendEmail: value,
					})
					await axios.post(generateUrl('/settings/users/preferences/newUser.sendEmail'), { value: value ? 'yes' : 'no' })
				} catch (e) {
					console.error('could not update newUser.sendEmail preference: ' + e.message, e)
				} finally {
					this.loadingSendMail = false
				}
			},
		},

		groupList() {
			const groups = Array.isArray(this.groups) ? this.groups : []

			return groups
				// filter out disabled and admin
				.filter(group => group.id !== 'disabled' && group.id !== 'admin')
				.map(group => this.formatGroupMenu(group))
		},

		adminGroupMenu() {
			return this.formatGroupMenu(this.groups.find(group => group.id === 'admin'))
		},
		disabledGroupMenu() {
			return this.formatGroupMenu(this.groups.find(group => group.id === 'disabled'))
		},
	},
	beforeMount() {
		this.$store.commit('initGroups', {
			groups: this.$store.getters.getServerData.groups,
			orderBy: this.$store.getters.getServerData.sortGroups,
			userCount: this.$store.getters.getServerData.userCount,
		})
		this.$store.dispatch('getPasswordPolicyMinLength')
	},
	created() {
		// init the OCA.Settings.UserList object
		// and add the registerAction method
		Object.assign(OCA, {
			Settings: {
				UserList: {
					registerAction: this.registerAction,
				},
			},
		})
	},
	methods: {
		toggleNewUserMenu() {
			this.showConfig.showNewUserForm = !this.showConfig.showNewUserForm
			if (this.showConfig.showNewUserForm) {
				Vue.nextTick(() => {
					window.newusername.focus()
				})
			}
		},
		getLocalstorage(key) {
			// force initialization
			const localConfig = this.$localStorage.get(key)
			// if localstorage is null, fallback to original values
			this.showConfig[key] = localConfig !== null ? localConfig === 'true' : this.showConfig[key]
			return this.showConfig[key]
		},
		setLocalStorage(key, status) {
			this.showConfig[key] = status
			this.$localStorage.set(key, status)
			return status
		},
		removeGroup(groupid) {
			const self = this
			// TODO migrate to a vue js confirm dialog component
			OC.dialogs.confirm(
				t('settings', 'You are about to remove the group {group}. The users will NOT be deleted.', { group: groupid }),
				t('settings', 'Please confirm the group removal '),
				function(success) {
					if (success) {
						self.$store.dispatch('removeGroup', groupid)
					}
				}
			)
		},

		/**
		 * Dispatch default quota set request
		 *
		 * @param {string|Object} quota Quota in readable format '5 GB' or Object {id: '5 GB', label: '5GB'}
		 */
		setDefaultQuota(quota = 'none') {
			this.$store.dispatch('setAppConfig', {
				app: 'files',
				key: 'default_quota',
				// ensure we only send the preset id
				value: quota.id ? quota.id : quota,
			}).then(() => {
				if (typeof quota !== 'object') {
					quota = { id: quota, label: quota }
				}
				this.defaultQuota = quota
			})
		},

		/**
		 * Validate quota string to make sure it's a valid human file size
		 *
		 * @param {string} quota Quota in readable format '5 GB'
		 * @returns {Promise|boolean}
		 */
		validateQuota(quota) {
			// only used for new presets sent through @Tag
			const validQuota = OC.Util.computerFileSize(quota)
			if (validQuota === null) {
				return this.setDefaultQuota('none')
			} else {
				// unify format output
				return this.setDefaultQuota(OC.Util.humanFileSize(OC.Util.computerFileSize(quota)))
			}
		},

		/**
		 * Register a new action for the user menu
		 *
		 * @param {string} icon the icon class
		 * @param {string} text the text to display
		 * @param {Function} action the function to run
		 * @returns {Array}
		 */
		registerAction(icon, text, action) {
			this.externalActions.push({
				icon,
				text,
				action,
			})
			return this.externalActions
		},

		/**
		 * Create a new group
		 *
		 * @param {string} gid The group id
		 */
		async createGroup(gid) {
			// group is not valid
			if (gid.trim() === '') {
				Vue.nextTick(() => {
					this.toggleAddGroupEntry(true)
				})
				return
			}

			try {
				this.loadingAddGroup = true
				await this.$store.dispatch('addGroup', gid.trim())

				this.toggleAddGroupEntry(false)
				this.$router.push({
					name: 'group',
					params: {
						selectedGroup: encodeURIComponent(gid.trim()),
					},
				})
			} catch {
				this.toggleAddGroupEntry(true)
			} finally {
				this.loadingAddGroup = false
			}
		},

		/**
		 * Toggle the add group entry editing state
		 * @param {boolean} [state] set state instead of toggling
		 */
		toggleAddGroupEntry(state) {
			if (state === undefined) {
				state = !this.$refs.addGroup.editing
			}
			this.$refs.addGroup.editing = state

			// focus input
			Vue.nextTick(() => {
				if (this.$refs.addGroup.$el) {
					const input = this.$refs.addGroup.$el.querySelector('form > input[type="text"]')
					if (input) {
						input.focus()
					}
				}
			})
		},

		/**
		 * Format a group to a menu entry
		 * @param {Object} group the group
		 * @returns {Object}
		 */
		formatGroupMenu(group) {
			const item = {}
			if (typeof group === 'undefined') {
				return {}
			}

			item.id = group.id
			item.title = group.name
			item.usercount = group.usercount

			// users count for all groups
			if (group.usercount - group.disabled > 0) {
				item.count = group.usercount - group.disabled
			}

			return item
		},
	},
}
</script>

<style lang="scss" scoped>
// force hiding the editing action for the add group entry
.app-navigation__list #addgroup::v-deep .app-navigation-entry__utils {
	display: none;
}
</style>
