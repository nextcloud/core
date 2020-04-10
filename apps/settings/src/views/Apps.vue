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

<template>
	<Content app-name="settings"
		:class="{ 'with-app-sidebar': currentApp}"
		:content-class="{ 'icon-loading': loadingList }"
		:navigation-class="{ 'icon-loading': loading }">
		<AppNavigation>
			<ul id="appscategories">
				<AppNavigationItem
					id="app-category-your-apps"
					:to="{ name: 'apps' }"
					:exact="true"
					icon="icon-category-installed"
					:title="t('settings', 'Your apps')" />
				<AppNavigationItem
					id="app-category-enabled"
					:to="{ name: 'apps-category', params: { category: 'enabled' } }"
					icon="icon-category-enabled"
					:title="t('settings', 'Active apps')" />
				<AppNavigationItem
					id="app-category-disabled"
					:to="{ name: 'apps-category', params: { category: 'disabled' } }"
					icon="icon-category-disabled"
					:title="t('settings', 'Disabled apps')" />
				<AppNavigationItem
					v-if="updateCount > 0"
					id="app-category-updates"
					:to="{ name: 'apps-category', params: { category: 'updates' } }"
					icon="icon-download"
					:title="t('settings', 'Updates')">
					<AppNavigationCounter slot="counter">
						{{ updateCount }}
					</AppNavigationCounter>
				</AppNavigationItem>
				<AppNavigationItem
					id="app-category-your-bundles"
					:to="{ name: 'apps-category', params: { category: 'app-bundles' } }"
					icon="icon-category-app-bundles"
					:title="t('settings', 'App bundles')" />

				<AppNavigationSpacer />

				<!-- App store categories -->
				<template v-if="settings.appstoreEnabled">
					<AppNavigationItem
						id="app-category-featured"
						:to="{ name: 'apps-category', params: { category: 'featured' } }"
						icon="icon-favorite"
						:title="t('settings', 'Featured apps')" />

					<AppNavigationItem
						v-for="cat in categories"
						:key="'icon-category-' + cat.ident"
						:icon="'icon-category-' + cat.ident"
						:to="{
							name: 'apps-category',
							params: { category: cat.ident },
						}"
						:title="cat.displayName" />
				</template>

				<AppNavigationItem
					id="app-developer-docs"
					href="settings.developerDocumentation"
					:title="t('settings', 'Developer documentation') + ' ↗'" />
			</ul>
		</AppNavigation>
		<AppContent class="app-settings-content" :class="{ 'icon-loading': loadingList }">
			<AppList :category="category" :app="currentApp" :search="searchQuery" />
		</AppContent>
		<AppSidebar v-if="id && currentApp" @close="hideAppDetails">
			<AppDetails :category="category" :app="currentApp" />
		</AppSidebar>
	</Content>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationCounter from '@nextcloud/vue/dist/Components/AppNavigationCounter'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationSpacer from '@nextcloud/vue/dist/Components/AppNavigationSpacer'
import AppSidebar from '@nextcloud/vue/dist/Components/AppSidebar'
import Content from '@nextcloud/vue/dist/Components/Content'
import Vue from 'vue'
import VueLocalStorage from 'vue-localstorage'

import AppList from '../components/AppList'
import AppDetails from '../components/AppDetails'

Vue.use(VueLocalStorage)

export default {
	name: 'Apps',
	components: {
		AppContent,
		AppDetails,
		AppList,
		AppNavigation,
		AppNavigationCounter,
		AppNavigationItem,
		AppNavigationSpacer,
		AppSidebar,
		Content,
	},
	props: {
		category: {
			type: String,
			default: 'installed',
		},
		id: {
			type: String,
			default: '',
		},
	},
	data() {
		return {
			searchQuery: '',
		}
	},
	computed: {
		loading() {
			return this.$store.getters.loading('categories')
		},
		loadingList() {
			return this.$store.getters.loading('list')
		},
		currentApp() {
			return this.apps.find(app => app.id === this.id)
		},
		categories() {
			return this.$store.getters.getCategories
		},
		apps() {
			return this.$store.getters.getAllApps
		},
		updateCount() {
			return this.$store.getters.getUpdateCount
		},
		settings() {
			return this.$store.getters.getServerData
		},
	},
	watch: {
		category: function(val, old) {
			this.setSearch('')
		},
	},
	beforeMount() {
		this.$store.dispatch('getCategories')
		this.$store.dispatch('getAllApps')
		this.$store.dispatch('getGroups', { offset: 0, limit: 5 })
		this.$store.commit('setUpdateCount', this.$store.getters.getServerData.updateCount)
	},
	mounted() {
		/**
		 * Register search
		 */
		this.appSearch = new OCA.Search(this.setSearch, this.resetSearch)
	},
	methods: {
		setSearch(query) {
			this.searchQuery = query
		},
		resetSearch() {
			this.setSearch('')
		},
		hideAppDetails() {
			this.$router.push({
				name: 'apps-category',
				params: { category: this.category },
			})
		},
	},
}
</script>
