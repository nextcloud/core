/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	/**
	 * @class OCA.Files.MainFileInfoDetailView
	 * @classdesc
	 *
	 * Displays main details about a file
	 *
	 */
	var MainFileInfoDetailView = OCA.Files.DetailFileInfoView.extend(
		/** @lends OCA.Files.MainFileInfoDetailView.prototype */ {

		className: 'mainFileInfoView',

		/**
		 * Associated file list instance, for file actions
		 *
		 * @type {OCA.Files.FileList}
		 */
		_fileList: null,

		/**
		 * File actions
		 *
		 * @type {OCA.Files.FileActions}
		 */
		_fileActions: null,

		/**
		 * @type {OCA.Files.SidebarPreviewManager}
		 */
		_previewManager: null,

		events: {
			'click a.action-favorite': '_onClickFavorite',
			'click a.action-default': '_onClickDefaultAction',
			'click a.permalink': '_onClickPermalink',
			'focus .permalink-field>input': '_onFocusPermalink'
		},

		template: function(data) {
			return OCA.Files.Templates['mainfileinfodetailsview'](data);
		},

		initialize: function(options) {
			options = options || {};
			this._fileList = options.fileList;
			this._fileActions = options.fileActions;
			if (!this._fileList) {
				throw 'Missing required parameter "fileList"';
			}
			if (!this._fileActions) {
				throw 'Missing required parameter "fileActions"';
			}
			this._previewManager = new OCA.Files.SidebarPreviewManager(this._fileList);

			this._setupClipboard();
		},

		_setupClipboard: function() {
			var clipboard = new Clipboard('.permalink');
			clipboard.on('success', function(e) {
				var $el = $(e.trigger);
				$el.tooltip('hide')
					.attr('data-original-title', t('core', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$el.tooltip('hide');
					$el.attr('data-original-title', t('files', 'Copy direct link (only works for users who have access to this file/folder)'))
						.tooltip('fixTitle');
				}, 3000);
			});
			clipboard.on('error', function(e) {
				var $row = this.$('.permalink-field');
				$row.toggleClass('hidden');
				if (!$row.hasClass('hidden')) {
					$row.find('>input').focus();
				}
			});
		},

		_onClickPermalink: function(e) {
			e.preventDefault();
			return;
		},

		_onFocusPermalink: function() {
			this.$('.permalink-field>input').select();
		},

		_onClickFavorite: function(event) {
			event.preventDefault();
			this._fileActions.triggerAction('Favorite', this.model, this._fileList);
		},

		_onClickDefaultAction: function(event) {
			event.preventDefault();
			this._fileActions.triggerAction(null, this.model, this._fileList);
		},

		_onModelChanged: function() {
			// simply re-render
			this.render();
		},

		_makePermalink: function(fileId) {
			var baseUrl = OC.getProtocol() + '://' + OC.getHost();
			return baseUrl + OC.generateUrl('/f/{fileId}', {fileId: fileId});
		},

		setFileInfo: function(fileInfo) {
			if (this.model) {
				this.model.off('change', this._onModelChanged, this);
			}
			this.model = fileInfo;
			if (this.model) {
				this.model.on('change', this._onModelChanged, this);
			}

			if (this.model) {
				var properties = [];
				if( !this.model.has('size') ) {
					properties.push(OC.Files.Client.PROPERTY_SIZE);
					properties.push(OC.Files.Client.PROPERTY_GETCONTENTLENGTH);
				}

				if( properties.length > 0){
					this.model.reloadProperties(properties);
				}
			}

			this.render();
		},

		/**
		 * Renders this details view
		 */
		render: function() {
			this.trigger('pre-render');

			if (this.model) {
				var isFavorite = (this.model.get('tags') || []).indexOf(OC.TAG_FAVORITE) >= 0;
				var availableActions = this._fileActions.get(
					this.model.get('mimetype'),
					this.model.get('type'),
					this.model.get('permissions')
				);
				var hasFavoriteAction = 'Favorite' in availableActions;
				this.$el.html(this.template({
					type: this.model.isImage()? 'image': '',
					nameLabel: t('files', 'Name'),
					name: this.model.get('displayName') || this.model.get('name'),
					pathLabel: t('files', 'Path'),
					path: this.model.get('path'),
					hasSize: this.model.has('size'),
					sizeLabel: t('files', 'Size'),
					size: OC.Util.humanFileSize(this.model.get('size'), true),
					altSize: n('files', '%n byte', '%n bytes', this.model.get('size')),
					dateLabel: t('files', 'Modified'),
					altDate: OC.Util.formatDate(this.model.get('mtime')),
					timestamp: this.model.get('mtime'),
					date: OC.Util.relativeModifiedDate(this.model.get('mtime')),
					hasFavoriteAction: hasFavoriteAction,
					starAltText: isFavorite ? t('files', 'Favorited') : t('files', 'Favorite'),
					starClass: isFavorite ? 'icon-starred' : 'icon-star',
					permalink: this._makePermalink(this.model.get('id')),
					permalinkTitle: t('files', 'Copy direct link (only works for users who have access to this file/folder)')
				}));

				// TODO: we really need OC.Previews
				var $iconDiv = this.$el.find('.thumbnail');
				var $container = this.$el.find('.thumbnailContainer');
				if (!this.model.isDirectory()) {
					$iconDiv.addClass('icon-loading icon-32');
					this._previewManager.loadPreview(this.model, $iconDiv, $container);
				} else {
					var iconUrl = this.model.get('icon') || OC.MimeType.getIconUrl('dir');
					if (typeof this.model.get('mountType') !== 'undefined') {
						iconUrl = OC.MimeType.getIconUrl('dir-' + this.model.get('mountType'))
					}
					$iconDiv.css('background-image', 'url("' + iconUrl + '")');
				}
				this.$el.find('[title]').tooltip({placement: 'bottom'});
			} else {
				this.$el.empty();
			}
			this.delegateEvents();

			this.trigger('post-render');
		}
	});

	OCA.Files.MainFileInfoDetailView = MainFileInfoDetailView;
})();
