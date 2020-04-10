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
	<Tab :id="id"
		:icon="icon"
		:name="name"
		:class="{ 'icon-loading': loading }">
		<!-- error message -->
		<div v-if="error" class="emptycontent">
			<div class="icon icon-error" />
			<h2>{{ error }}</h2>
		</div>

		<!-- shares content -->
		<template v-else>
			<!-- shared with me information -->
			<SharingEntrySimple v-if="isSharedWithMe" v-bind="sharedWithMe" class="sharing-entry__reshare">
				<template #avatar>
					<Avatar
						:user="sharedWithMe.user"
						:display-name="sharedWithMe.displayName"
						class="sharing-entry__avatar"
						tooltip-message="" />
				</template>
			</SharingEntrySimple>

			<!-- add new share input -->
			<SharingInput v-if="!loading"
				:can-reshare="canReshare"
				:file-info="fileInfo"
				:link-shares="linkShares"
				:reshare="reshare"
				:shares="shares"
				@add:share="addShare" />

			<!-- link shares list -->
			<SharingLinkList v-if="!loading"
				:can-reshare="canReshare"
				:file-info="fileInfo"
				:shares="linkShares" />

			<!-- other shares list -->
			<SharingList v-if="!loading"
				:shares="shares"
				:file-info="fileInfo" />

			<!-- inherited shares -->
			<SharingInherited v-if="canReshare && !loading" :file-info="fileInfo" />

			<!-- internal link copy -->
			<SharingEntryInternal :file-info="fileInfo" />

			<!-- projects -->
			<CollectionList v-if="fileInfo"
				:id="`${fileInfo.id}`"
				type="file"
				:name="fileInfo.name" />

			<!-- additionnal entries, use it with cautious -->
			<div v-for="(section, index) in sections"
				:ref="'section-' + index"
				:key="index"
				class="sharingTab__additionalContent">
				<component :is="section($refs['section-'+index], fileInfo)" :file-info="fileInfo" />
			</div>
		</template>
	</Tab>
</template>

<script>
import { CollectionList } from 'nextcloud-vue-collections'
import { generateOcsUrl } from '@nextcloud/router'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import axios from '@nextcloud/axios'
import Tab from '@nextcloud/vue/dist/Components/AppSidebarTab'

import { shareWithTitle } from '../utils/SharedWithMe'
import Share from '../models/Share'
import ShareTypes from '../mixins/ShareTypes'
import SharingEntryInternal from '../components/SharingEntryInternal'
import SharingEntrySimple from '../components/SharingEntrySimple'
import SharingInput from '../components/SharingInput'

import SharingInherited from './SharingInherited'
import SharingLinkList from './SharingLinkList'
import SharingList from './SharingList'

