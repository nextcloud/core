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

	/**
	 * @class OCA.Files.FileList
	 * @classdesc
	 *
	 * The FileList class manages a file list view.
	 * A file list view consists of a controls bar and
	 * a file list table.
	 *
	 * @param $el container element with existing markup for the #controls
	 * and a table
	 * @param {Object} [options] map of options, see other parameters
	 * @param {Object} [options.scrollContainer] scrollable container, defaults to $(window)
	 * @param {Object} [options.dragOptions] drag options, disabled by default
	 * @param {Object} [options.folderDropOptions] folder drop options, disabled by default
	 * @param {boolean} [options.detailsViewEnabled=true] whether to enable details view
	 * @param {boolean} [options.enableUpload=false] whether to enable uploader
	 * @param {OC.Files.Client} [options.filesClient] files client to use
	 */
	var FileList = function($el, options) {
		this.initialize($el, options);
	};
	/**
	 * @memberof OCA.Files
	 */
	FileList.prototype = {
		SORT_INDICATOR_ASC_CLASS: 'icon-triangle-n',
		SORT_INDICATOR_DESC_CLASS: 'icon-triangle-s',

		id: 'files',
		appName: t('files', 'Files'),
		isEmpty: true,
		useUndo:true,

		/**
		 * Top-level container with controls and file list
		 */
		$el: null,

		/**
		 * Files table
		 */
		$table: null,

		/**
		 * List of rows (table tbody)
		 */
		$fileList: null,

		$header: null,
		headers: [],

		$footer: null,
		footers: [],

		/**
		 * @type OCA.Files.BreadCrumb
		 */
		breadcrumb: null,

		/**
		 * @type OCA.Files.FileSummary
		 */
		fileSummary: null,

		/**
		 * @type OCA.Files.DetailsView
		 */
		_detailsView: null,

		/**
		 * Files client instance
		 *
		 * @type OC.Files.Client
		 */
		filesClient: null,

		/**
		 * Whether the file list was initialized already.
		 * @type boolean
		 */
		initialized: false,

		/**
		 * Wheater the file list was already shown once
		 * @type boolean
		 */
		shown: false,

		/**
		 * Number of files per page
		 * Always show a minimum of 1
		 *
		 * @return {int} page size
		 */
		pageSize: function() {
			var isGridView = this.$showGridView.is(':checked');
			var columns = 1;
			var rows = Math.ceil(this.$container.height() / 50);
			if (isGridView) {
				columns = Math.ceil(this.$container.width() / 160);
				rows = Math.ceil(this.$container.height() / 160);
			}
			return Math.max(columns*rows, columns);
		},

		/**
		 * Array of files in the current folder.
		 * The entries are of file data.
		 *
		 * @type Array.<OC.Files.FileInfo>
		 */
		files: [],

		/**
		 * Current directory entry
		 *
		 * @type OC.Files.FileInfo
		 */
		dirInfo: null,

		/**
		 * Whether to prevent or to execute the default file actions when the
		 * file name is clicked.
		 *
		 * @type boolean
		 */
		_defaultFileActionsDisabled: false,

		/**
		 * File actions handler, defaults to OCA.Files.FileActions
		 * @type OCA.Files.FileActions
		 */
		fileActions: null,
		/**
		 * File selection menu, defaults to OCA.Files.FileSelectionMenu
		 * @type OCA.Files.FileSelectionMenu
		 */
		fileMultiSelectMenu: null,
		/**
		 * Whether selection is allowed, checkboxes and selection overlay will
		 * be rendered
		 */
		_allowSelection: true,

		/**
		 * Map of file id to file data
		 * @type Object.<int, Object>
		 */
		_selectedFiles: {},

		/**
		 * Summary of selected files.
		 * @type OCA.Files.FileSummary
		 */
		_selectionSummary: null,

		/**
		 * If not empty, only files containing this string will be shown
		 * @type String
		 */
		_filter: '',

		/**
		 * @type Backbone.Model
		 */
		_filesConfig: undefined,

		/**
		 * Sort attribute
		 * @type String
		 */
		_sort: 'name',

		/**
		 * Sort direction: 'asc' or 'desc'
		 * @type String
		 */
		_sortDirection: 'asc',

		/**
		 * Sort comparator function for the current sort
		 * @type Function
		 */
		_sortComparator: null,

		/**
		 * Whether to do a client side sort.
		 * When false, clicking on a table header will call reload().
		 * When true, clicking on a table header will simply resort the list.
		 */
		_clientSideSort: true,

		/**
		 * Whether or not users can change the sort attribute or direction
		 */
		_allowSorting: true,

		/**
		 * Current directory
		 * @type String
		 */
		_currentDirectory: null,

		_dragOptions: null,
		_folderDropOptions: null,

		/**
		 * @type OC.Uploader
		 */
		_uploader: null,

		/**
		 * Initialize the file list and its components
		 *
		 * @param $el container element with existing markup for the #controls
		 * and a table
		 * @param options map of options, see other parameters
		 * @param options.scrollContainer scrollable container, defaults to $(window)
		 * @param options.dragOptions drag options, disabled by default
		 * @param options.folderDropOptions folder drop options, disabled by default
		 * @param options.scrollTo name of file to scroll to after the first load
		 * @param {OC.Files.Client} [options.filesClient] files API client
		 * @param {OC.Backbone.Model} [options.filesConfig] files app configuration
		 * @private
		 */
		initialize: function($el, options) {
			var self = this;
			options = options || {};
			if (this.initialized) {
				return;
			}

			if (options.shown) {
				this.shown = options.shown;
			}

			if (options.config) {
				this._filesConfig = options.config;
			} else if (!_.isUndefined(OCA.Files) && !_.isUndefined(OCA.Files.App)) {
				this._filesConfig = OCA.Files.App.getFilesConfig();
			} else {
				this._filesConfig = new OC.Backbone.Model({
					'showhidden': false
				});
			}

			if (options.dragOptions) {
				this._dragOptions = options.dragOptions;
			}
			if (options.folderDropOptions) {
				this._folderDropOptions = options.folderDropOptions;
			}
			if (options.filesClient) {
				this.filesClient = options.filesClient;
			} else {
				// default client if not specified
				this.filesClient = OC.Files.getClient();
			}

			this.$el = $el;
			if (options.id) {
				this.id = options.id;
			}
			this.$container = options.scrollContainer || $(window);
			this.$table = $el.find('table:first');
			this.$fileList = $el.find('#fileList');
			this.$header = $el.find('#filelist-header');
			this.$footer = $el.find('#filelist-footer');

			if (!_.isUndefined(this._filesConfig)) {
				this._filesConfig.on('change:showhidden', function() {
					var showHidden = this.get('showhidden');
					self.$el.toggleClass('hide-hidden-files', !showHidden);
					self.updateSelectionSummary();

					if (!showHidden) {
						// hiding files could make the page too small, need to try rendering next page
						self._onScroll();
					}
				});

				this.$el.toggleClass('hide-hidden-files', !this._filesConfig.get('showhidden'));
			}


			if (_.isUndefined(options.detailsViewEnabled) || options.detailsViewEnabled) {
				this._detailsView = new OCA.Files.DetailsView();
				this._detailsView.$el.addClass('disappear');
			}

			if (options && options.defaultFileActionsDisabled) {
				this._defaultFileActionsDisabled = options.defaultFileActionsDisabled
			}

			this._initFileActions(options.fileActions);

			if (this._detailsView) {
				this._detailsView.addDetailView(new OCA.Files.MainFileInfoDetailView({fileList: this, fileActions: this.fileActions}));
			}

			this.files = [];
			this._selectedFiles = {};
			this._selectionSummary = new OCA.Files.FileSummary(undefined, {config: this._filesConfig});
			// dummy root dir info
			this.dirInfo = new OC.Files.FileInfo({});

			this.fileSummary = this._createSummary();

			if (options.multiSelectMenu) {
				this.multiSelectMenuItems = options.multiSelectMenu;
				for (var i=0; i<this.multiSelectMenuItems.length; i++) {
					if (_.isFunction(this.multiSelectMenuItems[i])) {
						this.multiSelectMenuItems[i] = this.multiSelectMenuItems[i](this);
					}
				}
				this.fileMultiSelectMenu = new OCA.Files.FileMultiSelectMenu(this.multiSelectMenuItems);
				this.fileMultiSelectMenu.render();
				this.$el.find('.selectedActions').append(this.fileMultiSelectMenu.$el);
			}

			if (options.sorting) {
				this.setSort(options.sorting.mode, options.sorting.direction, false, false);
			} else {
				this.setSort('name', 'asc', false, false);
			}

			var breadcrumbOptions = {
				onClick: _.bind(this._onClickBreadCrumb, this),
				getCrumbUrl: function(part) {
					return self.linkTo(part.dir);
				}
			};
			// if dropping on folders is allowed, then also allow on breadcrumbs
			if (this._folderDropOptions) {
				breadcrumbOptions.onDrop = _.bind(this._onDropOnBreadCrumb, this);
				breadcrumbOptions.onOver = function() {
					self.$el.find('td.filename.ui-droppable').droppable('disable');
				};
				breadcrumbOptions.onOut = function() {
					self.$el.find('td.filename.ui-droppable').droppable('enable');
				};
			}
			this.breadcrumb = new OCA.Files.BreadCrumb(breadcrumbOptions);

			var $controls = this.$el.find('#controls');
			if ($controls.length > 0) {
				$controls.prepend(this.breadcrumb.$el);
				this.$table.addClass('has-controls');
			}

			this._renderNewButton();

			this.$el.find('thead th .columntitle').click(_.bind(this._onClickHeader, this));

			// Toggle for grid view, only register once
			this.$showGridView = $('input#showgridview:not(.registered)');
			this.$showGridView.on('change', _.bind(this._onGridviewChange, this));
			this.$showGridView.addClass('registered');
			$('#view-toggle').tooltip({placement: 'bottom', trigger: 'hover'});

			this._onResize = _.debounce(_.bind(this._onResize, this), 250);
			$('#app-content').on('appresized', this._onResize);
			$(window).resize(this._onResize);

			this.$el.on('show', this._onResize);

			// reload files list on share accept
			$('body').on('OCA.Notification.Action', function(eventObject) {
				if (eventObject.notification.app === 'files_sharing' && eventObject.action.type === 'POST') {
					self.reload()
				}
			});

			this.updateSearch();

			this.$fileList.on('click','td.filename>a.name, td.filesize, td.date', _.bind(this._onClickFile, this));

			this.$fileList.on("droppedOnFavorites", function (event, file) {
				self.fileActions.triggerAction('Favorite', self.getModelForFile(file), self);
			});

			this.$fileList.on('droppedOnTrash', function (event, filename, directory) {
				self.do_delete(filename, directory);
			});

			this.$fileList.on('change', 'td.selection>.selectCheckBox', _.bind(this._onClickFileCheckbox, this));
			this.$fileList.on('mouseover', 'td.selection', _.bind(this._onMouseOverCheckbox, this));
			this.$el.on('show', _.bind(this._onShow, this));
			this.$el.on('urlChanged', _.bind(this._onUrlChanged, this));
			this.$el.find('.select-all').click(_.bind(this._onClickSelectAll, this));
			this.$el.find('.actions-selected').click(function () {
				self.fileMultiSelectMenu.show(self);
				return false;
			});

			this.$container.on('scroll', _.bind(this._onScroll, this));

			if (options.scrollTo) {
				this.$fileList.one('updated', function() {
					self.scrollTo(options.scrollTo);
				});
			}

			this._operationProgressBar = new OCA.Files.OperationProgressBar();
			this._operationProgressBar.render();
			this.$el.find('#uploadprogresswrapper').replaceWith(this._operationProgressBar.$el);

			if (options.enableUpload) {
				// TODO: auto-create this element
				var $uploadEl = this.$el.find('#file_upload_start');
				if ($uploadEl.exists()) {
					this._uploader = new OC.Uploader($uploadEl, {
						progressBar: this._operationProgressBar,
						fileList: this,
						filesClient: this.filesClient,
						dropZone: $('#content'),
						maxChunkSize: options.maxChunkSize
					});

					this.setupUploadEvents(this._uploader);
				}
			}


			OC.Plugins.attach('OCA.Files.FileList', this);

			this.initHeadersAndFooters()
		},

		initHeadersAndFooters: function() {
			this.headers.sort(function(a, b) {
				return a.order - b.order;
			})
			this.footers.sort(function(a, b) {
				return a.order - b.order;
			})
			var uniqueIds = [];
			var self = this;
			this.headers.forEach(function(header) {
				if (header.id) {
					if (uniqueIds.indexOf(header.id) !== -1) {
						return
					}
					uniqueIds.push(header.id)
				}
				self.$header.append(header.el)

				setTimeout(function() {
					header.render(self)
				}, 0)
			})

			uniqueIds = [];
			this.footers.forEach(function(footer) {
				if (footer.id) {
					if (uniqueIds.indexOf(footer.id) !== -1) {
						return
					}
					uniqueIds.push(footer.id)
				}
				self.$footer.append(footer.el)
				setTimeout(function() {
					footer.render(self)
				}, 0)
			})
		},

		/**
		 * Destroy / uninitialize this instance.
		 */
		destroy: function() {
			if (this._newFileMenu) {
				this._newFileMenu.remove();
			}
			if (this._newButton) {
				this._newButton.remove();
			}
			if (this._detailsView) {
				this._detailsView.remove();
			}
			// TODO: also unregister other event handlers
			this.fileActions.off('registerAction', this._onFileActionsUpdated);
			this.fileActions.off('setDefault', this._onFileActionsUpdated);
			OC.Plugins.detach('OCA.Files.FileList', this);
			$('#app-content').off('appresized', this._onResize);
		},

		_selectionMode: 'single',
		_getCurrentSelectionMode: function () {
			return this._selectionMode;
		},
		_onClickToggleSelectionMode: function () {
			this._selectionMode = (this._selectionMode === 'range') ? 'single' : 'range';
			if (this._selectionMode === 'single') {
				this._removeHalfSelection();
			}
		},

		multiSelectMenuClick: function (ev, action) {
				var actionFunction = _.find(this.multiSelectMenuItems, function (item) {return item.name === action;}).action;
				if (actionFunction) {
					actionFunction(ev);
					return;
				}
				switch (action) {
					case 'delete':
						this._onClickDeleteSelected(ev)
						break;
					case 'download':
						this._onClickDownloadSelected(ev);
						break;
					case 'copyMove':
						this._onClickCopyMoveSelected(ev);
						break;
					case 'restore':
						this._onClickRestoreSelected(ev);
						break;
				}
		},
		/**
		 * Initializes the file actions, set up listeners.
		 *
		 * @param {OCA.Files.FileActions} fileActions file actions
		 */
		_initFileActions: function(fileActions) {
			var self = this;
			this.fileActions = fileActions;
			if (!this.fileActions) {
				this.fileActions = new OCA.Files.FileActions();
				this.fileActions.registerDefaultActions();
			}

			if (this._detailsView) {
				this.fileActions.registerAction({
					name: 'Details',
					displayName: t('files', 'Details'),
					mime: 'all',
					order: -50,
					iconClass: 'icon-details',
					permissions: OC.PERMISSION_NONE,
					actionHandler: function(fileName, context) {
						self._updateDetailsView(fileName);
					}
				});
			}

			this._onFileActionsUpdated = _.debounce(_.bind(this._onFileActionsUpdated, this), 100);
			this.fileActions.on('registerAction', this._onFileActionsUpdated);
			this.fileActions.on('setDefault', this._onFileActionsUpdated);
		},

		/**
		 * Returns a unique model for the given file name.
		 *
		 * @param {string|object} fileName file name or jquery row
		 * @return {OCA.Files.FileInfoModel} file info model
		 */
		getModelForFile: function(fileName) {
			var self = this;
			var $tr;
			// jQuery object ?
			if (fileName.is) {
				$tr = fileName;
				fileName = $tr.attr('data-file');
			} else {
				$tr = this.findFileEl(fileName);
			}

			if (!$tr || !$tr.length) {
				return null;
			}

			// if requesting the selected model, return it
			if (this._currentFileModel && this._currentFileModel.get('name') === fileName) {
				return this._currentFileModel;
			}

			// TODO: note, this is a temporary model required for synchronising
			// state between different views.
			// In the future the FileList should work with Backbone.Collection
			// and contain existing models that can be used.
			// This method would in the future simply retrieve the matching model from the collection.
			var model = new OCA.Files.FileInfoModel(this.elementToFile($tr), {
				filesClient: this.filesClient
			});
			if (!model.get('path')) {
				model.set('path', this.getCurrentDirectory(), {silent: true});
			}

			model.on('change', function(model) {
				// re-render row
				var highlightState = $tr.hasClass('highlighted');
				$tr = self.updateRow(
					$tr,
					model.toJSON(),
					{updateSummary: true, silent: false, animate: true}
				);

				// restore selection state
				var selected = !!self._selectedFiles[$tr.data('id')];
				self._selectFileEl($tr, selected);

				$tr.toggleClass('highlighted', highlightState);
			});
			model.on('busy', function(model, state) {
				self.showFileBusyState($tr, state);
			});

			return model;
		},

		/**
		 * Displays the details view for the given file and
		 * selects the given tab
		 *
		 * @param {string|OCA.Files.FileInfoModel} fileName file name or FileInfoModel for which to show details
		 * @param {string} [tabId] optional tab id to select
		 */
		showDetailsView: function(fileName, tabId) {
			console.warn('showDetailsView is deprecated! Use OCA.Files.Sidebar.activeTab. It will be removed in nextcloud 20.');
			this._updateDetailsView(fileName);
			if (tabId) {
				OCA.Files.Sidebar.setActiveTab(tabId);
			}
		},

		/**
		 * Update the details view to display the given file
		 *
		 * @param {string|OCA.Files.FileInfoModel} fileName file name from the current list or a FileInfoModel object
		 * @param {boolean} [show=true] whether to open the sidebar if it was closed
		 */
		_updateDetailsView: function(fileName, show) {
			if (!(OCA.Files && OCA.Files.Sidebar)) {
				console.error('No sidebar available');
				return;
			}

			if (!fileName) {
				OCA.Files.Sidebar.close()
				return
			} else if (typeof fileName !== 'string') {
				fileName = ''
			}

			// this is the old (terrible) way of getting the context.
			// don't use it anywhere else. Just provide the full path
			// of the file to the sidebar service
			var tr = this.findFileEl(fileName)
			var model = this.getModelForFile(tr)
			var path = model.attributes.path + '/' + model.attributes.name

			// make sure the file list has the correct context available
			if (this._currentFileModel) {
				this._currentFileModel.off();
			}
			this.$fileList.children().removeClass('highlighted');
			tr.addClass('highlighted');
			this._currentFileModel = model;

			// open sidebar and set file
			if (typeof show === 'undefined' || !!show || (OCA.Files.Sidebar.file !== '')) {
				OCA.Files.Sidebar.open(path.replace('//', '/'))
			}
		},

		/**
		 * Replaces the current details view element with the details view
		 * element of this file list.
		 *
		 * Each file list has its own DetailsView object, and each one has its
		 * own root element, but there can be just one details view/sidebar
		 * element in the document. This helper method replaces the current
		 * details view/sidebar element in the document with the element from
		 * the DetailsView object of this file list.
		 */
		_replaceDetailsViewElementIfNeeded: function() {
			var $appSidebar = $('#app-sidebar');
			if ($appSidebar.length === 0) {
				this._detailsView.$el.insertAfter($('#app-content'));
			} else if ($appSidebar[0] !== this._detailsView.el) {
				// "replaceWith()" can not be used here, as it removes the old
				// element instead of just detaching it.
				this._detailsView.$el.insertBefore($appSidebar);
				$appSidebar.detach();
			}
		},

		/**
		 * Event handler for when the window size changed
		 */
		_onResize: function() {
			var containerWidth = this.$el.width();
			var actionsWidth = 0;
			$.each(this.$el.find('#controls .actions'), function(index, action) {
				actionsWidth += $(action).outerWidth();
			});

			this.breadcrumb._resize();
		},

		/**
		 * Toggle showing gridview by default or not
		 *
		 * @returns {undefined}
		 */
		_onGridviewChange: function() {
			var show = this.$showGridView.is(':checked');
			// only save state if user is logged in
			if (OC.currentUser) {
				$.post(OC.generateUrl('/apps/files/api/v1/showgridview'), {
					show: show
				});
			}
			this.$showGridView.next('#view-toggle')
				.removeClass('icon-toggle-filelist icon-toggle-pictures')
				.addClass(show ? 'icon-toggle-filelist' : 'icon-toggle-pictures')

			$('.list-container').toggleClass('view-grid', show);
			if (show) {
				// If switching into grid view from list view, too few files might be displayed
				// Try rendering the next page
				this._onScroll();
			}
		},

		/**
		 * Event handler when leaving previously hidden state
		 */
		_onShow: function(e) {
			if (this.shown) {
				if (e.itemId === this.id) {
					this._setCurrentDir('/', false);
				}
				// Only reload if we don't navigate to a different directory
				if (typeof e.dir === 'undefined' || e.dir === this.getCurrentDirectory()) {
					this.reload();
				}
			}
			this.shown = true;
		},

		/**
		 * Event handler for when the URL changed
		 */
		_onUrlChanged: function(e) {
			if (e && _.isString(e.dir)) {
				var currentDir = this.getCurrentDirectory();
				// this._currentDirectory is NULL when fileList is first initialised
				if( (this._currentDirectory || this.$el.find('#dir').val()) && currentDir === e.dir) {
					return;
				}
				this.changeDirectory(e.dir, false, true);
			}
		},

		/**
		 * Selected/deselects the given file element and updated
		 * the internal selection cache.
		 *
		 * @param {Object} $tr single file row element
		 * @param {bool} state true to select, false to deselect
		 */
		_selectFileEl: function($tr, state) {
			var $checkbox = $tr.find('td.selection>.selectCheckBox');
			var oldData = !!this._selectedFiles[$tr.data('id')];
			var data;
			$checkbox.prop('checked', state);
			$tr.toggleClass('selected', state);
			// already selected ?
			if (state === oldData) {
				return;
			}
			data = this.elementToFile($tr);
			if (state) {
				this._selectedFiles[$tr.data('id')] = data;
				this._selectionSummary.add(data);
			}
			else {
				delete this._selectedFiles[$tr.data('id')];
				this._selectionSummary.remove(data);
			}
			if (this._detailsView && !this._detailsView.$el.hasClass('disappear')) {
				// hide sidebar
				this._updateDetailsView(null);
			}
			this.$el.find('.select-all').prop('checked', this._selectionSummary.getTotal() === this.files.length);
		},

		_selectRange: function($tr) {
			var checked = $tr.hasClass('selected');
			var $lastTr = $(this._lastChecked);
			var lastIndex = $lastTr.index();
			var currentIndex = $tr.index();
			var $rows = this.$fileList.children('tr');

			// last clicked checkbox below current one ?
			if (lastIndex > currentIndex) {
				var aux = lastIndex;
				lastIndex = currentIndex;
				currentIndex = aux;
			}

			// auto-select everything in-between
			for (var i = lastIndex; i <= currentIndex; i++) {
				this._selectFileEl($rows.eq(i), !checked);
			}
			this._removeHalfSelection();
			this._selectionMode = 'single';
		},

		_selectSingle: function($tr) {
			var state = !$tr.hasClass('selected');
			this._selectFileEl($tr, state);
		},

		_onMouseOverCheckbox: function(e) {
			if (this._getCurrentSelectionMode() !== 'range') {
				return;
			}
			var $currentTr = $(e.target).closest('tr');

			var $lastTr = $(this._lastChecked);
			var lastIndex = $lastTr.index();
			var currentIndex = $currentTr.index();
			var $rows = this.$fileList.children('tr');

			// last clicked checkbox below current one ?
			if (lastIndex > currentIndex) {
				var aux = lastIndex;
				lastIndex = currentIndex;
				currentIndex = aux;
			}

			// auto-select everything in-between
			this._removeHalfSelection();
			for (var i = 0; i <= $rows.length; i++) {
				var $tr = $rows.eq(i);
				var $checkbox = $tr.find('td.selection>.selectCheckBox');
				if(lastIndex <= i && i <= currentIndex) {
					$tr.addClass('halfselected');
					$checkbox.prop('checked', true);
				}
			}
		},

		_removeHalfSelection: function() {
			var $rows = this.$fileList.children('tr');
			for (var i = 0; i <= $rows.length; i++) {
				var $tr = $rows.eq(i);
				$tr.removeClass('halfselected');
				var $checkbox = $tr.find('td.selection>.selectCheckBox');
				$checkbox.prop('checked', !!this._selectedFiles[$tr.data('id')]);
			}
		},

		/**
		 * Event handler for when clicking on files to select them
		 */
		_onClickFile: function(event) {
			var $tr = $(event.target).closest('tr');
			if ($tr.hasClass('dragging')) {
				return;
			}
			if (this._allowSelection && (event.ctrlKey || event.shiftKey)) {
				event.preventDefault();
				if (event.shiftKey) {
					this._selectRange($tr);
				} else {
					this._selectSingle($tr);
				}
				this._lastChecked = $tr;
				this.updateSelectionSummary();
			} else {
				// clicked directly on the name
				if (!this._detailsView || $(event.target).is('.nametext, .name, .thumbnail') || $(event.target).closest('.nametext').length) {
					var filename = $tr.attr('data-file');
					var renaming = $tr.data('renaming');
					if (this._defaultFileActionsDisabled) {
						event.preventDefault();
					} else if (!renaming) {
						this.fileActions.currentFile = $tr.find('td');
						var mime = this.fileActions.getCurrentMimeType();
						var type = this.fileActions.getCurrentType();
						var permissions = this.fileActions.getCurrentPermissions();
						var action = this.fileActions.getDefault(mime,type, permissions);
						if (action) {
							event.preventDefault();
							action(filename, {
								$file: $tr,
								fileList: this,
								fileActions: this.fileActions,
								dir: $tr.attr('data-path') || this.getCurrentDirectory()
							});
						}
						// deselect row
						$(event.target).closest('a').blur();
					}
				} else {
					// Even if there is no Details action the default event
					// handler is prevented for consistency (although there
					// should always be a Details action); otherwise the link
					// would be downloaded by the browser when the user expected
					// the details to be shown.
					event.preventDefault();
					var filename = $tr.attr('data-file');
					this.fileActions.currentFile = $tr.find('td');
					var mime = this.fileActions.getCurrentMimeType();
					var type = this.fileActions.getCurrentType();
					var permissions = this.fileActions.getCurrentPermissions();
					var action = this.fileActions.get(mime, type, permissions)['Details'];
					if (action) {
						action(filename, {
							$file: $tr,
							fileList: this,
							fileActions: this.fileActions,
							dir: $tr.attr('data-path') || this.getCurrentDirectory()
						});
					}
				}
			}
		},

		/**
		 * Event handler for when clicking on a file's checkbox
		 */
		_onClickFileCheckbox: function(e) {
			var $tr = $(e.target).closest('tr');
			if(this._getCurrentSelectionMode() === 'range') {
				this._selectRange($tr);
			} else {
				this._selectSingle($tr);
			}
			this._lastChecked = $tr;
			this.updateSelectionSummary();
			if (this._detailsView && !this._detailsView.$el.hasClass('disappear')) {
				// hide sidebar
				this._updateDetailsView(null);
			}
		},

		/**
		 * Event handler for when selecting/deselecting all files
		 */
		_onClickSelectAll: function(e) {
			var hiddenFiles = this.$fileList.find('tr.hidden');
			var checked = e.target.checked;

			if (hiddenFiles.length > 0) {
				// set indeterminate alongside checked
				e.target.indeterminate = checked;
			} else {
				e.target.indeterminate = false
			}

			// Select only visible checkboxes to filter out unmatched file in search
			this.$fileList.find('td.selection > .selectCheckBox:visible').prop('checked', checked)
				.closest('tr').toggleClass('selected', checked);

			if (checked) {
				for (var i = 0; i < this.files.length; i++) {
					// a search will automatically hide the unwanted rows
					// let's only select the matches
					var fileData = this.files[i];
					var fileRow = this.$fileList.find('tr[data-id=' + fileData.id + ']');
					// do not select already selected ones
					if (!fileRow.hasClass('hidden') && _.isUndefined(this._selectedFiles[fileData.id])) {
						this._selectedFiles[fileData.id] = fileData;
						this._selectionSummary.add(fileData);
					}
				}
			} else {
				// if we have some hidden row, then we're in a search
				// Let's only deselect the visible ones
				if (hiddenFiles.length > 0) {
					var visibleFiles = this.$fileList.find('tr:not(.hidden)');
					var self = this;
					visibleFiles.each(function() {
						var id = parseInt($(this).data('id'));
						// do not deselect already deselected ones
						if (!_.isUndefined(self._selectedFiles[id])) {
							// a search will automatically hide the unwanted rows
							// let's only select the matches
							var fileData = self._selectedFiles[id];
							delete self._selectedFiles[fileData.id];
							self._selectionSummary.remove(fileData);
						}
					});
				} else {
					this._selectedFiles = {};
					this._selectionSummary.clear();
				}
			}
			this.updateSelectionSummary();
			if (this._detailsView && !this._detailsView.$el.hasClass('disappear')) {
				// hide sidebar
				this._updateDetailsView(null);
			}
		},

		/**
		 * Event handler for when clicking on "Download" for the selected files
		 */
		_onClickDownloadSelected: function(event) {
			var files;
			var self = this;
			var dir = this.getCurrentDirectory();

			if (this.isAllSelected() && this.getSelectedFiles().length > 1) {
				files = OC.basename(dir);
				dir = OC.dirname(dir) || '/';
			}
			else {
				files = _.pluck(this.getSelectedFiles(), 'name');
			}

			// don't allow a second click on the download action
			if(this.fileMultiSelectMenu.isDisabled('download')) {
				return false;
			}

			this.fileMultiSelectMenu.toggleLoading('download', true);
			var disableLoadingState = function(){
				self.fileMultiSelectMenu.toggleLoading('download', false);
			};

			if(this.getSelectedFiles().length > 1) {
				OCA.Files.Files.handleDownload(this.getDownloadUrl(files, dir, true), disableLoadingState);
			}
			else {
				var first = this.getSelectedFiles()[0];
				OCA.Files.Files.handleDownload(this.getDownloadUrl(first.name, dir, true), disableLoadingState);
			}
			event.preventDefault();
		},

		/**
		 * Event handler for when clicking on "Move" for the selected files
		 */
		_onClickCopyMoveSelected: function(event) {
			var files;
			var self = this;

			files = _.pluck(this.getSelectedFiles(), 'name');

			// don't allow a second click on the download action
			if(this.fileMultiSelectMenu.isDisabled('copyMove')) {
				return false;
			}

			var disableLoadingState = function(){
				self.fileMultiSelectMenu.toggleLoading('copyMove', false);
			};

			var actions = this.isSelectedMovable() ? OC.dialogs.FILEPICKER_TYPE_COPY_MOVE : OC.dialogs.FILEPICKER_TYPE_COPY;
			var dialogDir = self.getCurrentDirectory();
			if (typeof self.dirInfo.dirLastCopiedTo !== 'undefined') {
				dialogDir = self.dirInfo.dirLastCopiedTo;
			}
			OC.dialogs.filepicker(t('files', 'Choose target folder'), function(targetPath, type) {
				self.fileMultiSelectMenu.toggleLoading('copyMove', true);
				if (type === OC.dialogs.FILEPICKER_TYPE_COPY) {
					self.copy(files, targetPath, disableLoadingState);
				}
				if (type === OC.dialogs.FILEPICKER_TYPE_MOVE) {
					self.move(files, targetPath, disableLoadingState);
				}
				self.dirInfo.dirLastCopiedTo = targetPath;
			}, false, "httpd/unix-directory", true, actions, dialogDir);
			event.preventDefault();
		},

		/**
		 * Event handler for when clicking on "Delete" for the selected files
		 */
		_onClickDeleteSelected: function(event) {
			var files = null;
			if (!this.isAllSelected()) {
				files = _.pluck(this.getSelectedFiles(), 'name');
			}
			this.do_delete(files);
			event.preventDefault();
		},

		/**
		 * Event handler when clicking on a table header
		 */
		_onClickHeader: function(e) {
			if (this.$table.hasClass('multiselect')) {
				return;
			}
			var $target = $(e.target);
			var sort;
			if (!$target.is('a')) {
				$target = $target.closest('a');
			}
			sort = $target.attr('data-sort');
			if (sort && this._allowSorting) {
				if (this._sort === sort) {
					this.setSort(sort, (this._sortDirection === 'desc')?'asc':'desc', true, true);
				}
				else {
					if ( sort === 'name' ) {	//default sorting of name is opposite to size and mtime
						this.setSort(sort, 'asc', true, true);
					}
					else {
						this.setSort(sort, 'desc', true, true);
					}
				}
			}
		},

		/**
		 * Event handler when clicking on a bread crumb
		 */
		_onClickBreadCrumb: function(e) {
			// Select a crumb or a crumb in the menu
			var $el = $(e.target).closest('.crumb, .crumblist'),
				$targetDir = $el.data('dir');

			if ($targetDir !== undefined && e.which === 1) {
				e.preventDefault();
				this.changeDirectory($targetDir, true, true);
				this.updateSearch();
			}
		},

		/**
		 * Event handler for when scrolling the list container.
		 * This appends/renders the next page of entries when reaching the bottom.
		 */
		_onScroll: function(e) {
			if (this.$container.scrollTop() + this.$container.height() > this.$el.height() - 300) {
				this._nextPage(true);
			}
		},

		/**
		 * Event handler when dropping on a breadcrumb
		 */
		_onDropOnBreadCrumb: function( event, ui ) {
			var self = this;
			var $target = $(event.target);
			if (!$target.is('.crumb, .crumblist')) {
				$target = $target.closest('.crumb, .crumblist');
			}
			var targetPath = $(event.target).data('dir');
			var dir = this.getCurrentDirectory();
			while (dir.substr(0,1) === '/') {//remove extra leading /'s
				dir = dir.substr(1);
			}
			dir = '/' + dir;
			if (dir.substr(-1,1) !== '/') {
				dir = dir + '/';
			}
			// do nothing if dragged on current dir
			if (targetPath === dir || targetPath + '/' === dir) {
				return;
			}

			var files = this.getSelectedFiles();
			if (files.length === 0) {
				// single one selected without checkbox?
				files = _.map(ui.helper.find('tr'), function(el) {
					return self.elementToFile($(el));
				});
			}

			var movePromise = this.move(_.pluck(files, 'name'), targetPath);

			// re-enable td elements to be droppable
			// sometimes the filename drop handler is still called after re-enable,
			// it seems that waiting for a short time before re-enabling solves the problem
			setTimeout(function() {
				self.$el.find('td.filename.ui-droppable').droppable('enable');
			}, 10);

			return movePromise;
		},

		/**
		 * Sets a new page title
		 */
		setPageTitle: function(title){
			if (title) {
				title += ' - ';
			} else {
				title = '';
			}
			title += this.appName;
			// Sets the page title with the " - Nextcloud" suffix as in templates
			window.document.title = title + ' - ' + OC.theme.title;

			return true;
		},
		/**
		 * Returns the file info for the given file name from the internal collection.
		 *
		 * @param {string} fileName file name
		 * @return {OCA.Files.FileInfo} file info or null if it was not found
		 *
		 * @since 8.2
		 */
		findFile: function(fileName) {
			return _.find(this.files, function(aFile) {
				return (aFile.name === fileName);
			}) || null;
		},
		/**
		 * Returns the tr element for a given file name, but only if it was already rendered.
		 *
		 * @param {string} fileName file name
		 * @return {Object} jQuery object of the matching row
		 */
		findFileEl: function(fileName){
			// use filterAttr to avoid escaping issues
			return this.$fileList.find('tr').filterAttr('data-file', fileName);
		},

		/**
		 * Returns the file data from a given file element.
		 * @param $el file tr element
		 * @return file data
		 */
		elementToFile: function($el){
			$el = $($el);
			var data = {
				id: parseInt($el.attr('data-id'), 10),
				name: $el.attr('data-file'),
				mimetype: $el.attr('data-mime'),
				mtime: parseInt($el.attr('data-mtime'), 10),
				type: $el.attr('data-type'),
				etag: $el.attr('data-etag'),
				permissions: parseInt($el.attr('data-permissions'), 10),
				hasPreview: $el.attr('data-has-preview') === 'true',
				isEncrypted: $el.attr('data-e2eencrypted') === 'true'
			};
			var size = $el.attr('data-size');
			if (size) {
				data.size = parseInt(size, 10);
			}
			var icon = $el.attr('data-icon');
			if (icon) {
				data.icon = icon;
			}
			var mountType = $el.attr('data-mounttype');
			if (mountType) {
				data.mountType = mountType;
			}
			var path = $el.attr('data-path');
			if (path) {
				data.path = path;
			}
			return data;
		},

		/**
		 * Appends the next page of files into the table
		 * @param animate true to animate the new elements
		 * @return array of DOM elements of the newly added files
		 */
		_nextPage: function(animate) {
			var index = this.$fileList.children().length,
				count = this.pageSize(),
				hidden,
				tr,
				fileData,
				newTrs = [],
				isAllSelected = this.isAllSelected(),
				showHidden = this._filesConfig.get('showhidden');

			if (index >= this.files.length) {
				return false;
			}

			while (count > 0 && index < this.files.length) {
				fileData = this.files[index];
				if (this._filter) {
					hidden = fileData.name.toLowerCase().indexOf(this._filter.toLowerCase()) === -1;
				} else {
					hidden = false;
				}
				tr = this._renderRow(fileData, {updateSummary: false, silent: true, hidden: hidden});
				this.$fileList.append(tr);
				if (isAllSelected || this._selectedFiles[fileData.id]) {
					tr.addClass('selected');
					tr.find('.selectCheckBox').prop('checked', true);
				}
				if (animate) {
					tr.addClass('appear transparent');
				}
				newTrs.push(tr);
				index++;
				// only count visible rows
				if (showHidden || !tr.hasClass('hidden-file')) {
					count--;
				}
			}

			// trigger event for newly added rows
			if (newTrs.length > 0) {
				this.$fileList.trigger($.Event('fileActionsReady', {fileList: this, $files: newTrs}));
			}

			if (animate) {
				// defer, for animation
				window.setTimeout(function() {
					for (var i = 0; i < newTrs.length; i++ ) {
						newTrs[i].removeClass('transparent');
					}
				}, 0);
			}

			return newTrs;
		},

		/**
		 * Event handler for when file actions were updated.
		 * This will refresh the file actions on the list.
		 */
		_onFileActionsUpdated: function() {
			var self = this;
			var $files = this.$fileList.find('tr');
			if (!$files.length) {
				return;
			}

			$files.each(function() {
				self.fileActions.display($(this).find('td.filename'), false, self);
			});
			this.$fileList.trigger($.Event('fileActionsReady', {fileList: this, $files: $files}));

		},

		/**
		 * Sets the files to be displayed in the list.
		 * This operation will re-render the list and update the summary.
		 * @param filesArray array of file data (map)
		 */
		setFiles: function(filesArray) {
			var self = this;

			// detach to make adding multiple rows faster
			this.files = filesArray;

			this.$fileList.empty();

			if (this._allowSelection) {
				// The results table, which has no selection column, checks
				// whether the main table has a selection column or not in order
				// to align its contents with those of the main table.
				this.$el.addClass('has-selection');
			}

			// clear "Select all" checkbox
			this.$el.find('.select-all').prop('checked', false);

			// Save full files list while rendering

			this.isEmpty = this.files.length === 0;
			this._nextPage();

			this.updateEmptyContent();

			this.fileSummary.calculate(this.files);

			this._selectedFiles = {};
			this._selectionSummary.clear();
			this.updateSelectionSummary();
			$(window).scrollTop(0);

			this.$fileList.trigger(jQuery.Event('updated'));
			_.defer(function() {
				self.$el.closest('#app-content').trigger(jQuery.Event('apprendered'));
			});
		},

		/**
		 * Returns whether the given file info must be hidden
		 *
		 * @param {OC.Files.FileInfo} fileInfo file info
		 *
		 * @return {boolean} true if the file is a hidden file, false otherwise
		 */
		_isHiddenFile: function(file) {
			return file.name && file.name.charAt(0) === '.';
		},

		/**
		 * Returns the icon URL matching the given file info
		 *
		 * @param {OC.Files.FileInfo} fileInfo file info
		 *
		 * @return {string} icon URL
		 */
		_getIconUrl: function(fileInfo) {
			var mimeType = fileInfo.mimetype || 'application/octet-stream';
			if (mimeType === 'httpd/unix-directory') {
				// use default folder icon
				if (fileInfo.mountType === 'shared' || fileInfo.mountType === 'shared-root') {
					return OC.MimeType.getIconUrl('dir-shared');
				} else if (fileInfo.mountType === 'external-root') {
					return OC.MimeType.getIconUrl('dir-external');
				} else if (fileInfo.mountType !== undefined && fileInfo.mountType !== '') {
					return OC.MimeType.getIconUrl('dir-' + fileInfo.mountType);
				} else if (fileInfo.shareTypes && (
					fileInfo.shareTypes.indexOf(OC.Share.SHARE_TYPE_LINK) > -1
					|| fileInfo.shareTypes.indexOf(OC.Share.SHARE_TYPE_EMAIL) > -1)
				) {
					return OC.MimeType.getIconUrl('dir-public')
				} else if (fileInfo.shareTypes && fileInfo.shareTypes.length > 0) {
					return OC.MimeType.getIconUrl('dir-shared')
				}
				return OC.MimeType.getIconUrl('dir');
			}
			return OC.MimeType.getIconUrl(mimeType);
		},

		/**
		 * Creates a new table row element using the given file data.
		 * @param {OC.Files.FileInfo} fileData file info attributes
		 * @param options map of attributes
		 * @return new tr element (not appended to the table)
		 */
		_createRow: function(fileData, options) {
			var td, simpleSize, basename, extension, sizeColor,
				icon = fileData.icon || this._getIconUrl(fileData),
				name = fileData.name,
				// TODO: get rid of type, only use mime type
				type = fileData.type || 'file',
				mtime = parseInt(fileData.mtime, 10),
				mime = fileData.mimetype,
				path = fileData.path,
				dataIcon = null,
				linkUrl;
			options = options || {};

			if (isNaN(mtime)) {
				mtime = new Date().getTime();
			}

			if (type === 'dir') {
				mime = mime || 'httpd/unix-directory';

				if (fileData.isEncrypted) {
					icon = OC.MimeType.getIconUrl('dir-encrypted');
					dataIcon = icon;
				} else if (fileData.mountType && fileData.mountType.indexOf('external') === 0) {
					icon = OC.MimeType.getIconUrl('dir-external');
					dataIcon = icon;
				}
			}

			var permissions = fileData.permissions;
			if (permissions === undefined || permissions === null) {
				permissions = this.getDirectoryPermissions();
			}

			//containing tr
			var tr = $('<tr></tr>').attr({
				"data-id" : fileData.id,
				"data-type": type,
				"data-size": fileData.size,
				"data-file": name,
				"data-mime": mime,
				"data-mtime": mtime,
				"data-etag": fileData.etag,
				"data-permissions": permissions,
				"data-has-preview": fileData.hasPreview !== false,
				"data-e2eencrypted": fileData.isEncrypted === true
			});

			if (dataIcon) {
				// icon override
				tr.attr('data-icon', dataIcon);
			}

			if (fileData.mountType) {
				// dirInfo (parent) only exist for the "real" file list
				if (this.dirInfo.id) {
					// FIXME: HACK: detect shared-root
					if (fileData.mountType === 'shared' && this.dirInfo.mountType !== 'shared' && this.dirInfo.mountType !== 'shared-root') {
						// if parent folder isn't share, assume the displayed folder is a share root
						fileData.mountType = 'shared-root';
					} else if (fileData.mountType === 'external' && this.dirInfo.mountType !== 'external' && this.dirInfo.mountType !== 'external-root') {
						// if parent folder isn't external, assume the displayed folder is the external storage root
						fileData.mountType = 'external-root';
					}
				}
				tr.attr('data-mounttype', fileData.mountType);
			}

			if (!_.isUndefined(path)) {
				tr.attr('data-path', path);
			}
			else {
				path = this.getCurrentDirectory();
			}

			// selection td
			if (this._allowSelection) {
				td = $('<td class="selection"></td>');

				td.append(
					'<input id="select-' + this.id + '-' + fileData.id +
					'" type="checkbox" class="selectCheckBox checkbox"/><label for="select-' + this.id + '-' + fileData.id + '">' +
					'<span class="hidden-visually">' + t('files', 'Select') + '</span>' +
					'</label>'
				);

				tr.append(td);
			}

			// filename td
			td = $('<td class="filename"></td>');


			// linkUrl
			if (mime === 'httpd/unix-directory') {
				linkUrl = this.linkTo(path + '/' + name);
			}
			else {
				linkUrl = this.getDownloadUrl(name, path, type === 'dir');
			}
			var linkElem = $('<a></a>').attr({
				"class": "name",
				"href": linkUrl
			});
			if (this._defaultFileActionsDisabled) {
				linkElem = $('<p></p>').attr({
					"class": "name"
				})
			}

			linkElem.append('<div class="thumbnail-wrapper"><div class="thumbnail" style="background-image:url(' + icon + ');"></div></div>');

			// from here work on the display name
			name = fileData.displayName || name;

			// show hidden files (starting with a dot) completely in gray
			if(name.indexOf('.') === 0) {
				basename = '';
				extension = name;
			// split extension from filename for non dirs
			} else if (mime !== 'httpd/unix-directory' && name.indexOf('.') !== -1) {
				basename = name.substr(0, name.lastIndexOf('.'));
				extension = name.substr(name.lastIndexOf('.'));
			} else {
				basename = name;
				extension = false;
			}
			var nameSpan=$('<span></span>').addClass('nametext');
			var innernameSpan = $('<span></span>').addClass('innernametext').text(basename);


			var conflictingItems = this.$fileList.find('tr[data-file="' + this._jqSelEscape(name) + '"]');
			if (conflictingItems.length !== 0) {
				if (conflictingItems.length === 1) {
					// Update the path on the first conflicting item
					var $firstConflict = $(conflictingItems[0]),
						firstConflictPath = $firstConflict.attr('data-path') + '/';
					if (firstConflictPath.charAt(0) === '/') {
						firstConflictPath = firstConflictPath.substr(1);
					}
					if (firstConflictPath && firstConflictPath !== '/') {
						$firstConflict.find('td.filename span.innernametext').prepend($('<span></span>').addClass('conflict-path').text(firstConflictPath));
					}
				}

				var conflictPath = path + '/';
				if (conflictPath.charAt(0) === '/') {
					conflictPath = conflictPath.substr(1);
				}
				if (path && path !== '/') {
					nameSpan.append($('<span></span>').addClass('conflict-path').text(conflictPath));
				}
			}

			nameSpan.append(innernameSpan);
			linkElem.append(nameSpan);
			if (extension) {
				nameSpan.append($('<span></span>').addClass('extension').text(extension));
			}
			if (fileData.extraData) {
				if (fileData.extraData.charAt(0) === '/') {
					fileData.extraData = fileData.extraData.substr(1);
				}
				nameSpan.addClass('extra-data').attr('title', fileData.extraData);
				nameSpan.tooltip({placement: 'top'});
			}
			// dirs can show the number of uploaded files
			if (mime === 'httpd/unix-directory') {
				linkElem.append($('<span></span>').attr({
					'class': 'uploadtext',
					'currentUploads': 0
				}));
			}
			td.append(linkElem);
			tr.append(td);

			var isDarkTheme = OCA.Accessibility && OCA.Accessibility.theme === 'dark'

			try {
				var maxContrastHex = window.getComputedStyle(document.documentElement)
					.getPropertyValue('--color-text-maxcontrast').trim()
				if (maxContrastHex.length < 4) {
					throw Error();
				}
				var maxContrast = parseInt(maxContrastHex.substring(1, 3), 16)
			} catch(error) {
				var maxContrast = isDarkTheme ? 130 : 118
			}

			// size column
			if (typeof(fileData.size) !== 'undefined' && fileData.size >= 0) {
				simpleSize = OC.Util.humanFileSize(parseInt(fileData.size, 10), true);
				// rgb(118, 118, 118) / #767676
				// min. color contrast for normal text on white background according to WCAG AA
				sizeColor = Math.round(118-Math.pow((fileData.size/(1024*1024)), 2));

				// ensure that the brightest color is still readable
				// min. color contrast for normal text on white background according to WCAG AA
				if (sizeColor >= maxContrast) {
					sizeColor = maxContrast;
				}

				if (isDarkTheme) {
					sizeColor = Math.abs(sizeColor);
					// ensure that the dimmest color is still readable
					// min. color contrast for normal text on black background according to WCAG AA
					if (sizeColor < maxContrast) {
						sizeColor = maxContrast;
					}
				}
			} else {
				simpleSize = t('files', 'Pending');
			}

			td = $('<td></td>').attr({
				"class": "filesize",
				"style": 'color:rgb(' + sizeColor + ',' + sizeColor + ',' + sizeColor + ')'
			}).text(simpleSize);
			tr.append(td);

			// date column (1000 milliseconds to seconds, 60 seconds, 60 minutes, 24 hours)
			// difference in days multiplied by 5 - brightest shade for files older than 32 days (160/5)
			var modifiedColor = Math.round(((new Date()).getTime() - mtime )/1000/60/60/24*5 );

			// ensure that the brightest color is still readable
			// min. color contrast for normal text on white background according to WCAG AA
			if (modifiedColor >= maxContrast) {
				modifiedColor = maxContrast;
			}

			if (isDarkTheme) {
				modifiedColor = Math.abs(modifiedColor);

				// ensure that the dimmest color is still readable
				// min. color contrast for normal text on black background according to WCAG AA
				if (modifiedColor < maxContrast) {
					modifiedColor = maxContrast;
				}
			}

			var formatted;
			var text;
			if (mtime > 0) {
				formatted = OC.Util.formatDate(mtime);
				text = OC.Util.relativeModifiedDate(mtime);
			} else {
				formatted = t('files', 'Unable to determine date');
				text = '?';
			}
			td = $('<td></td>').attr({ "class": "date" });
			td.append($('<span></span>').attr({
				"class": "modified live-relative-timestamp",
				"title": formatted,
				"data-timestamp": mtime,
				"style": 'color:rgb('+modifiedColor+','+modifiedColor+','+modifiedColor+')'
			}).text(text)
			  .tooltip({placement: 'top'})
			);
			tr.find('.filesize').text(simpleSize);
			tr.append(td);
			return tr;
		},

		/* escape a selector expression for jQuery */
		_jqSelEscape: function (expression) {
			if (expression) {
				return expression.replace(/[!"#$%&'()*+,.\/:;<=>?@\[\\\]^`{|}~]/g, '\\$&');
			}
			return null;
		},

		/**
		 * Adds an entry to the files array and also into the DOM
		 * in a sorted manner.
		 *
		 * @param {OC.Files.FileInfo} fileData map of file attributes
		 * @param {Object} [options] map of attributes
		 * @param {boolean} [options.updateSummary] true to update the summary
		 * after adding (default), false otherwise. Defaults to true.
		 * @param {boolean} [options.silent] true to prevent firing events like "fileActionsReady",
		 * defaults to false.
		 * @param {boolean} [options.animate] true to animate the thumbnail image after load
		 * defaults to true.
		 * @return new tr element (not appended to the table)
		 */
		add: function(fileData, options) {
			var index;
			var $tr;
			var $rows;
			var $insertionPoint;
			options = _.extend({animate: true}, options || {});

			// there are three situations to cover:
			// 1) insertion point is visible on the current page
			// 2) insertion point is on a not visible page (visible after scrolling)
			// 3) insertion point is at the end of the list

			$rows = this.$fileList.children();
			index = this._findInsertionIndex(fileData);
			if (index > this.files.length) {
				index = this.files.length;
			}
			else {
				$insertionPoint = $rows.eq(index);
			}

			// is the insertion point visible ?
			if ($insertionPoint.length) {
				// only render if it will really be inserted
				$tr = this._renderRow(fileData, options);
				$insertionPoint.before($tr);
			}
			else {
				// if insertion point is after the last visible
				// entry, append
				if (index === $rows.length) {
					$tr = this._renderRow(fileData, options);
					this.$fileList.append($tr);
				}
			}

			this.isEmpty = false;
			this.files.splice(index, 0, fileData);

			if ($tr && options.animate) {
				$tr.addClass('appear transparent');
				window.setTimeout(function() {
					$tr.removeClass('transparent');
					$("#fileList tr").removeClass('mouseOver');
					$tr.addClass('mouseOver');
				});
			}

			if (options.scrollTo) {
				this.scrollTo(fileData.name);
			}

			// defaults to true if not defined
			if (typeof(options.updateSummary) === 'undefined' || !!options.updateSummary) {
				this.fileSummary.add(fileData, true);
				this.updateEmptyContent();
			}

			return $tr;
		},

		/**
		 * Creates a new row element based on the given attributes
		 * and returns it.
		 *
		 * @param {OC.Files.FileInfo} fileData map of file attributes
		 * @param {Object} [options] map of attributes
		 * @param {int} [options.index] index at which to insert the element
		 * @param {boolean} [options.updateSummary] true to update the summary
		 * after adding (default), false otherwise. Defaults to true.
		 * @param {boolean} [options.animate] true to animate the thumbnail image after load
		 * defaults to true.
		 * @return new tr element (not appended to the table)
		 */
		_renderRow: function(fileData, options) {
			options = options || {};
			var type = fileData.type || 'file',
				mime = fileData.mimetype,
				path = fileData.path || this.getCurrentDirectory(),
				permissions = parseInt(fileData.permissions, 10) || 0;

			var isEndToEndEncrypted = (type === 'dir' && fileData.isEncrypted);

			if (!isEndToEndEncrypted && fileData.isShareMountPoint) {
				permissions = permissions | OC.PERMISSION_UPDATE;
			}

			if (type === 'dir') {
				mime = mime || 'httpd/unix-directory';
			}
			var tr = this._createRow(
				fileData,
				options
			);
			var filenameTd = tr.find('td.filename');

			// TODO: move dragging to FileActions ?
			// enable drag only for deletable files
			if (this._dragOptions && permissions & OC.PERMISSION_DELETE) {
				filenameTd.draggable(this._dragOptions);
			}
			// allow dropping on folders
			if (this._folderDropOptions && mime === 'httpd/unix-directory') {
				tr.droppable(this._folderDropOptions);
			}

			if (options.hidden) {
				tr.addClass('hidden');
			}

			if (this._isHiddenFile(fileData)) {
				tr.addClass('hidden-file');
			}

			// display actions
			this.fileActions.display(filenameTd, !options.silent, this);

			if (mime !== 'httpd/unix-directory' && fileData.hasPreview !== false) {
				var iconDiv = filenameTd.find('.thumbnail');
				// lazy load / newly inserted td ?
				// the typeof check ensures that the default value of animate is true
				if (typeof(options.animate) === 'undefined' || !!options.animate) {
					this.lazyLoadPreview({
						fileId: fileData.id,
						path: path + '/' + fileData.name,
						mime: mime,
						etag: fileData.etag,
						callback: function(url) {
							iconDiv.css('background-image', 'url("' + url + '")');
						}
					});
				}
				else {
					// set the preview URL directly
					var urlSpec = {
							file: path + '/' + fileData.name,
							c: fileData.etag
						};
					var previewUrl = this.generatePreviewUrl(urlSpec);
					previewUrl = previewUrl.replace(/\(/g, '%28').replace(/\)/g, '%29');
					iconDiv.css('background-image', 'url("' + previewUrl + '")');
				}
			}
			return tr;
		},
		/**
		 * Returns the current directory
		 * @method getCurrentDirectory
		 * @return current directory
		 */
		getCurrentDirectory: function(){
			return this._currentDirectory || this.$el.find('#dir').val() || '/';
		},
		/**
		 * Returns the directory permissions
		 * @return permission value as integer
		 */
		getDirectoryPermissions: function() {
			return this && this.dirInfo && this.dirInfo.permissions ? this.dirInfo.permissions : parseInt(this.$el.find('#permissions').val(), 10);
		},
		/**
		 * Changes the current directory and reload the file list.
		 * @param {string} targetDir target directory (non URL encoded)
		 * @param {boolean} [changeUrl=true] if the URL must not be changed (defaults to true)
		 * @param {boolean} [force=false] set to true to force changing directory
		 * @param {string} [fileId] optional file id, if known, to be appended in the URL
		 */
		changeDirectory: function(targetDir, changeUrl, force, fileId) {
			var self = this;
			var currentDir = this.getCurrentDirectory();
			targetDir = targetDir || '/';
			if (!force && currentDir === targetDir) {
				return;
			}
			this._setCurrentDir(targetDir, changeUrl, fileId);

			// discard finished uploads list, we'll get it through a regular reload
			this._uploads = {};
			return this.reload().then(function(success){
				if (!success) {
					self.changeDirectory(currentDir, true);
				}
			});
		},
		linkTo: function(dir) {
			return OC.linkTo('files', 'index.php')+"?dir="+ encodeURIComponent(dir).replace(/%2F/g, '/');
		},

		/**
		 * @param {string} path
		 * @returns {boolean}
		 */
		_isValidPath: function(path) {
			var sections = path.split('/');
			for (var i = 0; i < sections.length; i++) {
				if (sections[i] === '..') {
					return false;
				}
			}

			return path.toLowerCase().indexOf(decodeURI('%0a')) === -1 &&
				path.toLowerCase().indexOf(decodeURI('%00')) === -1;
		},

		/**
		 * Sets the current directory name and updates the breadcrumb.
		 * @param targetDir directory to display
		 * @param changeUrl true to also update the URL, false otherwise (default)
		 * @param {string} [fileId] file id
		 */
		_setCurrentDir: function(targetDir, changeUrl, fileId) {
			targetDir = targetDir.replace(/\\/g, '/');
			if (!this._isValidPath(targetDir)) {
				targetDir = '/';
				changeUrl = true;
			}
			var previousDir = this.getCurrentDirectory(),
				baseDir = OC.basename(targetDir);

			if (baseDir !== '') {
				this.setPageTitle(baseDir);
			}
			else {
				this.setPageTitle();
			}

			if (targetDir.length > 0 && targetDir[0] !== '/') {
				targetDir = '/' + targetDir;
			}
			this._currentDirectory = targetDir;

			// legacy stuff
			this.$el.find('#dir').val(targetDir);

			if (changeUrl !== false) {
				var params = {
					dir: targetDir,
					previousDir: previousDir
				};
				if (fileId) {
					params.fileId = fileId;
				}
				this.$el.trigger(jQuery.Event('changeDirectory', params));
			}
			this.breadcrumb.setDirectory(this.getCurrentDirectory());
		},
		/**
		 * Sets the current sorting and refreshes the list
		 *
		 * @param sort sort attribute name
		 * @param direction sort direction, one of "asc" or "desc"
		 * @param update true to update the list, false otherwise (default)
		 * @param persist true to save changes in the database (default)
		 */
		setSort: function(sort, direction, update, persist) {
			var comparator = FileList.Comparators[sort] || FileList.Comparators.name;
			this._sort = sort;
			this._sortDirection = (direction === 'desc')?'desc':'asc';
			this._sortComparator = function(fileInfo1, fileInfo2) {
				var isFavorite = function(fileInfo) {
					return fileInfo.tags && fileInfo.tags.indexOf(OC.TAG_FAVORITE) >= 0;
				};

				if (isFavorite(fileInfo1) && !isFavorite(fileInfo2)) {
					return -1;
				} else if (!isFavorite(fileInfo1) && isFavorite(fileInfo2)) {
					return 1;
				}

				return direction === 'asc' ? comparator(fileInfo1, fileInfo2) : -comparator(fileInfo1, fileInfo2);
			};

			this.$el.find('thead th .sort-indicator')
				.removeClass(this.SORT_INDICATOR_ASC_CLASS)
				.removeClass(this.SORT_INDICATOR_DESC_CLASS)
				.toggleClass('hidden', true)
				.addClass(this.SORT_INDICATOR_DESC_CLASS);

			this.$el.find('thead th.column-' + sort + ' .sort-indicator')
				.removeClass(this.SORT_INDICATOR_ASC_CLASS)
				.removeClass(this.SORT_INDICATOR_DESC_CLASS)
				.toggleClass('hidden', false)
				.addClass(direction === 'desc' ? this.SORT_INDICATOR_DESC_CLASS : this.SORT_INDICATOR_ASC_CLASS);
			if (update) {
				if (this._clientSideSort) {
					this.files.sort(this._sortComparator);
					this.setFiles(this.files);
				}
				else {
					this.reload();
				}
			}

			if (persist && OC.getCurrentUser().uid) {
				$.post(OC.generateUrl('/apps/files/api/v1/sorting'), {
					mode: sort,
					direction: direction
				});
			}
		},

		/**
		 * Returns list of webdav properties to request
		 */
		_getWebdavProperties: function() {
			return [].concat(this.filesClient.getPropfindProperties());
		},

		/**
		 * Reloads the file list using ajax call
		 *
		 * @return ajax call object
		 */
		reload: function() {
			this._selectedFiles = {};
			this._selectionSummary.clear();
			if (this._currentFileModel) {
				this._currentFileModel.off();
			}
			this._currentFileModel = null;
			this.$el.find('.select-all').prop('checked', false);
			this.showMask();
			this._reloadCall = this.filesClient.getFolderContents(
				this.getCurrentDirectory(), {
					includeParent: true,
					properties: this._getWebdavProperties()
				}
			);
			if (this._detailsView) {
				// close sidebar
				this._updateDetailsView(null);
			}
			this._setCurrentDir(this.getCurrentDirectory(), false);
			var callBack = this.reloadCallback.bind(this);
			return this._reloadCall.then(callBack, callBack);
		},
		reloadCallback: function(status, result) {
			delete this._reloadCall;
			this.hideMask();

			if (status === 401) {
				return false;
			}

			// Firewall Blocked request?
			if (status === 403) {
				// Go home
				this.changeDirectory('/');
				OC.Notification.show(t('files', 'This operation is forbidden'), {type: 'error'});
				return false;
			}

			// Did share service die or something else fail?
			if (status === 500) {
				// Go home
				this.changeDirectory('/');
				OC.Notification.show(t('files', 'This directory is unavailable, please check the logs or contact the administrator'),
					{type: 'error'}
				);
				return false;
			}

			if (status === 503) {
				// Go home
				if (this.getCurrentDirectory() !== '/') {
					this.changeDirectory('/');
					// TODO: read error message from exception
					OC.Notification.show(t('files', 'Storage is temporarily not available'),
						{type: 'error'}
					);
				}
				return false;
			}

			if (status === 400 || status === 404 || status === 405) {
				// go back home
				this.changeDirectory('/');
				return false;
			}
			// aborted ?
			if (status === 0){
				return true;
			}

			this.updateStorageStatistics(true);

			// first entry is the root
			this.dirInfo = result.shift();
			this.breadcrumb.setDirectoryInfo(this.dirInfo);

			if (this.dirInfo.permissions) {
				this._updateDirectoryPermissions();
			}

			result.sort(this._sortComparator);
			this.setFiles(result);

			if (this.dirInfo) {
				var newFileId = this.dirInfo.id;
				// update fileid in URL
				var params = {
					dir: this.getCurrentDirectory()
				};
				if (newFileId) {
					params.fileId = newFileId;
				}
				this.$el.trigger(jQuery.Event('afterChangeDirectory', params));
			}
			return true;
		},

		updateStorageStatistics: function(force) {
			OCA.Files.Files.updateStorageStatistics(this.getCurrentDirectory(), force);
		},

		updateStorageQuotas: function() {
			OCA.Files.Files.updateStorageQuotas();
		},

		/**
		 * @deprecated do not use nor override
		 */
		getAjaxUrl: function(action, params) {
			return OCA.Files.Files.getAjaxUrl(action, params);
		},

		getDownloadUrl: function(files, dir, isDir) {
			return OCA.Files.Files.getDownloadUrl(files, dir || this.getCurrentDirectory(), isDir);
		},

		getUploadUrl: function(fileName, dir) {
			if (_.isUndefined(dir)) {
				dir = this.getCurrentDirectory();
			}

			var pathSections = dir.split('/');
			if (!_.isUndefined(fileName)) {
				pathSections.push(fileName);
			}
			var encodedPath = '';
			_.each(pathSections, function(section) {
				if (section !== '') {
					encodedPath += '/' + encodeURIComponent(section);
				}
			});
			return OC.linkToRemoteBase('webdav') + encodedPath;
		},

		/**
		 * Generates a preview URL based on the URL space.
		 * @param urlSpec attributes for the URL
		 * @param {int} urlSpec.x width
		 * @param {int} urlSpec.y height
		 * @param {String} urlSpec.file path to the file
		 * @return preview URL
		 */
		generatePreviewUrl: function(urlSpec) {
			urlSpec = urlSpec || {};
			if (!urlSpec.x) {
				urlSpec.x = this.$table.data('preview-x') || 250;
			}
			if (!urlSpec.y) {
				urlSpec.y = this.$table.data('preview-y') || 250;
			}
			urlSpec.x *= window.devicePixelRatio;
			urlSpec.y *= window.devicePixelRatio;
			urlSpec.x = Math.ceil(urlSpec.x);
			urlSpec.y = Math.ceil(urlSpec.y);
			urlSpec.forceIcon = 0;

			if (typeof urlSpec.fileId !== 'undefined') {
				delete urlSpec.file;
				return OC.generateUrl('/core/preview?') + $.param(urlSpec);
			} else {
				delete urlSpec.fileId;
				return OC.generateUrl('/core/preview.png?') + $.param(urlSpec);
			}

		},

		/**
		 * Lazy load a file's preview.
		 *
		 * @param path path of the file
		 * @param mime mime type
		 * @param callback callback function to call when the image was loaded
		 * @param etag file etag (for caching)
		 */
		lazyLoadPreview : function(options) {
			var self = this;
			var fileId = options.fileId;
			var path = options.path;
			var mime = options.mime;
			var ready = options.callback;
			var etag = options.etag;

			// get mime icon url
			var iconURL = OC.MimeType.getIconUrl(mime);
			var previewURL,
				urlSpec = {};
			ready(iconURL); // set mimeicon URL

			urlSpec.fileId = fileId;
			urlSpec.file = OCA.Files.Files.fixPath(path);
			if (options.x) {
				urlSpec.x = options.x;
			}
			if (options.y) {
				urlSpec.y = options.y;
			}
			if (options.a) {
				urlSpec.a = options.a;
			}
			if (options.mode) {
				urlSpec.mode = options.mode;
			}

			if (etag){
				// use etag as cache buster
				urlSpec.c = etag;
			}

			previewURL = self.generatePreviewUrl(urlSpec);
			previewURL = previewURL.replace(/\(/g, '%28').replace(/\)/g, '%29');

			// preload image to prevent delay
			// this will make the browser cache the image
			var img = new Image();
			img.onload = function(){
				// if loading the preview image failed (no preview for the mimetype) then img.width will < 5
				if (img.width > 5) {
					ready(previewURL, img);
				} else if (options.error) {
					options.error();
				}
			};
			if (options.error) {
				img.onerror = options.error;
			}
			img.src = previewURL;
		},

		_updateDirectoryPermissions: function() {
			var isCreatable = (this.dirInfo.permissions & OC.PERMISSION_CREATE) !== 0 && this.$el.find('#free_space').val() !== '0';
			this.$el.find('#permissions').val(this.dirInfo.permissions);
			this.$el.find('.creatable').toggleClass('hidden', !isCreatable);
			this.$el.find('.notCreatable').toggleClass('hidden', isCreatable);
		},
		/**
		 * Shows/hides action buttons
		 *
		 * @param show true for enabling, false for disabling
		 */
		showActions: function(show){
			this.$el.find('.actions,#file_action_panel').toggleClass('hidden', !show);
			if (show){
				// make sure to display according to permissions
				var permissions = this.getDirectoryPermissions();
				var isCreatable = (permissions & OC.PERMISSION_CREATE) !== 0;
				this.$el.find('.creatable').toggleClass('hidden', !isCreatable);
				this.$el.find('.notCreatable').toggleClass('hidden', isCreatable);
				// remove old style breadcrumbs (some apps might create them)
				this.$el.find('#controls .crumb').remove();
				// refresh breadcrumbs in case it was replaced by an app
				this.breadcrumb.render();
			}
			else{
				this.$el.find('.creatable, .notCreatable').addClass('hidden');
			}
		},
		/**
		 * Enables/disables viewer mode.
		 * In viewer mode, apps can embed themselves under the controls bar.
		 * In viewer mode, the actions of the file list will be hidden.
		 * @param show true for enabling, false for disabling
		 */
		setViewerMode: function(show){
			this.showActions(!show);
			this.$el.find('#filestable').toggleClass('hidden', show);
			this.$el.trigger(new $.Event('changeViewerMode', {viewerModeEnabled: show}));
		},
		/**
		 * Removes a file entry from the list
		 * @param name name of the file to remove
		 * @param {Object} [options] map of attributes
		 * @param {boolean} [options.updateSummary] true to update the summary
		 * after removing, false otherwise. Defaults to true.
		 * @return deleted element
		 */
		remove: function(name, options){
			options = options || {};
			var fileEl = this.findFileEl(name);
			var fileData = _.findWhere(this.files, {name: name});
			if (!fileData) {
				return;
			}
			var fileId = fileData.id;
			if (this._selectedFiles[fileId]) {
				// remove from selection first
				this._selectFileEl(fileEl, false);
				this.updateSelectionSummary();
			}
			if (this._selectedFiles[fileId]) {
				delete this._selectedFiles[fileId];
				this._selectionSummary.remove(fileData);
				this.updateSelectionSummary();
			}
			var index = this.files.findIndex(function(el){return el.name==name;});
			this.files.splice(index, 1);

			// TODO: improve performance on batch update
			this.isEmpty = !this.files.length;
			if (typeof(options.updateSummary) === 'undefined' || !!options.updateSummary) {
				this.updateEmptyContent();
				this.fileSummary.remove({type: fileData.type, size: fileData.size}, true);
			}

			if (!fileEl.length) {
				return null;
			}

			if (this._dragOptions && (fileEl.data('permissions') & OC.PERMISSION_DELETE)) {
				// file is only draggable when delete permissions are set
				fileEl.find('td.filename').draggable('destroy');
			}
			if (this._currentFileModel && this._currentFileModel.get('id') === fileId) {
				// Note: in the future we should call destroy() directly on the model
				// and the model will take care of the deletion.
				// Here we only trigger the event to notify listeners that
				// the file was removed.
				this._currentFileModel.trigger('destroy');
				this._updateDetailsView(null);
			}
			fileEl.remove();

			var lastIndex = this.$fileList.children().length;
			// if there are less elements visible than one page
			// but there are still pending elements in the array,
			// then directly append the next page
			if (lastIndex < this.files.length && lastIndex < this.pageSize()) {
				this._nextPage(true);
			}

			return fileEl;
		},
		/**
		 * Finds the index of the row before which the given
		 * fileData should be inserted, considering the current
		 * sorting
		 *
		 * @param {OC.Files.FileInfo} fileData file info
		 */
		_findInsertionIndex: function(fileData) {
			var index = 0;
			while (index < this.files.length && this._sortComparator(fileData, this.files[index]) > 0) {
				index++;
			}
			return index;
		},

		/**
		 * Moves a file to a given target folder.
		 *
		 * @param fileNames array of file names to move
		 * @param targetPath absolute target path
		 * @param callback function to call when movement is finished
		 * @param dir the dir path where fileNames are located (optionnal, will take current folder if undefined)
		 */
		move: function(fileNames, targetPath, callback, dir) {
			var self = this;

			dir = typeof dir === 'string' ? dir : this.getCurrentDirectory();
			if (dir.charAt(dir.length - 1) !== '/') {
				dir += '/';
			}
			var target = OC.basename(targetPath);
			if (!_.isArray(fileNames)) {
				fileNames = [fileNames];
			}

			var moveFileFunction = function(fileName) {
				var $tr = self.findFileEl(fileName);
				self.showFileBusyState($tr, true);
				if (targetPath.charAt(targetPath.length - 1) !== '/') {
					// make sure we move the files into the target dir,
					// not overwrite it
					targetPath = targetPath + '/';
				}
				return self.filesClient.move(dir + fileName, targetPath + fileName)
					.done(function() {
						// if still viewing the same directory
						if (OC.joinPaths(self.getCurrentDirectory(), '/') === OC.joinPaths(dir, '/')) {
							// recalculate folder size
							var oldFile = self.findFileEl(target);
							var newFile = self.findFileEl(fileName);
							var oldSize = oldFile.data('size');
							var newSize = oldSize + newFile.data('size');
							oldFile.data('size', newSize);
							oldFile.find('td.filesize').text(OC.Util.humanFileSize(newSize));

							self.remove(fileName);
						}
					})
					.fail(function(status) {
						if (status === 412) {
							// TODO: some day here we should invoke the conflict dialog
							OC.Notification.show(t('files', 'Could not move "{file}", target exists',
								{file: fileName}), {type: 'error'}
							);
						} else {
							OC.Notification.show(t('files', 'Could not move "{file}"',
								{file: fileName}), {type: 'error'}
							);
						}
					})
					.always(function() {
						self.showFileBusyState($tr, false);
					});
			};
			return this.reportOperationProgress(fileNames, moveFileFunction, callback);
		},

		_reflect: function (promise){
			return promise.then(function(v){ return {};}, function(e){ return {};});
		},

		reportOperationProgress: function (fileNames, operationFunction, callback){
			var self = this;
			self._operationProgressBar.showProgressBar(false);
			var mcSemaphore = new OCA.Files.Semaphore(5);
			var counter = 0;
			var promises = _.map(fileNames, function(arg) {
				return mcSemaphore.acquire().then(function(){
					return operationFunction(arg).always(function(){
						mcSemaphore.release();
						counter++;
						self._operationProgressBar.setProgressBarValue(100.0*counter/fileNames.length);
					});
				});
			});

			return Promise.all(_.map(promises, self._reflect)).then(function(){
				if (callback) {
					callback();
				}
				self._operationProgressBar.hideProgressBar();
			});
		},

		/**
		 * Copies a file to a given target folder.
		 *
		 * @param fileNames array of file names to copy
		 * @param targetPath absolute target path
		 * @param callback to call when copy is finished with success
		 * @param dir the dir path where fileNames are located (optionnal, will take current folder if undefined)
		 */
		copy: function(fileNames, targetPath, callback, dir) {
			var self = this;
			var filesToNotify = [];
			var count = 0;

			dir = typeof dir === 'string' ? dir : this.getCurrentDirectory();
			if (dir.charAt(dir.length - 1) !== '/') {
				dir += '/';
			}
			var target = OC.basename(targetPath);
			if (!_.isArray(fileNames)) {
				fileNames = [fileNames];
			}
			var copyFileFunction = function(fileName) {
				var $tr = self.findFileEl(fileName);
				self.showFileBusyState($tr, true);
				if (targetPath.charAt(targetPath.length - 1) !== '/') {
					// make sure we move the files into the target dir,
					// not overwrite it
					targetPath = targetPath + '/';
				}
				var targetPathAndName = targetPath + fileName;
				if ((dir + fileName) === targetPathAndName) {
					var dotIndex = targetPathAndName.indexOf(".");
					if ( dotIndex > 1) {
						var leftPartOfName = targetPathAndName.substr(0, dotIndex);
						var fileNumber = leftPartOfName.match(/\d+/);
						// TRANSLATORS name that is appended to copied files with the same name, will be put in parenthesis and appened with a number if it is the second+ copy
						var copyNameLocalized = t('files', 'copy');
						if (isNaN(fileNumber) ) {
							fileNumber++;
							targetPathAndName = targetPathAndName.replace(/(?=\.[^.]+$)/g, " (" + copyNameLocalized + " " + fileNumber + ")");
						}
						else {
							// Check if we have other files with 'copy X' and the same name
							var maxNum = 1;
							if (self.files !== null) {
								leftPartOfName = leftPartOfName.replace("/", "");
								leftPartOfName = leftPartOfName.replace(new RegExp("\\(" + copyNameLocalized + "( \\d+)?\\)"),"");
								// find the last file with the number extension and add one to the new name
								for (var j = 0; j < self.files.length; j++) {
									var cName = self.files[j].name;
									if (cName.indexOf(leftPartOfName) > -1) {
										if (cName.indexOf("(" + copyNameLocalized + ")") > 0) {
											targetPathAndName = targetPathAndName.replace(new RegExp(" \\(" + copyNameLocalized + "\\)"),"");
											if (maxNum == 1) {
												maxNum = 2;
											}
										}
										else {
											var cFileNumber = cName.match(new RegExp("\\(" + copyNameLocalized + " (\\d+)\\)"));
											if (cFileNumber && parseInt(cFileNumber[1]) >= maxNum) {
												maxNum = parseInt(cFileNumber[1]) + 1;
											}
										}
									}
								}
								targetPathAndName = targetPathAndName.replace(new RegExp(" \\(" + copyNameLocalized + " \\d+\\)"),"");
							}
							// Create the new file name with _x at the end
							// Start from 2 per a special request of the 'standard'
							var extensionName = " (" + copyNameLocalized + " " + maxNum +")";
							if (maxNum == 1) {
								extensionName = " (" + copyNameLocalized + ")";
							}
							targetPathAndName = targetPathAndName.replace(/(?=\.[^.]+$)/g, extensionName);
						}
					}
				}
				return self.filesClient.copy(dir + fileName, targetPathAndName)
					.done(function () {
						filesToNotify.push(fileName);

						// if still viewing the same directory
						if (OC.joinPaths(self.getCurrentDirectory(), '/') === OC.joinPaths(dir, '/')) {
							// recalculate folder size
							var oldFile = self.findFileEl(target);
							var newFile = self.findFileEl(fileName);
							var oldSize = oldFile.data('size');
							var newSize = oldSize + newFile.data('size');
							oldFile.data('size', newSize);
							oldFile.find('td.filesize').text(OC.Util.humanFileSize(newSize));
						}
						self.reload();
					})
					.fail(function(status) {
						if (status === 412) {
							// TODO: some day here we should invoke the conflict dialog
							OC.Notification.show(t('files', 'Could not copy "{file}", target exists',
								{file: fileName}), {type: 'error'}
							);
						} else {
							OC.Notification.show(t('files', 'Could not copy "{file}"',
								{file: fileName}), {type: 'error'}
							);
						}
					})
					.always(function() {
						self.showFileBusyState($tr, false);
						count++;

						/**
						 * We only show the notifications once the last file has been copied
						 */
						if (count === fileNames.length) {
							// Remove leading and ending /
							if (targetPath.slice(0, 1) === '/') {
								targetPath = targetPath.slice(1, targetPath.length);
							}
							if (targetPath.slice(-1) === '/') {
								targetPath = targetPath.slice(0, -1);
							}

							if (filesToNotify.length > 0) {
								// Since there's no visual indication that the files were copied, let's send some notifications !
								if (filesToNotify.length === 1) {
									OC.Notification.show(t('files', 'Copied {origin} inside {destination}',
										{
											origin: filesToNotify[0],
											destination: targetPath
										}
									), {timeout: 10});
								} else if (filesToNotify.length > 0 && filesToNotify.length < 3) {
									OC.Notification.show(t('files', 'Copied {origin} inside {destination}',
										{
											origin: filesToNotify.join(', '),
											destination: targetPath
										}
									), {timeout: 10});
								} else {
									OC.Notification.show(t('files', 'Copied {origin} and {nbfiles} other files inside {destination}',
										{
											origin: filesToNotify[0],
											nbfiles: filesToNotify.length - 1,
											destination: targetPath
										}
									), {timeout: 10});
								}
							}
						}
					});
			};
			return this.reportOperationProgress(fileNames, copyFileFunction, callback);
		},

		/**
		 * Updates the given row with the given file info
		 *
		 * @param {Object} $tr row element
		 * @param {OCA.Files.FileInfo} fileInfo file info
		 * @param {Object} options options
		 *
		 * @return {Object} new row element
		 */
		updateRow: function($tr, fileInfo, options) {
			this.files.splice($tr.index(), 1);
			$tr.remove();
			options = _.extend({silent: true}, options);
			options = _.extend(options, {updateSummary: false});
			$tr = this.add(fileInfo, options);
			this.$fileList.trigger($.Event('fileActionsReady', {fileList: this, $files: $tr}));
			return $tr;
		},

		/**
		 * Triggers file rename input field for the given file name.
		 * If the user enters a new name, the file will be renamed.
		 *
		 * @param oldName file name of the file to rename
		 */
		rename: function(oldName) {
			var self = this;
			var tr, td, input, form;
			tr = this.findFileEl(oldName);
			var oldFileInfo = this.files[tr.index()];
			tr.data('renaming',true);
			td = tr.children('td.filename');
			input = $('<input type="text" class="filename"/>').val(oldName);
			form = $('<form></form>');
			form.append(input);
			td.children('a.name').children(':not(.thumbnail-wrapper)').hide();
			td.append(form);
			input.focus();
			//preselect input
			var len = input.val().lastIndexOf('.');
			if ( len === -1 ||
				tr.data('type') === 'dir' ) {
				len = input.val().length;
			}
			input.selectRange(0, len);
			var checkInput = function () {
				var filename = input.val();
				if (filename !== oldName) {
					// Files.isFileNameValid(filename) throws an exception itself
					OCA.Files.Files.isFileNameValid(filename);
					if (self.inList(filename)) {
						throw t('files', '{newName} already exists', {newName: filename}, undefined, {
							escape: false
						});
					}
				}
				return true;
			};

			function restore() {
				input.tooltip('hide');
				tr.data('renaming',false);
				form.remove();
				td.children('a.name').children(':not(.thumbnail-wrapper)').show();
			}

			function updateInList(fileInfo) {
				self.updateRow(tr, fileInfo);
				self._updateDetailsView(fileInfo.name, false);
			}

			// TODO: too many nested blocks, move parts into functions
			form.submit(function(event) {
				event.stopPropagation();
				event.preventDefault();
				if (input.hasClass('error')) {
					return;
				}

				try {
					var newName = input.val().trim();
					input.tooltip('hide');
					form.remove();

					if (newName !== oldName) {
						checkInput();
						// mark as loading (temp element)
						self.showFileBusyState(tr, true);
						tr.attr('data-file', newName);
						var basename = newName;
						if (newName.indexOf('.') > 0 && tr.data('type') !== 'dir') {
							basename = newName.substr(0, newName.lastIndexOf('.'));
						}
						td.find('a.name span.nametext').text(basename);
						td.children('a.name').children(':not(.thumbnail-wrapper)').show();

						var path = tr.attr('data-path') || self.getCurrentDirectory();
						self.filesClient.move(OC.joinPaths(path, oldName), OC.joinPaths(path, newName))
							.done(function() {
								oldFileInfo.name = newName;
								updateInList(oldFileInfo);
							})
							.fail(function(status) {
								// TODO: 409 means current folder does not exist, redirect ?
								if (status === 404) {
									// source not found, so remove it from the list
									OC.Notification.show(t('files', 'Could not rename "{fileName}", it does not exist any more',
										{fileName: oldName}), {timeout: 7, type: 'error'}
									);

									self.remove(newName, {updateSummary: true});
									return;
								} else if (status === 412) {
									// target exists
									OC.Notification.show(
										t('files', 'The name "{targetName}" is already used in the folder "{dir}". Please choose a different name.',
										{
											targetName: newName,
											dir: self.getCurrentDirectory(),
										}),
										{
											type: 'error'
										}
									);
								} else {
									// restore the item to its previous state
									OC.Notification.show(t('files', 'Could not rename "{fileName}"',
										{fileName: oldName}), {type: 'error'}
									);
								}
								updateInList(oldFileInfo);
							});
					} else {
						// add back the old file info when cancelled
						self.files.splice(tr.index(), 1);
						tr.remove();
						tr = self.add(oldFileInfo, {updateSummary: false, silent: true});
						self.$fileList.trigger($.Event('fileActionsReady', {fileList: self, $files: $(tr)}));
					}
				} catch (error) {
					input.attr('title', error);
					input.tooltip({placement: 'right', trigger: 'manual'});
					input.tooltip('fixTitle');
					input.tooltip('show');
					input.addClass('error');
				}
				return false;
			});
			input.keyup(function(event) {
				// verify filename on typing
				try {
					checkInput();
					input.tooltip('hide');
					input.removeClass('error');
				} catch (error) {
					input.attr('title', error);
					input.tooltip({placement: 'right', trigger: 'manual'});
					input.tooltip('fixTitle');
					input.tooltip('show');
					input.addClass('error');
				}
				if (event.keyCode === 27) {
					restore();
				}
			});
			input.click(function(event) {
				event.stopPropagation();
				event.preventDefault();
			});
			input.blur(function() {
				if(input.hasClass('error')) {
					restore();
				} else {
					form.trigger('submit');
				}
			});
		},

		/**
		 * Create an empty file inside the current directory.
		 *
		 * @param {string} name name of the file
		 *
		 * @return {Promise} promise that will be resolved after the
		 * file was created
		 *
		 * @since 8.2
		 */
		createFile: function(name, options) {
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();

			OCA.Files.Files.isFileNameValid(name);

			if (this.lastAction) {
				this.lastAction();
			}

			name = this.getUniqueName(name);
			var targetPath = this.getCurrentDirectory() + '/' + name;

			self.filesClient.putFileContents(
					targetPath,
					' ', // dont create empty files which fails on some storage backends
					{
						contentType: 'text/plain',
						overwrite: true
					}
				)
				.done(function() {
					// TODO: error handling / conflicts
					options = _.extend({scrollTo: true}, options || {});
					self.addAndFetchFileInfo(targetPath, '', options).then(function(status, data) {
						deferred.resolve(status, data);
					}, function() {
						OC.Notification.show(t('files', 'Could not create file "{file}"',
							{file: name}), {type: 'error'}
						);
					});
				})
				.fail(function(status) {
					if (status === 412) {
						OC.Notification.show(t('files', 'Could not create file "{file}" because it already exists',
							{file: name}), {type: 'error'}
						);
					} else {
						OC.Notification.show(t('files', 'Could not create file "{file}"',
							{file: name}), {type: 'error'}
						);
					}
					deferred.reject(status);
				});

			return promise;
		},

		/**
		 * Create a directory inside the current directory.
		 *
		 * @param {string} name name of the directory
		 *
		 * @return {Promise} promise that will be resolved after the
		 * directory was created
		 *
		 * @since 8.2
		 */
		createDirectory: function(name) {
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();

			OCA.Files.Files.isFileNameValid(name);

			if (this.lastAction) {
				this.lastAction();
			}

			name = this.getUniqueName(name);
			var targetPath = this.getCurrentDirectory() + '/' + name;

			this.filesClient.createDirectory(targetPath)
				.done(function() {
					self.addAndFetchFileInfo(targetPath, '', {scrollTo:true}).then(function(status, data) {
						deferred.resolve(status, data);
					}, function() {
						OC.Notification.show(t('files', 'Could not create folder "{dir}"',
							{dir: name}), {type: 'error'}
						);
					});
				})
				.fail(function(createStatus) {
					// method not allowed, folder might exist already
					if (createStatus === 405) {
						// add it to the list, for completeness
						self.addAndFetchFileInfo(targetPath, '', {scrollTo:true})
							.done(function(status, data) {
								OC.Notification.show(t('files', 'Could not create folder "{dir}" because it already exists',
									{dir: name}), {type: 'error'}
								);
								// still consider a failure
								deferred.reject(createStatus, data);
							})
							.fail(function() {
								OC.Notification.show(t('files', 'Could not create folder "{dir}"',
									{dir: name}), {type: 'error'}
								);
								deferred.reject(status);
							});
					} else {
						OC.Notification.show(t('files', 'Could not create folder "{dir}"',
							{dir: name}), {type: 'error'}
						);
						deferred.reject(createStatus);
					}
				});

			return promise;
		},

		/**
		 * Add file into the list by fetching its information from the server first.
		 *
		 * If the given directory does not match the current directory, nothing will
		 * be fetched.
		 *
		 * @param {String} fileName file name
		 * @param {String} [dir] optional directory, defaults to the current one
		 * @param {Object} options same options as #add
		 * @return {Promise} promise that resolves with the file info, or an
		 * already resolved Promise if no info was fetched. The promise rejects
		 * if the file was not found or an error occurred.
		 *
		 * @since 9.0
		 */
		addAndFetchFileInfo: function(fileName, dir, options) {
			var self = this;
			var deferred = $.Deferred();
			if (_.isUndefined(dir)) {
				dir = this.getCurrentDirectory();
			} else {
				dir = dir || '/';
			}

			var targetPath = OC.joinPaths(dir, fileName);

			if ((OC.dirname(targetPath) || '/') !== this.getCurrentDirectory()) {
				// no need to fetch information
				deferred.resolve();
				return deferred.promise();
			}

			var addOptions = _.extend({
				animate: true,
				scrollTo: false
			}, options || {});

			this.filesClient.getFileInfo(targetPath, {
					properties: this._getWebdavProperties()
				})
				.then(function(status, data) {
					// remove first to avoid duplicates
					self.remove(data.name);
					self.add(data, addOptions);
					deferred.resolve(status, data);
				})
				.fail(function(status) {
					OCP.Toast.error(
						t('files', 'Could not fetch file details "{file}"', { file: fileName })
					);
					deferred.reject(status);
				});

			return deferred.promise();
		},

		/**
		 * Returns whether the given file name exists in the list
		 *
		 * @param {string} file file name
		 *
		 * @return {bool} true if the file exists in the list, false otherwise
		 */
		inList:function(file) {
			return this.findFile(file);
		},

		/**
		 * Shows busy state on a given file row or multiple
		 *
		 * @param {string|Array.<string>} files file name or array of file names
		 * @param {bool} [busy=true] busy state, true for busy, false to remove busy state
		 *
		 * @since 8.2
		 */
		showFileBusyState: function(files, state) {
			var self = this;
			if (!_.isArray(files) && !files.is) {
				files = [files];
			}

			if (_.isUndefined(state)) {
				state = true;
			}

			_.each(files, function(fileName) {
				// jquery element already ?
				var $tr;
				if (_.isString(fileName)) {
					$tr = self.findFileEl(fileName);
				} else {
					$tr = $(fileName);
				}

				var $thumbEl = $tr.find('.thumbnail');
				$tr.toggleClass('busy', state);

				if (state) {
					$thumbEl.parent().addClass('icon-loading-small');
				} else {
					$thumbEl.parent().removeClass('icon-loading-small');
				}
			});
		},

		/**
		 * Delete the given files from the given dir
		 * @param files file names list (without path)
		 * @param dir directory in which to delete the files, defaults to the current
		 * directory
		 */
		do_delete:function(files, dir) {
			var self = this;
			if (files && files.substr) {
				files=[files];
			}
			if (!files) {
				// delete all files in directory
				files = _.pluck(this.files, 'name');
			}
			// Finish any existing actions
			if (this.lastAction) {
				this.lastAction();
			}

			dir = dir || this.getCurrentDirectory();

			var removeFunction = function(fileName) {
				var $tr = self.findFileEl(fileName);
				self.showFileBusyState($tr, true);
				return self.filesClient.remove(dir + '/' + fileName)
					.done(function() {
						if (OC.joinPaths(self.getCurrentDirectory(), '/') === OC.joinPaths(dir, '/')) {
							self.remove(fileName);
						}
					})
					.fail(function(status) {
						if (status === 404) {
							// the file already did not exist, remove it from the list
							if (OC.joinPaths(self.getCurrentDirectory(), '/') === OC.joinPaths(dir, '/')) {
								self.remove(fileName);
							}
						} else {
							// only reset the spinner for that one file
							OC.Notification.show(t('files', 'Error deleting file "{fileName}".',
								{fileName: fileName}), {type: 'error'}
							);
						}
					})
					.always(function() {
						self.showFileBusyState($tr, false);
					});
			};
			return this.reportOperationProgress(files, removeFunction).then(function(){
					self.updateStorageStatistics();
					self.updateStorageQuotas();
				});
		},

		/**
		 * Creates the file summary section
		 */
		_createSummary: function() {
			var $tr = $('<tr class="summary"></tr>');

			if (this._allowSelection) {
				// Dummy column for selection, as all rows must have the same
				// number of columns.
				$tr.append('<td></td>');
			}

			this.$el.find('tfoot').append($tr);

			return new OCA.Files.FileSummary($tr, {config: this._filesConfig});
		},
		updateEmptyContent: function() {
			var permissions = this.getDirectoryPermissions();
			var isCreatable = (permissions & OC.PERMISSION_CREATE) !== 0;
			this.$el.find('#emptycontent').toggleClass('hidden', !this.isEmpty);
			this.$el.find('#emptycontent').toggleClass('hidden', !this.isEmpty);
			this.$el.find('#emptycontent .uploadmessage').toggleClass('hidden', !isCreatable || !this.isEmpty);
			this.$el.find('#filestable').toggleClass('hidden', this.isEmpty);
			this.$el.find('#filestable thead th').toggleClass('hidden', this.isEmpty);
		},
		/**
		 * Shows the loading mask.
		 *
		 * @see OCA.Files.FileList#hideMask
		 */
		showMask: function() {
			// in case one was shown before
			var $mask = this.$el.find('.mask');
			if ($mask.exists()) {
				return;
			}

			this.$table.addClass('hidden');
			this.$el.find('#emptycontent').addClass('hidden');

			$mask = $('<div class="mask transparent icon-loading"></div>');

			this.$el.append($mask);

			$mask.removeClass('transparent');
		},
		/**
		 * Hide the loading mask.
		 * @see OCA.Files.FileList#showMask
		 */
		hideMask: function() {
			this.$el.find('.mask').remove();
			this.$table.removeClass('hidden');
		},
		scrollTo:function(file) {
			if (!_.isArray(file)) {
				file = [file];
			}
			if (file.length === 1) {
				_.defer(function() {
					this.showDetailsView(file[0]);
				}.bind(this));
			}
			this.highlightFiles(file, function($tr) {
				$tr.addClass('searchresult');
				$tr.one('hover', function() {
					$tr.removeClass('searchresult');
				});
			});
		},
		/**
		 * @deprecated use setFilter(filter)
		 */
		filter:function(query) {
			this.setFilter('');
		},
		/**
		 * @deprecated use setFilter('')
		 */
		unfilter:function() {
			this.setFilter('');
		},
		/**
		 * hide files matching the given filter
		 * @param filter
		 */
		setFilter:function(filter) {
			var total = 0;
			if (this._filter === filter) {
				return;
			}
			this._filter = filter;
			this.fileSummary.setFilter(filter, this.files);
			total = this.fileSummary.getTotal();
			if (!this.$el.find('.mask').exists()) {
				this.hideIrrelevantUIWhenNoFilesMatch();
			}

			var visibleCount = 0;
			filter = filter.toLowerCase();

			function filterRows(tr) {
				var $e = $(tr);
				if ($e.data('file').toString().toLowerCase().indexOf(filter) === -1) {
					$e.addClass('hidden');
				} else {
					visibleCount++;
					$e.removeClass('hidden');
				}
			}

			var $trs = this.$fileList.find('tr');
			do {
				_.each($trs, filterRows);
				if (visibleCount < total) {
					$trs = this._nextPage(false);
				}
			} while (visibleCount < total && $trs.length > 0);

			this.$container.trigger('scroll');
		},
		hideIrrelevantUIWhenNoFilesMatch:function() {
			if (this._filter && this.fileSummary.summary.totalDirs + this.fileSummary.summary.totalFiles === 0) {
				this.$el.find('#filestable thead th').addClass('hidden');
				this.$el.find('#emptycontent').addClass('hidden');
				$('#searchresults').addClass('filter-empty');
				$('#searchresults .emptycontent').addClass('emptycontent-search');
				if ( $('#searchresults').length === 0 || $('#searchresults').hasClass('hidden') ) {
					var error = t('files', 'No search results in other folders for {tag}{filter}{endtag}', {filter:this._filter});
					this.$el.find('.nofilterresults').removeClass('hidden').
						find('p').html(error.replace('{tag}', '<strong>').replace('{endtag}', '</strong>'));
				}
			} else {
				$('#searchresults').removeClass('filter-empty');
				$('#searchresults .emptycontent').removeClass('emptycontent-search');
				this.$el.find('#filestable thead th').toggleClass('hidden', this.isEmpty);
				if (!this.$el.find('.mask').exists()) {
					this.$el.find('#emptycontent').toggleClass('hidden', !this.isEmpty);
				}
				this.$el.find('.nofilterresults').addClass('hidden');
			}
		},
		/**
		 * get the current filter
		 * @param filter
		 */
		getFilter:function(filter) {
			return this._filter;
		},
		/**
		 * update the search object to use this filelist when filtering
		 */
		updateSearch:function() {
			if (OCA.Search.files) {
				OCA.Search.files.setFileList(this);
			}
			if (OC.Search) {
				OC.Search.clear();
			}
		},
		/**
		 * Update UI based on the current selection
		 */
		updateSelectionSummary: function() {
			var summary = this._selectionSummary.summary;
			var selection;

			var showHidden = !!this._filesConfig.get('showhidden');
			if (summary.totalFiles === 0 && summary.totalDirs === 0) {
				this.$el.find('#headerName a.name>span:first').text(t('files','Name'));
				this.$el.find('#headerSize a>span:first').text(t('files','Size'));
				this.$el.find('#modified a>span:first').text(t('files','Modified'));
				this.$el.find('table').removeClass('multiselect');
				this.$el.find('.selectedActions').addClass('hidden');
			}
			else {
				this.$el.find('.selectedActions').removeClass('hidden');
				this.$el.find('#headerSize a>span:first').text(OC.Util.humanFileSize(summary.totalSize));

				var directoryInfo = n('files', '%n folder', '%n folders', summary.totalDirs);
				var fileInfo = n('files', '%n file', '%n files', summary.totalFiles);

				if (summary.totalDirs > 0 && summary.totalFiles > 0) {
					var selectionVars = {
						dirs: directoryInfo,
						files: fileInfo
					};
					selection = t('files', '{dirs} and {files}', selectionVars);
				} else if (summary.totalDirs > 0) {
					selection = directoryInfo;
				} else {
					selection = fileInfo;
				}

				if (!showHidden && summary.totalHidden > 0) {
					var hiddenInfo = n('files', 'including %n hidden', 'including %n hidden', summary.totalHidden);
					selection += ' (' + hiddenInfo + ')';
				}

				this.$el.find('#headerName a.name>span:first').text(selection);
				this.$el.find('#modified a>span:first').text('');
				this.$el.find('table').addClass('multiselect');

				if (this.fileMultiSelectMenu) {
					this.fileMultiSelectMenu.toggleItemVisibility('download', this.isSelectedDownloadable());
					this.fileMultiSelectMenu.toggleItemVisibility('delete', this.isSelectedDeletable());
					this.fileMultiSelectMenu.toggleItemVisibility('copyMove', this.isSelectedCopiable());
					if (this.isSelectedCopiable()) {
						if (this.isSelectedMovable()) {
							this.fileMultiSelectMenu.updateItemText('copyMove', t('files', 'Move or copy'));
						} else {
							this.fileMultiSelectMenu.updateItemText('copyMove', t('files', 'Copy'));
						}
					} else {
						this.fileMultiSelectMenu.toggleItemVisibility('copyMove', false);
					}
				}
			}
		},

		/**
		 * Check whether all selected files are copiable
		 */
		isSelectedCopiable: function() {
			return _.reduce(this.getSelectedFiles(), function(copiable, file) {
				var requiredPermission = $('#isPublic').val() ? OC.PERMISSION_UPDATE : OC.PERMISSION_READ;
				return copiable && (file.permissions & requiredPermission);
			}, true);
		},

		/**
		 * Check whether all selected files are movable
		 */
		isSelectedMovable: function() {
			return _.reduce(this.getSelectedFiles(), function(movable, file) {
				return movable && (file.permissions & OC.PERMISSION_UPDATE);
			}, true);
		},

		/**
		 * Check whether all selected files are downloadable
		 */
		isSelectedDownloadable: function() {
			return _.reduce(this.getSelectedFiles(), function(downloadable, file) {
				return downloadable && (file.permissions & OC.PERMISSION_READ);
			}, true);
		},

		/**
		 * Check whether all selected files are deletable
		 */
		isSelectedDeletable: function() {
			return _.reduce(this.getSelectedFiles(), function(deletable, file) {
				return deletable && (file.permissions & OC.PERMISSION_DELETE);
			}, true);
		},

		/**
		 * Are all files selected?
		 *
		 * @returns {Boolean} all files are selected
		 */
		isAllSelected: function() {
			var checkbox = this.$el.find('.select-all')
			var checked = checkbox.prop('checked')
			var indeterminate = checkbox.prop('indeterminate')
			return checked && !indeterminate;
		},

		/**
		 * Returns the file info of the selected files
		 *
		 * @return array of file names
		 */
		getSelectedFiles: function() {
			return _.values(this._selectedFiles);
		},

		getUniqueName: function(name) {
			if (this.findFileEl(name).exists()) {
				var numMatch;
				var parts=name.split('.');
				var extension = "";
				if (parts.length > 1) {
					extension=parts.pop();
				}
				var base=parts.join('.');
				numMatch=base.match(/\((\d+)\)/);
				var num=2;
				if (numMatch && numMatch.length>0) {
					num=parseInt(numMatch[numMatch.length-1], 10)+1;
					base=base.split('(');
					base.pop();
					base=$.trim(base.join('('));
				}
				name=base+' ('+num+')';
				if (extension) {
					name = name+'.'+extension;
				}
				// FIXME: ugly recursion
				return this.getUniqueName(name);
			}
			return name;
		},

		/**
		 * Shows a "permission denied" notification
		 */
		_showPermissionDeniedNotification: function() {
			var message = t('files', 'You don’t have permission to upload or create files here');
			OC.Notification.show(message, {type: 'error'});
		},

		/**
		 * Setup file upload events related to the file-upload plugin
		 *
		 * @param {OC.Uploader} uploader
		 */
		setupUploadEvents: function(uploader) {
			var self = this;

			self._uploads = {};

			// detect the progress bar resize
			uploader.on('resized', this._onResize);

			uploader.on('drop', function(e, data) {
				self._uploader.log('filelist handle fileuploaddrop', e, data);

				if (self.$el.hasClass('hidden')) {
					// do not upload to invisible lists
					e.preventDefault();
					return false;
				}

				var dropTarget = $(e.delegatedEvent.target);

				// check if dropped inside this container and not another one
				if (dropTarget.length
					&& !self.$el.is(dropTarget) // dropped on list directly
					&& !self.$el.has(dropTarget).length // dropped inside list
					&& !dropTarget.is(self.$container) // dropped on main container
					&& !self.$el.parent().is(dropTarget) // drop on the parent container (#app-content) since the main container might not have the full height
					) {
					e.preventDefault();
					return false;
				}

				// find the closest tr or crumb to use as target
				dropTarget = dropTarget.closest('tr, .crumb');

				// if dropping on tr or crumb, drag&drop upload to folder
				if (dropTarget && (dropTarget.data('type') === 'dir' ||
					dropTarget.hasClass('crumb'))) {

					// remember as context
					data.context = dropTarget;

					// if permissions are specified, only allow if create permission is there
					var permissions = dropTarget.data('permissions');
					if (!_.isUndefined(permissions) && (permissions & OC.PERMISSION_CREATE) === 0) {
						self._showPermissionDeniedNotification();
						return false;
					}
					var dir = dropTarget.data('file');
					// if from file list, need to prepend parent dir
					if (dir) {
						var parentDir = self.getCurrentDirectory();
						if (parentDir[parentDir.length - 1] !== '/') {
							parentDir += '/';
						}
						dir = parentDir + dir;
					}
					else{
						// read full path from crumb
						dir = dropTarget.data('dir') || '/';
					}

					// add target dir
					data.targetDir = dir;
				} else {
					// cancel uploads to current dir if no permission
					var isCreatable = (self.getDirectoryPermissions() & OC.PERMISSION_CREATE) !== 0;
					if (!isCreatable) {
						self._showPermissionDeniedNotification();
						e.stopPropagation();
						return false;
					}

					// we are dropping somewhere inside the file list, which will
					// upload the file to the current directory
					data.targetDir = self.getCurrentDirectory();
				}
			});
			uploader.on('add', function(e, data) {
				self._uploader.log('filelist handle fileuploadadd', e, data);

				// add ui visualization to existing folder
				if (data.context && data.context.data('type') === 'dir') {
					// add to existing folder

					// update upload counter ui
					var uploadText = data.context.find('.uploadtext');
					var currentUploads = parseInt(uploadText.attr('currentUploads'), 10);
					currentUploads += 1;
					uploadText.attr('currentUploads', currentUploads);

					var translatedText = n('files', 'Uploading %n file', 'Uploading %n files', currentUploads);
					if (currentUploads === 1) {
						self.showFileBusyState(uploadText.closest('tr'), true);
						uploadText.text(translatedText);
						uploadText.show();
					} else {
						uploadText.text(translatedText);
					}
				}

				if (!data.targetDir) {
					data.targetDir = self.getCurrentDirectory();
				}

			});
			/*
			 * when file upload done successfully add row to filelist
			 * update counter when uploading to sub folder
			 */
			uploader.on('done', function(e, upload) {
				var data = upload.data;
				self._uploader.log('filelist handle fileuploaddone', e, data);

				var status = data.jqXHR.status;
				if (status < 200 || status >= 300) {
					// error was handled in OC.Uploads already
					return;
				}

				var fileName = upload.getFileName();
				var fetchInfoPromise = self.addAndFetchFileInfo(fileName, upload.getFullPath());
				if (!self._uploads) {
					self._uploads = {};
				}
				if (OC.isSamePath(OC.dirname(upload.getFullPath() + '/'), self.getCurrentDirectory())) {
					self._uploads[fileName] = fetchInfoPromise;
				}

				var uploadText = self.$fileList.find('tr .uploadtext');
				self.showFileBusyState(uploadText.closest('tr'), false);
				uploadText.fadeOut();
				uploadText.attr('currentUploads', 0);

				self.updateStorageQuotas();
			});
			uploader.on('createdfolder', function(fullPath) {
				self.addAndFetchFileInfo(OC.basename(fullPath), OC.dirname(fullPath));
			});
			uploader.on('stop', function() {
				self._uploader.log('filelist handle fileuploadstop');

				// prepare list of uploaded file names in the current directory
				// and discard the other ones
				var promises = _.values(self._uploads);
				var fileNames = _.keys(self._uploads);
				self._uploads = [];

				// as soon as all info is fetched
				$.when.apply($, promises).then(function() {
					// highlight uploaded files
					self.highlightFiles(fileNames);
					self.updateStorageStatistics();
				});

				var uploadText = self.$fileList.find('tr .uploadtext');
				self.showFileBusyState(uploadText.closest('tr'), false);
				uploadText.fadeOut();
				uploadText.attr('currentUploads', 0);
			});
			uploader.on('fail', function(e, data) {
				self._uploader.log('filelist handle fileuploadfail', e, data);
				self._uploads = [];

				//if user pressed cancel hide upload chrome
				//cleanup uploading to a dir
				var uploadText = self.$fileList.find('tr .uploadtext');
				self.showFileBusyState(uploadText.closest('tr'), false);
				uploadText.fadeOut();
				uploadText.attr('currentUploads', 0);
				self.updateStorageStatistics();
			});

		},

		/**
		 * Scroll to the last file of the given list
		 * Highlight the list of files
		 * @param files array of filenames,
		 * @param {Function} [highlightFunction] optional function
		 * to be called after the scrolling is finished
		 */
		highlightFiles: function(files, highlightFunction) {
			// Detection of the uploaded element
			var filename = files[files.length - 1];
			var $fileRow = this.findFileEl(filename);

			while(!$fileRow.exists() && this._nextPage(false) !== false) { // Checking element existence
				$fileRow = this.findFileEl(filename);
			}

			if (!$fileRow.exists()) { // Element not present in the file list
				return;
			}

			var currentOffset = this.$container.scrollTop();
			var additionalOffset = this.$el.find("#controls").height()+this.$el.find("#controls").offset().top;

			// Animation
			var _this = this;
			var $scrollContainer = this.$container;
			if ($scrollContainer[0] === window) {
				// need to use "html" to animate scrolling
				// when the scroll container is the window
				$scrollContainer = $('html');
			}
			$scrollContainer.animate({
				// Scrolling to the top of the new element
				scrollTop: currentOffset + $fileRow.offset().top - $fileRow.height() * 2 - additionalOffset
			}, {
				duration: 500,
				complete: function() {
					// Highlighting function
					var highlightRow = highlightFunction;

					if (!highlightRow) {
						highlightRow = function($fileRow) {
							$fileRow.addClass("highlightUploaded");
							setTimeout(function() {
								$fileRow.removeClass("highlightUploaded");
							}, 2500);
						};
					}

					// Loop over uploaded files
					for(var i=0; i<files.length; i++) {
						var $fileRow = _this.findFileEl(files[i]);

						if($fileRow.length !== 0) { // Checking element existence
							highlightRow($fileRow);
						}
					}

				}
			});
		},

		_renderNewButton: function() {
			// if an upload button (legacy) already exists or no actions container exist, skip
			var $actionsContainer = this.$el.find('#controls .actions');
			if (!$actionsContainer.length || this.$el.find('.button.upload').length) {
				return;
			}
			var $newButton = $(OCA.Files.Templates['template_addbutton']({
				addText: t('files', 'New'),
				iconClass: 'icon-add'
			}));

			$actionsContainer.prepend($newButton);
			$newButton.tooltip({'placement': 'bottom'});

			$newButton.click(_.bind(this._onClickNewButton, this));
			this._newButton = $newButton;
		},

		_onClickNewButton: function(event) {
			var $target = $(event.target);
			if (!$target.hasClass('.button')) {
				$target = $target.closest('.button');
			}
			this._newButton.tooltip('hide');
			event.preventDefault();
			if ($target.hasClass('disabled')) {
				return false;
			}
			if (!this._newFileMenu) {
				this._newFileMenu = new OCA.Files.NewFileMenu({
					fileList: this
				});
				$('.actions').append(this._newFileMenu.$el);
			}
			this._newFileMenu.showAt($target);

			return false;
		},

		/**
		 * Register a tab view to be added to all views
		 */
		registerTabView: function(tabView) {
			console.warn('registerTabView is deprecated! It will be removed in nextcloud 20.');
			const enabled = tabView.canDisplay || undefined
			if (tabView.id) {
				OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab(tabView.id, tabView, enabled, true))
			}
		},

		/**
		 * Register a detail view to be added to all views
		 */
		registerDetailView: function(detailView) {
			console.warn('registerDetailView is deprecated! It will be removed in nextcloud 20.');
			if (detailView.el) {
				OCA.Files.Sidebar.registerSecondaryView(detailView)
			}
		},

		/**
		 * Register a view to be added to the breadcrumb view
		 */
		registerBreadCrumbDetailView: function(detailView) {
			if (this.breadcrumb) {
				this.breadcrumb.addDetailView(detailView);
			}
		},

		/**
		 * Returns the registered detail views.
		 *
		 * @return null|Array<OCA.Files.DetailFileInfoView> an array with the
		 *         registered DetailFileInfoViews, or null if the details view
		 *         is not enabled.
		 */
		getRegisteredDetailViews: function() {
			if (this._detailsView) {
				return this._detailsView.getDetailViews();
			}

			return null;
		},

		registerHeader: function(header) {
			this.headers.push(
				_.defaults(header, { order: 0 })
			);
		},

		registerFooter: function(footer) {
			this.footers.push(
				_.defaults(footer, { order: 0 })
			);
		}
	};

	FileList.MultiSelectMenuActions = {
		ToggleSelectionModeAction: function(fileList) {
			return {
				name: 'toggleSelectionMode',
				displayName: function(context) {
					return t('files', 'Select file range');
				},
				iconClass: 'icon-fullscreen',
				action: function() {
					fileList._onClickToggleSelectionMode();
				},
			};
		},
	},

	/**
	 * Sort comparators.
	 * @namespace OCA.Files.FileList.Comparators
	 * @private
	 */
	FileList.Comparators = {
		/**
		 * Compares two file infos by name, making directories appear
		 * first.
		 *
		 * @param {OC.Files.FileInfo} fileInfo1 file info
		 * @param {OC.Files.FileInfo} fileInfo2 file info
		 * @return {int} -1 if the first file must appear before the second one,
		 * 0 if they are identify, 1 otherwise.
		 */
		name: function(fileInfo1, fileInfo2) {
			if (fileInfo1.type === 'dir' && fileInfo2.type !== 'dir') {
				return -1;
			}
			if (fileInfo1.type !== 'dir' && fileInfo2.type === 'dir') {
				return 1;
			}
			return OC.Util.naturalSortCompare(fileInfo1.name, fileInfo2.name);
		},
		/**
		 * Compares two file infos by size.
		 *
		 * @param {OC.Files.FileInfo} fileInfo1 file info
		 * @param {OC.Files.FileInfo} fileInfo2 file info
		 * @return {int} -1 if the first file must appear before the second one,
		 * 0 if they are identify, 1 otherwise.
		 */
		size: function(fileInfo1, fileInfo2) {
			return fileInfo1.size - fileInfo2.size;
		},
		/**
		 * Compares two file infos by timestamp.
		 *
		 * @param {OC.Files.FileInfo} fileInfo1 file info
		 * @param {OC.Files.FileInfo} fileInfo2 file info
		 * @return {int} -1 if the first file must appear before the second one,
		 * 0 if they are identify, 1 otherwise.
		 */
		mtime: function(fileInfo1, fileInfo2) {
			return fileInfo1.mtime - fileInfo2.mtime;
		}
	};

	/**
	 * File info attributes.
	 *
	 * @typedef {Object} OC.Files.FileInfo
	 *
	 * @lends OC.Files.FileInfo
	 *
	 * @deprecated use OC.Files.FileInfo instead
	 *
	 */
	OCA.Files.FileInfo = OC.Files.FileInfo;

	OCA.Files.FileList = FileList;
})();

$(document).ready(function() {
	// FIXME: unused ?
	OCA.Files.FileList.useUndo = (window.onbeforeunload)?true:false;
	$(window).on('beforeunload', function () {
		if (OCA.Files.FileList.lastAction) {
			OCA.Files.FileList.lastAction();
		}
	});

});
