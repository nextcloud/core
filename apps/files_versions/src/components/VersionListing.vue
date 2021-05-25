<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author
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
	<li class="version-entry">
		<Avatar class="version-entry__avatar"
			:url="iconUrl" />
		<ListItemIcon v-for="version in versions"
			class="version-entry"
			:title="version"
			subtitle="< 1KB">
			<Actions
				menu-align="right"
				class="version-entry__actions">
				<ActionButton icon="icon-edit" @click="alert('Edit')">
					{{ t('files_versions','Restore') }}
				</ActionButton>
			</Actions>
		</ListItemIcon>
	</li>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import ListItemIcon from '@nextcloud/vue/dist/Components/ListItemIcon'

import SharesMixin from '../mixins/SharesMixin'

export default {
	name: 'VersionListing',

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionInput,
		ActionTextEditable,
		Avatar,
		ListItemIcon,
	},

	directives: {
		Tooltip,
	},

	props: {
		_fileInfo: {
			type: Object,
			required: true,
		},
		versions: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {

		}
	},

	computed: {
		iconUrl() {
			return OC.MimeType.getIconUrl(this.MimeType)
			console.log(iconUrl)
		},
	},
}
</script>

<style lang="scss" scoped>
.version-entry {
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
		&-unique {
			color: var(--color-text-maxcontrast);
		}
	}
	&__actions {
		margin-left: auto;
	}
}
</style>
