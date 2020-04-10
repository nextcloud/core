/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/* global getURLParameter */
/**
 * Utility class for file related operations
 */
(function() {
	var Files = {
		// file space size sync
		_updateStorageStatistics: function(currentDir) {
			var state = Files.updateStorageStatistics;
			if (state.dir){
				if (state.dir === currentDir) {
					return;
				}
				// cancel previous call, as it was for another dir
				state.call.abort();
			}
			state.dir = currentDir;
			state.call = $.getJSON(OC.filePath('files','ajax','getstoragestats.php') + '?dir=' + encodeURIComponent(currentDir),function(response) {
				state.dir = null;
				state.call = null;
				Files.updateMaxUploadFilesize(response);
			});
		},
		// update quota
		updateStorageQuotas: function() {
			Files._updateStorageQuotasThrottled();
		},
		_updateStorageQuotas: function() {
			var state = Files.updateStorageQuotas;
			state.call = $.getJSON(OC.filePath('files','ajax','getstoragestats.php'),function(response) {
				Files.updateQuota(response);
			});
		},
		/**
		 * Update storage statistics such as free space, max upload,
		 * etc based on the given directory.
		 *
		 * Note this function is debounced to avoid making too
		 * many ajax calls in a row.
		 *
		 * @param dir directory
		 * @param force whether to force retrieving
		 */
		updateStorageStatistics: function(dir, force) {
			if (!OC.currentUser) {
				return;
			}

			if (force) {
				Files._updateStorageStatistics(dir);
			}
			else {
				Files._updateStorageStatisticsDebounced(dir);
			}
		},

		updateMaxUploadFilesize:function(response) {
			if (response === undefined) {
				return;
			}
			if (response.data !== undefined && response.data.uploadMaxFilesize !== undefined) {
				$('#free_space').val(response.data.freeSpace);
				$('#upload.button').attr('data-original-title', response.data.maxHumanFilesize);
				$('#usedSpacePercent').val(response.data.usedSpacePercent);
				$('#owner').val(response.data.owner);
				$('#ownerDisplayName').val(response.data.ownerDisplayName);
				Files.displayStorageWarnings();
				OCA.Files.App.fileList._updateDirectoryPermissions();
			}
			if (response[0] === undefined) {
				return;
			}
			if (response[0].uploadMaxFilesize !== undefined) {
				$('#upload.button').attr('data-original-title', response[0].maxHumanFilesize);
				$('#usedSpacePercent').val(response[0].usedSpacePercent);
				Files.displayStorageWarnings();
			}

		},

		updateQuota:function(response) {
			if (response === undefined) {
				return;
			}
			if (response.data !== undefined
			 && response.data.quota !== undefined
			 && response.data.used !== undefined
			 && response.data.usedSpacePercent !== undefined) {
				var humanUsed = OC.Util.humanFileSize(response.data.used, true);
				var humanQuota = OC.Util.humanFileSize(response.data.quota, true);
				if (response.data.quota > 0) {
					$('#quota').attr('data-original-title', Math.floor(response.data.used/response.data.quota*1000)/10 + '%');
					$('#quota progress').val(response.data.usedSpacePercent);
					$('#quotatext').text(t('files', '{used} of {quota} used', {used: humanUsed, quota: humanQuota}));
				} else {
					$('#quotatext').text(t('files', '{used} used', {used: humanUsed}));
				}
				if (response.data.usedSpacePercent > 80) {
					$('#quota progress').addClass('warn');
				} else {
					$('#quota progress').removeClass('warn');
				}
			}

		},

		/**
		 * Fix path name by removing double slash at the beginning, if any
		 */
		fixPath: function(fileName) {
			if (fileName.substr(0, 2) == '//') {
				return fileName.substr(1);
			}
			return fileName;
		},

		/**
		 * Checks whether the given file name is valid.
		 * @param name file name to check
		 * @return true if the file name is valid.
		 * Throws a string exception with an error message if
		 * the file name is not valid
		 *
		 * NOTE: This function is duplicated in the filepicker inside core/src/OC/dialogs.js
		 */
		isFileNameValid: function (name) {
			var trimmedName = name.trim();
			if (trimmedName === '.' || trimmedName === '..')
			{
				throw t('files', '"{name}" is an invalid file name.', {name: name});
			} else if (trimmedName.length === 0) {
				throw t('files', 'File name cannot be empty.');
			} else if (trimmedName.indexOf('/') !== -1) {
				throw t('files', '"/" is not allowed inside a file name.');
			} else if (!!(trimmedName.match(OC.config.blacklist_files_regex))) {
				throw t('files', '"{name}" is not an allowed filetype', {name: name});
			}

			return true;
		},
		displayStorageWarnings: function() {
			if (!OC.Notification.isHidden()) {
				return;
			}

			var usedSpacePercent = $('#usedSpacePercent').val(),
				owner = $('#owner').val(),
				ownerDisplayName = $('#ownerDisplayName').val();
			if (usedSpacePercent > 98) {
				if (owner !== OC.getCurrentUser().uid) {
					OC.Notification.show(t('files', 'Storage of {owner} is full, files can not be updated or synced anymore!',
						{owner: ownerDisplayName}), {type: 'error'}
					);
					return;
				}
				OC.Notification.show(t('files',
					'Your storage is full, files can not be updated or synced anymore!'),
					{type : 'error'}
				);
				return;
			}
			if (usedSpacePercent > 90) {
				if (owner !== OC.getCurrentUser().uid) {
					OC.Notification.show(t('files', 'Storage of {owner} is almost full ({usedSpacePercent}%)',
						{
							usedSpacePercent: usedSpacePercent,
							owner: ownerDisplayName
						}),
						{
							type: 'error'
						}
					);
					return;
				}
				OC.Notification.show(t('files', 'Your storage is almost full ({usedSpacePercent}%)',
					{usedSpacePercent: usedSpacePercent}),
					{type : 'error'}
				);
			}
		},

		/**
		 * Returns the download URL of the given file(s)
		 * @param {string} filename string or array of file names to download
		 * @param {string} [dir] optional directory in which the file name is, defaults to the current directory
		 * @param {bool} [isDir=false] whether the given filename is a directory and might need a special URL
		 */
		getDownloadUrl: function(filename, dir, isDir) {
			if (!_.isArray(filename) && !isDir) {
				var pathSections = dir.split('/');
				pathSections.push(filename);
				var encodedPath = '';
				_.each(pathSections, function(section) {
					if (section !== '') {
						encodedPath += '/' + encodeURIComponent(section);
					}
				});
				return OC.linkToRemoteBase('webdav') + encodedPath;
			}

			if (_.isArray(filename)) {
				filename = JSON.stringify(filename);
			}

			var params = {
				dir: dir,
				files: filename
			};
			return this.getAjaxUrl('download', params);
		},

		/**
		 * Returns the ajax URL for a given action
		 * @param action action string
		 * @param params optional params map
		 */
		getAjaxUrl: function(action, params) {
			var q = '';
			if (params) {
				q = '?' + OC.buildQueryString(params);
			}
			return OC.filePath('files', 'ajax', action + '.php') + q;
		},

		/**
		 * Fetch the icon url for the mimetype
		 * @param {string} mime The mimetype
		 * @param {Files~mimeicon} ready Function to call when mimetype is retrieved
		 * @deprecated use OC.MimeType.getIconUrl(mime)
		 */
		getMimeIcon: function(mime, ready) {
			ready(OC.MimeType.getIconUrl(mime));
		},

		/**
		 * Generates a preview URL based on the URL space.
		 * @param urlSpec attributes for the URL
		 * @param {int} urlSpec.x width
		 * @param {int} urlSpec.y height
		 * @param {String} urlSpec.file path to the file
		 * @return preview URL
		 * @deprecated used OCA.Files.FileList.generatePreviewUrl instead
		 */
		generatePreviewUrl: function(urlSpec) {
			console.warn('DEPRECATED: please use generatePreviewUrl() from an OCA.Files.FileList instance');
			return OCA.Files.App.fileList.generatePreviewUrl(urlSpec);
		},

		/**
		 * Lazy load preview
		 * @deprecated used OCA.Files.FileList.lazyLoadPreview instead
		 */
		lazyLoadPreview : function(path, mime, ready, width, height, etag) {
			console.warn('DEPRECATED: please use lazyLoadPreview() from an OCA.Files.FileList instance');
			return FileList.lazyLoadPreview({
				path: path,
				mime: mime,
				callback: ready,
				width: width,
				height: height,
				etag: etag
			});
		},

		/**
		 * Initialize the files view
		 */
		initialize: function() {
			Files.bindKeyboardShortcuts(document, $);

			// TODO: move file list related code (upload) to OCA.Files.FileList
			$('#file_action_panel').attr('activeAction', false);

			// drag&drop support using jquery.fileupload
			// TODO use OC.dialogs
			$(document).bind('drop dragover', function (e) {
					e.preventDefault(); // prevent browser from doing anything, if file isn't dropped in dropZone
				});

			// display storage warnings
			setTimeout(Files.displayStorageWarnings, 100);

			// only possible at the moment if user is logged in or the files app is loaded
			if (OC.currentUser && OCA.Files.App && OC.config.session_keepalive) {
				// start on load - we ask the server every 5 minutes
				var func = _.bind(OCA.Files.App.fileList.updateStorageStatistics, OCA.Files.App.fileList);
				var updateStorageStatisticsInterval = 5*60*1000;
				var updateStorageStatisticsIntervalId = setInterval(func, updateStorageStatisticsInterval);

				// TODO: this should also stop when switching to another view
				// Use jquery-visibility to de-/re-activate file stats sync
				if ($.support.pageVisibility) {
					$(document).on({
						'show': function() {
							if (!updateStorageStatisticsIntervalId) {
								updateStorageStatisticsIntervalId = setInterval(func, updateStorageStatisticsInterval);
							}
						},
						'hide': function() {
							clearInterval(updateStorageStatisticsIntervalId);
							updateStorageStatisticsIntervalId = 0;
						}
					});
				}
			}


			$('#webdavurl').on('click touchstart', function () {
				this.focus();
				this.setSelectionRange(0, this.value.length);
			});

			$('#upload').tooltip({placement:'right'});

			//FIXME scroll to and highlight preselected file
			/*
			if (getURLParameter('scrollto')) {
				FileList.scrollTo(getURLParameter('scrollto'));
			}
			*/
		},

		/**
		 * Handles the download and calls the callback function once the download has started
		 * - browser sends download request and adds parameter with a token
		 * - server notices this token and adds a set cookie to the download response
		 * - browser now adds this cookie for the domain
		 * - JS periodically checks for this cookie and then knows when the download has started to call the callback
		 *
		 * @param {string} url download URL
		 * @param {Function} callback function to call once the download has started
		 */
		handleDownload: function(url, callback) {
			var randomToken = Math.random().toString(36).substring(2),
				checkForDownloadCookie = function() {
					if (!OC.Util.isCookieSetToValue('ocDownloadStarted', randomToken)){
						return false;
					} else {
						callback();
						return true;
					}
				};

			if (url.indexOf('?') >= 0) {
				url += '&';
			} else {
				url += '?';
			}
			OC.redirect(url + 'downloadStartSecret=' + randomToken);
			OC.Util.waitFor(checkForDownloadCookie, 500);
		}
	};

	Files._updateStorageStatisticsDebounced = _.debounce(Files._updateStorageStatistics, 250);
	Files._updateStorageQuotasThrottled = _.throttle(Files._updateStorageQuotas, 30000);
	OCA.Files.Files = Files;
})();

