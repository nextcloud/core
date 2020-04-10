/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

export default {
	props: {
		user: {
			type: Object,
			required: true,
		},
		settings: {
			type: Object,
			default: () => ({}),
		},
		groups: {
			type: Array,
			default: () => [],
		},
		subAdminsGroups: {
			type: Array,
			default: () => [],
		},
		quotaOptions: {
			type: Array,
			default: () => [],
		},
		showConfig: {
			type: Object,
			default: () => ({}),
		},
		languages: {
			type: Array,
			required: true,
		},
		externalActions: {
			type: Array,
			default: () => [],
		},
	},
	computed: {
		/* GROUPS MANAGEMENT */
		userGroups() {
			const userGroups = this.groups.filter(group => this.user.groups.includes(group.id))
			return userGroups
		},
		userSubAdminsGroups() {
			const userSubAdminsGroups = this.subAdminsGroups.filter(group => this.user.subadmin.includes(group.id))
			return userSubAdminsGroups
		},
		availableGroups() {
			return this.groups.map((group) => {
				// clone object because we don't want
				// to edit the original groups
				const groupClone = Object.assign({}, group)

				// two settings here:
				// 1. user NOT in group but no permission to add
				// 2. user is in group but no permission to remove
				groupClone.$isDisabled
					= (group.canAdd === false
						&& !this.user.groups.includes(group.id))
					|| (group.canRemove === false
						&& this.user.groups.includes(group.id))
				return groupClone
			})
		},

		/* QUOTA MANAGEMENT */
		usedSpace() {
			if (this.user.quota.used) {
				return t('settings', '{size} used', { size: OC.Util.humanFileSize(this.user.quota.used) })
			}
			return t('settings', '{size} used', { size: OC.Util.humanFileSize(0) })
		},
		usedQuota() {
			let quota = this.user.quota.quota
			if (quota > 0) {
				quota = Math.min(100, Math.round(this.user.quota.used / quota * 100))
			} else {
				const usedInGB = this.user.quota.used / (10 * Math.pow(2, 30))
				// asymptotic curve approaching 50% at 10GB to visualize used stace with infinite quota
				quota = 95 * (1 - (1 / (usedInGB + 1)))
			}
			return isNaN(quota) ? 0 : quota
		},
		// Mapping saved values to objects
		userQuota() {
			if (this.user.quota.quota >= 0) {
				// if value is valid, let's map the quotaOptions or return custom quota
				const humanQuota = OC.Util.humanFileSize(this.user.quota.quota)
				const userQuota = this.quotaOptions.find(quota => quota.id === humanQuota)
				return userQuota || { id: humanQuota, label: humanQuota }
			} else if (this.user.quota.quota === 'default') {
				// default quota is replaced by the proper value on load
				return this.quotaOptions[0]
			}
			return this.quotaOptions[1] // unlimited
		},

		/* PASSWORD POLICY? */
		minPasswordLength() {
			return this.$store.getters.getPasswordPolicyMinLength
		},

		/* LANGUAGE */
		userLanguage() {
			const availableLanguages = this.languages[0].languages.concat(this.languages[1].languages)
			const userLang = availableLanguages.find(lang => lang.code === this.user.language)
			if (typeof userLang !== 'object' && this.user.language !== '') {
				return {
					code: this.user.language,
					name: this.user.language,
				}
			} else if (this.user.language === '') {
				return false
			}
			return userLang
		},

		/* LAST LOGIN */
		userLastLoginTooltip() {
			if (this.user.lastLogin > 0) {
				return OC.Util.formatDate(this.user.lastLogin)
			}
			return ''
		},
		userLastLogin() {
			if (this.user.lastLogin > 0) {
				return OC.Util.relativeModifiedDate(this.user.lastLogin)
			}
			return t('settings', 'Never')
		},
	},
	methods: {
		/**
		 * Generate avatar url
		 *
		 * @param {string} user The user name
		 * @param {int} size Size integer, default 32
		 * @returns {string}
		 */
		generateAvatar(user, size = 32) {
			return OC.generateUrl(
				'/avatar/{user}/{size}?v={version}',
				{
					user: user,
					size: size,
					version: oc_userconfig.avatar.version,
				}
			)
		},
	},
}
