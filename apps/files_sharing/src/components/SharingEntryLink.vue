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
	<li :class="{'sharing-entry--share': share}" class="sharing-entry sharing-entry__link">
		<Avatar :is-no-user="true"
			:class="isEmailShareType ? 'icon-mail-white' : 'icon-public-white'"
			class="sharing-entry__avatar" />
		<div class="sharing-entry__desc">
			<h5>{{ title }}</h5>
		</div>

		<!-- clipboard -->
		<Actions v-if="share && !isEmailShareType && share.token"
			ref="copyButton"
			class="sharing-entry__copy">
			<ActionLink :href="shareLink"
				target="_blank"
				:icon="copied && copySuccess ? 'icon-checkmark-color' : 'icon-clippy'"
				@click.stop.prevent="copyLink">
				{{ clipboardTooltip }}
			</ActionLink>
		</Actions>

		<!-- pending actions -->
		<Actions v-if="!loading && (pendingPassword || pendingExpirationDate)"
			class="sharing-entry__actions"
			menu-align="right"
			:open.sync="open"
			@close="onNewLinkShare">
			<!-- pending data menu -->
			<ActionText v-if="errors.pending"
				icon="icon-error"
				:class="{ error: errors.pending}">
				{{ errors.pending }}
			</ActionText>
			<ActionText v-else icon="icon-info">
				{{ t('files_sharing', 'Please enter the following required information before creating the share') }}
			</ActionText>

			<!-- password -->
			<ActionText v-if="pendingPassword" icon="icon-password">
				{{ t('files_sharing', 'Password protection (enforced)') }}
			</ActionText>
			<ActionCheckbox v-else-if="config.enableLinkPasswordByDefault"
				:checked.sync="isPasswordProtected"
				:disabled="config.enforcePasswordForPublicLink || saving"
				class="share-link-password-checkbox"
				@uncheck="onPasswordDisable">
				{{ t('files_sharing', 'Password protection') }}
			</ActionCheckbox>
			<ActionInput v-if="pendingPassword || share.password"
				v-tooltip.auto="{
					content: errors.password,
					show: errors.password,
					trigger: 'manual',
					defaultContainer: '#app-sidebar'
				}"
				class="share-link-password"
				:value.sync="share.password"
				:disabled="saving"
				:required="config.enableLinkPasswordByDefault || config.enforcePasswordForPublicLink"
				:minlength="isPasswordPolicyEnabled && config.passwordPolicy.minLength"
				icon=""
				autocomplete="new-password"
				@submit="onNewLinkShare">
				{{ t('files_sharing', 'Enter a password') }}
			</ActionInput>

			<!-- expiration date -->
			<ActionText v-if="pendingExpirationDate" icon="icon-calendar-dark">
				{{ t('files_sharing', 'Expiration date (enforced)') }}
			</ActionText>
			<ActionInput v-if="pendingExpirationDate"
				v-model="share.expireDate"
				v-tooltip.auto="{
					content: errors.expireDate,
					show: errors.expireDate,
					trigger: 'manual',
					defaultContainer: '#app-sidebar'
				}"
				class="share-link-expire-date"
				:disabled="saving"
				:first-day-of-week="firstDay"
				:lang="lang"
				icon=""
				type="date"
				:not-before="dateTomorrow"
				:not-after="dateMaxEnforced">
				<!-- let's not submit when picked, the user
					might want to still edit or copy the password -->
				{{ t('files_sharing', 'Enter a date') }}
			</ActionInput>

			<ActionButton icon="icon-close" @click.prevent.stop="onCancel">
				{{ t('files_sharing', 'Cancel') }}
			</ActionButton>
		</Actions>

		<!-- actions -->
		<Actions v-else-if="!loading"
			class="sharing-entry__actions"
			menu-align="right"
			:open.sync="open"
			@close="onMenuClose">
			<template v-if="share">
				<template v-if="share.canEdit">
					<!-- folder -->
					<template v-if="isFolder && fileHasCreatePermission && config.isPublicUploadEnabled">
						<ActionRadio :checked="share.permissions === publicUploadRValue"
							:value="publicUploadRValue"
							:name="randomId"
							:disabled="saving"
							@change="togglePermissions">
							{{ t('files_sharing', 'Read only') }}
						</ActionRadio>
						<ActionRadio :checked="share.permissions === publicUploadRWValue"
							:value="publicUploadRWValue"
							:disabled="saving"
							:name="randomId"
							@change="togglePermissions">
							{{ t('files_sharing', 'Allow upload and editing') }}
						</ActionRadio>
						<ActionRadio :checked="share.permissions === publicUploadWValue"
							:value="publicUploadWValue"
							:disabled="saving"
							:name="randomId"
							class="sharing-entry__action--public-upload"
							@change="togglePermissions">
							{{ t('files_sharing', 'File drop (upload only)') }}
						</ActionRadio>
					</template>

					<!-- file -->
					<ActionCheckbox v-else
						:checked.sync="canUpdate"
						:disabled="saving"
						@change="queueUpdate('permissions')">
						{{ t('files_sharing', 'Allow editing') }}
					</ActionCheckbox>

					<ActionCheckbox
						:checked.sync="share.hideDownload"
						:disabled="saving"
						@change="queueUpdate('hideDownload')">
						{{ t('files_sharing', 'Hide download') }}
					</ActionCheckbox>

					<!-- password -->
					<ActionCheckbox :checked.sync="isPasswordProtected"
						:disabled="config.enforcePasswordForPublicLink || saving"
						class="share-link-password-checkbox"
						@uncheck="onPasswordDisable">
						{{ config.enforcePasswordForPublicLink
							? t('files_sharing', 'Password protection (enforced)')
							: t('files_sharing', 'Password protect') }}
					</ActionCheckbox>
					<ActionInput v-if="isPasswordProtected"
						ref="password"
						v-tooltip.auto="{
							content: errors.password,
							show: errors.password,
							trigger: 'manual',
							defaultContainer: '#app-sidebar'
						}"
						class="share-link-password"
						:class="{ error: errors.password}"
						:disabled="saving"
						:required="config.enforcePasswordForPublicLink"
						:value="hasUnsavedPassword ? share.newPassword : '***************'"
						icon="icon-password"
						autocomplete="new-password"
						:type="hasUnsavedPassword ? 'text': 'password'"
						@update:value="onPasswordChange"
						@submit="onPasswordSubmit">
						{{ t('files_sharing', 'Enter a password') }}
					</ActionInput>

					<!-- password protected by Talk -->
					<ActionCheckbox v-if="isPasswordProtectedByTalkAvailable"
						:checked.sync="isPasswordProtectedByTalk"
						:disabled="saving"
						class="share-link-password-talk-checkbox"
						@change="queueUpdate('sendPasswordByTalk')">
						{{ t('files_sharing', 'Video verification') }}
					</ActionCheckbox>

					<!-- expiration date -->
					<ActionCheckbox :checked.sync="hasExpirationDate"
						:disabled="config.isDefaultExpireDateEnforced || saving"
						class="share-link-expire-date-checkbox"
						@uncheck="onExpirationDisable">
						{{ config.isDefaultExpireDateEnforced
							? t('files_sharing', 'Expiration date (enforced)')
							: t('files_sharing', 'Set expiration date') }}
					</ActionCheckbox>
					<ActionInput v-if="hasExpirationDate"
						ref="expireDate"
						v-tooltip.auto="{
							content: errors.expireDate,
							show: errors.expireDate,
							trigger: 'manual',
							defaultContainer: '#app-sidebar'
						}"
						class="share-link-expire-date"
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
					<ActionCheckbox :checked.sync="hasNote"
						:disabled="saving"
						@uncheck="queueUpdate('note')">
						{{ t('files_sharing', 'Note to recipient') }}
					</ActionCheckbox>
					<ActionTextEditable v-if="hasNote"
						ref="note"
						v-tooltip.auto="{
							content: errors.note,
							show: errors.note,
							trigger: 'manual',
							defaultContainer: '#app-sidebar'
						}"
						:class="{ error: errors.note}"
						:disabled="saving"
						:placeholder="t('files_sharing', 'Enter a note for the share recipient')"
						:value="share.newNote || share.note"
						icon="icon-edit"
						@update:value="onNoteChange"
						@submit="onNoteSubmit" />
				</template>

				<!-- external sharing via url (social...) -->
				<ActionLink v-for="({icon, url, name}, index) in externalActions"
					:key="index"
					:href="url(shareLink)"
					:icon="icon"
					target="_blank">
					{{ name }}
				</ActionLink>

				<ActionButton v-if="share.canDelete"
					icon="icon-close"
					:disabled="saving"
					@click.prevent="onDelete">
					{{ t('files_sharing', 'Unshare') }}
				</ActionButton>
				<ActionButton v-if="!isEmailShareType && canReshare"
					class="new-share-link"
					icon="icon-add"
					@click.prevent.stop="onNewLinkShare">
					{{ t('files_sharing', 'Add another link') }}
				</ActionButton>
			</template>

			<!-- Create new share -->
			<ActionButton v-else-if="canReshare"
				class="new-share-link"
				icon="icon-add"
				@click.prevent.stop="onNewLinkShare">
				{{ t('files_sharing', 'Create a new share link') }}
			</ActionButton>
		</Actions>

		<!-- loading indicator to replace the menu -->
		<div v-else class="icon-loading-small sharing-entry__loading" />
	</li>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Vue from 'vue'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import ActionCheckbox from '@nextcloud/vue/dist/Components/ActionCheckbox'
