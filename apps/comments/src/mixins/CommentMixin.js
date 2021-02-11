/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import NewComment from '../services/NewComment'
import DeleteComment from '../services/DeleteComment'
import EditComment from '../services/EditComment'
import { showError, showUndo, TOAST_UNDO_TIMEOUT } from '@nextcloud/dialogs'

export default {
	props: {
		id: {
			type: Number,
			default: null,
		},
		message: {
			type: String,
			default: '',
		},
		ressourceId: {
			type: [String, Number],
			required: true,
		},
	},

	data() {
		return {
			deleted: false,
			editing: false,
			loading: false,
		}
	},

	methods: {
		// EDITION
		onEdit() {
			this.editing = true
		},
		onEditCancel() {
			this.editing = false
			// Restore original value
			this.updateLocalMessage(this.message)
		},
		async onEditComment(message) {
			this.loading = true
			try {
				await EditComment(this.commentsType, this.ressourceId, this.id, message)
				this.logger.debug('Comment edited', { commentsType: this.commentsType, ressourceId: this.ressourceId, id: this.id, message })
				this.$emit('update:message', message)
				this.editing = false
			} catch (error) {
				showError(t('comments', 'An error occurred while trying to edit the comment'))
				console.error(error)
			} finally {
				this.loading = false
			}
		},

		// DELETION
		onDeleteWithUndo() {
			this.deleted = true
			const timeOutDelete = setTimeout(this.onDelete, TOAST_UNDO_TIMEOUT)
			showUndo(t('comments', 'Comment deleted'), () => {
				clearTimeout(timeOutDelete)
				this.deleted = false
			})
		},
		async onDelete() {
			try {
				await DeleteComment(this.commentsType, this.ressourceId, this.id)
				this.logger.debug('Comment deleted', { commentsType: this.commentsType, ressourceId: this.ressourceId, id: this.id })
				this.$emit('delete', this.id)
			} catch (error) {
				showError(t('comments', 'An error occurred while trying to delete the comment'))
				console.error(error)
				this.deleted = false
			}
		},

		// CREATION
		async onNewComment(message) {
			this.loading = true
			try {
				const newComment = await NewComment(this.commentsType, this.ressourceId, message)
				this.logger.debug('New comment posted', { commentsType: this.commentsType, ressourceId: this.ressourceId, newComment })
				this.$emit('new', newComment)

				// Clear old content
				this.$emit('update:message', '')
				this.localMessage = ''
			} catch (error) {
				showError(t('comments', 'An error occurred while trying to create the comment'))
				console.error(error)
			} finally {
				this.loading = false
			}
		},
	},
}
