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
		<Avatar class="sharing-entry__avatar"
			:user="share.shareWith"
			:display-name="share.shareWithDisplayName"
			:url="share.shareWithAvatar" />
		<div v-tooltip.auto="tooltip" class="sharing-entry__desc">
			<h5>{{ title }}</h5>
		</div>
		<Actions
			menu-align="right"
			class="sharing-entry__actions"
			@close="onMenuClose">
			<template v-if="share.canEdit">
				<!-- edit permission -->
				<ActionCheckbox
					ref="canEdit"
					:checked.sync="canEdit"
					:value="permissionsEdit"
					:disabled="saving">
					{{ t('files_sharing', 'Allow editing') }}
				</ActionCheckbox>

				<!-- create permission -->
				<ActionCheckbox
					v-if="isFolder"
					ref="canCreate"
					:checked.sync="canCreate"
					:value="permissionsCreate"
					:disabled="saving">
					{{ t('files_sharing', 'Allow creating') }}
				</ActionCheckbox>

				<!-- delete permission -->
				<ActionCheckbox
					v-if="isFolder"
					ref="canDelete"
					:checked.sync="canDelete"
					:value="permissionsDelete"
					:disabled="saving">
					{{ t('files_sharing', 'Allow deleting') }}
				</ActionCheckbox>

				<!-- reshare permission -->
				<ActionCheckbox
					ref="canReshare"
					:checked.sync="canReshare"
					:value="permissionsShare"
					:disabled="saving">
					{{ t('files_sharing', 'Allow resharing') }}
				</ActionCheckbox>

				<!-- expiration date -->
				<ActionCheckbox :checked.sync="hasExpirationDate"
					:disabled="config.isDefaultExpireDateEnforced || saving"
					@uncheck="onExpirationDisable">
					{{ config.isDefaultExpireDateEnforced
						? t('files_sharing', 'Expiration date enforced')
						: t('files_sharing', 'Set expiration date') }}
				</ActionCheckbox>
				<ActionInput v-if="hasExpirationDate"
					ref="expireDate"
					v-tooltip.auto="{
						content: errors.expireDate,
						show: errors.expireDate,
						trigger: 'manual'
					}"
					:class="{ error: errors.expireDate}"
					:disabled="saving"
					:first-day-of-week="firstDay"
					:lang="lang"
					:value="share.expireDate"
					icon="icon-calendar-dark"
					type="date"
					:not-before="dateTomorrow"
					:not-after="dateMaxEnforced"
					@update:value="onExpirationChange">
					{{ t('files_sharing', 'Enter a date') }}
				</ActionInput>

				<!-- note -->
				<template v-if="canHaveNote">
					<ActionCheckbox
						:checked.sync="hasNote"
						:disabled="saving"
						@uncheck="queueUpdate('note')">
						{{ t('files_sharing', 'Note to recipient') }}
					</ActionCheckbox>
					<ActionTextEditable v-if="hasNote"
						ref="note"
						v-tooltip.auto="{
							content: errors.note,
							show: errors.note,
							trigger: 'manual'
						}"
						:class="{ error: errors.note}"
						:disabled="saving"
						:value="share.newNote || share.note"
						icon="icon-edit"
						@update:value="onNoteChange"
						@submit="onNoteSubmit" />
				</template>
			</template>

			<ActionButton v-if="share.canDelete"
				icon="icon-close"
				:disabled="saving"
				@click.prevent="onDelete">
				{{ t('files_sharing', 'Unshare') }}
			</ActionButton>
		</Actions>
	</li>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionTextEditable from '@nextcloud/vue/dist/Components/ActionTextEditable'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

import SharesMixin from '../mixins/SharesMixin'