// TODO: move to FileList
var createDragShadow = function(event) {
	// FIXME: inject file list instance somehow
	/* global FileList, Files */

	//select dragged file
	var isDragSelected = $(event.target).parents('tr').find('td input:first').prop('checked');
	if (!isDragSelected) {
		//select dragged file
		FileList._selectFileEl($(event.target).parents('tr:first'), true, false);
	}

	// do not show drag shadow for too many files
	var selectedFiles = _.first(FileList.getSelectedFiles(), FileList.pageSize());
	selectedFiles = _.sortBy(selectedFiles, FileList._fileInfoCompare);

	if (!isDragSelected && selectedFiles.length === 1) {
		//revert the selection
		FileList._selectFileEl($(event.target).parents('tr:first'), false, false);
	}

	// build dragshadow
	var dragshadow = $('<table class="dragshadow"></table>');
	var tbody = $('<tbody></tbody>');
	dragshadow.append(tbody);

	var dir = FileList.getCurrentDirectory();

	$(selectedFiles).each(function(i,elem) {
		// TODO: refactor this with the table row creation code
		var newtr = $('<tr/>')
			.attr('data-dir', dir)
			.attr('data-file', elem.name)
			.attr('data-origin', elem.origin);
		newtr.append($('<td class="filename" />').text(elem.name).css('background-size', 32));
		newtr.append($('<td class="size" />').text(OC.Util.humanFileSize(elem.size)));
		tbody.append(newtr);
		if (elem.type === 'dir') {
			newtr.find('td.filename')
				.css('background-image', 'url(' + OC.MimeType.getIconUrl('folder') + ')');
		} else {
			var path = dir + '/' + elem.name;
			Files.lazyLoadPreview(path, elem.mimetype, function(previewpath) {
				newtr.find('td.filename')
					.css('background-image', 'url(' + previewpath + ')');
			}, null, null, elem.etag);
		}
	});

	return dragshadow;
};

