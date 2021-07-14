/**
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Michael Jobst <mjobst+github@tecratech.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

(function() {

	_.extend(OC.Files.Client, {
		PROPERTY_COMMENTS_UNREAD: '{' + OC.Files.Client.NS_OWNCLOUD + '}comments-unread',
	})

	OCA.Comments = _.extend({}, OCA.Comments)
	if (!OCA.Comments) {
		/**
		 * @namespace
		 */
		OCA.Comments = {}
	}

	/**
	 * @namespace
	 */
	OCA.Comments.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		_formatCommentCount(count) {
			return OCA.Comments.Templates.filesplugin({
				count,
				countMessage: n('comments', '%n unread comment', '%n unread comments', count),
				iconUrl: OC.imagePath('core', 'actions/comment'),
			})
		},

		attach(fileList) {
			const self = this
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			const oldGetWebdavProperties = fileList._getWebdavProperties
			fileList._getWebdavProperties = function() {
				const props = oldGetWebdavProperties.apply(this, arguments)
				props.push(OC.Files.Client.PROPERTY_COMMENTS_UNREAD)
				return props
			}

			fileList.filesClient.addFileInfoParser(function(response) {
				const data = {}
				const props = response.propStat[0].properties
				const commentsUnread = props[OC.Files.Client.PROPERTY_COMMENTS_UNREAD]
				if (!_.isUndefined(commentsUnread) && commentsUnread !== '') {
					data.commentsUnread = parseInt(commentsUnread, 10)
				}
				return data
			})

			fileList.$el.addClass('has-comments')
			const oldCreateRow = fileList._createRow
			fileList._createRow = function(fileData) {
				const $tr = oldCreateRow.apply(this, arguments)
				if (fileData.commentsUnread) {
					$tr.attr('data-comments-unread', fileData.commentsUnread)
				}
				return $tr
			}

			// register "comment" action for reading comments
			fileList.fileActions.registerAction({
				name: 'Comment',
				displayName(context) {
					if (context && context.$file) {
						const unread = parseInt(context.$file.data('comments-unread'), 10)
						if (unread >= 0) {
							return n('comments', '1 new comment', '{unread} new comments', unread, { unread })
						}
					}
					return t('comments', 'Comment')
				},
				mime: 'all',
				order: -140,
				iconClass: 'icon-comment',
				permissions: OC.PERMISSION_READ,
				type: OCA.Files.FileActions.TYPE_INLINE,
				render(actionSpec, isDefault, context) {
					const $file = context.$file
					const unreadComments = $file.data('comments-unread')
					if (unreadComments) {
						const $actionLink = $(self._formatCommentCount(unreadComments))
						context.$file.find('a.name>span.fileactions').append($actionLink)
						return $actionLink
					}
					return ''
				},
				actionHandler(fileName, context) {
					context.$file.find('.action-comment').tooltip('hide')
					// open sidebar in comments section
					OCA.Files.Sidebar.setActiveTab('comments')
					OCA.Files.Sidebar.open(context.dir + '/' + fileName)
				},
			})

			// add attribute to "elementToFile"
			const oldElementToFile = fileList.elementToFile
			fileList.elementToFile = function($el) {
				const fileInfo = oldElementToFile.apply(this, arguments)
				const commentsUnread = $el.data('comments-unread')
				if (commentsUnread) {
					fileInfo.commentsUnread = commentsUnread
				}
				return fileInfo
			}
		},
	}

})()

OC.Plugins.register('OCA.Files.FileList', OCA.Comments.FilesPlugin)
