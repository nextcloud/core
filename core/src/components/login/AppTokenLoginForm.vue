<template>
	<form name="login"
		method="post"
		:action="loginActionUrl"
		@submit="submit">
		<fieldset>
			<p class="grouptop">
				<input id="user"
					ref="user"
					v-model="user"
					type="text"
					name="user"
					autocapitalize="off"
					:autocomplete="autoCompleteAllowed ? 'on' : 'off'"
					:placeholder="t('core', 'Username')"
					:aria-label="t('core', 'Username')"
					required
					@change="updateUsername">
				<label for="user" class="infield">{{ t('core', 'Username') }}</label>
			</p>

			<p class="groupbottom">
				<input id="password"
					ref="password"
					:type="passwordInputType"
					class="password-with-toggle"
					name="password"
					:autocomplete="autoCompleteAllowed ? 'on' : 'off'"
					:placeholder="t('core', 'App token')"
					:aria-label="t('core', 'Password')"
					required>
				<label for="password"
					class="infield">{{ t('Password') }}</label>
				<a href="#" class="toggle-password" @click.stop.prevent="togglePassword">
					<img :src="toggleIcon">
				</a>
			</p>

			<LoginButton :loading="loading" :inverted-colors="invertedColors" />

			<input type="hidden"
				name="requesttoken"
				:value="OC.requestToken">
		</fieldset>
	</form>
</template>

<script>
import LoginButton from './LoginButton'
import { generateUrl, imagePath } from '@nextcloud/router'

export default {
	name: 'AppTokenLoginForm',
	components: { LoginButton },
	props: {
		username: {
			type: String,
			default: '',
		},
		invertedColors: {
			type: Boolean,
			default: false,
		},
		autoCompleteAllowed: {
			type: Boolean,
			default: true,
		},
	},
	data() {
		return {
			loading: false,
			user: this.username,
			password: '',
			passwordInputType: 'password',
		}
	},
	computed: {
		toggleIcon() {
			return imagePath('core', 'actions/toggle.svg')
		},
		loginActionUrl() {
			return generateUrl('login/flow/apptoken')
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
		},
	},
}
</script>

<style scoped>

</style>