//options for file drag/drop
//start&stop handlers needs some cleaning up
// TODO: move to FileList class
var dragOptions={
	revert: 'invalid',
	revertDuration: 300,
	opacity: 0.7,
	appendTo: 'body',
	cursorAt: { left: 24, top: 18 },
	helper: createDragShadow,
	cursor: 'move',

	start: function(event, ui){
		var $selectedFiles = $('td.filename input:checkbox:checked');
		if (!$selectedFiles.length) {
			$selectedFiles = $(this);
		}
		$selectedFiles.closest('tr').addClass('animate-opacity dragging');
		$selectedFiles.closest('tr').filter('.ui-droppable').droppable( 'disable' );
		// Show breadcrumbs menu
		$('.crumbmenu').addClass('canDropChildren');

	},
	stop: function(event, ui) {
		var $selectedFiles = $('td.filename input:checkbox:checked');
		if (!$selectedFiles.length) {
			$selectedFiles = $(this);
		}

		var $tr = $selectedFiles.closest('tr');
		$tr.removeClass('dragging');
		$tr.filter('.ui-droppable').droppable( 'enable' );

		setTimeout(function() {
			$tr.removeClass('animate-opacity');
		}, 300);
		// Hide breadcrumbs menu
		$('.crumbmenu').removeClass('canDropChildren');
	},
	drag: function(event, ui) {
		var scrollingArea = window;
		var currentScrollTop = $(scrollingArea).scrollTop();
		var scrollArea = Math.min(Math.floor($(window).innerHeight() / 2), 100);

		var bottom = $(window).innerHeight() - scrollArea;
		var top = $(window).scrollTop() + scrollArea;
		if (event.pageY < top) {
			$(scrollingArea).animate({
				scrollTop: currentScrollTop - 10
			}, 400);

		} else if (event.pageY > bottom) {
			$(scrollingArea).animate({
				scrollTop: currentScrollTop + 10
			}, 400);
		}

	}
};
// sane browsers support using the distance option
if ( $('html.ie').length === 0) {
	dragOptions['distance'] = 20;
}

// TODO: move to FileList class
var folderDropOptions = {
	hoverClass: "canDrop",
	drop: function( event, ui ) {
		// don't allow moving a file into a selected folder
		/* global FileList */
		if ($(event.target).parents('tr').find('td input:first').prop('checked') === true) {
			return false;
		}

		var $tr = $(this).closest('tr');
		if (($tr.data('permissions') & OC.PERMISSION_CREATE) === 0) {
			FileList._showPermissionDeniedNotification();
			return false;
		}
		var targetPath = FileList.getCurrentDirectory() + '/' + $tr.data('file');

		var files = FileList.getSelectedFiles();
		if (files.length === 0) {
			// single one selected without checkbox?
			files = _.map(ui.helper.find('tr'), function(el) {
				return FileList.elementToFile($(el));
			});
		}

		FileList.move(_.pluck(files, 'name'), targetPath);
	},
	tolerance: 'pointer'
};

// for backward compatibility
window.Files = OCA.Files.Files;
