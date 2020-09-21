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
		<div class="warning" v-if="grantParams">
			<h2>{{ t('core', 'Connect to your account') }}</h2>
			<p class="info" v-html="t('core', 'Please log in before granting %1$s access to your %2$s account.').replace('%1$s', '<strong>' + grantParams.client + '</strong>').replace('%2$s',  grantParams.instanceName)" />
			<p class="info">{{ t('core', 'If you are not trying to set up a new device or app, someone is trying to trick you into granting them access to your data. In this case do not proceed and instead contact your system administrator.') }}</p>
		</div>
		<transition name="fade" mode="out-in">
			<div v-if="!passwordlessLogin && !appTokenLogin && !resetPassword && resetPasswordTarget === ''"
				key="login">
				<LoginForm
					:username.sync="user"
					:redirect-url="redirectUrl"
					:direct-login="directLogin"
					:messages="messages"
					:errors="errors"
					:throttle-delay="throttleDelay"
					:inverted-colors="invertedColors"
					:auto-complete-allowed="autoCompleteAllowed"
					@submit="loading = true" />
				<a v-if="canResetPassword && resetPasswordLink !== ''"
					id="lost-password"
					:href="resetPasswordLink">
					{{ t('core', 'Forgot password?') }}
				</a>
				<a v-else-if="canResetPassword && !resetPassword"
					id="lost-password"
					:href="resetPasswordLink"
					@click.prevent="resetPassword = true">
					{{ t('core', 'Forgot password?') }}
				</a>
				<br>
				<a v-if="hasPasswordless" @click.prevent="passwordlessLogin = true">
					{{ t('core', 'Log in with a device') }}
				</a>
				<br>
				<a v-if="hasAppToken" @click.prevent="appTokenLogin = true">
					{{ t('core', 'Alternative log in using app token') }}
				</a>
			</div>
			<div v-else-if="!loading && passwordlessLogin"
				key="reset"
				class="login-additional">
				<PasswordLessLoginForm
					:username.sync="user"
					:redirect-url="redirectUrl"
					:inverted-colors="invertedColors"
					:auto-complete-allowed="autoCompleteAllowed"
					:is-https="isHttps"
					:has-public-key-credential="hasPublicKeyCredential"
					@submit="loading = true" />
				<a @click.prevent="passwordlessLogin = false">
					{{ t('core', 'Back') }}
				</a>
			</div>
			<div v-else-if="!loading && appTokenLogin"
				key="app-token"
				class="login-additional">
				<AppTokenLoginForm
					:username.sync="user"
					:inverted-colors="invertedColors"
					:auto-complete-allowed="autoCompleteAllowed" />
				<a @click.prevent="appTokenLogin = false">
					{{ t('core', 'Back') }}
				</a>
			</div>
			<div v-else-if="!loading && canResetPassword"
				key="reset"
				class="login-additional">
				<div class="lost-password-container">
					<ResetPassword v-if="resetPassword"
						:username.sync="user"
						:reset-password-link="resetPasswordLink"
						:inverted-colors="invertedColors"
						@abort="resetPassword = false" />
				</div>
			</div>
			<div v-else-if="resetPasswordTarget !== ''">
				<UpdatePassword :username.sync="user"
					:reset-password-target="resetPasswordTarget"
					:inverted-colors="invertedColors"
					@done="passwordResetFinished" />
			</div>
		</transition>
	</div>
</template>

<script>
import LoginForm from '../components/login/LoginForm.vue'
import PasswordLessLoginForm from '../components/login/PasswordLessLoginForm.vue'
import AppTokenLoginForm from '../components/login/AppTokenLoginForm.vue'
import ResetPassword from '../components/login/ResetPassword.vue'
import UpdatePassword from '../components/login/UpdatePassword.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'Login',
	components: {
		LoginForm,
		PasswordLessLoginForm,
		AppTokenLoginForm,
		ResetPassword,
		UpdatePassword,
	},
	props: {
		username: {
			type: String,
			default: '',
		},
		redirectUrl: {
			type: String,
		},
		grantParams: {
			type: Object,
			default: null
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
		canResetPassword: {
			type: Boolean,
			default: false,
		},
		resetPasswordLink: {
			type: String,
		},
		resetPasswordTarget: {
			type: String,
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
		hasPasswordless: {
			type: Boolean,
			default: false,
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
			loading: false,
			user: this.username,
			passwordlessLogin: false,
			resetPassword: false,
			appTokenLogin: false,
		}
	},
	computed: {
		hasAppToken() {
			return this.redirectUrl
				&& this.redirectUrl.indexOf(generateUrl('login/flow')) === 0
				&& new URL(this.redirectUrl, window.location.origin).searchParams.has('clientIdentifier') === false
		}
	},
	methods: {
		passwordResetFinished() {
			this.resetPasswordTarget = ''
			this.directLogin = true
		},
	},
}
</script>

<style>
	.fade-enter-active, .fade-leave-active {
		transition: opacity .3s;
	}
	.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
		opacity: 0;
	}
</style>
