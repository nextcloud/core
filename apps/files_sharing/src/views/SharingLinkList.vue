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
	<ul v-if="canLinkShare" class="sharing-link-list">
		<!-- If no link shares, show the add link default entry -->
		<SharingEntryLink v-if="!hasLinkShares && canReshare"
			:can-reshare="canReshare"
			:file-info="fileInfo"
			@add:share="addShare" />

		<!-- Else we display the list -->
		<template v-if="hasShares">
			<!-- using shares[index] to work with .sync -->
			<SharingEntryLink v-for="(share, index) in shares"
				:key="share.id"
				:can-reshare="canReshare"
				:share.sync="shares[index]"
				:file-info="fileInfo"
				@add:share="addShare(...arguments)"
				@update:share="awaitForShare(...arguments)"
				@remove:share="removeShare" />
		</template>
	</ul>
</template>

<script>
// eslint-disable-next-line no-unused-vars
import Share from '../models/Share'
import ShareTypes from '../mixins/ShareTypes'
import SharingEntryLink from '../components/SharingEntryLink'

export default {
	name: 'SharingLinkList',

	components: {
		SharingEntryLink,
	},

	mixins: [ShareTypes],

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		shares: {
			type: Array,
			default: () => [],
			required: true,
		},
		canReshare: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			canLinkShare: OC.getCapabilities()['files_sharing']['public']['enabled'],
		}
	},

	computed: {
		/**
		 * Do we have link shares?
		 * Using this to still show the `new link share`
		 * button regardless of mail shares
		 *
		 * @returns {Array}
		 */
		hasLinkShares() {
			return this.shares.filter(share => share.type === this.SHARE_TYPES.SHARE_TYPE_LINK).length > 0
		},

		/**
		 * Do we have any link or email shares?
		 *
		 * @returns {boolean}
		 */
		hasShares() {
			return this.shares.length > 0
		},
	},

	methods: {
		/**
		 * Add a new share into the link shares list
		 * and return the newly created share component
		 *
		 * @param {Share} share the share to add to the array
		 * @param {Function} resolve a function to run after the share is added and its component initialized
		 */
		addShare(share, resolve) {
			this.shares.unshift(share)
			this.awaitForShare(share, resolve)
		},

		/**
		 * Await for next tick and render after the list updated
		 * Then resolve with the matched vue component of the
		 * provided share object
		 *
		 * @param {Share} share newly created share
		 * @param {Function} resolve a function to execute after
		 */
		awaitForShare(share, resolve) {
			this.$nextTick(() => {
				const newShare = this.$children.find(component => component.share === share)
				if (newShare) {
					resolve(newShare)
				}
			})
		},

		/**
		 * Remove a share from the shares list
		 *
		 * @param {Share} share the share to remove
		 */
		removeShare(share) {
			const index = this.shares.findIndex(item => item === share)
			this.shares.splice(index, 1)
		},
	},
}
</script>
