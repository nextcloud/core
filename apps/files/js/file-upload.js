/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/**
 * The file upload code uses several hooks to interact with blueimps jQuery file upload library:
 * 1. the core upload handling hooks are added when initializing the plugin,
 * 2. if the browser supports progress events they are added in a separate set after the initialization
 * 3. every app can add it's own triggers for fileupload
 *    - files adds d'n'd handlers and also reacts to done events to add new rows to the filelist
 *    - TODO pictures upload button
 *    - TODO music upload button
 */

/* global jQuery, md5 */

/**
 * File upload object
 *
 * @class OC.FileUpload
 * @classdesc
 *
 * Represents a file upload
 *
 * @param {OC.Uploader} uploader uploader
 * @param {Object} data blueimp data
 */
OC.FileUpload = function(uploader, data) {
	this.uploader = uploader;
	this.data = data;
	var basePath = '';
	if (this.uploader.fileList) {
		basePath = this.uploader.fileList.getCurrentDirectory();
	}
	var path = OC.joinPaths(basePath, this.getFile().relativePath || '', this.getFile().name);
	this.id = 'web-file-upload-' + md5(path) + '-' + (new Date()).getTime();
};
OC.FileUpload.CONFLICT_MODE_DETECT = 0;
OC.FileUpload.CONFLICT_MODE_OVERWRITE = 1;
OC.FileUpload.CONFLICT_MODE_AUTORENAME = 2;
OC.FileUpload.prototype = {

	/**
	 * Unique upload id
	 *
	 * @type string
	 */
	id: null,

	/**
	 * Upload element
	 *
	 * @type Object
	 */
	$uploadEl: null,

	/**
	 * Target folder
	 *
	 * @type string
	 */
	_targetFolder: '',

	/**
	 * @type int
	 */
	_conflictMode: OC.FileUpload.CONFLICT_MODE_DETECT,

	/**
	 * New name from server after autorename
	 *
	 * @type String
	 */
	_newName: null,

	/**
	 * Returns the unique upload id
	 *
	 * @return string
	 */
	getId: function() {
		return this.id;
	},

	/**
	 * Returns the file to be uploaded
	 *
	 * @return {File} file
	 */
	getFile: function() {
		return this.data.files[0];
	},

	/**
	 * Return the final filename.
	 *
	 * @return {String} file name
	 */
	getFileName: function() {
		// autorenamed name
		if (this._newName) {
			return this._newName;
		}
		return this.getFile().name;
	},

	setTargetFolder: function(targetFolder) {
		this._targetFolder = targetFolder;
	},

	getTargetFolder: function() {
		return this._targetFolder;
	},

	/**
	 * Get full path for the target file, including relative path,
	 * without the file name.
	 *
	 * @return {String} full path
	 */
	getFullPath: function() {
		return OC.joinPaths(this._targetFolder, this.getFile().relativePath || '');
	},

	/**
	 * Get full path for the target file,
	 * including relative path and file name.
	 *
	 * @return {String} full path
	 */
	getFullFilePath: function() {
		return OC.joinPaths(this.getFullPath(), this.getFile().name);
	},

	/**
	 * Returns conflict resolution mode.
	 *
	 * @return {int} conflict mode
	 */
	getConflictMode: function() {
		return this._conflictMode || OC.FileUpload.CONFLICT_MODE_DETECT;
	},

	/**
	 * Set conflict resolution mode.
	 * See CONFLICT_MODE_* constants.
	 *
	 * @param {int} mode conflict mode
	 */
	setConflictMode: function(mode) {
		this._conflictMode = mode;
	},

	deleteUpload: function() {
		delete this.data.jqXHR;
	},

	/**
	 * Trigger autorename and append "(2)".
	 * Multiple calls will increment the appended number.
	 */
	autoRename: function() {
		var name = this.getFile().name;
		if (!this._renameAttempt) {
			this._renameAttempt = 1;
		}

		var dotPos = name.lastIndexOf('.');
		var extPart = '';
		if (dotPos > 0) {
			this._newName = name.substr(0, dotPos);
			extPart = name.substr(dotPos);
		} else {
			this._newName = name;
		}

		// generate new name
		this._renameAttempt++;
		this._newName = this._newName + ' (' + this._renameAttempt + ')' + extPart;
	},

	/**
	 * Submit the upload
	 */
	submit: function() {
		var self = this;
		var data = this.data;
		var file = this.getFile();

		if (self.aborted === true) {
			return $.Deferred().resolve().promise();
		}
		// it was a folder upload, so make sure the parent directory exists already
		var folderPromise;
		if (file.relativePath) {
			folderPromise = this.uploader.ensureFolderExists(this.getFullPath());
		} else {
			folderPromise = $.Deferred().resolve().promise();
		}

		if (this.uploader.fileList) {
			this.data.url = this.uploader.fileList.getUploadUrl(this.getFileName(), this.getFullPath());
		}

		if (!this.data.headers) {
			this.data.headers = {};
		}

		// webdav without multipart
		this.data.multipart = false;
		this.data.type = 'PUT';

		delete this.data.headers['If-None-Match'];
		if (this._conflictMode === OC.FileUpload.CONFLICT_MODE_DETECT
			|| this._conflictMode === OC.FileUpload.CONFLICT_MODE_AUTORENAME) {
			this.data.headers['If-None-Match'] = '*';
		}

		var userName = this.uploader.davClient.getUserName();
		var password = this.uploader.davClient.getPassword();
		if (userName) {
			// copy username/password from DAV client
			this.data.headers['Authorization'] =
				'Basic ' + btoa(userName + ':' + (password || ''));
		}

		var chunkFolderPromise;
		if ($.support.blobSlice
			&& this.uploader.fileUploadParam.maxChunkSize
			&& this.getFile().size > this.uploader.fileUploadParam.maxChunkSize
		) {
			data.isChunked = true;
			chunkFolderPromise = this.uploader.davClient.createDirectory(
				'uploads/' + OC.getCurrentUser().uid + '/' + this.getId()
			);
			// TODO: if fails, it means same id already existed, need to retry
		} else {
			chunkFolderPromise = $.Deferred().resolve().promise();
			var mtime = this.getFile().lastModified;
			if (mtime) {
				data.headers['X-OC-Mtime'] = mtime / 1000;
			}
		}

		// wait for creation of the required directory before uploading
		return Promise.all([folderPromise, chunkFolderPromise]).then(function() {
			if (self.aborted !== true) {
				data.submit();
			}
		}, function() {
			self.abort();
		});

	},

	/**
	 * Process end of transfer
	 */
	done: function() {
		if (!this.data.isChunked) {
			return $.Deferred().resolve().promise();
		}

		var uid = OC.getCurrentUser().uid;
		var mtime = this.getFile().lastModified;
		var size = this.getFile().size;
		var headers = {};
		if (mtime) {
			headers['X-OC-Mtime'] = mtime / 1000;
		}
		if (size) {
			headers['OC-Total-Length'] = size;

		}

		return this.uploader.davClient.move(
			'uploads/' + uid + '/' + this.getId() + '/.file',
			'files/' + uid + '/' + OC.joinPaths(this.getFullPath(), this.getFileName()),
			true,
			headers
		);
	},

	_deleteChunkFolder: function() {
		// delete transfer directory for this upload
		this.uploader.davClient.remove(
			'uploads/' + OC.getCurrentUser().uid + '/' + this.getId()
		);
	},

	/**
	 * Abort the upload
	 */
	abort: function() {
		if (this.data.isChunked) {
			this._deleteChunkFolder();
		}
		this.data.abort();
		this.deleteUpload();
		this.aborted = true;
	},

	/**
	 * Fail the upload
	 */
	fail: function() {
		this.deleteUpload();
		if (this.data.isChunked) {
			this._deleteChunkFolder();
		}
	},

	/**
	 * Returns the server response
	 *
	 * @return {Object} response
	 */
	getResponse: function() {
		var response = this.data.response();
		if (response.errorThrown) {
			// attempt parsing Sabre exception is available
			var xml = response.jqXHR.responseXML;
			if (xml && xml.documentElement.localName === 'error' && xml.documentElement.namespaceURI === 'DAV:') {
				var messages = xml.getElementsByTagNameNS('http://sabredav.org/ns', 'message');
				var exceptions = xml.getElementsByTagNameNS('http://sabredav.org/ns', 'exception');
				if (messages.length) {
					response.message = messages[0].textContent;
				}
				if (exceptions.length) {
					response.exception = exceptions[0].textContent;
				}
				return response;
			}
		}

		if (typeof response.result !== 'string' && response.result) {
			//fetch response from iframe
			response = $.parseJSON(response.result[0].body.innerText);
			if (!response) {
				// likely due to internal server error
				response = {status: 500};
			}
		} else {
			response = response.result;
		}
		return response;
	},

	/**
	 * Returns the status code from the response
	 *
	 * @return {int} status code
	 */
	getResponseStatus: function() {
		if (this.uploader.isXHRUpload()) {
			var xhr = this.data.response().jqXHR;
			if (xhr) {
				return xhr.status;
			}
			return null;
		}
		return this.getResponse().status;
	},

	/**
	 * Returns the response header by name
	 *
	 * @param {String} headerName header name
	 * @return {Array|String} response header value(s)
	 */
	getResponseHeader: function(headerName) {
		headerName = headerName.toLowerCase();
		if (this.uploader.isXHRUpload()) {
			return this.data.response().jqXHR.getResponseHeader(headerName);
		}

		var headers = this.getResponse().headers;
		if (!headers) {
			return null;
		}

		var value =  _.find(headers, function(value, key) {
			return key.toLowerCase() === headerName;
		});
		if (_.isArray(value) && value.length === 1) {
			return value[0];
		}
		return value;
	}
};

