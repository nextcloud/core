/*
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

if (!OCA.Sharing) {
	/**
	 * @namespace OCA.Sharing
	 */
	OCA.Sharing = {}
}
/**
 * @namespace
 */
OCA.Sharing.App = {

	_inFileList: null,
	_outFileList: null,
	_overviewFileList: null,
	_pendingFileList: null,

	initSharingIn: function($el) {
		if (this._inFileList) {
			return this._inFileList
		}

		this._inFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.self',
				sharedWithUser: true,
				fileActions: this._createFileActions(),
				config: OCA.Files.App.getFilesConfig(),
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._inFileList)
		this._inFileList.appName = t('files_sharing', 'Shared with you')
		this._inFileList.$el.find('#emptycontent').html('<div class="icon-shared"></div>'
			+ '<h2>' + t('files_sharing', 'Nothing shared with you yet') + '</h2>'
			+ '<p>' + t('files_sharing', 'Files and folders others share with you will show up here') + '</p>')
		return this._inFileList
	},

	initSharingOut: function($el) {
		if (this._outFileList) {
			return this._outFileList
		}
		this._outFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.others',
				sharedWithUser: false,
				fileActions: this._createFileActions(),
				config: OCA.Files.App.getFilesConfig(),
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._outFileList)
		this._outFileList.appName = t('files_sharing', 'Shared with others')
		this._outFileList.$el.find('#emptycontent').html('<div class="icon-shared"></div>'
			+ '<h2>' + t('files_sharing', 'Nothing shared yet') + '</h2>'
			+ '<p>' + t('files_sharing', 'Files and folders you share will show up here') + '</p>')
		return this._outFileList
	},

	initSharingLinks: function($el) {
		if (this._linkFileList) {
			return this._linkFileList
		}
		this._linkFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.link',
				linksOnly: true,
				fileActions: this._createFileActions(),
				config: OCA.Files.App.getFilesConfig(),
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._linkFileList)
		this._linkFileList.appName = t('files_sharing', 'Shared by link')
		this._linkFileList.$el.find('#emptycontent').html('<div class="icon-public"></div>'
			+ '<h2>' + t('files_sharing', 'No shared links') + '</h2>'
			+ '<p>' + t('files_sharing', 'Files and folders you share by link will show up here') + '</p>')
		return this._linkFileList
	},

	initSharingDeleted: function($el) {
		if (this._deletedFileList) {
			return this._deletedFileList
		}
		this._deletedFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.deleted',
				defaultFileActionsDisabled: true,
				showDeleted: true,
				sharedWithUser: true,
				fileActions: this._restoreShareAction(),
				config: OCA.Files.App.getFilesConfig(),
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._deletedFileList)
		this._deletedFileList.appName = t('files_sharing', 'Deleted shares')
		this._deletedFileList.$el.find('#emptycontent').html('<div class="icon-share"></div>'
			+ '<h2>' + t('files_sharing', 'No deleted shares') + '</h2>'
			+ '<p>' + t('files_sharing', 'Shares you deleted will show up here') + '</p>')
		return this._deletedFileList
	},

	initSharingPening: function($el) {
		if (this._pendingFileList) {
			return this._pendingFileList
		}
		this._pendingFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.pending',
				showPending: true,
				sharedWithUser: true,
				fileActions: this._acceptShareAction(),
				config: OCA.Files.App.getFilesConfig(),
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._pendingFileList)
		this._pendingFileList.appName = t('files_sharing', 'Pending shares')
		this._pendingFileList.$el.find('#emptycontent').html('<div class="icon-share"></div>'
			+ '<h2>' + t('files_sharing', 'No pending shares') + '</h2>'
			+ '<p>' + t('files_sharing', 'Shares you have received but not confirmed will show up here') + '</p>')
		return this._pendingFileList
	},

	initShareingOverview: function($el) {
		if (this._overviewFileList) {
			return this._overviewFileList
		}
		this._overviewFileList = new OCA.Sharing.FileList(
			$el,
			{
				id: 'shares.overview',
				config: OCA.Files.App.getFilesConfig(),
				isOverview: true,
				// The file list is created when a "show" event is handled, so
				// it should be marked as "shown" like it would have been done
				// if handling the event with the file list already created.
				shown: true,
			}
		)

		this._extendFileList(this._overviewFileList)
		this._overviewFileList.appName = t('files_sharing', 'Shares')
		this._overviewFileList.$el.find('#emptycontent').html('<div class="icon-share"></div>'
			+ '<h2>' + t('files_sharing', 'No shares') + '</h2>'
			+ '<p>' + t('files_sharing', 'Shares will show up here') + '</p>')
		return this._overviewFileList
	},

	removeSharingIn: function() {
		if (this._inFileList) {
			this._inFileList.$fileList.empty()
		}
	},

	removeSharingOut: function() {
		if (this._outFileList) {
			this._outFileList.$fileList.empty()
		}
	},

	removeSharingLinks: function() {
		if (this._linkFileList) {
			this._linkFileList.$fileList.empty()
		}
	},

	removeSharingDeleted: function() {
		if (this._deletedFileList) {
			this._deletedFileList.$fileList.empty()
		}
	},

	removeSharingPending: function() {
		if (this._pendingFileList) {
			this._pendingFileList.$fileList.empty()
		}
	},

	removeSharingOverview: function() {
		if (this._overviewFileList) {
			this._overviewFileList.$fileList.empty()
		}
	},

	/**
	 * Destroy the app
	 */
	destroy: function() {
		OCA.Files.fileActions.off('setDefault.app-sharing', this._onActionsUpdated)
		OCA.Files.fileActions.off('registerAction.app-sharing', this._onActionsUpdated)
		this.removeSharingIn()
		this.removeSharingOut()
		this.removeSharingLinks()
		this._inFileList = null
		this._outFileList = null
		this._linkFileList = null
		this._overviewFileList = null
		delete this._globalActionsInitialized
	},

	_createFileActions: function() {
		// inherit file actions from the files app
		const fileActions = new OCA.Files.FileActions()
		// note: not merging the legacy actions because legacy apps are not
		// compatible with the sharing overview and need to be adapted first
		fileActions.registerDefaultActions()
		fileActions.merge(OCA.Files.fileActions)

		if (!this._globalActionsInitialized) {
			// in case actions are registered later
			this._onActionsUpdated = _.bind(this._onActionsUpdated, this)
			OCA.Files.fileActions.on('setDefault.app-sharing', this._onActionsUpdated)
			OCA.Files.fileActions.on('registerAction.app-sharing', this._onActionsUpdated)
			this._globalActionsInitialized = true
		}

		// when the user clicks on a folder, redirect to the corresponding
		// folder in the files app instead of opening it directly
		fileActions.register('dir', 'Open', OC.PERMISSION_READ, '', function(filename, context) {
			OCA.Files.App.setActiveView('files', { silent: true })
			OCA.Files.App.fileList.changeDirectory(OC.joinPaths(context.$file.attr('data-path'), filename), true, true)
		})
		fileActions.setDefault('dir', 'Open')
		return fileActions
	},

	_restoreShareAction: function() {
		const fileActions = new OCA.Files.FileActions()
		fileActions.registerAction({
			name: 'Restore',
			displayName: t('files_sharing', 'Restore'),
			altText: t('files_sharing', 'Restore share'),
			mime: 'all',
			permissions: OC.PERMISSION_ALL,
			iconClass: 'icon-history',
			type: OCA.Files.FileActions.TYPE_INLINE,
			actionHandler: function(fileName, context) {
				const shareId = context.$file.data('shareId')
				$.post(OC.linkToOCS('apps/files_sharing/api/v1/deletedshares', 2) + shareId)
					.success(function(result) {
						context.fileList.remove(context.fileInfoModel.attributes.name)
					}).fail(function() {
						OC.Notification.showTemporary(t('files_sharing', 'Something happened. Unable to restore the share.'))
					})
			},
		})
		return fileActions
	},

	_acceptShareAction: function() {
		const fileActions = new OCA.Files.FileActions()
		fileActions.registerAction({
			name: 'Accept share',
			displayName: t('files_sharing', 'Accept share'),
			mime: 'all',
			permissions: OC.PERMISSION_ALL,
			iconClass: 'icon-checkmark',
			type: OCA.Files.FileActions.TYPE_INLINE,
			actionHandler: function(fileName, context) {
				const shareId = context.$file.data('shareId')
				$.post(OC.linkToOCS('apps/files_sharing/api/v1/shares/pending', 2) + shareId)
					.success(function(result) {
						context.fileList.remove(context.fileInfoModel.attributes.name)
					}).fail(function() {
						OC.Notification.showTemporary(t('files_sharing', 'Something happened. Unable to accept the share.'))
					})
			},
		})
		fileActions.registerAction({
			name: 'Reject share',
			displayName: t('files_sharing', 'Reject share'),
			mime: 'all',
			permissions: OC.PERMISSION_ALL,
			iconClass: 'icon-close',
			type: OCA.Files.FileActions.TYPE_INLINE,
			actionHandler: function(fileName, context) {
				const shareId = context.$file.data('shareId')
				$.ajax({
					url: OC.linkToOCS('apps/files_sharing/api/v1/shares', 2) + shareId,
					type: 'DELETE',
				}).success(function(result) {
					context.fileList.remove(context.fileInfoModel.attributes.name)
				}).fail(function() {
					OC.Notification.showTemporary(t('files_sharing', 'Something happened. Unable to reject the share.'))
				})
			},
		})
		return fileActions
	},

	_onActionsUpdated: function(ev) {
		_.each([this._inFileList, this._outFileList, this._linkFileList], function(list) {
			if (!list) {
				return
			}

			if (ev.action) {
				list.fileActions.registerAction(ev.action)
			} else if (ev.defaultAction) {
				list.fileActions.setDefault(
					ev.defaultAction.mime,
					ev.defaultAction.name
				)
			}
		})
	},

	_extendFileList: function(fileList) {
		// remove size column from summary
		fileList.fileSummary.$el.find('.filesize').remove()
	},
}

