/*
 * Copyright (c) 2014
 * @copyright Copyright (c) 2016, Björn Schießle <bjoern@schiessle.org>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/* global FileActions, Files, FileList */
/* global dragOptions, folderDropOptions */
if (!OCA.Sharing) {
	OCA.Sharing = {};
}
if (!OCA.Files) {
	OCA.Files = {};
}
/**
 * @namespace
 */
OCA.Sharing.PublicApp = {
	_initialized: false,

	/**
	 * Initializes the public share app.
	 *
	 * @param $el container
	 */
	initialize: function ($el) {
		var self = this;
		var fileActions;
		if (this._initialized) {
			return;
		}
		fileActions = new OCA.Files.FileActions();
		// default actions
		fileActions.registerDefaultActions();
		// regular actions
		fileActions.merge(OCA.Files.fileActions);

		// in case apps would decide to register file actions later,
		// replace the global object with this one
		OCA.Files.fileActions = fileActions;

		this._initialized = true;
		this.initialDir = $('#dir').val();

		var token = $('#sharingToken').val();
		var hideDownload = $('#hideDownload').val();


		// file list mode ?
		if ($el.find('#filestable').length) {
			var filesClient = new OC.Files.Client({
				host: OC.getHost(),
				port: OC.getPort(),
				userName: token,
				// note: password not be required, the endpoint
				// will recognize previous validation from the session
				root: OC.getRootPath() + '/public.php/webdav',
				useHTTPS: OC.getProtocol() === 'https'
			});

			this.fileList = new OCA.Files.FileList(
				$el,
				{
					id: 'files.public',
					dragOptions: dragOptions,
					folderDropOptions: folderDropOptions,
					fileActions: fileActions,
					detailsViewEnabled: false,
					filesClient: filesClient,
					enableUpload: true,
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
						{
								name: 'delete',
								displayName: t('files', 'Delete'),
								iconClass: 'icon-delete',
						}
					]
				}
			);
			if (hideDownload === 'true') {
				this.fileList._allowSelection = false;
			}
			this.files = OCA.Files.Files;
			this.files.initialize();
			// TODO: move to PublicFileList.initialize() once
			// the code was split into a separate class
			OC.Plugins.attach('OCA.Sharing.PublicFileList', this.fileList);
		}

		var mimetype = $('#mimetype').val();
		var mimetypeIcon = $('#mimetypeIcon').val();
		mimetypeIcon = mimetypeIcon.substring(0, mimetypeIcon.length - 3);
		mimetypeIcon = mimetypeIcon + 'svg';

		var previewSupported = $('#previewSupported').val();

		if (typeof FileActions !== 'undefined') {
			// Show file preview if previewer is available, images are already handled by the template
			if (mimetype.substr(0, mimetype.indexOf('/')) !== 'image' && $('.publicpreview').length === 0) {
				// Trigger default action if not download TODO
				var action = FileActions.getDefault(mimetype, 'file', OC.PERMISSION_READ);
				if (typeof action !== 'undefined') {
					action($('#filename').val());
				}
			}
		}


		// dynamically load image previews
		var bottomMargin = 350;
		var previewWidth = $(window).width();
		var previewHeight = $(window).height() - bottomMargin;
		previewHeight = Math.max(200, previewHeight);
		var params = {
			x: Math.ceil(previewWidth * window.devicePixelRatio),
			y: Math.ceil(previewHeight * window.devicePixelRatio),
			a: 'true',
			file: encodeURIComponent(this.initialDir + $('#filename').val()),
			scalingup: 0
		};

		var imgcontainer = $('<img class="publicpreview" alt="">');
		if (hideDownload === 'false') {
			imgcontainer = $('<a href="' + $('#previewURL').val() + '" target="_blank"></a>').append(imgcontainer);
		}
		var img = imgcontainer.hasClass('publicpreview')? imgcontainer: imgcontainer.find('.publicpreview');
		img.css({
			'max-width': previewWidth,
			'max-height': previewHeight
		});

		var fileSize = parseInt($('#filesize').val(), 10);
		var maxGifSize = parseInt($('#maxSizeAnimateGif').val(), 10);

		if (mimetype === 'image/gif' &&
			(maxGifSize === -1 || fileSize <= (maxGifSize * 1024 * 1024))) {
			img.attr('src', $('#downloadURL').val());
			imgcontainer.appendTo('#imgframe');
		} else if (mimetype.substr(0, mimetype.indexOf('/')) === 'text' && window.btoa) {
			if (OC.appswebroots['files_texteditor'] !== undefined) {
				// the text editor handles the previewing
				return;
			}
			// Undocumented Url to public WebDAV endpoint
			var url = parent.location.protocol + '//' + location.host + OC.linkTo('', 'public.php/webdav');
			$.ajax({
				url: url,
				headers: {
					Authorization: 'Basic ' + btoa(token + ':'),
					Range: 'bytes=0-10000'
				}
			}).then(function (data) {
				self._showTextPreview(data, previewHeight);
			});
		} else if ((previewSupported === 'true' && mimetype.substr(0, mimetype.indexOf('/')) !== 'video') ||
			mimetype.substr(0, mimetype.indexOf('/')) === 'image' &&
			mimetype !== 'image/svg+xml') {
			img.attr('src', OC.generateUrl('/apps/files_sharing/publicpreview/' + token + '?' + OC.buildQueryString(params)));
			imgcontainer.appendTo('#imgframe');
		} else if (mimetype.substr(0, mimetype.indexOf('/')) !== 'video') {
			img.attr('src', mimetypeIcon);
			img.attr('width', 128);
			// "#imgframe" is either empty or it contains an audio preview that
			// the icon should appear before, so the container should be
			// prepended to the frame.
			imgcontainer.prependTo('#imgframe');
		}
		else if (previewSupported === 'true') {
			$('#imgframe > video').attr('poster', OC.generateUrl('/apps/files_sharing/publicpreview/' + token + '?' + OC.buildQueryString(params)));
		}

		if (this.fileList) {
			// TODO: move this to a separate PublicFileList class that extends OCA.Files.FileList (+ unit tests)
			this.fileList.getDownloadUrl = function (filename, dir, isDir) {
				var path = dir || this.getCurrentDirectory();
				if (_.isArray(filename)) {
					filename = JSON.stringify(filename);
				}
				var params = {
					path: path
				};
				if (filename) {
					params.files = filename;
				}
				return OC.generateUrl('/s/' + token + '/download') + '?' + OC.buildQueryString(params);
			};

			this.fileList._createRow = function(fileData) {
				var $tr = OCA.Files.FileList.prototype._createRow.apply(this, arguments);
				if (hideDownload === 'true') {
					this.fileActions.currentFile = $tr.find('td');
					var mime = this.fileActions.getCurrentMimeType();
					var type = this.fileActions.getCurrentType();
					var permissions = this.fileActions.getCurrentPermissions();
					var action = this.fileActions.getDefault(mime, type, permissions);

					// Remove the link. This means that files without a default action fail hard
					$tr.find('a.name').attr('href', '#');

					this.fileActions.actions.all = {};
				}
				return $tr;
			};

			this.fileList.isSelectedDownloadable = function () {
				return hideDownload !== 'true';
			};

			this.fileList.getUploadUrl = function(fileName, dir) {
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
				var base = '';

				if (!this._uploader.isXHRUpload()) {
					// also add auth in URL due to POST workaround
					base = OC.getProtocol() + '://' + token + '@' + OC.getHost() + (OC.getPort() ? ':' + OC.getPort() : '');
				}
				return base + OC.getRootPath() + '/public.php/webdav' + encodedPath;
			};

			this.fileList.getAjaxUrl = function (action, params) {
				params = params || {};
				params.t = token;
				return OC.filePath('files_sharing', 'ajax', action + '.php') + '?' + OC.buildQueryString(params);
			};

			this.fileList.linkTo = function (dir) {
				return OC.generateUrl('/s/' + token + '', {dir: dir});
			};

			this.fileList.generatePreviewUrl = function (urlSpec) {
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
				var token = $('#dirToken').val();
				return OC.generateUrl('/apps/files_sharing/publicpreview/' + token + '?' + OC.buildQueryString(urlSpec));
			};

			this.fileList.updateEmptyContent = function() {
				this.$el.find('#emptycontent .uploadmessage').text(
					t('files_sharing', 'You can upload into this folder')
				);
				OCA.Files.FileList.prototype.updateEmptyContent.apply(this, arguments);
			};

			this.fileList._uploader.on('fileuploadadd', function(e, data) {
				if (!data.headers) {
					data.headers = {};
				}

				data.headers.Authorization = 'Basic ' + btoa(token + ':');
			});

			// do not allow sharing from the public page
			delete this.fileList.fileActions.actions.all.Share;

			this.fileList.changeDirectory(this.initialDir || '/', false, true);

			// URL history handling
			this.fileList.$el.on('changeDirectory', _.bind(this._onDirectoryChanged, this));
			OC.Util.History.addOnPopStateHandler(_.bind(this._onUrlChanged, this));

			$('#download').click(function (e) {
				e.preventDefault();
				OC.redirect(FileList.getDownloadUrl());
			});

			if (hideDownload === 'true') {
				this.fileList.$el.find('#headerSelection').remove();
				this.fileList.$el.find('.summary').find('td:first-child').remove();
			}
		}

		$(document).on('click', '#directLink', function () {
			$(this).focus();
			$(this).select();
		});

		$('.save-form').submit(function (event) {
			event.preventDefault();

			var remote = $(this).find('#remote_address').val();
			var token = $('#sharingToken').val();
			var owner = $('#save-external-share').data('owner');
			var ownerDisplayName = $('#save-external-share').data('owner-display-name');
			var name = $('#save-external-share').data('name');
			var isProtected = $('#save-external-share').data('protected') ? 1 : 0;
			OCA.Sharing.PublicApp._createFederatedShare(remote, token, owner, ownerDisplayName, name, isProtected);
		});

		$('#remote_address').on("keyup paste", function() {
			if ($(this).val() === '' || $('#save-external-share > .icon.icon-loading-small').length > 0) {
				$('#save-button-confirm').prop('disabled', true);
			} else {
				$('#save-button-confirm').prop('disabled', false);
			}
		});

		self._bindShowTermsAction();

		// legacy
		window.FileList = this.fileList;
	},

	/**
	 * Binds the click action for the "terms of service" action.
	 * Shows an OC info dialog on click.
	 *
	 * @private
	 */
	_bindShowTermsAction: function() {
		$('#show-terms-dialog').on('click', function() {
			OC.dialogs.info($('#disclaimerText').val(), t('files_sharing', 'Terms of service'));
		});
	},

	_showTextPreview: function (data, previewHeight) {
		var textDiv = $('<div/>').addClass('text-preview');
		textDiv.text(data);
		textDiv.appendTo('#imgframe');
		var divHeight = textDiv.height();
		if (data.length > 999) {
			var ellipsis = $('<div/>').addClass('ellipsis');
			ellipsis.html('(&#133;)');
			ellipsis.appendTo('#imgframe');
		}
		if (divHeight > previewHeight) {
			textDiv.height(previewHeight);
		}
	},

	_onDirectoryChanged: function (e) {
		OC.Util.History.pushState({
			// arghhhh, why is this not called "dir" !?
			path: e.dir
		});
	},

	_onUrlChanged: function (params) {
		this.fileList.changeDirectory(params.path || params.dir, false, true);
	},


	/**
	 * fall back to old behaviour where we redirect the user to his server to mount
	 * the public link instead of creating a dedicated federated share
	 *
	 * @param remote
	 * @param token
	 * @param owner
	 * @param ownerDisplayName
	 * @param name
	 * @param isProtected
	 * @private
	 */
	_legacyCreateFederatedShare: function (remote, token, owner, ownerDisplayName, name, isProtected) {

		var self = this;
		var location = window.location.protocol + '//' + window.location.host + OC.getRootPath();

		if(remote.substr(-1) !== '/') {
			remote += '/'
		}

		var url = remote + 'index.php/apps/files#' + 'remote=' + encodeURIComponent(location) // our location is the remote for the other server
			+ "&token=" + encodeURIComponent(token) + "&owner=" + encodeURIComponent(owner) +"&ownerDisplayName=" + encodeURIComponent(ownerDisplayName) + "&name=" + encodeURIComponent(name) + "&protected=" + isProtected;


		if (remote.indexOf('://') > 0) {
			OC.redirect(url);
		} else {
			// if no protocol is specified, we automatically detect it by testing https and http
			// this check needs to happen on the server due to the Content Security Policy directive
			$.get(OC.generateUrl('apps/files_sharing/testremote'), {remote: remote}).then(function (protocol) {
				if (protocol !== 'http' && protocol !== 'https') {
					self._toggleLoading();
					OC.dialogs.alert(t('files_sharing', 'No compatible server found at {remote}', {remote: remote}),
						t('files_sharing', 'Invalid server URL'));
				} else {
					OC.redirect(protocol + '://' + url);
				}
			});
		}
	},

	_toggleLoading: function() {
		var loading = $('#save-external-share > .icon.icon-loading-small').length === 0;
		if (loading) {
			$('#save-external-share > .icon-external')
				.removeClass("icon-external")
				.addClass("icon-loading-small");
			$('#save-external-share #save-button-confirm').prop("disabled", true);

		} else {
			$('#save-external-share > .icon-loading-small')
				.addClass("icon-external")
				.removeClass("icon-loading-small");
			$('#save-external-share #save-button-confirm').prop("disabled", false);

		}
	},

	_createFederatedShare: function (remote, token, owner, ownerDisplayName, name, isProtected) {
		var self = this;

		this._toggleLoading();

		if (remote.indexOf('@') === -1) {
			this._legacyCreateFederatedShare(remote, token, owner, ownerDisplayName, name, isProtected);
			return;
		}

		$.post(
			OC.generateUrl('/apps/federatedfilesharing/createFederatedShare'),
			{
				'shareWith': remote,
				'token': token
			}
		).done(
			function (data) {
				var url = data.remoteUrl;

				if (url.indexOf('://') > 0) {
					OC.redirect(url);
				} else {
					OC.redirect('http://' + url);
				}
			}
		).fail(
			function (jqXHR) {
				OC.dialogs.alert(JSON.parse(jqXHR.responseText).message,
					t('files_sharing', 'Failed to add the public link to your Nextcloud'));
				self._toggleLoading();
			}
		);
	}
};

$(document).ready(function () {
	// FIXME: replace with OC.Plugins.register()
	if (window.TESTING) {
		return;
	}

	var App = OCA.Sharing.PublicApp;
	// defer app init, to give a chance to plugins to register file actions
	_.defer(function () {
		App.initialize($('#preview'));
	});

	if (window.Files) {
		// HACK: for oc-dialogs previews that depends on Files:
		Files.generatePreviewUrl = function (urlSpec) {
			return App.fileList.generatePreviewUrl(urlSpec);
		};
	}

});
