
<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
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
	<AppSidebarTab
		:id="id"
		ref="tab"
		:name="name"
		:icon="icon"
		@bottomReached="onScrollBottomReached">
		<!-- Fallback loading -->
		<EmptyContent v-if="loading" icon="icon-loading" />

		<!-- Using a dummy div as Vue mount replace the element directly
			It does NOT append to the content -->
		<div ref="mount" />
	</AppSidebarTab>
</template>

<script>
import AppSidebarTab from '@nextcloud/vue/dist/Components/AppSidebarTab'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

export default {
	name: 'SidebarTab',

	components: {
		AppSidebarTab,
		EmptyContent,
	},

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		icon: {
			type: String,
			required: true,
		},

		/**
		 * Lifecycle methods.
		 * They are prefixed with `on` to avoid conflict with Vue
		 * methods like this.destroy
		 */
		onMount: {
			type: Function,
			required: true,
		},
		onUpdate: {
			type: Function,
			required: true,
		},
		onDestroy: {
			type: Function,
			required: true,
		},
		onScrollBottomReached: {
			type: Function,
			default: () => {},
		},
	},

	data() {
		return {
			loading: true,
		}
	},

	computed: {
		// TODO: implement a better way to force pass a prop from Sidebar
		activeTab() {
			return this.$parent.activeTab
		},
	},

	watch: {
		async fileInfo(newFile, oldFile) {
			// Update fileInfo on change
			if (newFile.id !== oldFile.id) {
				this.loading = true
				await this.onUpdate(this.fileInfo)
				this.loading = false
			}
		},
	},

	async mounted() {
		this.loading = true
		// Mount the tab:  mounting point,   fileInfo,      vue context
		await this.onMount(this.$refs.mount, this.fileInfo, this.$refs.tab)
		this.loading = false
	},

	async beforeDestroy() {
		// unmount the tab
		await this.onDestroy()
	},
}
</script>
