<!--
  - @copyright Copyright (c) 2020 Georg Ehrke <oc.list@georgehrke.com>
  - @author Georg Ehrke <oc.list@georgehrke.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Modal
		size="normal"
		:title="$t('user_status', 'Set status')"
		@close="closeModal">
		<div class="set-status-modal">
			<!-- Status selector -->
			<div class="set-status-modal__header">
				<h3>{{ $t('user_status', 'Online status') }}</h3>
			</div>
			<div class="set-status-modal__online-status">
				<OnlineStatusSelect v-for="status in statuses"
					:key="status.type"
					v-bind="status"
					:checked="status.type === statusType"
					@select="changeStatus" />
			</div>

			<!-- Status message -->
			<div class="set-status-modal__header">
				<h3>{{ $t('user_status', 'Status message') }}</h3>
			</div>
			<div class="set-status-modal__custom-input">
				<EmojiPicker @select="setIcon">
					<button
						class="custom-input__emoji-button">
						{{ visibleIcon }}
					</button>
				</EmojiPicker>
				<CustomMessageInput
					ref="customMessageInput"
					:message="message"
					@change="setMessage"
					@submit="saveStatus" />
			</div>
			<PredefinedStatusesList
				@selectStatus="selectPredefinedMessage" />
			<ClearAtSelect
				:clear-at="clearAt"
				@selectClearAt="setClearAt" />
			<div class="status-buttons">
				<button class="status-buttons__select" :disabled="isSavingStatus" @click="clearStatus">
					{{ $t('user_status', 'Clear status message') }}
				</button>
				<button class="status-buttons__primary primary" :disabled="isSavingStatus" @click="saveStatus">
					{{ $t('user_status', 'Set status message') }}
				</button>
			</div>
		</div>
	</Modal>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import EmojiPicker from '@nextcloud/vue/dist/Components/EmojiPicker'
import Modal from '@nextcloud/vue/dist/Components/Modal'

import { getAllStatusOptions } from '../services/statusOptionsService'
import OnlineStatusMixin from '../mixins/OnlineStatusMixin'
import PredefinedStatusesList from './PredefinedStatusesList'
import CustomMessageInput from './CustomMessageInput'
import ClearAtSelect from './ClearAtSelect'
import OnlineStatusSelect from './OnlineStatusSelect'

export default {
	name: 'SetStatusModal',

	components: {
		ClearAtSelect,
		CustomMessageInput,
		EmojiPicker,
		Modal,
		OnlineStatusSelect,
		PredefinedStatusesList,
	},
	mixins: [OnlineStatusMixin],

	data() {
		return {
			clearAt: null,
			icon: null,
			message: '',
			isSavingStatus: false,
			statuses: getAllStatusOptions(),
		}
	},
	computed: {
		/**
		 * Returns the user-set icon or a smiley in case no icon is set
		 *
		 * @returns {String}
		 */
		visibleIcon() {
			return this.icon || '😀'
		},
	},

	/**
	 * Loads the current status when a user opens dialog
	 */
	mounted() {
		this.messageId = this.$store.state.userStatus.messageId
		this.icon = this.$store.state.userStatus.icon
		this.message = this.$store.state.userStatus.message || ''

		if (this.$store.state.userStatus.clearAt !== null) {
			this.clearAt = {
				type: '_time',
				time: this.$store.state.userStatus.clearAt,
			}
		}
	},
	methods: {
		/**
		 * Closes the Set Status modal
		 */
		closeModal() {
			this.$emit('close')
		},
		/**
		 * Sets a new icon
		 *
		 * @param {String} icon The new icon
		 */
		setIcon(icon) {
			this.messageId = null
			this.icon = icon
			this.$nextTick(() => {
				this.$refs.customMessageInput.focus()
			})
		},
		/**
		 * Sets a new message
		 *
		 * @param {String} message The new message
		 */
		setMessage(message) {
			this.messageId = null
			this.message = message
		},
		/**
		 * Sets a new clearAt value
		 *
		 * @param {Object} clearAt The new clearAt object
		 */
		setClearAt(clearAt) {
			this.clearAt = clearAt
		},
		/**
		 * Sets new icon/message/clearAt based on a predefined message
		 *
		 * @param {Object} status The predefined status object
		 */
		selectPredefinedMessage(status) {
			this.messageId = status.id
			this.clearAt = status.clearAt
			this.icon = status.icon
			this.message = status.message
		},
		/**
		 * Saves the status and closes the
		 *
		 * @returns {Promise<void>}
		 */
		async saveStatus() {
			if (this.isSavingStatus) {
				return
			}

			try {
				this.isSavingStatus = true

				if (this.messageId !== null) {
					await this.$store.dispatch('setPredefinedMessage', {
						messageId: this.messageId,
						clearAt: this.clearAt,
					})
				} else {
					await this.$store.dispatch('setCustomMessage', {
						message: this.message,
						icon: this.icon,
						clearAt: this.clearAt,
					})
				}
			} catch (err) {
				showError(this.$t('user_status', 'There was an error saving the status'))
				console.debug(err)
				this.isSavingStatus = false
				return
			}

			this.isSavingStatus = false
			this.closeModal()
		},
		/**
		 *
		 * @returns {Promise<void>}
		 */
		async clearStatus() {
			try {
				this.isSavingStatus = true

				await this.$store.dispatch('clearMessage')
			} catch (err) {
				showError(this.$t('user_status', 'There was an error clearing the status'))
				console.debug(err)
				this.isSavingStatus = false
				return
			}

			this.isSavingStatus = false
			this.closeModal()
		},
	},
}
</script>

<style lang="scss" scoped>
.set-status-modal {
	min-width: 500px;
	min-height: 200px;
	padding: 8px 20px 20px 20px;
	// Enable scrollbar for too long content, same way as in Dashboard customize
	max-height: 70vh;
	overflow: auto;

	&__header {
		text-align: center;
		font-weight: bold;
	}

	&__online-status {
		display: grid;
		// Space between the two sections
		margin-bottom: 40px;
		grid-template-columns: 1fr 1fr;
	}

	&__custom-input {
		display: flex;
		width: 100%;
		margin-bottom: 10px;

		.custom-input__emoji-button {
			flex-basis: 40px;
			flex-grow: 0;
			width: 40px;
			height: 34px;
			margin-right: 0;
			border-right: none;
			border-radius: var(--border-radius) 0 0 var(--border-radius);
		}
	}

	.status-buttons {
		display: flex;

		button {
			flex-basis: 50%;
		}
	}
}

</style>
