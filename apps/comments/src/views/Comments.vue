<!--
  - @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
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
	<div class="comments" :class="{ 'icon-loading': isFirstLoading }">
		<!-- Editor -->
		<Comment v-bind="editorData"
			:auto-complete="autoComplete"
			:editor="true"
			:ressource-id="ressourceId"
			class="comments__writer"
			@new="onNewComment" />

		<template v-if="!isFirstLoading">
			<EmptyContent v-if="!hasComments && done" icon="icon-comment">
				{{ t('comments', 'No comments yet, start the conversation!') }}
			</EmptyContent>

			<!-- Comments -->
			<Comment v-for="comment in comments"
				v-else
				:key="comment.props.id"
				v-bind="comment.props"
				:auto-complete="autoComplete"
				:message.sync="comment.props.message"
				:ressource-id="ressourceId"
				:user-data="genMentionsData(comment.props.mentions)"
				class="comments__list"
				@delete="onDelete" />

			<!-- Loading more message -->
			<div v-if="loading && !isFirstLoading" class="comments__info icon-loading" />

			<div v-else-if="hasComments && done" class="comments__info">
				{{ t('comments', 'No more messages') }}
			</div>

			<!-- Error message -->
			<EmptyContent v-else-if="error" class="comments__error" icon="icon-error">
				{{ error }}
				<template #desc>
					<button icon="icon-history" @click="getComments">
						{{ t('comments', 'Retry') }}
					</button>
				</template>
			</EmptyContent>
		</template>
	</div>
</template>

<script>
import { generateOcsUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import VTooltip from 'v-tooltip'
import Vue from 'vue'

import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

import Comment from '../components/Comment'
import getComments, { DEFAULT_LIMIT } from '../services/GetComments'
import cancelableRequest from '../utils/cancelableRequest'

Vue.use(VTooltip)

export default {
	name: 'Comments',

	components: {
		// Avatar,
		Comment,
		EmptyContent,
	},

	data() {
		return {
			error: '',
			loading: false,
			done: false,

			ressourceId: null,
			offset: 0,
			comments: [],

			cancelRequest: () => {},

			editorData: {
				actorDisplayName: getCurrentUser().displayName,
				actorId: getCurrentUser().uid,
				key: 'editor',
			},

			Comment,
		}
	},

	computed: {
		hasComments() {
			return this.comments.length > 0
		},
		isFirstLoading() {
			return this.loading && this.offset === 0
		},
	},

	methods: {
		/**
		 * Update current ressourceId and fetch new data
		 * @param {Number} ressourceId the current ressourceId (fileId...)
		 */
		async update(ressourceId) {
			this.ressourceId = ressourceId
			this.resetState()
			this.getComments()
		},

		/**
		 * Ran when the bottom of the tab is reached
		 */
		onScrollBottomReached() {
			/**
			 * Do not fetch more if we:
			 * - are showing an error
			 * - already fetched everything
			 * - are currently loading
			 */
			if (this.error || this.done || this.loading) {
				return
			}
			this.getComments()
		},

		/**
		 * Make sure we have all mentions as Array of objects
		 * @param {Array} mentions the mentions list
		 * @returns {Object[]}
		 */
		genMentionsData(mentions) {
			const list = Object.values(mentions).flat()
			return list.reduce((mentions, mention) => {
				mentions[mention.mentionId] = {
					// TODO: support groups
					icon: 'icon-user',
					id: mention.mentionId,
					label: mention.mentionDisplayName,
					source: 'users',
					primary: getCurrentUser().uid === mention.mentionId,
				}
				return mentions
			}, {})
		},

		/**
		 * Get the existing shares infos
		 */
		async getComments() {
			// Cancel any ongoing request
			this.cancelRequest('cancel')

			try {
				this.loading = true
				this.error = ''

				// Init cancellable request
				const { request, cancel } = cancelableRequest(getComments)
				this.cancelRequest = cancel

				// Fetch comments
				const comments = await request({
					commentsType: this.commentsType,
					ressourceId: this.ressourceId,
				}, { offset: this.offset })

				this.logger.debug(`Processed ${comments.length} comments`, { comments })

				// We received less than the requested amount,
				// we're done fetching comments
				if (comments.length < DEFAULT_LIMIT) {
					this.done = true
				}

				// Insert results
				this.comments.push(...comments)

				// Increase offset for next fetch
				this.offset += DEFAULT_LIMIT
			} catch (error) {
				if (error.message === 'cancel') {
					return
				}
				this.error = t('comments', 'Unable to load the comments list')
				console.error('Error loading the comments list', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Autocomplete @mentions
		 * @param {string} search the query
		 * @param {Function} callback the callback to process the results with
		 */
		async autoComplete(search, callback) {
			const results = await axios.get(generateOcsUrl('core', 2) + 'autocomplete/get', {
				params: {
					search,
					itemType: 'files',
					itemId: this.ressourceId,
					sorter: 'commenters|share-recipients',
					limit: OC.appConfig?.comments?.maxAutoCompleteResults || 25,
				},
			})
			return callback(results.data.ocs.data)
		},

		/**
		 * Add newly created comment to the list
		 * @param {Object} comment the new comment
		 */
		onNewComment(comment) {
			this.comments.unshift(comment)
		},

		/**
		 * Remove deleted comment from the list
		 * @param {number} id the deleted comment
		 */
		onDelete(id) {
			const index = this.comments.findIndex(comment => comment.props.id === id)
			if (index > -1) {
				this.comments.splice(index, 1)
			} else {
				console.error('Could not find the deleted comment in the list', id)
			}
		},

		/**
		 * Reset the current view to its default state
		 */
		resetState() {
			this.error = ''
			this.loading = false
			this.done = false
			this.offset = 0
			this.comments = []
		},
	},
}
</script>

<style lang="scss" scoped>
.comments {
	// Do not add emptycontent top margin
	&__error{
		margin-top: 0;
	}

	&__info {
		height: 60px;
		color: var(--color-text-maxcontrast);
		text-align: center;
		line-height: 60px;
	}
}
</style>
