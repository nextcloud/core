/*
 * Copyright (c) 2014
 *
 * @author Vincent Petry
 * @copyright 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/* global dragOptions, folderDropOptions, OC */
(function() {

	if (!OCA.Files) {
		/**
		 * Namespace for the files app
		 * @namespace OCA.Files
		 */
		OCA.Files = {};
	}

	/**
	 * @namespace OCA.Files.App
	 */
	OCA.Files.App = {
		/**
		 * Navigation control
		 *
		 * @member {OCA.Files.Navigation}
		 */
		navigation: null,

		/**
		 * File list for the "All files" section.
		 *
		 * @member {OCA.Files.FileList}
		 */
		fileList: null,

		currentFileList: null,

		/**
		 * Backbone model for storing files preferences
		 */
		_filesConfig: null,

		/**
		 * Initializes the files app
		 */
		initialize: function() {
			this.navigation = new OCA.Files.Navigation($('#app-navigation'));
			this.$showHiddenFiles = $('input#showhiddenfilesToggle');
			var showHidden = $('#showHiddenFiles').val() === "1";
			this.$showHiddenFiles.prop('checked', showHidden);

			// crop image previews
			this.$cropImagePreviews = $('input#cropimagepreviewsToggle');
			var cropImagePreviews = $('#cropImagePreviews').val() === "1";
			this.$cropImagePreviews.prop('checked', cropImagePreviews);

			if ($('#fileNotFound').val() === "1") {
				OC.Notification.show(t('files', 'File could not be found'), {type: 'error'});
			}

			this._filesConfig = new OC.Backbone.Model({
				showhidden: showHidden,
				cropimagepreviews: cropImagePreviews,
			});

			var urlParams = OC.Util.History.parseUrlQuery();
			var fileActions = new OCA.Files.FileActions();
			// default actions
			fileActions.registerDefaultActions();
			// regular actions
			fileActions.merge(OCA.Files.fileActions);

			this._onActionsUpdated = _.bind(this._onActionsUpdated, this);
			OCA.Files.fileActions.on('setDefault.app-files', this._onActionsUpdated);
			OCA.Files.fileActions.on('registerAction.app-files', this._onActionsUpdated);

			this.files = OCA.Files.Files;

			// TODO: ideally these should be in a separate class / app (the embedded "all files" app)
			this.fileList = new OCA.Files.FileList(
				$('#app-content-files'), {
					dragOptions: dragOptions,
					folderDropOptions: folderDropOptions,
					fileActions: fileActions,
					allowLegacyActions: true,
					scrollTo: urlParams.scrollto,
					filesClient: OC.Files.getClient(),
					multiSelectMenu: [
						{
							name: 'copyMove',
							displayName:  t('files', 'Move or copy'),
							iconClass: 'icon-external',
						},
						{
							name: 'download',
							displayName:  t('files', 'Download'),
							iconClass: 'icon-download',
						},
						OCA.Files.FileList.MultiSelectMenuActions.ToggleSelectionModeAction,
						{
							name: 'delete',
							displayName: t('files', 'Delete'),
							iconClass: 'icon-delete',
						},
					],
					sorting: {
						mode: $('#defaultFileSorting').val(),
						direction: $('#defaultFileSortingDirection').val()
					},
					config: this._filesConfig,
					enableUpload: true,
					maxChunkSize: OC.appConfig.files && OC.appConfig.files.max_chunk_size
				}
			);
			this.updateCurrentFileList(this.fileList)
			this.files.initialize();

			// for backward compatibility, the global FileList will
			// refer to the one of the "files" view
			window.FileList = this.fileList;

			OC.Plugins.attach('OCA.Files.App', this);

			this._setupEvents();
			// trigger URL change event handlers
			this._onPopState(urlParams);

			$('#quota.has-tooltip').tooltip({
				placement: 'top'
			});

			this._debouncedPersistShowHiddenFilesState = _.debounce(this._persistShowHiddenFilesState, 1200);
			this._debouncedPersistCropImagePreviewsState = _.debounce(this._persistCropImagePreviewsState, 1200);

			if (sessionStorage.getItem('WhatsNewServerCheck') < (Date.now() - 3600*1000)) {
				OCP.WhatsNew.query(); // for Nextcloud server
				sessionStorage.setItem('WhatsNewServerCheck', Date.now());
			}
		},

		/**
		 * Destroy the app
		 */
		destroy: function() {
			this.navigation = null;
			this.fileList.destroy();
			this.fileList = null;
			this.files = null;
			OCA.Files.fileActions.off('setDefault.app-files', this._onActionsUpdated);
			OCA.Files.fileActions.off('registerAction.app-files', this._onActionsUpdated);
		},

		_onActionsUpdated: function(ev) {
			// forward new action to the file list
			if (ev.action) {
				this.fileList.fileActions.registerAction(ev.action);
			} else if (ev.defaultAction) {
				this.fileList.fileActions.setDefault(
					ev.defaultAction.mime,
					ev.defaultAction.name
				);
			}
		},

		/**
		 * Set the currently active file list
		 *
		 * Due to the file list implementations being registered after clicking the
		 * navigation item for the first time, OCA.Files.App is not aware of those until
		 * they have initialized themselves. Therefore the files list needs to call this
		 * method manually
		 *
		 * @param {OCA.Files.FileList} newFileList
		 */
		updateCurrentFileList: function(newFileList) {
			this.currentFileList = newFileList;
		},

		/**
		 * Return the currently active file list
		 * @return {?OCA.Files.FileList}
		 */
		getCurrentFileList: function () {
			return this.currentFileList;
		},

		/**
		 * Returns the container of the currently visible app.
		 *
		 * @return app container
		 */
		getCurrentAppContainer: function() {
			return this.navigation.getActiveContainer();
		},

		/**
		 * Sets the currently active view
		 * @param viewId view id
		 */
		setActiveView: function(viewId, options) {
			this.navigation.setActiveItem(viewId, options);
		},

		/**
		 * Returns the view id of the currently active view
		 * @return view id
		 */
		getActiveView: function() {
			return this.navigation.getActiveItem();
		},

		/**
		 *
		 * @returns {Backbone.Model}
		 */
		getFilesConfig: function() {
			return this._filesConfig;
		},

		/**
		 * Setup events based on URL changes
		 */
		_setupEvents: function() {
			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));

			// detect when app changed their current directory
			$('#app-content').delegate('>div', 'changeDirectory', _.bind(this._onDirectoryChanged, this));
			$('#app-content').delegate('>div', 'afterChangeDirectory', _.bind(this._onAfterDirectoryChanged, this));
			$('#app-content').delegate('>div', 'changeViewerMode', _.bind(this._onChangeViewerMode, this));

			$('#app-navigation').on('itemChanged', _.bind(this._onNavigationChanged, this));
			this.$showHiddenFiles.on('change', _.bind(this._onShowHiddenFilesChange, this));
			this.$cropImagePreviews.on('change', _.bind(this._onCropImagePreviewsChange, this));
		},

		/**
		 * Toggle showing hidden files according to the settings checkbox
		 *
		 * @returns {undefined}
		 */
		_onShowHiddenFilesChange: function() {
			var show = this.$showHiddenFiles.is(':checked');
			this._filesConfig.set('showhidden', show);
			this._debouncedPersistShowHiddenFilesState();
		},

		/**
		 * Persist show hidden preference on the server
		 *
		 * @returns {undefined}
		 */
		_persistShowHiddenFilesState: function() {
			var show = this._filesConfig.get('showhidden');
			$.post(OC.generateUrl('/apps/files/api/v1/showhidden'), {
				show: show
			});
		},

		/**
		 * Toggle cropping image previews according to the settings checkbox
		 *
		 * @returns void
		 */
		_onCropImagePreviewsChange: function() {
			var crop = this.$cropImagePreviews.is(':checked');
			this._filesConfig.set('cropimagepreviews', crop);
			this._debouncedPersistCropImagePreviewsState();
		},

		/**
		 * Persist crop image previews preference on the server
		 *
		 * @returns void
		 */
		_persistCropImagePreviewsState: function() {
			var crop = this._filesConfig.get('cropimagepreviews');
			$.post(OC.generateUrl('/apps/files/api/v1/cropimagepreviews'), {
				crop: crop
			});
		},

		/**
		 * Event handler for when the current navigation item has changed
		 */
		_onNavigationChanged: function(e) {
			var params;
			if (e && e.itemId) {
				params = {
					view: typeof e.view === 'string' && e.view !== '' ? e.view : e.itemId,
					dir: e.dir ? e.dir : '/'
				};
				this._changeUrl(params.view, params.dir);
				OC.Apps.hideAppSidebar($('.detailsView'));
				this.navigation.getActiveContainer().trigger(new $.Event('urlChanged', params));
			}
		},

		/**
		 * Event handler for when an app notified that its directory changed
		 */
		_onDirectoryChanged: function(e) {
			if (e.dir) {
				this._changeUrl(this.navigation.getActiveItem(), e.dir, e.fileId);
			}
		},

		/**
		 * Event handler for when an app notified that its directory changed
		 */
		_onAfterDirectoryChanged: function(e) {
			if (e.dir && e.fileId) {
				this._changeUrl(this.navigation.getActiveItem(), e.dir, e.fileId);
			}
		},

		/**
		 * Event handler for when an app notifies that it needs space
		 * for viewer mode.
		 */
		_onChangeViewerMode: function(e) {
			var state = !!e.viewerModeEnabled;
			if (e.viewerModeEnabled) {
				OC.Apps.hideAppSidebar($('.detailsView'));
			}
			$('#app-navigation').toggleClass('hidden', state);
			$('.app-files').toggleClass('viewer-mode no-sidebar', state);
		},

		/**
		 * Event handler for when the URL changed
		 */
		_onPopState: function(params) {
			params = _.extend({
				dir: '/',
				view: 'files'
			}, params);
			var lastId = this.navigation.getActiveItem();
			if (!this.navigation.itemExists(params.view)) {
				params.view = 'files';
			}
			this.navigation.setActiveItem(params.view, {silent: true});
			if (lastId !== this.navigation.getActiveItem()) {
				this.navigation.getActiveContainer().trigger(new $.Event('show'));
			}
			this.navigation.getActiveContainer().trigger(new $.Event('urlChanged', params));
		},

		/**
		 * Encode URL params into a string, except for the "dir" attribute
		 * that gets encoded as path where "/" is not encoded
		 *
		 * @param {Object.<string>} params
		 * @return {string} encoded params
		 */
		_makeUrlParams: function(params) {
			var dir = params.dir;
			delete params.dir;
			return 'dir=' + OC.encodePath(dir) + '&' + OC.buildQueryString(params);
		},

		/**
		 * Change the URL to point to the given dir and view
		 */
		_changeUrl: function(view, dir, fileId) {
			var params = {dir: dir};
			if (view !== 'files') {
				params.view = view;
			} else if (fileId) {
				params.fileid = fileId;
			}
			var currentParams = OC.Util.History.parseUrlQuery();
			if (currentParams.dir === params.dir && currentParams.view === params.view && currentParams.fileid !== params.fileid) {
				// if only fileid changed or was added, replace instead of push
				OC.Util.History.replaceState(this._makeUrlParams(params));
			} else {
				OC.Util.History.pushState(this._makeUrlParams(params));
			}
		}
	};
})();

window.addEventListener('DOMContentLoaded', function() {
	// wait for other apps/extensions to register their event handlers and file actions
	// in the "ready" clause
	_.defer(function() {
		OCA.Files.App.initialize();
	});
});
