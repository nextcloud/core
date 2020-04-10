<!--
  - @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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

<script>
export default {
	computed: {
		appGroups() {
			return this.app.groups.map(group => { return { id: group, name: group } })
		},
		loading() {
			const self = this
			return function(id) {
				return self.$store.getters.loading(id)
			}
		},
		installing() {
			return this.$store.getters.loading('install')
		},
		enableButtonText() {
			if (this.app.needsDownload) {
				return t('settings', 'Download and enable')
			}
			return t('settings', 'Enable')
		},
		forceEnableButtonText() {
			if (this.app.needsDownload) {
				return t('settings', 'Enable untested app')
			}
			return t('settings', 'Enable untested app')
		},
		enableButtonTooltip() {
			if (this.app.needsDownload) {
				return t('settings', 'The app will be downloaded from the app store')
			}
			return false
		},
		forceEnableButtonTooltip() {
			const base = t('settings', 'This app is not marked as compatible with your Nextcloud version. If you continue you will still be able to install the app. Note that the app might not work as expected.')
			if (this.app.needsDownload) {
				return base + ' ' + t('settings', 'The app will be downloaded from the app store')
			}
			return base
		},
	},
	mounted() {
		if (this.app.groups.length > 0) {
			this.groupCheckedAppsData = true
		}
	},
	methods: {
		asyncFindGroup(query) {
			return this.$store.dispatch('getGroups', { search: query, limit: 5, offset: 0 })
		},
		isLimitedToGroups(app) {
			if (this.app.groups.length || this.groupCheckedAppsData) {
				return true
			}
			return false
		},
		setGroupLimit: function() {
			if (!this.groupCheckedAppsData) {
				this.$store.dispatch('enableApp', { appId: this.app.id, groups: [] })
			}
		},
		canLimitToGroups(app) {
			if ((app.types && app.types.includes('filesystem'))
					|| app.types.includes('prelogin')
					|| app.types.includes('authentication')
					|| app.types.includes('logging')
					|| app.types.includes('prevent_group_restriction')) {
				return false
			}
			return true
		},
		addGroupLimitation(group) {
			const groups = this.app.groups.concat([]).concat([group.id])
			this.$store.dispatch('enableApp', { appId: this.app.id, groups: groups })
		},
		removeGroupLimitation(group) {
			const currentGroups = this.app.groups.concat([])
			const index = currentGroups.indexOf(group.id)
			if (index > -1) {
				currentGroups.splice(index, 1)
			}
			this.$store.dispatch('enableApp', { appId: this.app.id, groups: currentGroups })
		},
		forceEnable(appId) {
			this.$store.dispatch('forceEnableApp', { appId: appId, groups: [] })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
		enable(appId) {
			this.$store.dispatch('enableApp', { appId: appId, groups: [] })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
		disable(appId) {
			this.$store.dispatch('disableApp', { appId: appId })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
		remove(appId) {
			this.$store.dispatch('uninstallApp', { appId: appId })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
		install(appId) {
			this.$store.dispatch('enableApp', { appId: appId })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
		update(appId) {
			this.$store.dispatch('updateApp', { appId: appId })
				.then((response) => { OC.Settings.Apps.rebuildNavigation() })
				.catch((error) => { OC.Notification.show(error) })
		},
	},
}
</script>
