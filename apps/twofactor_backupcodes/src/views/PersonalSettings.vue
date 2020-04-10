<template>
	<div>
		<button v-if="!enabled"
			id="generate-backup-codes"
			:disabled="generatingCodes"
			@click="generateBackupCodes">
			{{ t('twofactor_backupcodes', 'Generate backup codes') }}
			<span :class="{'icon-loading-small': generatingCodes}" />
		</button>
		<template v-else>
			<p>
				<template v-if="!haveCodes">
					{{ t('twofactor_backupcodes', 'Backup codes have been generated. {used} of {total} codes have been used.', {used, total}) }}
				</template>
				<template v-else>
					{{ t('twofactor_backupcodes', 'These are your backup codes. Please save and/or print them as you will not be able to read the codes again later') }}
					<ul>
						<li v-for="code in codes" :key="code" class="backup-code">
							{{ code }}
						</li>
					</ul>
					<a :href="downloadUrl"
						class="button primary"
						:download="downloadFilename">{{ t('twofactor_backupcodes', 'Save backup codes') }}</a>
					<button class="button"
						@click="printCodes">
						{{ t('twofactor_backupcodes', 'Print backup codes') }}
					</button>
				</template>
			</p>
			<p>
				<button id="generate-backup-codes"
					@click="generateBackupCodes">
					{{ t('twofactor_backupcodes', 'Regenerate backup codes') }}
				</button>
			</p>
			<p>
				<em>
					{{ t('twofactor_backupcodes', 'If you regenerate backup codes, you automatically invalidate old codes.') }}
				</em>
			</p>
		</template>
	</div>
</template>

<script>
import confirmPassword from '@nextcloud/password-confirmation'
import { print } from '../service/PrintService'

export default {
	name: 'PersonalSettings',
	data() {
		return {
			generatingCodes: false,
		}
	},
	computed: {
		downloadUrl: function() {
			if (!this.codes) {
				return ''
			}
			return 'data:text/plain,' + encodeURIComponent(this.codes.reduce((prev, code) => {
				return prev + code + '\r\n'
			}, ''))
		},
		downloadFilename: function() {
			const name = OC.theme.name || 'Nextcloud'
			return name + '-backup-codes.txt'
		},
		enabled: function() {
			return this.$store.state.enabled
		},
		total: function() {
			return this.$store.state.total
		},
		used: function() {
			return this.$store.state.used
		},
		codes: function() {
			return this.$store.state.codes
		},
		name: function() {
			return OC.theme.name || 'Nextcloud'
		},
		haveCodes() {
			return this.codes && this.codes.length > 0
		},
	},
	methods: {
		generateBackupCodes: function() {
			confirmPassword().then(() => {
				// Hide old codes
				this.generatingCodes = true

				this.$store.dispatch('generate').then(data => {
					this.generatingCodes = false
				}).catch(err => {
					OC.Notification.showTemporary(t('twofactor_backupcodes', 'An error occurred while generating your backup codes'))
					this.generatingCodes = false
					throw err
				})
			}).catch(console.error.bind(this))
		},

		getPrintData: function(codes) {
			if (!codes) {
				return ''
			}
			return codes.reduce((prev, code) => {
				return prev + code + '<br>'
			}, '')
		},

		printCodes: function() {
			print(this.getPrintData(this.codes))
		},
	},
}
</script>

<style scoped>
	.backup-code {
		font-family: monospace;
		letter-spacing: 0.02em;
		font-size: 1.2em;
	}
	.button {
		display: inline-block;
	}
</style>
