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
	<ul id="sharing-inherited-shares">
		<!-- Main collapsible entry -->
		<SharingEntrySimple
			class="sharing-entry__inherited"
			:title="mainTitle"
			:subtitle="subTitle">
			<template #avatar>
				<div class="avatar-shared icon-more-white" />
			</template>
			<ActionButton :icon="showInheritedSharesIcon" @click.prevent.stop="toggleInheritedShares">
				{{ toggleTooltip }}
			</ActionButton>
		</SharingEntrySimple>

		<!-- Inherited shares list -->
		<SharingEntryInherited v-for="share in shares"
			:key="share.id"
			:share="share" />
	</ul>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import axios from '@nextcloud/axios'

import Share from '../models/Share'
import SharingEntryInherited from '../components/SharingEntryInherited'
import SharingEntrySimple from '../components/SharingEntrySimple'

export default {
	name: 'SharingInherited',

	components: {
		ActionButton,
		SharingEntryInherited,
		SharingEntrySimple,
	},

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},

	data() {
		return {
			loaded: false,
			loading: false,
			showInheritedShares: false,
			shares: [],
		}
	},
	computed: {
		showInheritedSharesIcon() {
			if (this.loading) {
				return 'icon-loading-small'
			}
			if (this.showInheritedShares) {
				return 'icon-triangle-n'
			}
			return 'icon-triangle-s'
		},
		mainTitle() {
			return t('files_sharing', 'Others with access')
		},
		subTitle() {
			return (this.showInheritedShares && this.shares.length === 0)
				? t('files_sharing', 'No other users with access found')
				: ''
		},
		toggleTooltip() {
			return this.fileInfo.type === 'dir'
				? t('files_sharing', 'Toggle list of others with access to this directory')
				: t('files_sharing', 'Toggle list of others with access to this file')
		},
		fullPath() {
			const path = `${this.fileInfo.path}/${this.fileInfo.name}`
			return path.replace('//', '/')
		},
	},
	watch: {
		fileInfo() {
			this.resetState()
		},
	},
	methods: {
		/**
		 * Toggle the list view and fetch/reset the state
		 */
		toggleInheritedShares() {
			this.showInheritedShares = !this.showInheritedShares
			if (this.showInheritedShares) {
				this.fetchInheritedShares()
			} else {
				this.resetState()
			}
		},
		/**
		 * Fetch the Inherited Shares array
		 */
		async fetchInheritedShares() {
			this.loading = true
			try {
				const url = generateOcsUrl(`apps/files_sharing/api/v1/shares/inherited?format=json&path=${this.fullPath}`, 2)
				const shares = await axios.get(url.replace(/\/$/, ''))
				this.shares = shares.data.ocs.data
					.map(share => new Share(share))
					.sort((a, b) => b.createdTime - a.createdTime)
				console.info(this.shares)
				this.loaded = true
			} catch (error) {
				OC.Notification.showTemporary(t('files_sharing', 'Unable to fetch inherited shares'), { type: 'error' })
			} finally {
				this.loading = false
			}
		},
		/**
		 * Reset current component state
		 */
		resetState() {
			this.loaded = false
			this.loading = false
			this.showInheritedShares = false
			this.shares = []
		},
	},
}
</script>

<style lang="scss" scoped>
.sharing-entry__inherited {
	.avatar-shared {
		width: 32px;
		height: 32px;
		line-height: 32px;
		font-size: 18px;
		background-color: var(--color-text-maxcontrast);
		border-radius: 50%;
		flex-shrink: 0;
	}
}
</style>