export default {
	name: 'SharingTab',

	components: {
		Avatar,
		CollectionList,
		SharingEntryInternal,
		SharingEntrySimple,
		SharingInherited,
		SharingInput,
		SharingLinkList,
		SharingList,
		Tab,
	},

	mixins: [ShareTypes],

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
	},

	data() {
		return {
			error: '',
			expirationInterval: null,
			icon: 'icon-share',
			loading: true,
			name: t('files_sharing', 'Sharing'),
			// reshare Share object
			reshare: null,
			sharedWithMe: {},
			shares: [],
			linkShares: [],
			sections: OCA.Sharing.ShareTabSections.getSections(),
		}
	},

	computed: {
		/**
		 * Needed to differenciate the tabs
		 * pulled from the AppSidebarTab component
		 *
		 * @returns {string}
		 */
		id() {
			return 'sharing'
		},

		/**
		 * Returns the current active tab
		 * needed because AppSidebarTab also uses $parent.activeTab
		 *
		 * @returns {string}
		 */
		activeTab() {
			return this.$parent.activeTab
		},

		/**
		 * Is this share shared with me?
		 *
		 * @returns {boolean}
		 */
		isSharedWithMe() {
			return Object.keys(this.sharedWithMe).length > 0
		},

		canReshare() {
			return !!(this.fileInfo.permissions & OC.PERMISSION_SHARE)
				|| !!(this.reshare && this.reshare.hasSharePermission)
		},
	},

	watch: {
		fileInfo() {
			this.resetState()
			this.getShares()
		},
	},

	beforeMount() {
		this.getShares()
	},

	methods: {
		/**
		 * Get the existing shares infos
		 */
		async getShares() {
			try {
				this.loading = true

				// init params
				const shareUrl = generateOcsUrl('apps/files_sharing/api/v1', 2) + 'shares'
				const format = 'json'
				// TODO: replace with proper getFUllpath implementation of our own FileInfo model
				const path = (this.fileInfo.path + '/' + this.fileInfo.name).replace('//', '/')

				// fetch shares
				const fetchShares = axios.get(shareUrl, {
					params: {
						format,
						path,
						reshares: true,
					},
				})
				const fetchSharedWithMe = axios.get(shareUrl, {
					params: {
						format,
						path,
						shared_with_me: true,
					},
				})

				// wait for data
				const [shares, sharedWithMe] = await Promise.all([fetchShares, fetchSharedWithMe])
				this.loading = false

				// process results
				this.processSharedWithMe(sharedWithMe)
				this.processShares(shares)
			} catch (error) {
				this.error = t('files_sharing', 'Unable to load the shares list')
				this.loading = false
				console.error('Error loading the shares list', error)
			}
		},

		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			clearInterval(this.expirationInterval)
			this.loading = true
			this.error = ''
			this.sharedWithMe = {}
			this.shares = []
		},

		/**
		 * Update sharedWithMe.subtitle with the appropriate
		 * expiration time left
		 *
		 * @param {Share} share the sharedWith Share object
		 */
		updateExpirationSubtitle(share) {
			const expiration = moment(share.expireDate).unix()
			this.$set(this.sharedWithMe, 'subtitle', t('files_sharing', 'Expires {relativetime}', {
				relativetime: OC.Util.relativeModifiedDate(expiration * 1000),
			}))

			// share have expired
			if (moment().unix() > expiration) {
				clearInterval(this.expirationInterval)
				// TODO: clear ui if share is expired
				this.$set(this.sharedWithMe, 'subtitle', t('files_sharing', 'this share just expired.'))
			}
		},

		/**
		 * Process the current shares data
		 * and init shares[]
		 *
		 * @param {Object} share the share ocs api request data
		 * @param {Object} share.data the request data
		 */
		processShares({ data }) {
			if (data.ocs && data.ocs.data && data.ocs.data.length > 0) {
				// create Share objects and sort by newest
				const shares = data.ocs.data
					.map(share => new Share(share))
					.sort((a, b) => b.createdTime - a.createdTime)

				this.linkShares = shares.filter(share => share.type === this.SHARE_TYPES.SHARE_TYPE_LINK || share.type === this.SHARE_TYPES.SHARE_TYPE_EMAIL)
				this.shares = shares.filter(share => share.type !== this.SHARE_TYPES.SHARE_TYPE_LINK && share.type !== this.SHARE_TYPES.SHARE_TYPE_EMAIL)
			}
		},

		/**
		 * Process the sharedWithMe share data
		 * and init sharedWithMe
		 *
		 * @param {Object} share the share ocs api request data
		 * @param {Object} share.data the request data
		 */
		processSharedWithMe({ data }) {
			if (data.ocs && data.ocs.data && data.ocs.data[0]) {
				const share = new Share(data)
				const title = shareWithTitle(share)
				const displayName = share.ownerDisplayName
				const user = share.owner

				this.sharedWithMe = {
					displayName,
					title,
					user,
				}
				this.reshare = share

				// If we have an expiration date, use it as subtitle
				// Refresh the status every 10s and clear if expired
				if (share.expireDate && moment(share.expireDate).unix() > moment().unix()) {
					// first update
					this.updateExpirationSubtitle(share)
					// interval update
					this.expirationInterval = setInterval(this.updateExpirationSubtitle, 10000, share)
				}
			}
		},

		/**
		 * Insert share at top of arrays
		 *
		 * @param {Share} share the share to insert
		 */
		addShare(share) {
			// only catching share type MAIL as link shares are added differently
			// meaning: not from the ShareInput
			if (share.type === this.SHARE_TYPES.SHARE_TYPE_EMAIL) {
				this.linkShares.unshift(share)
			} else {
				this.shares.unshift(share)
			}
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