/**
 * keeps track of uploads in progress and implements callbacks for the conflicts dialog
 * @namespace
 */

OC.Uploader = function() {
	this.init.apply(this, arguments);
};

OC.Uploader.prototype = _.extend({
	/**
	 * @type Array<OC.FileUpload>
	 */
	_uploads: {},

	/**
	 * Count of upload done promises that have not finished yet.
	 *
	 * @type int
	 */
	_pendingUploadDoneCount: 0,

	/**
	 * Is it currently uploading?
	 *
	 * @type boolean
	 */
	_uploading: false,

	/**
	 * List of directories known to exist.
	 *
	 * Key is the fullpath and value is boolean, true meaning that the directory
	 * was already created so no need to create it again.
	 */
	_knownDirs: {},

	/**
	 * @type OCA.Files.FileList
	 */
	fileList: null,

	/**
	 * @type OCA.Files.OperationProgressBar
	 */
	progressBar: null,

	/**
	 * @type OC.Files.Client
	 */
	filesClient: null,

	/**
	 * Webdav client pointing at the root "dav" endpoint
	 *
	 * @type OC.Files.Client
	 */
	davClient: null,

	/**
	 * Function that will allow us to know if Ajax uploads are supported
	 * @link https://github.com/New-Bamboo/example-ajax-upload/blob/master/public/index.html
	 * also see article @link http://blog.new-bamboo.co.uk/2012/01/10/ridiculously-simple-ajax-uploads-with-formdata
	 */
	_supportAjaxUploadWithProgress: function() {
		if (window.TESTING) {
			return true;
		}
		return supportFileAPI() && supportAjaxUploadProgressEvents() && supportFormData();

		// Is the File API supported?
		function supportFileAPI() {
			var fi = document.createElement('INPUT');
			fi.type = 'file';
			return 'files' in fi;
		}

		// Are progress events supported?
		function supportAjaxUploadProgressEvents() {
			var xhr = new XMLHttpRequest();
			return !! (xhr && ('upload' in xhr) && ('onprogress' in xhr.upload));
		}

		// Is FormData supported?
		function supportFormData() {
			return !! window.FormData;
		}
	},

	/**
	 * Returns whether an XHR upload will be used
	 *
	 * @return {bool} true if XHR upload will be used,
	 * false for iframe upload
	 */
	isXHRUpload: function () {
		return !this.fileUploadParam.forceIframeTransport &&
			((!this.fileUploadParam.multipart && $.support.xhrFileUpload) ||
			$.support.xhrFormDataFileUpload);
	},

	/**
	 * Makes sure that the upload folder and its parents exists
	 *
	 * @param {String} fullPath full path
	 * @return {Promise} promise that resolves when all parent folders
	 * were created
	 */
	ensureFolderExists: function(fullPath) {
		if (!fullPath || fullPath === '/') {
			return $.Deferred().resolve().promise();
		}

		// remove trailing slash
		if (fullPath.charAt(fullPath.length - 1) === '/') {
			fullPath = fullPath.substr(0, fullPath.length - 1);
		}

		var self = this;
		var promise = this._knownDirs[fullPath];

		if (this.fileList) {
			// assume the current folder exists
			this._knownDirs[this.fileList.getCurrentDirectory()] = $.Deferred().resolve().promise();
		}

		if (!promise) {
			var deferred = new $.Deferred();
			promise = deferred.promise();
			this._knownDirs[fullPath] = promise;

			// make sure all parents already exist
			var parentPath = OC.dirname(fullPath);
			var parentPromise = this._knownDirs[parentPath];
			if (!parentPromise) {
				parentPromise = this.ensureFolderExists(parentPath);
			}

			parentPromise.then(function() {
				self.filesClient.createDirectory(fullPath).always(function(status) {
					// 405 is expected if the folder already exists
					if ((status >= 200 && status < 300) || status === 405) {
						if (status !== 405) {
							self.trigger('createdfolder', fullPath);
						}
						deferred.resolve();
						return;
					}
					OC.Notification.show(t('files', 'Could not create folder "{dir}"', {dir: fullPath}), {type: 'error'});
					deferred.reject();
				});
			}, function() {
				deferred.reject();
			});
		}

		return promise;
	},

	/**
	 * Submit the given uploads
	 *
	 * @param {Array} array of uploads to start
	 */
	submitUploads: function(uploads) {
		var self = this;
		_.each(uploads, function(upload) {
			self._uploads[upload.data.uploadId] = upload;
		});
		self.totalToUpload = _.reduce(uploads, function(memo, upload) { return memo+upload.getFile().size; }, 0);
		var semaphore = new OCA.Files.Semaphore(5);
		var promises = _.map(uploads, function(upload) {
			return semaphore.acquire().then(function(){
				return upload.submit().then(function(){
					semaphore.release();
				});
			});
		});
	},

	confirmBeforeUnload: function() {
		if (this._uploading) {
			return t('files', 'This will stop your current uploads.')
		}
	},

	/**
	 * Show conflict for the given file object
	 *
	 * @param {OC.FileUpload} file upload object
	 */
	showConflict: function(fileUpload) {
		//show "file already exists" dialog
		var self = this;
		var file = fileUpload.getFile();
		// already attempted autorename but the server said the file exists ? (concurrently added)
		if (fileUpload.getConflictMode() === OC.FileUpload.CONFLICT_MODE_AUTORENAME) {
			// attempt another autorename, defer to let the current callback finish
			_.defer(function() {
				self.onAutorename(fileUpload);
			});
			return;
		}
		// retrieve more info about this file
		this.filesClient.getFileInfo(fileUpload.getFullFilePath()).then(function(status, fileInfo) {
			var original = fileInfo;
			var replacement = file;
			original.directory = original.path;
			OC.dialogs.fileexists(fileUpload, original, replacement, self);
		});
	},
	/**
	 * cancels all uploads
	 */
	cancelUploads:function() {
		this.log('canceling uploads');
		jQuery.each(this._uploads, function(i, upload) {
			upload.abort();
		});
		this.clear();
	},
	/**
	 * Clear uploads
	 */
	clear: function() {
		this._knownDirs = {};
	},
	/**
	 * Returns an upload by id
	 *
	 * @param {int} data uploadId
	 * @return {OC.FileUpload} file upload
	 */
	getUpload: function(data) {
		if (_.isString(data)) {
			return this._uploads[data];
		} else if (data.uploadId && this._uploads[data.uploadId]) {
			this._uploads[data.uploadId].data = data;
			return this._uploads[data.uploadId];
		}
		return null;
	},

	/**
	 * Removes an upload from the list of known uploads.
	 *
	 * @param {OC.FileUpload} upload the upload to remove.
	 */
	removeUpload: function(upload) {
		if (!upload || !upload.data || !upload.data.uploadId) {
			return;
		}

		delete this._uploads[upload.data.uploadId];
	},

	showUploadCancelMessage: _.debounce(function() {
		OC.Notification.show(t('files', 'Upload cancelled.'), {timeout : 7, type: 'error'});
	}, 500),
	/**
	 * callback for the conflicts dialog
	 */
	onCancel:function() {
		this.cancelUploads();
	},
	/**
	 * callback for the conflicts dialog
	 * calls onSkip, onReplace or onAutorename for each conflict
	 * @param {object} conflicts - list of conflict elements
	 */
	onContinue:function(conflicts) {
		var self = this;
		//iterate over all conflicts
		jQuery.each(conflicts, function (i, conflict) {
			conflict = $(conflict);
			var keepOriginal = conflict.find('.original input[type="checkbox"]:checked').length === 1;
			var keepReplacement = conflict.find('.replacement input[type="checkbox"]:checked').length === 1;
			if (keepOriginal && keepReplacement) {
				// when both selected -> autorename
				self.onAutorename(conflict.data('data'));
			} else if (keepReplacement) {
				// when only replacement selected -> overwrite
				self.onReplace(conflict.data('data'));
			} else {
				// when only original selected -> skip
				// when none selected -> skip
				self.onSkip(conflict.data('data'));
			}
		});
	},
	/**
	 * handle skipping an upload
	 * @param {OC.FileUpload} upload
	 */
	onSkip:function(upload) {
		this.log('skip', null, upload);
		upload.deleteUpload();
	},
	/**
	 * handle replacing a file on the server with an uploaded file
	 * @param {FileUpload} data
	 */
	onReplace:function(upload) {
		this.log('replace', null, upload);
		upload.setConflictMode(OC.FileUpload.CONFLICT_MODE_OVERWRITE);
		this.submitUploads([upload]);
	},
	/**
	 * handle uploading a file and letting the server decide a new name
	 * @param {object} upload
	 */
	onAutorename:function(upload) {
		this.log('autorename', null, upload);
		upload.setConflictMode(OC.FileUpload.CONFLICT_MODE_AUTORENAME);

		do {
			upload.autoRename();
			// if file known to exist on the client side, retry
		} while (this.fileList && this.fileList.inList(upload.getFileName()));

		// resubmit upload
		this.submitUploads([upload]);
	},
	_trace: false, //TODO implement log handler for JS per class?
	log: function(caption, e, data) {
		if (this._trace) {
			console.log(caption);
			console.log(data);
		}
	},
	/**
	 * checks the list of existing files prior to uploading and shows a simple dialog to choose
	 * skip all, replace all or choose which files to keep
	 *
	 * @param {array} selection of files to upload
	 * @param {object} callbacks - object with several callback methods
	 * @param {Function} callbacks.onNoConflicts
	 * @param {Function} callbacks.onSkipConflicts
	 * @param {Function} callbacks.onReplaceConflicts
	 * @param {Function} callbacks.onChooseConflicts
	 * @param {Function} callbacks.onCancel
	 */
	checkExistingFiles: function (selection, callbacks) {
		var fileList = this.fileList;
		var conflicts = [];
		// only keep non-conflicting uploads
		selection.uploads = _.filter(selection.uploads, function(upload) {
			var file = upload.getFile();
			if (file.relativePath) {
				// can't check in subfolder contents
				return true;
			}
			if (!fileList) {
				// no list to check against
				return true;
			}
			var fileInfo = fileList.findFile(file.name);
			if (fileInfo) {
				conflicts.push([
					// original
					_.extend(fileInfo, {
						directory: fileInfo.directory || fileInfo.path || fileList.getCurrentDirectory()
					}),
					// replacement (File object)
					upload
				]);
				return false;
			}
			return true;
		});
		if (conflicts.length) {
			// wait for template loading
			OC.dialogs.fileexists(null, null, null, this).done(function() {
				_.each(conflicts, function(conflictData) {
					OC.dialogs.fileexists(conflictData[1], conflictData[0], conflictData[1].getFile(), this);
				});
			});
		}

		// upload non-conflicting files
		// note: when reaching the server they might still meet conflicts
		// if the folder was concurrently modified, these will get added
		// to the already visible dialog, if applicable
		callbacks.onNoConflicts(selection);
	},

	_updateProgressBarOnUploadStop: function() {
		if (this._pendingUploadDoneCount === 0) {
			// All the uploads ended and there is no pending operation, so hide
			// the progress bar.
			// Note that this happens here only with non-chunked uploads; if the
			// upload was chunked then this will have been executed after all
			// the uploads ended but before the upload done handler that reduces
			// the pending operation count was executed.
			this._hideProgressBar();

			return;
		}

		this._setProgressBarText(t('files', 'Processing files …'), t('files', '…'));

		// Nothing is being uploaded at this point, and the pending operations
		// can not be cancelled, so the cancel button should be hidden.
		this._hideCancelButton();
	},

	_hideProgressBar: function() {
		this.progressBar.hideProgressBar();
	},

	_hideCancelButton: function() {
		this.progressBar.hideCancelButton();
	},

	_showProgressBar: function() {
		this.progressBar.showProgressBar();
	},

	_setProgressBarValue: function(value) {
		this.progressBar.setProgressBarValue(value);
	},

	_setProgressBarText: function(textDesktop, textMobile, title) {
		this.progressBar.setProgressBarText(textDesktop, textMobile, title);
	},

	/**
	 * Returns whether the given file is known to be a received shared file
	 *
	 * @param {Object} file file
	 * @return {bool} true if the file is a shared file
	 */
	_isReceivedSharedFile: function(file) {
		if (!window.FileList) {
			return false;
		}
		var $tr = window.FileList.findFileEl(file.name);
		if (!$tr.length) {
			return false;
		}

		return ($tr.attr('data-mounttype') === 'shared-root' && $tr.attr('data-mime') !== 'httpd/unix-directory');
	},

	/**
	 * Initialize the upload object
	 *
	 * @param {Object} $uploadEl upload element
	 * @param {Object} options
	 * @param {OCA.Files.FileList} [options.fileList] file list object
	 * @param {OC.Files.Client} [options.filesClient] files client object
	 * @param {Object} [options.dropZone] drop zone for drag and drop upload
	 */
	init: function($uploadEl, options) {
		var self = this;

		options = options || {};

		this.fileList = options.fileList;
		this.progressBar = options.progressBar;
		this.filesClient = options.filesClient || OC.Files.getClient();
		this.davClient = new OC.Files.Client({
			host: this.filesClient.getHost(),
			root: OC.linkToRemoteBase('dav'),
			useHTTPS: OC.getProtocol() === 'https',
			userName: this.filesClient.getUserName(),
			password: this.filesClient.getPassword()
		});

		$uploadEl = $($uploadEl);
		this.$uploadEl = $uploadEl;

		if ($uploadEl.exists()) {
			this.progressBar.on('cancel', function() {
				self.cancelUploads();
			});

			this.fileUploadParam = {
				type: 'PUT',
				dropZone: options.dropZone, // restrict dropZone to content div
				autoUpload: false,
				sequentialUploads: false,
				limitConcurrentUploads: 10,
				/**
				 * on first add of every selection
				 * - check all files of originalFiles array with files in dir
				 * - on conflict show dialog
				 *   - skip all -> remember as single skip action for all conflicting files
				 *   - replace all -> remember as single replace action for all conflicting files
				 *   - choose -> show choose dialog
				 *     - mark files to keep
				 *       - when only existing -> remember as single skip action
				 *       - when only new -> remember as single replace action
				 *       - when both -> remember as single autorename action
				 * - start uploading selection
				 * @param {object} e
				 * @param {object} data
				 * @returns {boolean}
				 */
				add: function(e, data) {
					self.log('add', e, data);
					var that = $(this), freeSpace;

					var upload = new OC.FileUpload(self, data);
					// can't link directly due to jQuery not liking cyclic deps on its ajax object
					data.uploadId = upload.getId();

					// create a container where we can store the data objects
					if ( ! data.originalFiles.selection ) {
						// initialize selection and remember number of files to upload
						data.originalFiles.selection = {
							uploads: [],
							filesToUpload: data.originalFiles.length,
							totalBytes: 0
						};
					}
					// TODO: move originalFiles to a separate container, maybe inside OC.Upload
					var selection = data.originalFiles.selection;

					// add uploads
					if ( selection.uploads.length < selection.filesToUpload ) {
						// remember upload
						selection.uploads.push(upload);
					}

					//examine file
					var file = upload.getFile();
					try {
						// FIXME: not so elegant... need to refactor that method to return a value
						Files.isFileNameValid(file.name);
					}
					catch (errorMessage) {
						data.textStatus = 'invalidcharacters';
						data.errorThrown = errorMessage;
					}

					if (data.targetDir) {
						upload.setTargetFolder(data.targetDir);
						delete data.targetDir;
					}

					// in case folder drag and drop is not supported file will point to a directory
					// http://stackoverflow.com/a/20448357
					if ( ! file.type && file.size % 4096 === 0 && file.size <= 102400) {
						var dirUploadFailure = false;
						try {
							var reader = new FileReader();
							reader.readAsBinaryString(file);
						} catch (NS_ERROR_FILE_ACCESS_DENIED) {
							//file is a directory
							dirUploadFailure = true;
						}

						if (dirUploadFailure) {
							data.textStatus = 'dirorzero';
							data.errorThrown = t('files',
								'Unable to upload {filename} as it is a directory or has 0 bytes',
								{filename: file.name}
							);
						}
					}

					// only count if we're not overwriting an existing shared file
					if (self._isReceivedSharedFile(file)) {
						file.isReceivedShare = true;
					} else {
						// add size
						selection.totalBytes += file.size;
					}

					// check free space
					freeSpace = $('#free_space').val();
					if (freeSpace >= 0 && selection.totalBytes > freeSpace) {
						data.textStatus = 'notenoughspace';
						data.errorThrown = t('files',
							'Not enough free space, you are uploading {size1} but only {size2} is left', {
							'size1': OC.Util.humanFileSize(selection.totalBytes),
							'size2': OC.Util.humanFileSize($('#free_space').val())
						});
					}

					// end upload for whole selection on error
					if (data.errorThrown) {
						// trigger fileupload fail handler
						var fu = that.data('blueimp-fileupload') || that.data('fileupload');
						fu._trigger('fail', e, data);
						return false; //don't upload anything
					}

					// check existing files when all is collected
					if ( selection.uploads.length >= selection.filesToUpload ) {

						//remove our selection hack:
						delete data.originalFiles.selection;

						var callbacks = {

							onNoConflicts: function (selection) {
								self.submitUploads(selection.uploads);
							},
							onSkipConflicts: function (selection) {
								//TODO mark conflicting files as toskip
							},
							onReplaceConflicts: function (selection) {
								//TODO mark conflicting files as toreplace
							},
							onChooseConflicts: function (selection) {
								//TODO mark conflicting files as chosen
							},
							onCancel: function (selection) {
								$.each(selection.uploads, function(i, upload) {
									upload.abort();
								});
							}
						};

						self.checkExistingFiles(selection, callbacks);

					}

					return true; // continue adding files
				},
				/**
				 * called after the first add, does NOT have the data param
				 * @param {object} e
				 */
				start: function(e) {
					self.log('start', e, null);
					//hide the tooltip otherwise it covers the progress bar
					$('#upload').tooltip('hide');
					self._uploading = true;
				},
				fail: function(e, data) {
					var upload = self.getUpload(data);
					var status = null;
					if (upload) {
						status = upload.getResponseStatus();
					}
					self.log('fail', e, upload);

					self.removeUpload(upload);

					if (data.textStatus === 'abort' || data.errorThrown === 'abort') {
						self.showUploadCancelMessage();
					} else if (status === 412) {
						// file already exists
						self.showConflict(upload);
					} else if (status === 404) {
						// target folder does not exist any more
						OC.Notification.show(t('files', 'Target folder "{dir}" does not exist any more', {dir: upload.getFullPath()} ), {type: 'error'});
						self.cancelUploads();
					} else if (data.textStatus === 'notenoughspace') {
						// not enough space
						OC.Notification.show(t('files', 'Not enough free space'), {type: 'error'});
						self.cancelUploads();
					} else {
						// HTTP connection problem or other error
						var message = t('files', 'An unknown error has occurred');
						if (upload) {
							var response = upload.getResponse();
							if (response) {
								message = response.message;
							}
						}
						OC.Notification.show(message || data.errorThrown, {type: 'error'});
					}

					if (upload) {
						upload.fail();
					}
				},
				/**
				 * called for every successful upload
				 * @param {object} e
				 * @param {object} data
				 */
				done:function(e, data) {
					var upload = self.getUpload(data);
					var that = $(this);
					self.log('done', e, upload);

					self.removeUpload(upload);

					var status = upload.getResponseStatus();
					if (status < 200 || status >= 300) {
						// trigger fail handler
						var fu = that.data('blueimp-fileupload') || that.data('fileupload');
						fu._trigger('fail', e, data);
						return;
					}
				},
				/**
				 * called after last upload
				 * @param {object} e
				 * @param {object} data
				 */
				stop: function(e, data) {
					self.log('stop', e, data);
					self._uploading = false;
				}
			};

			if (options.maxChunkSize) {
				this.fileUploadParam.maxChunkSize = options.maxChunkSize;
			}

			// initialize jquery fileupload (blueimp)
			var fileupload = this.$uploadEl.fileupload(this.fileUploadParam);

			if (this._supportAjaxUploadWithProgress()) {
				//remaining time
				var lastUpdate, lastSize, bufferSize, buffer, bufferIndex, bufferIndex2, bufferTotal;

				var dragging = false;

				// add progress handlers
				fileupload.on('fileuploadadd', function(e, data) {
					self.log('progress handle fileuploadadd', e, data);
					self.trigger('add', e, data);
				});
				// add progress handlers
				fileupload.on('fileuploadstart', function(e, data) {
					self.log('progress handle fileuploadstart', e, data);
					self._setProgressBarText(t('files', 'Uploading …'), t('files', '…'));
					self._setProgressBarValue(0);
					self._showProgressBar();
					// initial remaining time variables
					lastUpdate   = new Date().getTime();
					lastSize     = 0;
					bufferSize   = 20;
					buffer       = [];
					bufferIndex  = 0;
					bufferIndex2 = 0;
					bufferTotal  = 0;
					for(var i = 0; i < bufferSize; i++){
						buffer[i]  = 0;
					}
					self.trigger('start', e, data);
				});
				fileupload.on('fileuploadprogress', function(e, data) {
					self.log('progress handle fileuploadprogress', e, data);
					//TODO progressbar in row
					self.trigger('progress', e, data);
				});
				fileupload.on('fileuploadprogressall', function(e, data) {
					self.log('progress handle fileuploadprogressall', e, data);
					var total = self.totalToUpload;
					var progress = (data.loaded / total) * 100;
					var thisUpdate = new Date().getTime();
					var diffUpdate = (thisUpdate - lastUpdate)/1000; // eg. 2s
					lastUpdate = thisUpdate;
					var diffSize = data.loaded - lastSize;
					lastSize = data.loaded;
					diffSize = diffSize / diffUpdate; // apply timing factor, eg. 1MiB/2s = 0.5MiB/s, unit is byte per second
					var remainingSeconds = ((total - data.loaded) / diffSize);
					if(remainingSeconds >= 0) {
						bufferTotal = bufferTotal - (buffer[bufferIndex]) + remainingSeconds;
						buffer[bufferIndex] = remainingSeconds; //buffer to make it smoother
						bufferIndex = (bufferIndex + 1) % bufferSize;
						bufferIndex2++;
					}
					var smoothRemainingSeconds;
					if (bufferIndex2 > 0 && bufferIndex2 < 20) {
						smoothRemainingSeconds = bufferTotal / bufferIndex2;
					} else if (bufferSize > 0) {
						smoothRemainingSeconds = bufferTotal / bufferSize;
					} else {
						smoothRemainingSeconds = 1;
					}

					var h = moment.duration(smoothRemainingSeconds, "seconds").humanize();
					if (!(smoothRemainingSeconds >= 0 && smoothRemainingSeconds < 14400)) {
						// show "Uploading ..." for durations longer than 4 hours
						h = t('files', 'Uploading …');
					}
					self._setProgressBarText(h, h, t('files', '{loadedSize} of {totalSize} ({bitrate})' , {
							loadedSize: OC.Util.humanFileSize(data.loaded),
							totalSize: OC.Util.humanFileSize(total),
							bitrate: OC.Util.humanFileSize(data.bitrate / 8) + '/s'
						}));
					self._setProgressBarValue(progress);
					self.trigger('progressall', e, data);
				});
				fileupload.on('fileuploadstop', function(e, data) {
					self.log('progress handle fileuploadstop', e, data);

					self.clear();
					self._updateProgressBarOnUploadStop();
					self.trigger('stop', e, data);
				});
				fileupload.on('fileuploadfail', function(e, data) {
					self.log('progress handle fileuploadfail', e, data);
					self.trigger('fail', e, data);
				});
				fileupload.on('fileuploaddragover', function(e){
					$('#app-content').addClass('file-drag');
					$('#emptycontent .icon-folder').addClass('icon-filetype-folder-drag-accept');

					var filerow = $(e.delegatedEvent.target).closest('tr');

					if(!filerow.hasClass('dropping-to-dir')){
						$('.dropping-to-dir .icon-filetype-folder-drag-accept').removeClass('icon-filetype-folder-drag-accept');
						$('.dropping-to-dir').removeClass('dropping-to-dir');
						$('.dir-drop').removeClass('dir-drop');
					}

					if(filerow.attr('data-type') === 'dir'){
						$('#app-content').addClass('dir-drop');
						filerow.addClass('dropping-to-dir');
						filerow.find('.thumbnail').addClass('icon-filetype-folder-drag-accept');
					}

					dragging = true;
				});

				var disableDropState = function() {
					$('#app-content').removeClass('file-drag');
					$('.dropping-to-dir').removeClass('dropping-to-dir');
					$('.dir-drop').removeClass('dir-drop');
					$('.icon-filetype-folder-drag-accept').removeClass('icon-filetype-folder-drag-accept');

					dragging = false;
				};

				fileupload.on('fileuploaddragleave fileuploaddrop', disableDropState);

				// In some browsers the "drop" event can be triggered with no
				// files even if the "dragover" event seemed to suggest that a
				// file was being dragged (and thus caused "fileuploaddragover"
				// to be triggered).
				fileupload.on('fileuploaddropnofiles', function() {
					if (!dragging) {
						return;
					}

					disableDropState();

					OC.Notification.show(t('files', 'Uploading that item is not supported'), {type: 'error'});
				});

				fileupload.on('fileuploadchunksend', function(e, data) {
					// modify the request to adjust it to our own chunking
					var upload = self.getUpload(data);
					var range = data.contentRange.split(' ')[1];
					var chunkId = range.split('/')[0].split('-')[0];
					data.url = OC.getRootPath() +
						'/remote.php/dav/uploads' +
						'/' + OC.getCurrentUser().uid +
						'/' + upload.getId() +
						'/' + chunkId;
					delete data.contentRange;
					delete data.headers['Content-Range'];
				});
				fileupload.on('fileuploaddone', function(e, data) {
					var upload = self.getUpload(data);

					self._pendingUploadDoneCount++;

					upload.done().then(function() {
						self._pendingUploadDoneCount--;
						if (Object.keys(self._uploads).length === 0 && self._pendingUploadDoneCount === 0) {
							// All the uploads ended and there is no pending
							// operation, so hide the progress bar.
							// Note that this happens here only with chunked
							// uploads; if the upload was non-chunked then this
							// handler is immediately executed, before the
							// jQuery upload done handler that removes the
							// upload from the list, and thus at this point
							// there is still at least one upload that has not
							// ended (although the upload stop handler is always
							// executed after all the uploads have ended, which
							// hides the progress bar in that case).
							self._hideProgressBar();
						}

						self.trigger('done', e, upload);
					}).fail(function(status, response) {
						var message = response.message;
						if (status === 507) {
							// not enough space
							OC.Notification.show(message || t('files', 'Not enough free space'), {type: 'error'});
							self.cancelUploads();
						} else if (status === 409) {
							OC.Notification.show(message || t('files', 'Target folder does not exist any more'), {type: 'error'});
						} else {
							OC.Notification.show(message || t('files', 'Error when assembling chunks, status code {status}', {status: status}), {type: 'error'});
						}
						self.trigger('fail', e, data);
					});
				});
				fileupload.on('fileuploaddrop', function(e, data) {
					self.trigger('drop', e, data);
					if (e.isPropagationStopped()) {
						return false;
					}
				});
			}
			window.onbeforeunload = function() {
				return self.confirmBeforeUnload();
			}
		}

		//add multiply file upload attribute to all browsers except konqueror (which crashes when it's used)
		if (navigator.userAgent.search(/konqueror/i) === -1) {
			this.$uploadEl.attr('multiple', 'multiple');
		}

		return this.fileUploadParam;
	}
}, OC.Backbone.Events);
