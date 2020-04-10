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
	<div v-if="!adding">
		<input v-model="deviceName"
			type="text"
			:disabled="loading"
			:placeholder="t('settings', 'App name')"
			@keydown.enter="submit">
		<button class="button"
			:disabled="loading"
			@click="submit">
			{{ t('settings', 'Create new app password')	}}
		</button>
	</div>
	<div v-else>
		{{ t('settings', 'Use the credentials below to configure your app or device.') }}
		{{ t('settings', 'For security reasons this password will only be shown once.') }}
		<div class="app-password-row">
			<span class="app-password-label">{{ t('settings', 'Username') }}</span>
			<input :value="loginName"
				type="text"
				class="monospaced"
				readonly="readonly"
				@focus="selectInput">
		</div>
		<div class="app-password-row">
			<span class="app-password-label">{{ t('settings', 'Password') }}</span>
			<input ref="appPassword"
				:value="appPassword"
				type="text"
				class="monospaced"
				readonly="readonly"
				@focus="selectInput">
			<a ref="clipboardButton"
				v-tooltip="copyTooltipOptions"
				v-clipboard:copy="appPassword"
				v-clipboard:success="onCopyPassword"
				v-clipboard:error="onCopyPasswordFailed"
				class="icon icon-clippy"
				@mouseover="hoveringCopyButton = true"
				@mouseleave="hoveringCopyButton = false" />
			<button class="button"
				@click="reset">
				{{ t('settings', 'Done') }}
			</button>
		</div>
		<div class="app-password-row">
			<span class="app-password-label" />
			<a v-if="!showQR"
				@click="showQR = true">
				{{ t('settings', 'Show QR code for mobile apps') }}
			</a>
			<QR v-else
				:value="qrUrl" />
		</div>
	</div>
</template>

<script>
import QR from '@chenfengyuan/vue-qrcode'
import confirmPassword from '@nextcloud/password-confirmation'

export default {
	name: 'AuthTokenSetupDialogue',
	components: {
		QR,
	},
	props: {
		add: {
			type: Function,
			required: true,
		},
	},
	data() {
		return {
			adding: false,
			loading: false,
			deviceName: '',
			appPassword: '',
			loginName: '',
			passwordCopied: false,
			showQR: false,
			qrUrl: '',
			hoveringCopyButton: false,
		}
	},
	computed: {
		copyTooltipOptions() {
			const base = {
				hideOnTargetClick: false,
				trigger: 'manual',
			}

			if (this.passwordCopied) {
				return {
					...base,
					content: t('core', 'Copied!'),
					show: true,
				}
			} else {
				return {
					...base,
					content: t('core', 'Copy'),
					show: this.hoveringCopyButton,
				}
			}
		},
	},
	methods: {
		selectInput(e) {
			e.currentTarget.select()
		},
		submit: function() {
			confirmPassword()
				.then(() => {
					this.loading = true
					return this.add(this.deviceName)
				})
				.then(token => {
					this.adding = true
					this.loginName = token.loginName
					this.appPassword = token.token

					const server = window.location.protocol + '//' + window.location.host + OC.getRootPath()
					this.qrUrl = `nc://login/user:${token.loginName}&password:${token.token}&server:${server}`

					this.$nextTick(() => {
						this.$refs.appPassword.select()
					})
				})
				.catch(err => {
					console.error('could not create a new app password', err)
					OC.Notification.showTemporary(t('core', 'Error while creating device token'))

					this.reset()
				})
		},
		onCopyPassword() {
			this.passwordCopied = true
			this.$refs.clipboardButton.blur()
			setTimeout(() => { this.passwordCopied = false }, 3000)
		},
		onCopyPasswordFailed() {
			OC.Notification.showTemporary(t('core', 'Could not copy app password. Please copy it manually.'))
		},
		reset() {
			this.adding = false
			this.loading = false
			this.showQR = false
			this.qrUrl = ''
			this.deviceName = ''
			this.appPassword = ''
			this.loginName = ''
		},
	},
}
</script>

<style lang="scss" scoped>
	.app-password-row {
		display: table-row;

		.icon {
			background-size: 16px 16px;
			display: inline-block;
			position: relative;
			top: 3px;
			margin-left: 5px;
			margin-right: 8px;
		}

	}

	.app-password-label {
		display: table-cell;
		padding-right: 1em;
		text-align: right;
		vertical-align: middle;
	}

	.monospaced {
		width: 245px;
		font-family: monospace;
	}
</style>