$(document).ready(function() {
	$('#app-content-sharingin').on('show', function(e) {
		OCA.Sharing.App.initSharingIn($(e.target))
	})
	$('#app-content-sharingin').on('hide', function() {
		OCA.Sharing.App.removeSharingIn()
	})
	$('#app-content-sharingout').on('show', function(e) {
		OCA.Sharing.App.initSharingOut($(e.target))
	})
	$('#app-content-sharingout').on('hide', function() {
		OCA.Sharing.App.removeSharingOut()
	})
	$('#app-content-sharinglinks').on('show', function(e) {
		OCA.Sharing.App.initSharingLinks($(e.target))
	})
	$('#app-content-sharinglinks').on('hide', function() {
		OCA.Sharing.App.removeSharingLinks()
	})
	$('#app-content-deletedshares').on('show', function(e) {
		OCA.Sharing.App.initSharingDeleted($(e.target))
	})
	$('#app-content-deletedshares').on('hide', function() {
		OCA.Sharing.App.removeSharingDeleted()
	})
	$('#app-content-pendingshares').on('show', function(e) {
		OCA.Sharing.App.initSharingPening($(e.target))
	})
	$('#app-content-pendingshares').on('hide', function() {
		OCA.Sharing.App.removeSharingPending()
	})
	$('#app-content-shareoverview').on('show', function(e) {
		OCA.Sharing.App.initShareingOverview($(e.target))
	})
	$('#app-content-shareoverview').on('hide', function() {
		OCA.Sharing.App.removeSharingOverview()
	})
})
