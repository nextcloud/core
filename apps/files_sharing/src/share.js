/* eslint-disable */
/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {

	_.extend(OC.Files.Client, {
		PROPERTY_SHARE_TYPES:	'{' + OC.Files.Client.NS_OWNCLOUD + '}share-types',
		PROPERTY_OWNER_ID:	'{' + OC.Files.Client.NS_OWNCLOUD + '}owner-id',
		PROPERTY_OWNER_DISPLAY_NAME:	'{' + OC.Files.Client.NS_OWNCLOUD + '}owner-display-name'
	})

	if (!OCA.Sharing) {
		OCA.Sharing = {}
	}

	/**
	 * @namespace
	 */
	OCA.Sharing.Util = {

		/**
		 * Regular expression for splitting parts of remote share owners:
		 * "user@example.com/"
		 * "user@example.com/path/to/owncloud"
		 * "user@anotherexample.com@example.com/path/to/owncloud
		 */
		_REMOTE_OWNER_REGEXP: new RegExp('^(([^@]*)@(([^@^/^\\s]*)@)?)([^[\\s/]*)([/](.*))?$'),

		/**
		 * Initialize the sharing plugin.
		 *
		 * Registers the "Share" file action and adds additional
		 * DOM attributes for the sharing file info.
		 *
		 * @param {OCA.Files.FileList} fileList file list to be extended
		 */
		attach: function(fileList) {
			// core sharing is disabled/not loaded
			if (!OC.Share) {
				return
			}
			if (fileList.id === 'trashbin' || fileList.id === 'files.public') {
				return
			}
			var fileActions = fileList.fileActions
			var oldCreateRow = fileList._createRow
			fileList._createRow = function(fileData) {

				var tr = oldCreateRow.apply(this, arguments)
				var sharePermissions = OCA.Sharing.Util.getSharePermissions(fileData)

				if (fileData.permissions === 0) {
					// no permission, disabling sidebar
					delete fileActions.actions.all.Comment
					delete fileActions.actions.all.Details
					delete fileActions.actions.all.Goto
				}
				tr.attr('data-share-permissions', sharePermissions)
				if (fileData.shareOwner) {
					tr.attr('data-share-owner', fileData.shareOwner)
					tr.attr('data-share-owner-id', fileData.shareOwnerId)
					// user should always be able to rename a mount point
					if (fileData.mountType === 'shared-root') {
						tr.attr('data-permissions', fileData.permissions | OC.PERMISSION_UPDATE)
					}
				}
				if (fileData.recipientData && !_.isEmpty(fileData.recipientData)) {
					tr.attr('data-share-recipient-data', JSON.stringify(fileData.recipientData))
				}
				if (fileData.shareTypes) {
					tr.attr('data-share-types', fileData.shareTypes.join(','))
				}
				return tr
			}

			var oldElementToFile = fileList.elementToFile
			fileList.elementToFile = function($el) {
				var fileInfo = oldElementToFile.apply(this, arguments)
				fileInfo.sharePermissions = $el.attr('data-share-permissions') || undefined
				fileInfo.shareOwner = $el.attr('data-share-owner') || undefined
				fileInfo.shareOwnerId = $el.attr('data-share-owner-id') || undefined

				if ($el.attr('data-share-types')) {
					fileInfo.shareTypes = $el.attr('data-share-types').split(',')
				}

				if ($el.attr('data-expiration')) {
					var expirationTimestamp = parseInt($el.attr('data-expiration'))
					fileInfo.shares = []
					fileInfo.shares.push({ expiration: expirationTimestamp })
				}

				return fileInfo
			}

			var oldGetWebdavProperties = fileList._getWebdavProperties
			fileList._getWebdavProperties = function() {
				var props = oldGetWebdavProperties.apply(this, arguments)
				props.push(OC.Files.Client.PROPERTY_OWNER_ID)
				props.push(OC.Files.Client.PROPERTY_OWNER_DISPLAY_NAME)
				props.push(OC.Files.Client.PROPERTY_SHARE_TYPES)
				return props
			}

			fileList.filesClient.addFileInfoParser(function(response) {
				var data = {}
				var props = response.propStat[0].properties
				var permissionsProp = props[OC.Files.Client.PROPERTY_PERMISSIONS]

				if (permissionsProp && permissionsProp.indexOf('S') >= 0) {
					data.shareOwner = props[OC.Files.Client.PROPERTY_OWNER_DISPLAY_NAME]
					data.shareOwnerId = props[OC.Files.Client.PROPERTY_OWNER_ID]
				}

				var shareTypesProp = props[OC.Files.Client.PROPERTY_SHARE_TYPES]
				if (shareTypesProp) {
					data.shareTypes = _.chain(shareTypesProp).filter(function(xmlvalue) {
						return (xmlvalue.namespaceURI === OC.Files.Client.NS_OWNCLOUD && xmlvalue.nodeName.split(':')[1] === 'share-type')
					}).map(function(xmlvalue) {
						return parseInt(xmlvalue.textContent || xmlvalue.text, 10)
					}).value()
				}

				return data
			})

			// use delegate to catch the case with multiple file lists
			fileList.$el.on('fileActionsReady', function(ev) {
				var $files = ev.$files

				_.each($files, function(file) {
					var $tr = $(file)
					var shareTypes = $tr.attr('data-share-types') || ''
					var shareOwner = $tr.attr('data-share-owner')
					if (shareTypes || shareOwner) {
						var hasLink = false
						var hasShares = false
						_.each(shareTypes.split(',') || [], function(shareType) {
							shareType = parseInt(shareType, 10)
							if (shareType === OC.Share.SHARE_TYPE_LINK) {
								hasLink = true
							} else if (shareType === OC.Share.SHARE_TYPE_EMAIL) {
								hasLink = true
							} else if (shareType === OC.Share.SHARE_TYPE_USER) {
								hasShares = true
							} else if (shareType === OC.Share.SHARE_TYPE_GROUP) {
								hasShares = true
							} else if (shareType === OC.Share.SHARE_TYPE_REMOTE) {
								hasShares = true
							} else if (shareType === OC.Share.SHARE_TYPE_CIRCLE) {
								hasShares = true
							} else if (shareType === OC.Share.SHARE_TYPE_ROOM) {
								hasShares = true
							}
						})
						OCA.Sharing.Util._updateFileActionIcon($tr, hasShares, hasLink)
					}
				})
			})

			fileList.$el.on('changeDirectory', function() {
				OCA.Sharing.sharesLoaded = false
			})

			fileActions.registerAction({
				name: 'Share',
				displayName: function(context) {
					if (context && context.$file) {
						var shareType = parseInt(context.$file.data('share-types'), 10)
						var shareOwner = context.$file.data('share-owner-id')
						if (shareType >= 0 || shareOwner) {
							return t('core', 'Shared')
						}
					}
					return t('core', 'Share')
				},
				altText: t('core', 'Share'),
				mime: 'all',
				order: -150,
				permissions: OC.PERMISSION_ALL,
				iconClass: function(fileName, context) {
					var shareType = parseInt(context.$file.data('share-types'), 10)
					if (shareType === OC.Share.SHARE_TYPE_EMAIL
						|| shareType === OC.Share.SHARE_TYPE_LINK) {
						return 'icon-public'
					}
					return 'icon-shared'
				},
				icon: function(fileName, context) {
					var shareOwner = context.$file.data('share-owner-id')
					if (shareOwner) {
						return OC.generateUrl(`/avatar/${shareOwner}/32`)
					}
				},
				type: OCA.Files.FileActions.TYPE_INLINE,
				actionHandler: function(fileName, context) {
					// do not open sidebar if permission is set and equal to 0
					var permissions = parseInt(context.$file.data('share-permissions'), 10)
					if (isNaN(permissions) || permissions > 0) {
						fileList.showDetailsView(fileName, 'sharing')
					}
				},
				render: function(actionSpec, isDefault, context) {
					var permissions = parseInt(context.$file.data('permissions'), 10)
					// if no share permissions but share owner exists, still show the link
					if ((permissions & OC.PERMISSION_SHARE) !== 0 || context.$file.attr('data-share-owner')) {
						return fileActions._defaultRenderAction.call(fileActions, actionSpec, isDefault, context)
					}
					// don't render anything
					return null
				}
			})

			// register share breadcrumbs component
			var breadCrumbSharingDetailView = new OCA.Sharing.ShareBreadCrumbView()
			fileList.registerBreadCrumbDetailView(breadCrumbSharingDetailView)
		},

		/**
		 * Update file list data attributes
		 */
		_updateFileListDataAttributes: function(fileList, $tr, shareModel) {
			// files app current cannot show recipients on load, so we don't update the
			// icon when changed for consistency
			if (fileList.id === 'files') {
				return
			}
			var recipients = _.pluck(shareModel.get('shares'), 'share_with_displayname')
			// note: we only update the data attribute because updateIcon()
			if (recipients.length) {
				var recipientData = _.mapObject(shareModel.get('shares'), function(share) {
					return { shareWith: share.share_with, shareWithDisplayName: share.share_with_displayname }
				})
				$tr.attr('data-share-recipient-data', JSON.stringify(recipientData))
			} else {
				$tr.removeAttr('data-share-recipient-data')
			}
		},

		/**
		 * Update the file action share icon for the given file
		 *
		 * @param $tr file element of the file to update
		 * @param {boolean} hasUserShares true if a user share exists
		 * @param {boolean} hasLinkShares true if a link share exists
		 *
		 * @returns {boolean} true if the icon was set, false otherwise
		 */
		_updateFileActionIcon: function($tr, hasUserShares, hasLinkShares) {
			// if the statuses are loaded already, use them for the icon
			// (needed when scrolling to the next page)
			if (hasUserShares || hasLinkShares || $tr.attr('data-share-recipient-data') || $tr.attr('data-share-owner')) {
				OCA.Sharing.Util._markFileAsShared($tr, true, hasLinkShares)
				return true
			}
			return false
		},

		/**
		 * Marks/unmarks a given file as shared by changing its action icon
		 * and folder icon.
		 *
		 * @param $tr file element to mark as shared
		 * @param hasShares whether shares are available
		 * @param hasLink whether link share is available
		 */
		_markFileAsShared: function($tr, hasShares, hasLink) {
			var action = $tr.find('.fileactions .action[data-action="Share"]')
			var type = $tr.data('type')
			var icon = action.find('.icon')
			var message, recipients, avatars
			var ownerId = $tr.attr('data-share-owner-id')
			var owner = $tr.attr('data-share-owner')
			var mountType = $tr.attr('data-mounttype')
			var shareFolderIcon
			var iconClass = 'icon-shared'
			action.removeClass('shared-style')
			// update folder icon
			if (type === 'dir' && (hasShares || hasLink || ownerId)) {
				if (typeof mountType !== 'undefined' && mountType !== 'shared-root' && mountType !== 'shared') {
					shareFolderIcon = OC.MimeType.getIconUrl('dir-' + mountType)
				} else if (hasLink) {
					shareFolderIcon = OC.MimeType.getIconUrl('dir-public')
				} else {
					shareFolderIcon = OC.MimeType.getIconUrl('dir-shared')
				}
				$tr.find('.filename .thumbnail').css('background-image', 'url(' + shareFolderIcon + ')')
				$tr.attr('data-icon', shareFolderIcon)
			} else if (type === 'dir') {
				var isEncrypted = $tr.attr('data-e2eencrypted')
				// FIXME: duplicate of FileList._createRow logic for external folder,
				// need to refactor the icon logic into a single code path eventually
				if (isEncrypted === 'true') {
					shareFolderIcon = OC.MimeType.getIconUrl('dir-encrypted')
					$tr.attr('data-icon', shareFolderIcon)
				} else if (mountType && mountType.indexOf('external') === 0) {
					shareFolderIcon = OC.MimeType.getIconUrl('dir-external')
					$tr.attr('data-icon', shareFolderIcon)
				} else {
					shareFolderIcon = OC.MimeType.getIconUrl('dir')
					// back to default
					$tr.removeAttr('data-icon')
				}
				$tr.find('.filename .thumbnail').css('background-image', 'url(' + shareFolderIcon + ')')
			}
			// update share action text / icon
			if (hasShares || ownerId) {
				recipients = $tr.data('share-recipient-data')
				action.addClass('shared-style')

				avatars = '<span>' + t('core', 'Shared') + '</span>'
				// even if reshared, only show "Shared by"
				if (ownerId) {
					message = t('core', 'Shared by')
					avatars = OCA.Sharing.Util._formatRemoteShare(ownerId, owner, message)
				} else if (recipients) {
					avatars = OCA.Sharing.Util._formatShareList(recipients)
				}
				action.html(avatars).prepend(icon)

				if (ownerId || recipients) {
					var avatarElement = action.find('.avatar')
					avatarElement.each(function() {
						$(this).avatar($(this).data('username'), 32)
					})
					action.find('span[title]').tooltip({ placement: 'top' })
				}
			} else {
				action.html('<span class="hidden-visually">' + t('core', 'Shared') + '</span>').prepend(icon)
			}
			if (hasLink) {
				iconClass = 'icon-public'
			}
			icon.removeClass('icon-shared icon-public').addClass(iconClass)
		},
		/**	
		 * Format a remote address	
		 *	
		* @param {String} shareWith userid, full remote share, or whatever	
		* @param {String} shareWithDisplayName	
		* @param {String} message	
		* @returns {String} HTML code to display	
		*/	
		_formatRemoteShare: function(shareWith, shareWithDisplayName, message) {	
			var parts = OCA.Sharing.Util._REMOTE_OWNER_REGEXP.exec(shareWith)	
			if (!parts || !parts[6]) {
				// display avatar of the user	
				var avatar = '<span class="avatar" data-username="' + escapeHTML(shareWith) + '" title="' + message + ' ' + escapeHTML(shareWithDisplayName) + '"></span>'	
				var hidden = '<span class="hidden-visually">' + message + ' ' + escapeHTML(shareWithDisplayName) + '</span> '	
				return avatar + hidden	
			}	

			var userName = parts[2]
			var userDomain = parts[4]
			var server = parts[5]

			var tooltip = message + ' ' + userName
			if (userDomain) {
				tooltip += '@' + userDomain
			}
			if (server) {
				tooltip += '@' + server
			}

			var html = '<span class="remoteAddress" title="' + escapeHTML(tooltip) + '">'	
			html += '<span class="username">' + escapeHTML(userName) + '</span>'	
			if (userDomain) {	
				html += '<span class="userDomain">@' + escapeHTML(userDomain) + '</span>'	
			}	
			html += '</span> '	
			return html	
		},	
		/**	
		 * Loop over all recipients in the list and format them using	
		 * all kind of fancy magic.	
		 *	
		* @param {Object} recipients array of all the recipients	
		* @returns {String[]} modified list of recipients	
		*/	
		_formatShareList: function(recipients) {	
			var _parent = this	
			recipients = _.toArray(recipients)	
			recipients.sort(function(a, b) {	
				return a.shareWithDisplayName.localeCompare(b.shareWithDisplayName)	
			})	
			return $.map(recipients, function(recipient) {	
				return _parent._formatRemoteShare(recipient.shareWith, recipient.shareWithDisplayName, t('core', 'Shared with'))	
			})	
		},
		
		/**	
		 * Marks/unmarks a given file as shared by changing its action icon	
		 * and folder icon.	
		 *	
		* @param $tr file element to mark as shared	
		* @param hasShares whether shares are available	
		* @param hasLink whether link share is available	
		*/	
		markFileAsShared: function($tr, hasShares, hasLink) {	
			var action = $tr.find('.fileactions .action[data-action="Share"]')	
			var type = $tr.data('type')	
			var icon = action.find('.icon')	
			var message, recipients, avatars	
			var ownerId = $tr.attr('data-share-owner-id')	
			var owner = $tr.attr('data-share-owner')	
			var mountType = $tr.attr('data-mounttype')	
			var shareFolderIcon	
			var iconClass = 'icon-shared'	
			action.removeClass('shared-style')	
			// update folder icon	
			if (type === 'dir' && (hasShares || hasLink || ownerId)) {	
				if (typeof mountType !== 'undefined' && mountType !== 'shared-root' && mountType !== 'shared') {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir-' + mountType)	
				} else if (hasLink) {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir-public')	
				} else {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir-shared')	
				}	
				$tr.find('.filename .thumbnail').css('background-image', 'url(' + shareFolderIcon + ')')	
				$tr.attr('data-icon', shareFolderIcon)	
			} else if (type === 'dir') {	
				var isEncrypted = $tr.attr('data-e2eencrypted')	
				// FIXME: duplicate of FileList._createRow logic for external folder,	
				// need to refactor the icon logic into a single code path eventually	
				if (isEncrypted === 'true') {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir-encrypted')	
					$tr.attr('data-icon', shareFolderIcon)	
				} else if (mountType && mountType.indexOf('external') === 0) {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir-external')	
					$tr.attr('data-icon', shareFolderIcon)	
				} else {	
					shareFolderIcon = OC.MimeType.getIconUrl('dir')	
					// back to default	
					$tr.removeAttr('data-icon')	
				}	
				$tr.find('.filename .thumbnail').css('background-image', 'url(' + shareFolderIcon + ')')	
			}	
			// update share action text / icon	
			if (hasShares || ownerId) {	
				recipients = $tr.data('share-recipient-data')	
				action.addClass('shared-style')	

				avatars = '<span>' + t('core', 'Shared') + '</span>'	
				// even if reshared, only show "Shared by"	
				if (ownerId) {	
					message = t('core', 'Shared by')	
					avatars = this._formatRemoteShare(ownerId, owner, message)	
				} else if (recipients) {	
					avatars = this._formatShareList(recipients)	
				}	
				action.html(avatars).prepend(icon)	

				if (ownerId || recipients) {	
					var avatarElement = action.find('.avatar')	
					avatarElement.each(function() {	
						$(this).avatar($(this).data('username'), 32)	
					})	
					action.find('span[title]').tooltip({ placement: 'top' })	
				}	
			} else {	
				action.html('<span class="hidden-visually">' + t('core', 'Shared') + '</span>').prepend(icon)	
			}	
			if (hasLink) {	
				iconClass = 'icon-public'	
			}	
			icon.removeClass('icon-shared icon-public').addClass(iconClass)	
		},

		/**
		 * @param {Array} fileData
		 * @returns {String}
		 */
		getSharePermissions: function(fileData) {
			return fileData.sharePermissions
		}
	}
})()

OC.Plugins.register('OCA.Files.FileList', OCA.Sharing.Util)
