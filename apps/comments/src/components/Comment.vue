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
	<div v-show="!deleted"
		:class="{'comment--loading': loading}"
		class="comment">
		<!-- Comment header toolbar -->
		<div class="comment__header">
			<!-- Author -->
			<Avatar class="comment__avatar"
				:display-name="actorDisplayName"
				:user="actorId"
				:size="32" />
			<span class="comment__author">{{ actorDisplayName }}</span>

			<!-- Comment actions,
				show if we have a message id and current user is author -->
			<Actions v-if="isOwnComment && id && !loading" class="comment__actions">
				<template v-if="!editing">
					<ActionButton
						:close-after-click="true"
						icon="icon-rename"
						@click="onEdit">
						{{ t('comments', 'Edit comment') }}
					</ActionButton>
					<ActionSeparator />
					<ActionButton
						:close-after-click="true"
						icon="icon-delete"
						@click="onDeleteWithUndo">
						{{ t('comments', 'Delete comment') }}
					</ActionButton>
				</template>

				<ActionButton v-else
					icon="icon-close"
					@click="onEditCancel">
					{{ t('comments', 'Cancel edit') }}
				</ActionButton>
			</Actions>

			<!-- Show loading if we're editing or deleting, not on new ones -->
			<div v-if="id && loading" class="comment_loading icon-loading-small" />

			<!-- Relative time to the comment creation -->
			<Moment v-else-if="creationDateTime" class="comment__timestamp" :timestamp="timestamp" />
		</div>

		<!-- Message editor -->
		<div class="comment__editor " v-if="editor || editing">
			<RichContenteditable ref="editor"
				v-model="localMessage"
				:auto-complete="autoComplete"
				:contenteditable="!loading"
				@submit="onSubmit" />
			<input v-tooltip="t('comments', 'Post comment')"
				:class="loading ? 'icon-loading-small' :'icon-confirm'"
				class="comment__submit"
				type="submit"
				:disabled="isEmptyMessage"
				value=""
				@click="onSubmit">
		</div>

		<!-- Message content -->
		<!-- The html is escaped and sanitized before rendering -->
		<!-- eslint-disable-next-line vue/no-v-html-->
		<div v-else
			:class="{'comment__message--expanded': expanded}"
			class="comment__message"
			@click="onExpand"
			v-html="renderedContent" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import moment from '@nextcloud/moment'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionSeparator from '@nextcloud/vue/dist/Components/ActionSeparator'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import RichContenteditable from '@nextcloud/vue/dist/Components/RichContenteditable'
import RichEditorMixin from '@nextcloud/vue/dist/Mixins/richEditor'

import Moment from './Moment'
import CommentMixin from '../mixins/CommentMixin'

export default {
	name: 'Comment',

	components: {
		ActionButton,
		Actions,
		ActionSeparator,
		Avatar,
		Moment,
		RichContenteditable,
	},
	mixins: [RichEditorMixin, CommentMixin],

	inheritAttrs: false,

	props: {
		actorDisplayName: {
			type: String,
			required: true,
		},
		actorId: {
			type: String,
			required: true,
		},
		creationDateTime: {
			type: String,
			default: null,
		},

		/**
		 * Force the editor display
		 */
		editor: {
			type: Boolean,
			default: false,
		},

		/**
		 * Provide the autocompletion data
		 */
		autoComplete: {
			type: Function,
			required: true,
		},
	},

	data() {
		return {
			expanded: false,
			// Only change data locally and update the original
			// parent data when the request is sent and resolved
			localMessage: '',
		}
	},

	computed: {

		/**
		 * Is the current user the author of this comment
		 * @returns {boolean}
		 */
		isOwnComment() {
			return getCurrentUser().uid === this.actorId
		},

		/**
		 * Rendered content as html string
		 * @returns {string}
		 */
		renderedContent() {
			if (this.isEmptyMessage) {
				return ''
			}
			return this.renderContent(this.localMessage)
		},

		isEmptyMessage() {
			return !this.localMessage || this.localMessage.trim() === ''
		},

		timestamp() {
			// seconds, not milliseconds
			return parseInt(moment(this.creationDateTime).format('x'), 10) / 1000
		},
	},

	watch: {
		// If the data change, update the local value
		message(message) {
			this.updateLocalMessage(message)
		},
	},

	beforeMount() {
		// Init localMessage
		this.updateLocalMessage(this.message)
	},

	methods: {
		/**
		 * Update local Message on outer change
		 * @param {string} message the message to set
		 */
		updateLocalMessage(message) {
			this.localMessage = message.toString()
		},

		/**
		 * Dispatch message between edit and create
		 */
		onSubmit() {
			// Do not submit if message is empty
			if (this.localMessage.trim() === '') {
				return
			}

			if (this.editor) {
				this.onNewComment(this.localMessage.trim())
				this.$nextTick(() => {
					// Focus the editor again
					this.$refs.editor.$el.focus()
				})
				return
			}
			this.onEditComment(this.localMessage.trim())
		},

		onExpand() {
			this.expanded = true
		},
	},

}
</script>

<style lang="scss" scoped>
$comment-padding: 10px;

.comment {
	position: relative;
	padding: $comment-padding 0 $comment-padding * 1.5;

	&__header {
		display: flex;
		align-items: center;
		min-height: 44px;
		padding: $comment-padding / 2 0;
	}

	&__author,
	&__actions {
		margin-left: $comment-padding !important;
	}

	&__author {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
		color: var(--color-text-maxcontrast);
	}

	&_loading,
	&__timestamp {
		margin-left: auto;
		color: var(--color-text-maxcontrast);
	}

	&__editor,
	&__message {
		position: relative;
		// Avatar size, align with author name
		padding-left: 32px + $comment-padding;
	}

	&__submit {
		position: absolute;
		right: 0;
		bottom: 0;
		width: 44px;
		height: 44px;
		// Align with input border
		margin: 1px;
		cursor: pointer;
		opacity: .7;
		border: none;
		background-color: transparent !important;

		&:disabled {
			cursor: not-allowed;
			opacity: .5;
		}

		&:focus,
		&:hover {
			opacity: 1;
		}
	}

	&__message {
		white-space: pre-wrap;
		word-break: break-word;
		max-height: 70px;
		overflow: hidden;
		&--expanded {
			max-height: none;
			overflow: visible;
		}
	}
}

.rich-contenteditable__input {
	min-height: 44px;
	margin: 0;
	padding: $comment-padding;
}

</style>
