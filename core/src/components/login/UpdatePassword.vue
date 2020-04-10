<!--
  - @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
	<form @submit.prevent="submit">
		<fieldset>
			<p>
				<label for="password" class="infield">{{ t('core', 'New password') }}</label>
				<input id="password"
					v-model="password"
					type="password"
					name="password"
					required
					:placeholder="t('core', 'New password')">
			</p>

			<div v-if="encrypted" class="update">
				<p>
					{{ t('core', 'Your files are encrypted. There will be no way to get your data back after your password is reset. If you are not sure what to do, please contact your administrator before you continue. Do you really want to continue?') }}
				</p>
				<input id="encrypted-continue"
					v-model="proceed"
					type="checkbox"
					class="checkbox">
				<label for="encrypted-continue">
					{{ t('core', 'I know what I\'m doing') }}
				</label>
			</div>

			<div id="submit-wrapper">
				<input id="submit"
					type="submit"
					class="login primary"
					title=""
					:value="!loading ? t('core', 'Reset password') : t('core', 'Resetting password')">
				<div class="submit-icon"
					:class="{
						'icon-loading-small': loading && invertedColors,
						'icon-loading-small-dark': loading && !invertedColors
					}" />
			</div>

			<p v-if="error && message" :class="{warning: error}">
				{{ message }}
			</p>
		</fieldset>
	</form>
</template>

<script>
import Axios from '@nextcloud/axios'

export default {
	name: 'UpdatePassword',
	props: {
		username: {
			type: String,
			required: true,
		},
		resetPasswordTarget: {
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
			password: '',
			encrypted: false,
			proceed: false,
		}
	},
	watch: {
		username(value) {
			this.user = value
		},
	},
	methods: {
		async submit() {
			this.loading = true
			this.error = false
			this.message = ''

			try {
				const { data } = await Axios.post(this.resetPasswordTarget, {
					password: this.password,
					proceed: this.proceed,
				})
				if (data && data.status === 'success') {
					this.message = 'send-success'
					this.$emit('update:username', this.user)
					this.$emit('done')
				} else if (data && data.encryption) {
					this.encrypted = true
				} else if (data && data.msg) {
					throw new Error(data.msg)
				} else {
					throw new Error()
				}
			} catch (e) {
				this.error = true
				this.message = e.message ? e.message : t('core', 'Password can not be changed. Please contact your administrator.')
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
	fieldset {
		text-align: center;
	}

	input[type=submit] {
		margin-top: 20px;
	}
</style>
