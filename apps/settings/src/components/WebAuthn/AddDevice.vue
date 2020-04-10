<!--
  - @copyright 2020, Roeland Jago Douma <roeland@famdouma.nl>
  -
  - @author Roeland Jago Douma <roeland@famdouma.nl>
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
	<div v-if="!isHttps">
		{{ t('settings', 'Passwordless authentication requires a secure connection.') }}
	</div>
	<div v-else>
		<div v-if="step === RegistrationSteps.READY">
			<button @click="start">
				{{ t('settings', 'Add WebAuthn device') }}
			</button>
		</div>

		<div v-else-if="step === RegistrationSteps.REGISTRATION"
			class="new-webauthn-device">
			<span class="icon-loading-small webauthn-loading" />
			{{ t('settings', 'Please authorize your WebAuthn device.') }}
		</div>

		<div v-else-if="step === RegistrationSteps.NAMING"
			class="new-webauthn-device">
			<span class="icon-loading-small webauthn-loading" />
			<input v-model="name"
				type="text"
				:placeholder="t('settings', 'Name your device')"
				@:keyup.enter="submit">
			<button @click="submit">
				{{ t('settings', 'Add') }}
			</button>
		</div>

		<div v-else-if="step === RegistrationSteps.PERSIST"
			class="new-webauthn-device">
			<span class="icon-loading-small webauthn-loading" />
			{{ t('settings', 'Adding your device …') }}
		</div>

		<div v-else>
			Invalid registration step. This should not have happened.
		</div>
	</div>
</template>

<script>
import confirmPassword from '@nextcloud/password-confirmation'

import logger from '../../logger'
import {
	startRegistration,
	finishRegistration,
} from '../../service/WebAuthnRegistrationSerice'

const logAndPass = (text) => (data) => {
	logger.debug(text)
	return data
}

const RegistrationSteps = Object.freeze({
	READY: 1,
	REGISTRATION: 2,
	NAMING: 3,
	PERSIST: 4,
})

export default {
	name: 'AddDevice',
	props: {
		httpWarning: Boolean,
		isHttps: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			name: '',
			credential: {},
			RegistrationSteps,
			step: RegistrationSteps.READY,
		}
	},
	methods: {
		arrayToBase64String(a) {
			return btoa(String.fromCharCode(...a))
		},
		start() {
			this.step = RegistrationSteps.REGISTRATION
			console.debug('Starting WebAuthn registration')

			return confirmPassword()
				.then(this.getRegistrationData)
				.then(this.register.bind(this))
				.then(() => { this.step = RegistrationSteps.NAMING })
				.catch(err => {
					console.error(err.name, err.message)
					this.step = RegistrationSteps.READY
				})
		},

		getRegistrationData() {
			console.debug('Fetching webauthn registration data')

			const base64urlDecode = function(input) {
				// Replace non-url compatible chars with base64 standard chars
				input = input
					.replace(/-/g, '+')
					.replace(/_/g, '/')

				// Pad out with standard base64 required padding characters
				const pad = input.length % 4
				if (pad) {
					if (pad === 1) {
						throw new Error('InvalidLengthError: Input base64url string is the wrong length to determine padding')
					}
					input += new Array(5 - pad).join('=')
				}

				return window.atob(input)
			}

			return startRegistration()
				.then(publicKey => {
					console.debug(publicKey)
					publicKey.challenge = Uint8Array.from(base64urlDecode(publicKey.challenge), c => c.charCodeAt(0))
					publicKey.user.id = Uint8Array.from(publicKey.user.id, c => c.charCodeAt(0))
					return publicKey
				})
				.catch(err => {
					console.error('Error getting webauthn registration data from server', err)
					throw new Error(t('settings', 'Server error while trying to add WebAuthn device'))
				})
		},

		register(publicKey) {
			console.debug('starting webauthn registration')

			return navigator.credentials.create({ publicKey })
				.then(data => {
					this.credential = {
						id: data.id,
						type: data.type,
						rawId: this.arrayToBase64String(new Uint8Array(data.rawId)),
						response: {
							clientDataJSON: this.arrayToBase64String(new Uint8Array(data.response.clientDataJSON)),
							attestationObject: this.arrayToBase64String(new Uint8Array(data.response.attestationObject)),
						},
					}
				})
		},

		submit() {
			this.step = RegistrationSteps.PERSIST

			return confirmPassword()
				.then(logAndPass('confirmed password'))
				.then(this.saveRegistrationData)
				.then(logAndPass('registration data saved'))
				.then(() => this.reset())
				.then(logAndPass('app reset'))
				.catch(console.error.bind(this))
		},

		async saveRegistrationData() {
			try {
				const device = await finishRegistration(this.name, JSON.stringify(this.credential))

				logger.info('new device added', { device })

				this.$emit('added', device)
			} catch (err) {
				logger.error('Error persisting webauthn registration', { error: err })
				throw new Error(t('settings', 'Server error while trying to complete WebAuthn device registration'))
			}
		},

		reset() {
			this.name = ''
			this.registrationData = {}
			this.step = RegistrationSteps.READY
		},
	},
}
</script>

<style scoped>
	.webauthn-loading {
		display: inline-block;
		vertical-align: sub;
		margin-left: 2px;
		margin-right: 2px;
	}

	.new-webauthn-device {
		line-height: 300%;
	}
</style>
