/*
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	if (!OCA.SystemTags) {
		/**
		 * @namespace
		 */
		OCA.SystemTags = {}
	}

	OCA.SystemTags.App = {

		initFileList: function($el) {
			if (this._fileList) {
				return this._fileList
			}

			this._fileList = new OCA.SystemTags.FileList(
				$el,
				{
					id: 'systemtags',
					fileActions: this._createFileActions(),
					config: OCA.Files.App.getFilesConfig(),
					// The file list is created when a "show" event is handled,
					// so it should be marked as "shown" like it would have been
					// done if handling the event with the file list already
					// created.
					shown: true,
				}
			)

			this._fileList.appName = t('systemtags', 'Tags')
			return this._fileList
		},

		removeFileList: function() {
			if (this._fileList) {
				this._fileList.$fileList.empty()
			}
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
				OCA.Files.fileActions.on('setDefault.app-systemtags', this._onActionsUpdated)
				OCA.Files.fileActions.on('registerAction.app-systemtags', this._onActionsUpdated)
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

		_onActionsUpdated: function(ev) {
			if (!this._fileList) {
				return
			}

			if (ev.action) {
				this._fileList.fileActions.registerAction(ev.action)
			} else if (ev.defaultAction) {
				this._fileList.fileActions.setDefault(
					ev.defaultAction.mime,
					ev.defaultAction.name
				)
			}
		},

		/**
		 * Destroy the app
		 */
		destroy: function() {
			OCA.Files.fileActions.off('setDefault.app-systemtags', this._onActionsUpdated)
			OCA.Files.fileActions.off('registerAction.app-systemtags', this._onActionsUpdated)
			this.removeFileList()
			this._fileList = null
			delete this._globalActionsInitialized
		},
	}

})()

$(document).ready(function() {
	$('#app-content-systemtagsfilter').on('show', function(e) {
		OCA.SystemTags.App.initFileList($(e.target))
	})
	$('#app-content-systemtagsfilter').on('hide', function() {
		OCA.SystemTags.App.removeFileList()
	})
})
