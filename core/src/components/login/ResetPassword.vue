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
	<form @submit.prevent="submit">
		<fieldset>
			<p>
				<input id="user"
					v-model="user"
					type="text"
					name="user"
					:placeholder="t('core', 'Username or email')"
					:aria-label="t('core', 'Username or email')"
					required
					@change="updateUsername">
				<!--<?php p($_['user_autofocus'] ? 'autofocus' : ''); ?>
				autocomplete="<?php p($_['login_form_autocomplete']); ?>" autocapitalize="none" autocorrect="off"-->
				<label for="user" class="infield">{{ t('core', 'Username or email') }}</label>
			</p>
			<div id="reset-password-wrapper">
				<input id="reset-password-submit"
					type="submit"
					class="login primary"
					title=""
					:value="t('core', 'Reset password')">
				<div class="submit-icon"
					:class="{
						'icon-confirm-white': !loading,
						'icon-loading-small': loading && invertedColors,
						'icon-loading-small-dark': loading && !invertedColors,
					}" />
			</div>
			<p v-if="message === 'send-success'"
				class="update">
				{{ t('core', 'A password reset message has been sent to the e-mail address of this account. If you do not receive it, check your spam/junk folders or ask your local administrator for help.') }}
				<br>
				{{ t('core', 'If it is not there ask your local administrator.') }}
			</p>
			<p v-else-if="message === 'send-error'"
				class="update warning">
				{{ t('core', 'Couldn\'t send reset email. Please contact your administrator.') }}
			</p>
			<p v-else-if="message === 'reset-error'"
				class="update warning">
				{{ t('core', 'Password can not be changed. Please contact your administrator.') }}
			</p>
			<p v-else-if="message"
				class="update"
				:class="{warning: error}" />

			<a href="#"
				@click.prevent="$emit('abort')">
				{{ t('core', 'Back to login') }}
			</a>
		</fieldset>
	</form>
</template>

<script>
import axios from '@nextcloud/axios'

import { generateUrl } from '../../OC/routing'

export default {
	name: 'ResetPassword',
	props: {
		username: {
			type: String,
			required: true,
		},
		resetPasswordLink: {
			type: String,
			required: true,
		},
		invertedColors: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			error: false,
			loading: false,
			message: undefined,
			user: this.username,
		}
	},
	watch: {
		username(value) {
			this.user = value
		},
	},
	methods: {
		updateUsername() {
			this.$emit('update:username', this.user)
		},
		submit() {
			this.loading = true
			this.error = false
			this.message = ''
			const url = generateUrl('/lostpassword/email')

			const data = {
				user: this.user,
			}

			return axios.post(url, data)
				.then(resp => resp.data)
				.then(data => {
					if (data.status !== 'success') {
						throw new Error(`got status ${data.status}`)
					}

					this.message = 'send-success'
				})
				.catch(e => {
					console.error('could not send reset e-mail request', e)

					this.error = true
					this.message = 'send-error'
				})
				.then(() => { this.loading = false })
		},
	},
}
</script>

<style scoped>
	.update {
		width: auto;
	}
</style>
