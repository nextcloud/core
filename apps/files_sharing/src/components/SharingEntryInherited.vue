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
	<SharingEntrySimple
		:key="share.id"
		class="sharing-entry__inherited"
		:title="share.shareWithDisplayName">
		<template #avatar>
			<Avatar
				:user="share.shareWith"
				:display-name="share.shareWithDisplayName"
				class="sharing-entry__avatar"
				tooltip-message="" />
		</template>
		<ActionText icon="icon-user">
			{{ t('files_sharing', 'Added by {initiator}', { initiator: share.ownerDisplayName }) }}
		</ActionText>
		<ActionLink v-if="share.canDelete && share.fileSource"
			icon="icon-folder"
			:href="fileTargetUrl">
			{{ t('files_sharing', 'Via folder') }}
		</ActionLink>
		<ActionButton v-if="share.canDelete"
			icon="icon-close"
			@click.prevent="onDelete">
			{{ t('files_sharing', 'Unshare') }}
		</actionbutton>
	</SharingEntrySimple>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import ActionText from '@nextcloud/vue/dist/Components/ActionText'

// eslint-disable-next-line no-unused-vars
import Share from '../models/Share'
import SharesMixin from '../mixins/SharesMixin'
import SharingEntrySimple from '../components/SharingEntrySimple'

export default {
	name: 'SharingEntryInherited',

	components: {
		ActionButton,
		ActionLink,
		ActionText,
		Avatar,
		SharingEntrySimple,
	},

	mixins: [SharesMixin],

	props: {
		share: {
			type: Share,
			required: true,
		},
	},

	computed: {
		fileTargetUrl() {
			return generateUrl('/f/{fileid}', {
				fileid: this.share.fileSource,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.sharing-entry {
	display: flex;
	align-items: center;
	height: 44px;
	&__desc {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 8px;
		line-height: 1.2em;
		p {
			color: var(--color-text-maxcontrast);
		}
	}
	&__actions {
		margin-left: auto;
	}
}
</style>