import ActionRadio from '@nextcloud/vue/dist/Components/ActionRadio'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionText from '@nextcloud/vue/dist/Components/ActionText'
import ActionTextEditable from '@nextcloud/vue/dist/Components/ActionTextEditable'
import ActionLink from '@nextcloud/vue/dist/Components/ActionLink'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

import Share from '../models/Share'
import SharesMixin from '../mixins/SharesMixin'

const passwordSet = 'abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789'

export default {
	name: 'SharingEntryLink',

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionRadio,
		ActionInput,
		ActionLink,
		ActionText,
		ActionTextEditable,
		Avatar,
	},

	directives: {
		Tooltip,
	},

	mixins: [SharesMixin],

	props: {
		canReshare: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			copySuccess: true,
			copied: false,

			publicUploadRWValue: OC.PERMISSION_UPDATE | OC.PERMISSION_CREATE | OC.PERMISSION_READ | OC.PERMISSION_DELETE,
			publicUploadRValue: OC.PERMISSION_READ,
			publicUploadWValue: OC.PERMISSION_CREATE,

			ExternalLinkActions: OCA.Sharing.ExternalLinkActions.state,
		}
	},

	computed: {
		/**
		 * Generate a unique random id for this SharingEntryLink only
		 * This allows ActionRadios to have the same name prop
		 * but not to impact others SharingEntryLink
		 * @returns {string}
		 */
		randomId() {
			return Math.random().toString(27).substr(2)
		},

		/**
		 * Link share label
		 * TODO: allow editing
		 * @returns {string}
		 */
		title() {
			// if we have a valid existing share (not pending)
			if (this.share && this.share.id) {
				if (!this.isShareOwner && this.share.ownerDisplayName) {
					return t('files_sharing', 'Shared via link by {initiator}', {
						initiator: this.share.ownerDisplayName,
					})
				}
				if (this.share.label && this.share.label.trim() !== '') {
					return this.share.label
				}
				if (this.isEmailShareType) {
					return this.share.shareWith
				}
			}
			return t('files_sharing', 'Share link')
		},

		/**
		 * Does the current share have an expiration date
		 * @returns {boolean}
		 */
		hasExpirationDate: {
			get: function() {
				return this.config.isDefaultExpireDateEnforced || !!this.share.expireDate
			},
			set: function(enabled) {
				this.share.expireDate = enabled
					? this.config.defaultExpirationDateString !== ''
						? this.config.defaultExpirationDateString
						: moment().format('YYYY-MM-DD')
					: ''
			},
		},

		dateMaxEnforced() {
			return this.config.isDefaultExpireDateEnforced
				&& moment().add(1 + this.config.defaultExpireDate, 'days')
		},

		/**
		 * Is the current share password protected ?
		 * @returns {boolean}
		 */
		isPasswordProtected: {
			get: function() {
				return this.config.enforcePasswordForPublicLink
					|| !!this.share.password
			},
			set: async function(enabled) {
				// TODO: directly save after generation to make sure the share is always protected
				Vue.set(this.share, 'password', enabled ? await this.generatePassword() : '')
				Vue.set(this.share, 'newPassword', this.share.password)
			},
		},

		/**
		 * Is Talk enabled?
		 * @returns {boolean}
		 */
		isTalkEnabled() {
			return OC.appswebroots['spreed'] !== undefined
		},

		/**
		 * Is it possible to protect the password by Talk?
		 * @returns {boolean}
		 */
		isPasswordProtectedByTalkAvailable() {
			return this.isPasswordProtected && this.isTalkEnabled
		},

		/**
		 * Is the current share password protected by Talk?
		 * @returns {boolean}
		 */
		isPasswordProtectedByTalk: {
			get: function() {
				return this.share.sendPasswordByTalk
			},
			set: async function(enabled) {
				this.share.sendPasswordByTalk = enabled
			},
		},

		/**
		 * Is the current share an email share ?
		 * @returns {boolean}
		 */
		isEmailShareType() {
			return this.share
				? this.share.type === this.SHARE_TYPES.SHARE_TYPE_EMAIL
				: false
		},

		/**
		 * Pending data.
		 * If the share still doesn't have an id, it is not synced
		 * Therefore this is still not valid and requires user input
		 * @returns {boolean}
		 */
		pendingPassword() {
			return this.config.enforcePasswordForPublicLink && this.share && !this.share.id
		},
		pendingExpirationDate() {
			return this.config.isDefaultExpireDateEnforced && this.share && !this.share.id
		},

		/**
		 * Can the recipient edit the file ?
		 * @returns {boolean}
		 */
		canUpdate: {
			get: function() {
				return this.share.hasUpdatePermission
			},
			set: function(enabled) {
				this.share.permissions = enabled
					? OC.PERMISSION_READ | OC.PERMISSION_UPDATE
					: OC.PERMISSION_READ
			},
		},

		// if newPassword exists, but is empty, it means
		// the user deleted the original password
		hasUnsavedPassword() {
			return this.share.newPassword !== undefined
		},

		/**
		 * Is the current share a folder ?
		 * TODO: move to a proper FileInfo model?
		 * @returns {boolean}
		 */
		isFolder() {
			return this.fileInfo.type === 'dir'
		},

		/**
		 * Does the current file/folder have create permissions
		 * TODO: move to a proper FileInfo model?
		 * @returns {boolean}
		 */
		fileHasCreatePermission() {
			return !!(this.fileInfo.permissions & OC.PERMISSION_CREATE)
		},

		/**
		 * Return the public share link
		 * @returns {string}
		 */
		shareLink() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/s/') + this.share.token
		},

		/**
		 * Clipboard v-tooltip message
		 * @returns {string}
		 */
		clipboardTooltip() {
			if (this.copied) {
				return this.copySuccess
					? t('files_sharing', 'Link copied')
					: t('files_sharing', 'Cannot copy, please copy the link manually')
			}
			return t('files_sharing', 'Copy to clipboard')
		},

		/**
		 * External aditionnal actions for the menu
		 * @returns {Array}
		 */
		externalActions() {
			return this.ExternalLinkActions.actions
		},

		isPasswordPolicyEnabled() {
			return typeof this.config.passwordPolicy === 'object'
		},
	},

	methods: {
		/**
		 * Create a new share link and append it to the list
		 */
		async onNewLinkShare() {
			const shareDefaults = {
				share_type: OC.Share.SHARE_TYPE_LINK,
			}
			if (this.config.isDefaultExpireDateEnforced) {
				// default is empty string if not set
				// expiration is the share object key, not expireDate
				shareDefaults.expiration = this.config.defaultExpirationDateString
			}
			if (this.config.enableLinkPasswordByDefault) {
				shareDefaults.password = await this.generatePassword()
			}

			// do not push yet if we need a password or an expiration date
			if (this.config.enforcePasswordForPublicLink || this.config.isDefaultExpireDateEnforced) {
				this.loading = true
				// if a share already exists, pushing it
				if (this.share && !this.share.id) {
					if (this.checkShare(this.share)) {
						await this.pushNewLinkShare(this.share, true)
						return true
					} else {
						this.open = true
						OC.Notification.showTemporary(t('files_sharing', 'Error, please enter proper password and/or expiration date'))
						return false
					}
				}

				// ELSE, show the pending popovermenu
				// if password enforced, pre-fill with random one
				if (this.config.enforcePasswordForPublicLink) {
					shareDefaults.password = await this.generatePassword()
				}

				// create share & close menu
				const share = new Share(shareDefaults)
				const component = await new Promise(resolve => {
					this.$emit('add:share', share, resolve)
				})

				// open the menu on the
				// freshly created share component
				this.open = false
				this.loading = false
				component.open = true

			// Nothing enforced, creating share directly
			} else {
				const share = new Share(shareDefaults)
				await this.pushNewLinkShare(share)
			}
		},

		/**
		 * Push a new link share to the server
		 * And update or append to the list
		 * accordingly
		 *
		 * @param {Share} share the new share
		 * @param {boolean} [update=false] do we update the current share ?
		 */
		async pushNewLinkShare(share, update) {
			try {
				this.loading = true
				this.errors = {}

				const path = (this.fileInfo.path + '/' + this.fileInfo.name).replace('//', '/')
				const newShare = await this.createShare({
					path,
					shareType: OC.Share.SHARE_TYPE_LINK,
					password: share.password,
					expireDate: share.expireDate,
					// we do not allow setting the publicUpload
					// before the share creation.
					// Todo: We also need to fix the createShare method in
					// lib/Controller/ShareAPIController.php to allow file drop
					// (currently not supported on create, only update)
				})

				this.open = false

				console.debug('Link share created', newShare)

				// if share already exists, copy link directly on next tick
				let component
				if (update) {
					component = await new Promise(resolve => {
						this.$emit('update:share', newShare, resolve)
					})
				} else {
					// adding new share to the array and copying link to clipboard
					// using promise so that we can copy link in the same click function
					// and avoid firefox copy permissions issue
					component = await new Promise(resolve => {
						this.$emit('add:share', newShare, resolve)
					})
				}

				// Execute the copy link method
				// freshly created share component
				// ! somehow does not works on firefox !
				component.copyLink()

			} catch ({ response }) {
				const message = response.data.ocs.meta.message
				if (message.match(/password/i)) {
					this.onSyncError('password', message)
				} else if (message.match(/date/i)) {
					this.onSyncError('expireDate', message)
				} else {
					this.onSyncError('pending', message)
				}
			} finally {
				this.loading = false
			}
		},

		/**
		 * On permissions change
		 * @param {Event} event js event
		 */
		togglePermissions(event) {
			const permissions = parseInt(event.target.value, 10)
			this.share.permissions = permissions
			this.queueUpdate('permissions')
		},

		/**
		 * Generate a valid policy password or
		 * request a valid password if password_policy
		 * is enabled
		 *
		 * @returns {string} a valid password
		 */
		async generatePassword() {
			// password policy is enabled, let's request a pass
			if (this.config.passwordPolicy.api && this.config.passwordPolicy.api.generate) {
				try {
					const request = await axios.get(this.config.passwordPolicy.api.generate)
					if (request.data.ocs.data.password) {
						return request.data.ocs.data.password
					}
				} catch (error) {
					console.info('Error generating password from password_policy', error)
				}
			}

			// generate password of 10 length based on passwordSet
			return Array(10).fill(0)
				.reduce((prev, curr) => {
					prev += passwordSet.charAt(Math.floor(Math.random() * passwordSet.length))
					return prev
				}, '')
		},

		async copyLink() {
			try {
				await this.$copyText(this.shareLink)
				// focus and show the tooltip
				this.$refs.copyButton.$el.focus()
				this.copySuccess = true
				this.copied = true
			} catch (error) {
				this.copySuccess = false
				this.copied = true
				console.error(error)
			} finally {
				setTimeout(() => {
					this.copySuccess = false
					this.copied = false
				}, 4000)
			}
		},

		/**
		 * Update newPassword values
		 * of share. If password is set but not newPassword
		 * then the user did not changed the password
		 * If both co-exists, the password have changed and
		 * we show it in plain text.
		 * Then on submit (or menu close), we sync it.
		 * @param {string} password the changed password
		 */
		onPasswordChange(password) {
			this.$set(this.share, 'newPassword', password)
		},

		/**
		 * Uncheck password protection
		 * We need this method because @update:checked
		 * is ran simultaneously as @uncheck, so
		 * so we cannot ensure data is up-to-date
		 */
		onPasswordDisable() {
			this.share.password = ''

			// reset password state after sync
			this.$delete(this.share, 'newPassword')

			// only update if valid share.
			if (this.share.id) {
				this.queueUpdate('password')
			}
		},

		/**
		 * Menu have been closed or password has been submited.
		 * The only property that does not get
		 * synced automatically is the password
		 * So let's check if we have an unsaved
		 * password.
		 * expireDate is saved on datepicker pick
		 * or close.
		 */
		onPasswordSubmit() {
			if (this.hasUnsavedPassword) {
				this.share.password = this.share.newPassword.trim()
				this.queueUpdate('password')
			}
		},

		/**
		 * Save potential changed data on menu close
		 */
		onMenuClose() {
			this.onPasswordSubmit()
			this.onNoteSubmit()
		},

		/**
		 * Cancel the share creation
		 * Used in the pending popover
		 */
		onCancel() {
			// this.share already exists at this point,
			// but is incomplete as not pushed to server
			// YET. We can safely delete the share :)
			this.$emit('remove:share', this.share)
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
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		padding: 8px;
		line-height: 1.2em;
	}

	&:not(.sharing-entry--share) &__actions {
		.new-share-link {
			border-top: 1px solid var(--color-border);
		}
	}

	.sharing-entry__action--public-upload {
		border-bottom: 1px solid var(--color-border);
	}

	&__loading {
		width: 44px;
		height: 44px;
		margin: 0;
		padding: 14px;
		margin-left: auto;
	}

	// put menus to the left
	// but only the first one
	.action-item {
		margin-left: auto;
		~ .action-item,
		~ .sharing-entry__loading {
			margin-left: 0;
		}
	}

	.icon-checkmark-color {
		opacity: 1;
	}
}
</style>
