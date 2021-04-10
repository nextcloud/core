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
	<form ref="loginForm"
		method="post"
		name="login"
		:action="loginActionUrl"
		@submit="submit">
		<fieldset>
			<div v-if="apacheAuthFailed"
				class="warning">
				{{ t('core', 'Server side authentication failed!') }}<br>
				<small>{{ t('core', 'Please contact your administrator.') }}
				</small>
			</div>
			<div v-for="(message, index) in messages"
				:key="index"
				class="warning">
				{{ message }}<br>
			</div>
			<div v-if="internalException"
				class="warning">
				{{ t('core', 'An internal error occurred.') }}<br>
				<small>{{ t('core', 'Please try again or contact your administrator.') }}
				</small>
			</div>
			<div id="message"
				class="hidden">
				<img class="float-spinner"
					alt=""
					:src="loadingIcon">
				<span id="messageText" />
				<!-- the following div ensures that the spinner is always inside the #message div -->
				<div style="clear: both;" />
			</div>
			<p class="grouptop"
				:class="{shake: invalidPassword}">
				<input id="user"
					ref="user"
					v-model="user"
					type="text"
					name="user"
					autocapitalize="off"
					:autocomplete="autoCompleteAllowed ? 'on' : 'off'"
					:placeholder="t('core', 'Username or email')"
					:aria-label="t('core', 'Username or email')"
					required
					@change="updateUsername">
				<label for="user" class="infield">{{ t('core', 'Username or email') }}</label>
			</p>

			<p class="groupbottom"
				:class="{shake: invalidPassword}">
				<input id="password"
					ref="password"
					:type="passwordInputType"
					class="password-with-toggle"
					name="password"
					:autocomplete="autoCompleteAllowed ? 'on' : 'off'"
					:placeholder="t('core', 'Password')"
					:aria-label="t('core', 'Password')"
					required>
				<label for="password"
					class="infield">{{ t('Password') }}</label>
				<a href="#" class="toggle-password" @click.stop.prevent="togglePassword">
					<img :src="toggleIcon">
				</a>
			</p>

			<LoginButton :loading="loading" :inverted-colors="invertedColors" />

			<p v-if="invalidPassword"
				class="warning wrongPasswordMsg">
				{{ t('core', 'Wrong username or password.') }}
			</p>
			<p v-else-if="userDisabled"
				class="warning userDisabledMsg">
				{{ t('core', 'User disabled') }}
			</p>

			<p v-if="throttleDelay && throttleDelay > 5000"
				class="warning throttledMsg">
				{{ t('core', 'We have detected multiple invalid login attempts from your IP. Therefore your next login is throttled up to 30 seconds.') }}
			</p>

			<input v-if="redirectUrl"
				type="hidden"
				name="redirect_url"
				:value="redirectUrl">
			<input type="hidden"
				name="timezone"
				:value="timezone">
			<input type="hidden"
				name="timezone_offset"
				:value="timezoneOffset">
			<input type="hidden"
				name="requesttoken"
				:value="OC.requestToken">
			<input v-if="directLogin"
				type="hidden"
				name="direct"
				value="1">
		</fieldset>
	</form>
</template>

<script>
import jstz from 'jstimezonedetect'
import LoginButton from './LoginButton'
import {
	generateUrl,
	imagePath,
} from '@nextcloud/router'

export default {
	name: 'LoginForm',
	components: { LoginButton },
	props: {
		username: {
			type: String,
			default: '',
		},
		redirectUrl: {
			type: String,
		},
		errors: {
			type: Array,
			default: () => [],
		},
		messages: {
			type: Array,
			default: () => [],
		},
		throttleDelay: {
			type: Number,
		},
		invertedColors: {
			type: Boolean,
			default: false,
		},
		autoCompleteAllowed: {
			type: Boolean,
			default: true,
		},
		directLogin: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			loading: false,
			timezone: jstz.determine().name(),
			timezoneOffset: (-new Date().getTimezoneOffset() / 60),
			user: this.username,
			password: '',
			passwordInputType: 'password',
		}
	},
	computed: {
		apacheAuthFailed() {
			return this.errors.indexOf('apacheAuthFailed') !== -1
		},
		internalException() {
			return this.errors.indexOf('internalexception') !== -1
		},
		invalidPassword() {
			return this.errors.indexOf('invalidpassword') !== -1
		},
		userDisabled() {
			return this.errors.indexOf('userdisabled') !== -1
		},
		toggleIcon() {
			return imagePath('core', 'actions/toggle.svg')
		},
		loadingIcon() {
			return imagePath('core', 'loading-dark.gif')
		},
		loginActionUrl() {
			return generateUrl('login')
		},
	},
	mounted() {
		if (this.username === '') {
			this.$refs.user.focus()
		} else {
			this.$refs.password.focus()
		}
	},
	methods: {
		togglePassword() {
			if (this.passwordInputType === 'password') {
				this.passwordInputType = 'text'
			} else {
				this.passwordInputType = 'password'
			}
		},
		updateUsername() {
			this.$emit('update:username', this.user)
		},
		submit() {
			this.loading = true
			this.$emit('submit')
		},
	},
}
</script>
