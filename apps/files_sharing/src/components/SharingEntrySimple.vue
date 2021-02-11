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
	<li class="sharing-entry">
		<slot name="avatar" />
		<div v-tooltip="tooltip" class="sharing-entry__desc">
			<h5>{{ title }}</h5>
			<p v-if="subtitle">
				{{ subtitle }}
			</p>
		</div>
		<Actions v-if="$slots['default']" menu-align="right" class="sharing-entry__actions">
			<slot />
		</Actions>
	</li>
</template>

<script>
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'SharingEntrySimple',

	components: {
		Actions,
	},

	directives: {
		Tooltip,
	},

	props: {
		title: {
			type: String,
			default: '',
			required: true,
		},
		tooltip: {
			type: String,
			default: '',
		},
		subtitle: {
			type: String,
			default: '',
		},
		isUnique: {
			type: Boolean,
			default: true,
		},
	},

}
</script>

<style lang="scss" scoped>
.sharing-entry {
	display: flex;
	align-items: center;
	min-height: 44px;
	&__desc {
		padding: 8px;
		line-height: 1.2em;
		position: relative;
		flex: 1 1;
		min-width: 0;
		h5 {
			white-space: nowrap;
			text-overflow: ellipsis;
			overflow: hidden;
			max-width: inherit;
		}
		p {
			color: var(--color-text-maxcontrast);
		}
	}
	&__actions {
		margin-left: auto !important;
	}
}
</style>