export default {
	name: 'SharingEntry',

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionInput,
		ActionTextEditable,
		Avatar,
	},

	directives: {
		Tooltip,
	},

	mixins: [SharesMixin],

	data() {
		return {
			permissionsEdit: OC.PERMISSION_UPDATE,
			permissionsCreate: OC.PERMISSION_CREATE,
			permissionsDelete: OC.PERMISSION_DELETE,
			permissionsRead: OC.PERMISSION_READ,
			permissionsShare: OC.PERMISSION_SHARE,
		}
	},

	computed: {
		title() {
			let title = this.share.shareWithDisplayName
			if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				title += ` (${t('files_sharing', 'group')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_ROOM) {
				title += ` (${t('files_sharing', 'conversation')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE) {
				title += ` (${t('files_sharing', 'remote')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP) {
				title += ` (${t('files_sharing', 'remote group')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GUEST) {
				title += ` (${t('files_sharing', 'guest')})`
			}
			return title
		},

		tooltip() {
			if (this.share.owner !== this.share.uidFileOwner) {
				const data = {
					// todo: strong or italic?
					// but the t function escape any html from the data :/
					user: this.share.shareWithDisplayName,
					owner: this.share.owner,
				}

				if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
					return t('files_sharing', 'Shared with the group {user} by {owner}', data)
				} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_ROOM) {
					return t('files_sharing', 'Shared with the conversation {user} by {owner}', data)
				}

				return t('files_sharing', 'Shared with {user} by {owner}', data)
			}
			return null
		},

		canHaveNote() {
			return this.share.type !== this.SHARE_TYPES.SHARE_TYPE_REMOTE
				&& this.share.type !== this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP
		},

		/**
		 * Can the sharee edit the shared file ?
		 */
		canEdit: {
			get: function() {
				return this.share.hasUpdatePermission
			},
			set: function(checked) {
				this.updatePermissions({ isEditChecked: checked })
			},
		},

		/**
		 * Can the sharee create the shared file ?
		 */
		canCreate: {
			get: function() {
				return this.share.hasCreatePermission
			},
			set: function(checked) {
				this.updatePermissions({ isCreateChecked: checked })
			},
		},

		/**
		 * Can the sharee delete the shared file ?
		 */
		canDelete: {
			get: function() {
				return this.share.hasDeletePermission
			},
			set: function(checked) {
				this.updatePermissions({ isDeleteChecked: checked })
			},
		},

		/**
		 * Can the sharee reshare the file ?
		 */
		canReshare: {
			get: function() {
				return this.share.hasSharePermission
			},
			set: function(checked) {
				this.updatePermissions({ isReshareChecked: checked })
			},
		},

		/**
		 * Is the current share a folder ?
		 * @returns {boolean}
		 */
		isFolder() {
			return this.fileInfo.type === 'dir'
		},

		/**
		 * Does the current share have an expiration date
		 * @returns {boolean}
		 */
		hasExpirationDate: {
			get: function() {
				return this.config.isDefaultInternalExpireDateEnforced || !!this.share.expireDate
			},
			set: function(enabled) {
				this.share.expireDate = enabled
					? this.config.defaultInternalExpirationDateString !== ''
						? this.config.defaultInternalExpirationDateString
						: moment().format('YYYY-MM-DD')
					: ''
			},
		},

		dateMaxEnforced() {
			return this.config.isDefaultInternalExpireDateEnforced
				&& moment().add(1 + this.config.defaultInternalExpireDate, 'days')
		},

	},

	methods: {
		updatePermissions({ isEditChecked = this.canEdit, isCreateChecked = this.canCreate, isDeleteChecked = this.canDelete, isReshareChecked = this.canReshare } = {}) {
			// calc permissions if checked
			const permissions = this.permissionsRead
				| (isCreateChecked ? this.permissionsCreate : 0)
				| (isDeleteChecked ? this.permissionsDelete : 0)
				| (isEditChecked ? this.permissionsEdit : 0)
				| (isReshareChecked ? this.permissionsShare : 0)

			this.share.permissions = permissions
			this.queueUpdate('permissions')
		},

		/**
		 * Save potential changed data on menu close
		 */
		onMenuClose() {
			this.onNoteSubmit()
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
