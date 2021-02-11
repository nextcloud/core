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

import PQueue from 'p-queue'
import debounce from 'debounce'

import Share from '../models/Share'
import SharesRequests from './ShareRequests'
import ShareTypes from './ShareTypes'
import Config from '../services/ConfigService'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	mixins: [SharesRequests, ShareTypes],

	props: {
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		share: {
			type: Share,
			default: null,
		},
		isUnique: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			config: new Config(),

			// errors helpers
			errors: {},

			// component status toggles
			loading: false,
			saving: false,
			open: false,

			// concurrency management queue
			// we want one queue per share
			updateQueue: new PQueue({ concurrency: 1 }),

			/**
			 * ! This allow vue to make the Share class state reactive
			 * ! do not remove it ot you'll lose all reactivity here
			 */
			reactiveState: this.share?.state,

			SHARE_TYPES: {
				SHARE_TYPE_USER: OC.Share.SHARE_TYPE_USER,
				SHARE_TYPE_GROUP: OC.Share.SHARE_TYPE_GROUP,
				SHARE_TYPE_LINK: OC.Share.SHARE_TYPE_LINK,
				SHARE_TYPE_EMAIL: OC.Share.SHARE_TYPE_EMAIL,
				SHARE_TYPE_REMOTE: OC.Share.SHARE_TYPE_REMOTE,
				SHARE_TYPE_CIRCLE: OC.Share.SHARE_TYPE_CIRCLE,
				SHARE_TYPE_GUEST: OC.Share.SHARE_TYPE_GUEST,
				SHARE_TYPE_REMOTE_GROUP: OC.Share.SHARE_TYPE_REMOTE_GROUP,
				SHARE_TYPE_ROOM: OC.Share.SHARE_TYPE_ROOM,
			},
		}
	},

	computed: {

		/**
		 * Does the current share have a note
		 * @returns {boolean}
		 */
		hasNote: {
			get() {
				return this.share.note !== ''
			},
			set(enabled) {
				this.share.note = enabled
					? null // enabled but user did not changed the content yet
					: '' // empty = no note = disabled
			},
		},

		dateTomorrow() {
			return moment().add(1, 'days')
		},

		/**
		 * Datepicker lang values
		 * https://github.com/nextcloud/nextcloud-vue/pull/146
		 * TODO: have this in vue-components
		 *
		 * @returns {int}
		 */
		firstDay() {
			return window.firstDay
				? window.firstDay
				: 0 // sunday as default
		},
		lang() {
			// fallback to default in case of unavailable data
			return {
				days: window.dayNamesShort
					? window.dayNamesShort // provided by nextcloud
					: ['Sun.', 'Mon.', 'Tue.', 'Wed.', 'Thu.', 'Fri.', 'Sat.'],
				months: window.monthNamesShort
					? window.monthNamesShort // provided by nextcloud
					: ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May.', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'],
				placeholder: {
					date: 'Select Date', // TODO: Translate
				},
			}
		},

		isShareOwner() {
			return this.share && this.share.owner === getCurrentUser().uid
		},

	},

	methods: {
		/**
		 * Check if a share is valid before
		 * firing the request
		 *
		 * @param {Share} share the share to check
		 * @returns {Boolean}
		 */
		checkShare(share) {
			if (share.password) {
				if (typeof share.password !== 'string' || share.password.trim() === '') {
					return false
				}
			}
			if (share.expirationDate) {
				const date = moment(share.expirationDate)
				if (!date.isValid()) {
					return false
				}
			}
			return true
		},

		/**
		 * ActionInput can be a little tricky to work with.
		 * Since we expect a string and not a Date,
		 * we need to process the value here
		 *
		 * @param {Date} date js date to be parsed by moment.js
		 */
		onExpirationChange(date) {
			// format to YYYY-MM-DD
			const value = moment(date).format('YYYY-MM-DD')
			this.share.expireDate = value
			this.queueUpdate('expireDate')
		},

		/**
		 * Uncheck expire date
		 * We need this method because @update:checked
		 * is ran simultaneously as @uncheck, so
		 * so we cannot ensure data is up-to-date
		 */
		onExpirationDisable() {
			this.share.expireDate = ''
			this.queueUpdate('expireDate')
		},

		/**
		 * Note changed, let's save it to a different key
		 * @param {String} note the share note
		 */
		onNoteChange(note) {
			this.$set(this.share, 'newNote', note.trim())
		},

		/**
		 * When the note change, we trim, save and dispatch
		 *
		 * @param {string} note the note
		 */
		onNoteSubmit() {
			if (this.share.newNote) {
				this.share.note = this.share.newNote
				this.$delete(this.share, 'newNote')
				this.queueUpdate('note')
			}
		},

		/**
		 * Delete share button handler
		 */
		async onDelete() {
			try {
				this.loading = true
				this.open = false
				await this.deleteShare(this.share.id)
				console.debug('Share deleted', this.share.id)
				this.$emit('remove:share', this.share)
			} catch (error) {
				// re-open menu if error
				this.open = true
			} finally {
				this.loading = false
			}
		},

		/**
		 * Send an update of the share to the queue
		 *
		 * @param {string} propertyNames the properties to sync
		 */
		queueUpdate(...propertyNames) {
			if (propertyNames.length === 0) {
				// Nothing to update
				return
			}

			if (this.share.id) {
				const properties = {}
				// force value to string because that is what our
				// share api controller accepts
				propertyNames.map(p => (properties[p] = this.share[p].toString()))

				this.updateQueue.add(async() => {
					this.saving = true
					this.errors = {}
					try {
						await this.updateShare(this.share.id, properties)

						// clear any previous errors
						this.$delete(this.errors, propertyNames[0])

						// reset password state after sync
						this.$delete(this.share, 'newPassword')
					} catch ({ message }) {
						if (message && message !== '') {
							this.onSyncError(propertyNames[0], message)
						}
					} finally {
						this.saving = false
					}
				})
			} else {
				console.error('Cannot update share.', this.share, 'No valid id')
			}
		},

		/**
		 * Manage sync errors
		 * @param {string} property the errored property, e.g. 'password'
		 * @param {string} message the error message
		 */
		onSyncError(property, message) {
			// re-open menu if closed
			this.open = true
			switch (property) {
			case 'password':
			case 'pending':
			case 'expireDate':
			case 'label':
			case 'note': {
				// show error
				this.$set(this.errors, property, message)

				let propertyEl = this.$refs[property]
				if (propertyEl) {
					if (propertyEl.$el) {
						propertyEl = propertyEl.$el
					}
					// focus if there is a focusable action element
					const focusable = propertyEl.querySelector('.focusable')
					if (focusable) {
						focusable.focus()
					}
				}
				break
			}
			case 'sendPasswordByTalk': {
				// show error
				this.$set(this.errors, property, message)

				// Restore previous state
				this.share.sendPasswordByTalk = !this.share.sendPasswordByTalk
				break
			}
			}
		},

		/**
		 * Debounce queueUpdate to avoid requests spamming
		 * more importantly for text data
		 *
		 * @param {string} property the property to sync
		 */
		debounceQueueUpdate: debounce(function(property) {
			this.queueUpdate(property)
		}, 500),

		/**
		 * Returns which dates are disabled for the datepicker
		 * @param {Date} date date to check
		 * @returns {boolean}
		 */
		disabledDate(date) {
			const dateMoment = moment(date)
			return (this.dateTomorrow && dateMoment.isBefore(this.dateTomorrow, 'day'))
				|| (this.dateMaxEnforced && dateMoment.isSameOrAfter(this.dateMaxEnforced, 'day'))
		},
	},
}
