<!--
  - @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
  -
  - @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div>
		<h3>{{ t('files', 'Transfer ownership of a file or folder') }} </h3>
		<form @submit.prevent="submit">
			<p class="transfer-select-row">
				<span>{{ readableDirectory }}</span>
				<button v-if="directory === undefined" @click.prevent="start">
					{{ t('files', 'Choose file or folder to transfer') }}
				</button>
				<button v-else @click.prevent="start">
					{{ t('files', 'Change') }}
				</button>
				<span class="error">{{ directoryPickerError }}</span>
			</p>
			<p class="new-owner-row">
				<label for="targetUser">
					<span>{{ t('files', 'New owner') }}</span>
				</label>
				<Multiselect
					id="targetUser"
					v-model="selectedUser"
					:options="formatedUserSuggestions"
					:multiple="false"
					:searchable="true"
					:placeholder="t('files', 'Search users')"
					:preselect-first="true"
					:preserve-search="true"
					:loading="loadingUsers"
					track-by="user"
					label="displayName"
					:internal-search="false"
					:clear-on-select="false"
					:user-select="true"
					class="middle-align"
					@search-change="findUserDebounced" />
			</p>
			<p>
				<input type="submit"
					class="primary"
					:value="submitButtonText"
					:disabled="!canSubmit">
				<span class="error">{{ submitError }}</span>
			</p>
		</form>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { generateOcsUrl } from '@nextcloud/router'
import { getFilePickerBuilder, showSuccess } from '@nextcloud/dialogs'
import { Multiselect } from '@nextcloud/vue/dist/Components/Multiselect'
import Vue from 'vue'

import logger from '../logger'

const picker = getFilePickerBuilder(t('files', 'Choose a file or folder to transfer'))
	.setMultiSelect(false)
	.setModal(true)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'TransferOwnershipDialogue',
	components: {
		Multiselect,
	},
	data() {
		return {
			directory: undefined,
			directoryPickerError: undefined,
			submitError: undefined,
			loadingUsers: false,
			selectedUser: null,
			userSuggestions: {},
			config: {
				minSearchStringLength: parseInt(OC.config['sharing.minSearchStringLength'], 10) || 0,
			},
		}
	},
	computed: {
		canSubmit() {
			return !!this.directory && !!this.selectedUser
		},
		formatedUserSuggestions() {
			return Object.keys(this.userSuggestions).map((uid) => {
				const user = this.userSuggestions[uid]
				return {
					user: user.uid,
					displayName: user.displayName,
					icon: 'icon-user',
				}
			})
		},
		submitButtonText() {
			if (!this.canSubmit) {
				return t('files', 'Transfer')
			}
			const components = this.readableDirectory.split('/')
			return t('files', 'Transfer {path} to {userid}', { path: components[components.length - 1], userid: this.selectedUser.displayName })
		},
		readableDirectory() {
			if (!this.directory) {
				return ''
			}
			return this.directory.substring(1)
		},
	},
	created() {
		this.findUserDebounced = debounce(this.findUser, 300)
		this.findUser('')
	},
	methods: {
		start() {
			this.directoryPickerError = undefined

			picker.pick()
				.then(dir => dir === '' ? '/' : dir)
				.then(dir => {
					logger.debug(`path ${dir} selected for transferring ownership`)
					if (!dir.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}
					// /ocs/v2.php/apps/files/api/v1/transferownership
					// /ocs/v2.php/apps/files/api/v1/transferownership
					this.directory = dir
				}).catch(error => {
					logger.error(`Selecting object for transfer aborted: ${error.message || 'Unknown error'}`, { error })

					this.directoryPickerError = error.message || t('files', 'Unknown error')
				})
		},
		async findUser(query) {
			this.query = query.trim()

			if (query.length < this.config.minSearchStringLength) {
				return
			}

			this.loadingUsers = true
			try {
				const response = await axios.get(generateOcsUrl('apps/files_sharing/api/v1') + 'sharees', {
					params: {
						format: 'json',
						itemType: 'file',
						search: query,
						perPage: 20,
						lookup: false,
					},
				})

				if (response.data.ocs.meta.statuscode !== 100) {
					logger.error('Error fetching suggestions', { response })
				}

				this.userSuggestions = {}
				response.data.ocs.data.exact.users.concat(response.data.ocs.data.users).forEach(user => {
					Vue.set(this.userSuggestions, user.value.shareWith, {
						uid: user.value.shareWith,
						displayName: user.label,
					})
				})
			} catch (error) {
				logger.error('could not fetch users', { error })
			} finally {
				this.loadingUsers = false
			}
		},
		submit() {
			if (!this.canSubmit) {
				logger.warn('ignoring form submit')
			}

			this.submitError = undefined
			const data = {
				path: this.directory,
				recipient: this.selectedUser.user,
			}
			logger.debug('submit transfer ownership form', data)

			const url = generateOcsUrl('apps/files/api/v1/', 2) + 'transferownership'

			axios.post(url, data)
				.then(resp => resp.data)
				.then(data => {
					logger.info('Transfer ownership request sent', { data })

					this.directory = undefined
					this.selectedUser = null
					showSuccess(t('files', 'Ownership transfer request sent'))
				})
				.catch(error => {
					logger.error('Could not send ownership transfer request', { error })

					if (error?.response?.status === 403) {
						this.submitError = t('files', 'Cannot transfer ownership of a file or folder you don\'t own')
					} else {
						this.submitError = error.message || t('files', 'Unknown error')
					}
				})
		},
	},
}
</script>

<style scoped lang="scss">
.middle-align {
	vertical-align: middle;
}
p {
	margin-top: 12px;
	margin-bottom: 12px;
}
.new-owner-row {
	display: flex;

	label {
		display: flex;
		align-items: center;

		span {
			margin-right: 8px;
		}
	}

	.multiselect {
		flex-grow: 1;
		max-width: 280px;
	}
}
.transfer-select-row {
	span {
		margin-right: 8px;
	}
}
</style>
