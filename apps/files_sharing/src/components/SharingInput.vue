<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
  -
  - @author John Molakvoæ <skjnldsv@protonmail.com>
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
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<Multiselect ref="multiselect"
		class="sharing-input"
		:clear-on-select="false"
		:disabled="!canReshare"
		:hide-selected="true"
		:internal-search="false"
		:loading="loading"
		:options="options"
		:placeholder="inputPlaceholder"
		:preselect-first="true"
		:preserve-search="true"
		:searchable="true"
		:user-select="true"
		open-direction="below"
		@search-change="asyncFind"
		@select="addShare">
		<template #noOptions>
			{{ t('files_sharing', 'No recommendations. Start typing.') }}
		</template>
		<template #noResult>
			{{ noResultText }}
		</template>
	</Multiselect>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'

import Config from '../services/ConfigService'
import Share from '../models/Share'
import ShareRequests from '../mixins/ShareRequests'
import ShareTypes from '../mixins/ShareTypes'

export default {
	name: 'SharingInput',

	components: {
		Multiselect,
	},

	mixins: [ShareTypes, ShareRequests],

	props: {
		shares: {
			type: Array,
			default: () => [],
			required: true,
		},
		linkShares: {
			type: Array,
			default: () => [],
			required: true,
		},
		fileInfo: {
			type: Object,
			default: () => {},
			required: true,
		},
		reshare: {
			type: Share,
			default: null,
		},
		canReshare: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
			config: new Config(),
			loading: false,
			query: '',
			recommendations: [],
			ShareSearch: OCA.Sharing.ShareSearch.state,
			suggestions: [],
		}
	},

	computed: {
		/**
		 * Implement ShareSearch
		 * allows external appas to inject new
		 * results into the autocomplete dropdown
		 * Used for the guests app
		 *
		 * @returns {Array}
		 */
		externalResults() {
			return this.ShareSearch.results
		},
		inputPlaceholder() {
			const allowRemoteSharing = this.config.isRemoteShareAllowed

			if (!this.canReshare) {
				return t('files_sharing', 'Resharing is not allowed')
			}
			// We can always search with email addresses for users too
			if (!allowRemoteSharing) {
				return t('files_sharing', 'Name or email …')
			}

			return t('files_sharing', 'Name, email, or Federated Cloud ID …')
		},

		isValidQuery() {
			return this.query && this.query.trim() !== '' && this.query.length > this.config.minSearchStringLength
		},

		options() {
			if (this.isValidQuery) {
				return this.suggestions
			}
			return this.recommendations
		},

		noResultText() {
			if (this.loading) {
				return t('files_sharing', 'Searching …')
			}
			return t('files_sharing', 'No elements found.')
		},
	},

	mounted() {
		this.getRecommendations()
	},

	methods: {
		async asyncFind(query, id) {
			// save current query to check if we display
			// recommendations or search results
			this.query = query.trim()
			if (this.isValidQuery) {
				// start loading now to have proper ux feedback
				// during the debounce
				this.loading = true
				await this.debounceGetSuggestions(query)
			}
		},

		/**
		 * Get suggestions
		 *
		 * @param {string} search the search query
		 * @param {boolean} [lookup=false] search on lookup server
		 */
		async getSuggestions(search, lookup = false) {
			this.loading = true

			if (OC.getCapabilities().files_sharing.sharee.query_lookup_default === true) {
				lookup = true
			}

			const shareType = [
				this.SHARE_TYPES.SHARE_TYPE_USER,
				this.SHARE_TYPES.SHARE_TYPE_GROUP,
				this.SHARE_TYPES.SHARE_TYPE_REMOTE,
				this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP,
				this.SHARE_TYPES.SHARE_TYPE_CIRCLE,
				this.SHARE_TYPES.SHARE_TYPE_ROOM,
				this.SHARE_TYPES.SHARE_TYPE_GUEST,
				this.SHARE_TYPES.SHARE_TYPE_DECK,
			]

			if (OC.getCapabilities().files_sharing.public.enabled === true) {
				shareType.push(this.SHARE_TYPES.SHARE_TYPE_EMAIL)
			}

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1') + 'sharees', {
				params: {
					format: 'json',
					itemType: this.fileInfo.type === 'dir' ? 'folder' : 'file',
					search,
					lookup,
					perPage: this.config.maxAutocompleteResults,
					shareType,
				},
			})

			if (request.data.ocs.meta.statuscode !== 100) {
				console.error('Error fetching suggestions', request)
				return
			}

			const data = request.data.ocs.data
			const exact = request.data.ocs.data.exact
			data.exact = [] // removing exact from general results

			// flatten array of arrays
			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			const exactSuggestions = this.filterOutExistingShares(rawExactSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
			const suggestions = this.filterOutExistingShares(rawSuggestions)
				.map(share => this.formatForMultiselect(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)

			// lookup clickable entry
			// show if enabled and not already requested
			const lookupEntry = []
			if (data.lookupEnabled && !lookup) {
				lookupEntry.push({
					isNoUser: true,
					displayName: t('files_sharing', 'Search globally'),
					lookup: true,
				})
			}

			// if there is a condition specified, filter it
			const externalResults = this.externalResults.filter(result => !result.condition || result.condition(this))

			const allSuggestions = exactSuggestions.concat(suggestions).concat(externalResults).concat(lookupEntry)

			// Count occurances of display names in order to provide a distinguishable description if needed
			const nameCounts = allSuggestions.reduce((nameCounts, result) => {
				if (!result.displayName) {
					return nameCounts
				}
				if (!nameCounts[result.displayName]) {
					nameCounts[result.displayName] = 0
				}
				nameCounts[result.displayName]++
				return nameCounts
			}, {})

			this.suggestions = allSuggestions.map(item => {
				// Make sure that items with duplicate displayName get the shareWith applied as a description
				if (nameCounts[item.displayName] > 1 && !item.desc) {
					return { ...item, desc: item.shareWithDisplayNameUnique }
				}
				return item
			})

			this.loading = false
			console.info('suggestions', this.suggestions)
		},

		/**
		 * Debounce getSuggestions
		 *
		 * @param {...*} args the arguments
		 */
		debounceGetSuggestions: debounce(function(...args) {
			this.getSuggestions(...args)
		}, 300),

		/**
		 * Get the sharing recommendations
		 */
		async getRecommendations() {
			this.loading = true

			const request = await axios.get(generateOcsUrl('apps/files_sharing/api/v1') + 'sharees_recommended', {
				params: {
					format: 'json',
					itemType: this.fileInfo.type,
				},
			})

			if (request.data.ocs.meta.statuscode !== 100) {
				console.error('Error fetching recommendations', request)
				return
			}

			const externalResults = this.externalResults.filter(result => !result.condition || result.condition(this))

			const exact = request.data.ocs.data.exact

			// flatten array of arrays
			const rawRecommendations = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			this.recommendations = this.filterOutExistingShares(rawRecommendations)
				.map(share => this.formatForMultiselect(share))
				.concat(externalResults)

			this.loading = false
			console.info('recommendations', this.recommendations)
		},

		/**
		 * Filter out existing shares from
		 * the provided shares search results
		 *
		 * @param {Object[]} shares the array of shares object
		 * @returns {Object[]}
		 */
		filterOutExistingShares(shares) {
			return shares.reduce((arr, share) => {
				// only check proper objects
				if (typeof share !== 'object') {
					return arr
				}
				try {
					if (share.value.shareType === this.SHARE_TYPES.SHARE_TYPE_USER) {
						// filter out current user
						if (share.value.shareWith === getCurrentUser().uid) {
							return arr
						}

						// filter out the owner of the share
						if (this.reshare && share.value.shareWith === this.reshare.owner) {
							return arr
						}
					}

					// filter out existing mail shares
					if (share.value.shareType === this.SHARE_TYPES.SHARE_TYPE_EMAIL) {
						const emails = this.linkShares.map(elem => elem.shareWith)
						if (emails.indexOf(share.value.shareWith.trim()) !== -1) {
							return arr
						}
					} else { // filter out existing shares
						// creating an object of uid => type
						const sharesObj = this.shares.reduce((obj, elem) => {
							obj[elem.shareWith] = elem.type
							return obj
						}, {})

						// if shareWith is the same and the share type too, ignore it
						const key = share.value.shareWith.trim()
						if (key in sharesObj
							&& sharesObj[key] === share.value.shareType) {
							return arr
						}
					}

					// ALL GOOD
					// let's add the suggestion
					arr.push(share)
				} catch {
					return arr
				}
				return arr
			}, [])
		},

		/**
		 * Get the icon based on the share type
		 * @param {number} type the share type
		 * @returns {string} the icon class
		 */
		shareTypeToIcon(type) {
			switch (type) {
			case this.SHARE_TYPES.SHARE_TYPE_GUEST:
				// default is a user, other icons are here to differenciate
				// themselves from it, so let's not display the user icon
				// case this.SHARE_TYPES.SHARE_TYPE_REMOTE:
				// case this.SHARE_TYPES.SHARE_TYPE_USER:
				return 'icon-user'
			case this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP:
			case this.SHARE_TYPES.SHARE_TYPE_GROUP:
				return 'icon-group'
			case this.SHARE_TYPES.SHARE_TYPE_EMAIL:
				return 'icon-mail'
			case this.SHARE_TYPES.SHARE_TYPE_CIRCLE:
				return 'icon-circle'
			case this.SHARE_TYPES.SHARE_TYPE_ROOM:
				return 'icon-room'
			case this.SHARE_TYPES.SHARE_TYPE_DECK:
				return 'icon-deck'

			default:
				return ''
			}
		},

		/**
		 * Format shares for the multiselect options
		 * @param {Object} result select entry item
		 * @returns {Object}
		 */
		formatForMultiselect(result) {
			let desc
			if ((result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_REMOTE
					|| result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP
			) && result.value.server) {
				desc = t('files_sharing', 'on {server}', { server: result.value.server })
			} else if (result.value.shareType === this.SHARE_TYPES.SHARE_TYPE_EMAIL) {
				desc = result.value.shareWith
			} else {
				desc = result.shareWithDescription ?? ''
			}

			return {
				shareWith: result.value.shareWith,
				shareType: result.value.shareType,
				user: result.uuid || result.value.shareWith,
				isNoUser: result.value.shareType !== this.SHARE_TYPES.SHARE_TYPE_USER,
				displayName: result.name || result.label,
				desc,
				shareWithDisplayNameUnique: result.shareWithDisplayNameUnique || '',
				icon: this.shareTypeToIcon(result.value.shareType),
			}
		},

		/**
		 * Process the new share request
		 * @param {Object} value the multiselect option
		 */
		async addShare(value) {
			if (value.lookup) {
				await this.getSuggestions(this.query, true)

				// focus the input again
				this.$nextTick(() => {
					this.$refs.multiselect.$el.querySelector('.multiselect__input').focus()
				})
				return true
			}

			// TODO: reset the search string when done
			// https://github.com/shentao/vue-multiselect/issues/633

			// handle externalResults from OCA.Sharing.ShareSearch
			if (value.handler) {
				const share = await value.handler(this)
				this.$emit('add:share', new Share(share))
				return true
			}

			this.loading = true
			try {
				const path = (this.fileInfo.path + '/' + this.fileInfo.name).replace('//', '/')
				const share = await this.createShare({
					path,
					shareType: value.shareType,
					shareWith: value.shareWith,
					permissions: this.fileInfo.sharePermissions & OC.getCapabilities().files_sharing.default_permissions,
				})
				this.$emit('add:share', share)

				this.getRecommendations()

			} catch (response) {
				// focus back if any error
				const input = this.$refs.multiselect.$el.querySelector('input')
				if (input) {
					input.focus()
				}
				this.query = value.shareWith
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style lang="scss">
.sharing-input {
	width: 100%;
	margin: 10px 0;

	// properly style the lookup entry
	.multiselect__option {
		span[lookup] {
			.avatardiv {
				background-image: var(--icon-search-fff);
				background-repeat: no-repeat;
				background-position: center;
				background-color: var(--color-text-maxcontrast) !important;
				div {
					display: none;
				}
			}
		}
	}
}
</style>
