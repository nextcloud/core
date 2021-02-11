<template>
	<div>
		<p class="settings-hint">
			{{ t('settings', 'Two-factor authentication can be enforced for all users and specific groups. If they do not have a two-factor provider configured, they will be unable to log into the system.') }}
		</p>
		<p v-if="loading">
			<span class="icon-loading-small two-factor-loading" />
			<span>{{ t('settings', 'Enforce two-factor authentication') }}</span>
		</p>
		<p v-else>
			<input id="two-factor-enforced"
				v-model="enforced"
				type="checkbox"
				class="checkbox">
			<label for="two-factor-enforced">{{ t('settings', 'Enforce two-factor authentication') }}</label>
		</p>
		<template v-if="enforced">
			<h3>{{ t('settings', 'Limit to groups') }}</h3>
			{{ t('settings', 'Enforcement of two-factor authentication can be set for certain groups only.') }}
			<p>
				{{ t('settings', 'Two-factor authentication is enforced for all members of the following groups.') }}
			</p>
			<p>
				<Multiselect v-model="enforcedGroups"
					:options="groups"
					:placeholder="t('settings', 'Enforced groups')"
					:disabled="loading"
					:multiple="true"
					:searchable="true"
					:loading="loadingGroups"
					:show-no-options="false"
					:close-on-select="false"
					@search-change="searchGroup" />
			</p>
			<p>
				{{ t('settings', 'Two-factor authentication is not enforced for members of the following groups.') }}
			</p>
			<p>
				<Multiselect v-model="excludedGroups"
					:options="groups"
					:placeholder="t('settings', 'Excluded groups')"
					:disabled="loading"
					:multiple="true"
					:searchable="true"
					:loading="loadingGroups"
					:show-no-options="false"
					:close-on-select="false"
					@search-change="searchGroup" />
			</p>
			<p>
				<em>
					<!-- this text is also found in the documentation. update it there as well if it ever changes -->
					{{ t('settings', 'When groups are selected/excluded, they use the following logic to determine if a user has 2FA enforced: If no groups are selected, 2FA is enabled for everyone except members of the excluded groups. If groups are selected, 2FA is enabled for all members of these. If a user is both in a selected and excluded group, the selected takes precedence and 2FA is enforced.') }}
				</em>
			</p>
		</template>
		<p>
			<button v-if="dirty"
				class="button primary"
				:disabled="loading"
				@click="saveChanges">
				{{ t('settings', 'Save changes') }}
			</button>
		</p>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import _ from 'lodash'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'AdminTwoFactor',
	components: {
		Multiselect,
	},
	data() {
		return {
			loading: false,
			dirty: false,
			groups: [],
			loadingGroups: false,
		}
	},
	computed: {
		enforced: {
			get() {
				return this.$store.state.enforced
			},
			set(val) {
				this.dirty = true
				this.$store.commit('setEnforced', val)
			},
		},
		enforcedGroups: {
			get() {
				return this.$store.state.enforcedGroups
			},
			set(val) {
				this.dirty = true
				this.$store.commit('setEnforcedGroups', val)
			},
		},
		excludedGroups: {
			get() {
				return this.$store.state.excludedGroups
			},
			set(val) {
				this.dirty = true
				this.$store.commit('setExcludedGroups', val)
			},
		},
	},
	mounted() {
		// Groups are loaded dynamically, but the assigned ones *should*
		// be valid groups, so let's add them as initial state
		this.groups = _.sortedUniq(_.uniq(this.enforcedGroups.concat(this.excludedGroups)))

		// Populate the groups with a first set so the dropdown is not empty
		// when opening the page the first time
		this.searchGroup('')
	},
	methods: {
		searchGroup: _.debounce(function(query) {
			this.loadingGroups = true
			axios.get(generateOcsUrl(`cloud/groups?offset=0&search=${encodeURIComponent(query)}&limit=20`, 2))
				.then(res => res.data.ocs)
				.then(ocs => ocs.data.groups)
				.then(groups => { this.groups = _.sortedUniq(_.uniq(this.groups.concat(groups))) })
				.catch(err => console.error('could not search groups', err))
				.then(() => { this.loadingGroups = false })
		}, 500),

		saveChanges() {
			this.loading = true

			const data = {
				enforced: this.enforced,
				enforcedGroups: this.enforcedGroups,
				excludedGroups: this.excludedGroups,
			}
			axios.put(generateUrl('/settings/api/admin/twofactorauth'), data)
				.then(resp => resp.data)
				.then(state => {
					this.state = state
					this.dirty = false
				})
				.catch(err => {
					console.error('could not save changes', err)
				})
				.then(() => { this.loading = false })
		},
	},
}
</script>

<style>
	.two-factor-loading {
		display: inline-block;
		vertical-align: sub;
		margin-left: -2px;
		margin-right: 1px;
	}
</style>
