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
	<div id="security" class="section">
		<h2>{{ t('settings', 'Devices & sessions') }}</h2>
		<p class="settings-hint hidden-when-empty">
			{{ t('settings', 'Web, desktop and mobile clients currently logged in to your account.') }}
		</p>
		<AuthTokenList :tokens="tokens"
			@toggleScope="toggleTokenScope"
			@rename="rename"
			@delete="deleteToken"
			@wipe="wipeToken" />
		<AuthTokenSetupDialogue v-if="canCreateToken" :add="addNewToken" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import confirmPassword from '@nextcloud/password-confirmation'

import AuthTokenList from './AuthTokenList'
import AuthTokenSetupDialogue from './AuthTokenSetupDialogue'

const confirm = () => {
	return new Promise(resolve => {
		OC.dialogs.confirm(
			t('core', 'Do you really want to wipe your data from this device?'),
			t('core', 'Confirm wipe'),
			resolve,
			true
		)
	})
}

/**
 * Tap into a promise without losing the value
 * @param {Function} cb the callback
 * @returns {any} val the value
 */
const tap = cb => val => {
	cb(val)
	return val
}

export default {
	name: 'AuthTokenSection',
	components: {
		AuthTokenSetupDialogue,
		AuthTokenList,
	},
	props: {
		tokens: {
			type: Array,
			required: true,
		},
		canCreateToken: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			baseUrl: OC.generateUrl('/settings/personal/authtokens'),
		}
	},
	methods: {
		addNewToken(name) {
			console.debug('creating a new app token', name)

			const data = {
				name,
			}
			return axios.post(this.baseUrl, data)
				.then(resp => resp.data)
				.then(tap(() => console.debug('app token created')))
				.then(tap(data => this.tokens.push(data.deviceToken)))
				.catch(err => {
					console.error.bind('could not create app password', err)
					OC.Notification.showTemporary(t('core', 'Error while creating device token'))
					throw err
				})
		},
		toggleTokenScope(token, scope, value) {
			console.debug('updating app token scope', token.id, scope, value)

			const oldVal = token.scope[scope]
			token.scope[scope] = value

			return this.updateToken(token)
				.then(tap(() => console.debug('app token scope updated')))
				.catch(err => {
					console.error.bind('could not update app token scope', err)
					OC.Notification.showTemporary(t('core', 'Error while updating device token scope'))

					// Restore
					token.scope[scope] = oldVal

					throw err
				})
		},
		rename(token, newName) {
			console.debug('renaming app token', token.id, token.name, newName)

			const oldName = token.name
			token.name = newName

			return this.updateToken(token)
				.then(tap(() => console.debug('app token name updated')))
				.catch(err => {
					console.error.bind('could not update app token name', err)
					OC.Notification.showTemporary(t('core', 'Error while updating device token name'))

					// Restore
					token.name = oldName
				})
		},
		updateToken(token) {
			return axios.put(this.baseUrl + '/' + token.id, token)
				.then(resp => resp.data)
		},
		deleteToken(token) {
			console.debug('deleting app token', token)

			this.tokens = this.tokens.filter(t => t !== token)

			return axios.delete(this.baseUrl + '/' + token.id)
				.then(resp => resp.data)
				.then(tap(() => console.debug('app token deleted')))
				.catch(err => {
					console.error.bind('could not delete app token', err)
					OC.Notification.showTemporary(t('core', 'Error while deleting the token'))

					// Restore
					this.tokens.push(token)
				})
		},
		async wipeToken(token) {
			console.debug('wiping app token', token)

			try {
				await confirmPassword()

				if (!(await confirm())) {
					console.debug('wipe aborted by user')
					return
				}
				await axios.post(this.baseUrl + '/wipe/' + token.id)
				console.debug('app token marked for wipe')

				token.type = 2
			} catch (err) {
				console.error('could not wipe app token', err)
				OC.Notification.showTemporary(t('core', 'Error while wiping the device with the token'))
			}
		},
	},
}
</script>

<style scoped>

</style>
