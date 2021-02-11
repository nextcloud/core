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
	<div id="security-webauthn" class="section">
		<h2>{{ t('settings', 'Passwordless Authentication') }}</h2>
		<p class="settings-hint hidden-when-empty">
			{{ t('settings', 'Set up your account for passwordless authentication following the FIDO2 standard.') }}
		</p>
		<p v-if="devices.length === 0">
			{{ t('settings', 'No devices configured.') }}
		</p>
		<p v-else>
			{{ t('settings', 'The following devices are configured for your account:') }}
		</p>
		<Device v-for="device in sortedDevices"
			:key="device.id"
			:name="device.name"
			@delete="deleteDevice(device.id)" />

		<p v-if="!hasPublicKeyCredential" class="warning">
			{{ t('settings', 'Your browser does not support WebAuthn.') }}
		</p>

		<AddDevice v-if="hasPublicKeyCredential" :is-https="isHttps" @added="deviceAdded" />
	</div>
</template>

<script>
import confirmPassword from '@nextcloud/password-confirmation'
import sortBy from 'lodash/fp/sortBy'

import AddDevice from './AddDevice'
import Device from './Device'
import logger from '../../logger'
import { removeRegistration } from '../../service/WebAuthnRegistrationSerice'

const sortByName = sortBy('name')

export default {
	components: {
		AddDevice,
		Device,
	},
	props: {
		initialDevices: {
			type: Array,
			required: true,
		},
		isHttps: {
			type: Boolean,
			default: false,
		},
		hasPublicKeyCredential: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			devices: this.initialDevices,
		}
	},
	computed: {
		sortedDevices() {
			return sortByName(this.devices)
		},
	},
	methods: {
		deviceAdded(device) {
			logger.debug(`adding new device to the list ${device.id}`)

			this.devices.push(device)
		},
		async deleteDevice(id) {
			logger.info(`deleting webauthn device ${id}`)

			await confirmPassword()
			await removeRegistration(id)

			this.devices = this.devices.filter(d => d.id !== id)

			logger.info(`webauthn device ${id} removed successfully`)
		},
	},
}
</script>

<style scoped>

</style>
