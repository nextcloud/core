/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/* global dav */

(function(OC, FileInfo) {
	/**
	 * @class OC.Files.Client
	 * @classdesc Client to access files on the server
	 *
	 * @param {Object} options
	 * @param {String} options.host host name
	 * @param {int} [options.port] port
	 * @param {boolean} [options.useHTTPS] whether to use https
	 * @param {String} [options.root] root path
	 * @param {String} [options.userName] user name
	 * @param {String} [options.password] password
	 *
	 * @since 8.2
	 */
	var Client = function(options) {
		this._root = options.root;
		if (this._root.charAt(this._root.length - 1) === '/') {
			this._root = this._root.substr(0, this._root.length - 1);
		}

		var url = Client.PROTOCOL_HTTP + '://';
		if (options.useHTTPS) {
			url = Client.PROTOCOL_HTTPS + '://';
		}

		url += options.host + this._root;
		this._host = options.host;
		this._defaultHeaders = options.defaultHeaders || {
				'X-Requested-With': 'XMLHttpRequest',
				'requesttoken': OC.requestToken
			};
		this._baseUrl = url;

		var clientOptions = {
			baseUrl: this._baseUrl,
			xmlNamespaces: {
				'DAV:': 'd',
				'http://owncloud.org/ns': 'oc',
				'http://nextcloud.org/ns': 'nc',
				'http://open-collaboration-services.org/ns': 'ocs'
			}
		};
		if (options.userName) {
			clientOptions.userName = options.userName;
		}
		if (options.password) {
			clientOptions.password = options.password;
		}
		this._client = new dav.Client(clientOptions);
		this._client.xhrProvider = _.bind(this._xhrProvider, this);
		this._fileInfoParsers = [];
	};

	Client.NS_OWNCLOUD = 'http://owncloud.org/ns';
	Client.NS_NEXTCLOUD = 'http://nextcloud.org/ns';
	Client.NS_DAV = 'DAV:';
	Client.NS_OCS = 'http://open-collaboration-services.org/ns';

	Client.PROPERTY_GETLASTMODIFIED	= '{' + Client.NS_DAV + '}getlastmodified';
	Client.PROPERTY_GETETAG	= '{' + Client.NS_DAV + '}getetag';
	Client.PROPERTY_GETCONTENTTYPE	= '{' + Client.NS_DAV + '}getcontenttype';
	Client.PROPERTY_RESOURCETYPE	= '{' + Client.NS_DAV + '}resourcetype';
	Client.PROPERTY_INTERNAL_FILEID	= '{' + Client.NS_OWNCLOUD + '}fileid';
	Client.PROPERTY_PERMISSIONS	= '{' + Client.NS_OWNCLOUD + '}permissions';
	Client.PROPERTY_SIZE	= '{' + Client.NS_OWNCLOUD + '}size';
	Client.PROPERTY_GETCONTENTLENGTH	= '{' + Client.NS_DAV + '}getcontentlength';
	Client.PROPERTY_ISENCRYPTED	= '{' + Client.NS_DAV + '}is-encrypted';
	Client.PROPERTY_SHARE_PERMISSIONS	= '{' + Client.NS_OCS + '}share-permissions';

	Client.PROTOCOL_HTTP	= 'http';
	Client.PROTOCOL_HTTPS	= 'https';

	Client._PROPFIND_PROPERTIES = [
		/**
		 * Modified time
		 */
		[Client.NS_DAV, 'getlastmodified'],
		/**
		 * Etag
		 */
		[Client.NS_DAV, 'getetag'],
		/**
		 * Mime type
		 */
		[Client.NS_DAV, 'getcontenttype'],
		/**
		 * Resource type "collection" for folders, empty otherwise
		 */
		[Client.NS_DAV, 'resourcetype'],
		/**
		 * File id
		 */
		[Client.NS_OWNCLOUD, 'fileid'],
		/**
		 * Letter-coded permissions
		 */
		[Client.NS_OWNCLOUD, 'permissions'],
		//[Client.NS_OWNCLOUD, 'downloadURL'],
		/**
		 * Folder sizes
		 */
		[Client.NS_OWNCLOUD, 'size'],
		/**
		 * File sizes
		 */
		[Client.NS_DAV, 'getcontentlength'],
		/**
		 * Preview availability
		 */
		[Client.NS_NEXTCLOUD, 'has-preview'],
		/**
		 * Mount type
		 */
		[Client.NS_NEXTCLOUD, 'mount-type'],
		/**
		 * Encryption state
		 */
		[Client.NS_NEXTCLOUD, 'is-encrypted'],
		/**
		 * Share permissions
		 */
		[Client.NS_OCS, 'share-permissions']
	];

	/**
	 * @memberof OC.Files
	 */
	Client.prototype = {

		/**
		 * Root path of the Webdav endpoint
		 *
		 * @type string
		 */
		_root: null,

		/**
		 * Client from the library
		 *
		 * @type dav.Client
		 */
		_client: null,

		/**
		 * Array of file info parsing functions.
		 *
		 * @type Array<OC.Files.Client~parseFileInfo>
		 */
		_fileInfoParsers: [],

		/**
		 * Returns the configured XHR provider for davclient
		 * @return {XMLHttpRequest}
		 */
		_xhrProvider: function() {
			var headers = this._defaultHeaders;
			var xhr = new XMLHttpRequest();
			var oldOpen = xhr.open;
			// override open() method to add headers
			xhr.open = function() {
				var result = oldOpen.apply(this, arguments);
				_.each(headers, function(value, key) {
					xhr.setRequestHeader(key, value);
				});
				return result;
			};

			OC.registerXHRForErrorProcessing(xhr);
			return xhr;
		},

		/**
		 * Prepends the base url to the given path sections
		 *
		 * @param {...String} path sections
		 *
		 * @return {String} base url + joined path, any leading or trailing slash
		 * will be kept
		 */
		_buildUrl: function() {
			var path = this._buildPath.apply(this, arguments);
			if (path.charAt([path.length - 1]) === '/') {
				path = path.substr(0, path.length - 1);
			}
			if (path.charAt(0) === '/') {
				path = path.substr(1);
			}
			return this._baseUrl + '/' + path;
		},

		/**
		 * Append the path to the root and also encode path
		 * sections
		 *
		 * @param {...String} path sections
		 *
		 * @return {String} joined path, any leading or trailing slash
		 * will be kept
		 */
		_buildPath: function() {
			var path = OC.joinPaths.apply(this, arguments);
			var sections = path.split('/');
			var i;
			for (i = 0; i < sections.length; i++) {
				sections[i] = encodeURIComponent(sections[i]);
			}
			path = sections.join('/');
			return path;
		},

		/**
		 * Parse headers string into a map
		 *
		 * @param {string} headersString headers list as string
		 *
		 * @return {Object.<String,Array>} map of header name to header contents
		 */
		_parseHeaders: function(headersString) {
			var headerRows = headersString.split('\n');
			var headers = {};
			for (var i = 0; i < headerRows.length; i++) {
				var sepPos = headerRows[i].indexOf(':');
				if (sepPos < 0) {
					continue;
				}

				var headerName = headerRows[i].substr(0, sepPos);
				var headerValue = headerRows[i].substr(sepPos + 2);

				if (!headers[headerName]) {
					// make it an array
					headers[headerName] = [];
				}

				headers[headerName].push(headerValue);
			}
			return headers;
		},

		/**
		 * Parses the etag response which is in double quotes.
		 *
		 * @param {string} etag etag value in double quotes
		 *
		 * @return {string} etag without double quotes
		 */
		_parseEtag: function(etag) {
			if (etag.charAt(0) === '"') {
				return etag.split('"')[1];
			}
			return etag;
		},

		/**
		 * Parse Webdav result
		 *
		 * @param {Object} response XML object
		 *
		 * @return {Array.<FileInfo>} array of file info
		 */
		_parseFileInfo: function(response) {
			var path = decodeURIComponent(response.href);
			if (path.substr(0, this._root.length) === this._root) {
				path = path.substr(this._root.length);
			}

			if (path.charAt(path.length - 1) === '/') {
				path = path.substr(0, path.length - 1);
			}

			if (response.propStat.length === 0 || response.propStat[0].status !== 'HTTP/1.1 200 OK') {
				return null;
			}

			var props = response.propStat[0].properties;

			var data = {
				id: props[Client.PROPERTY_INTERNAL_FILEID],
				path: OC.dirname(path) || '/',
				name: OC.basename(path),
				mtime: (new Date(props[Client.PROPERTY_GETLASTMODIFIED])).getTime()
			};

			var etagProp = props[Client.PROPERTY_GETETAG];
			if (!_.isUndefined(etagProp)) {
				data.etag = this._parseEtag(etagProp);
			}

			var sizeProp = props[Client.PROPERTY_GETCONTENTLENGTH];
			if (!_.isUndefined(sizeProp)) {
				data.size = parseInt(sizeProp, 10);
			}

			sizeProp = props[Client.PROPERTY_SIZE];
			if (!_.isUndefined(sizeProp)) {
				data.size = parseInt(sizeProp, 10);
			}

			var hasPreviewProp = props['{' + Client.NS_NEXTCLOUD + '}has-preview'];
			if (!_.isUndefined(hasPreviewProp)) {
				data.hasPreview = hasPreviewProp === 'true';
			} else {
				data.hasPreview = true;
			}

			var isEncryptedProp = props['{' + Client.NS_NEXTCLOUD + '}is-encrypted'];
			if (!_.isUndefined(isEncryptedProp)) {
				data.isEncrypted = isEncryptedProp === '1';
			} else {
				data.isEncrypted = false;
			}

			var isFavouritedProp = props['{' + Client.NS_OWNCLOUD + '}favorite'];
			if (!_.isUndefined(isFavouritedProp)) {
				data.isFavourited = isFavouritedProp === '1';
			} else {
				data.isFavourited = false;
			}

			var contentType = props[Client.PROPERTY_GETCONTENTTYPE];
			if (!_.isUndefined(contentType)) {
				data.mimetype = contentType;
			}

			var resType = props[Client.PROPERTY_RESOURCETYPE];
			if (!data.mimetype && resType) {
				var xmlvalue = resType[0];
				if (xmlvalue.namespaceURI === Client.NS_DAV && xmlvalue.nodeName.split(':')[1] === 'collection') {
					data.mimetype = 'httpd/unix-directory';
				}
			}

			data.permissions = OC.PERMISSION_NONE;
			var permissionProp = props[Client.PROPERTY_PERMISSIONS];
			if (!_.isUndefined(permissionProp)) {
				var permString = permissionProp || '';
				data.mountType = null;
				for (var i = 0; i < permString.length; i++) {
					var c = permString.charAt(i);
					switch (c) {
						// FIXME: twisted permissions
						case 'C':
						case 'K':
							data.permissions |= OC.PERMISSION_CREATE;
							break;
						case 'G':
							data.permissions |= OC.PERMISSION_READ;
							break;
						case 'W':
						case 'N':
						case 'V':
							data.permissions |= OC.PERMISSION_UPDATE;
							break;
						case 'D':
							data.permissions |= OC.PERMISSION_DELETE;
							break;
						case 'R':
							data.permissions |= OC.PERMISSION_SHARE;
							break;
						case 'M':
							if (!data.mountType) {
								// TODO: how to identify external-root ?
								data.mountType = 'external';
							}
							break;
						case 'S':
							// TODO: how to identify shared-root ?
							data.mountType = 'shared';
							break;
					}
				}
			}

			var sharePermissionsProp = props[Client.PROPERTY_SHARE_PERMISSIONS];
			if (!_.isUndefined(sharePermissionsProp)) {
				data.sharePermissions = parseInt(sharePermissionsProp);
			}

			var mounTypeProp = props['{' + Client.NS_NEXTCLOUD + '}mount-type'];
			if (!_.isUndefined(mounTypeProp)) {
				data.mountType = mounTypeProp;
			}

			// extend the parsed data using the custom parsers
			_.each(this._fileInfoParsers, function(parserFunction) {
				_.extend(data, parserFunction(response, data) || {});
			});

			return new FileInfo(data);
		},

		/**
		 * Parse Webdav multistatus
		 *
		 * @param {Array} responses
		 */
		_parseResult: function(responses) {
			var self = this;
			return _.map(responses, function(response) {
				return self._parseFileInfo(response);
			});
		},

		/**
		 * Returns whether the given status code means success
		 *
		 * @param {int} status status code
		 *
		 * @return true if status code is between 200 and 299 included
		 */
		_isSuccessStatus: function(status) {
			return status >= 200 && status <= 299;
		},

		/**
		 * Parse the Sabre exception out of the given response, if any
		 *
		 * @param {Object} response object
		 * @return {Object} array of parsed message and exception (only the first one)
		 */
		_getSabreException: function(response) {
			var result = {};
			var xml = response.xhr.responseXML;
			if (xml === null) {
				return result;
			}
			var messages = xml.getElementsByTagNameNS('http://sabredav.org/ns', 'message');
			var exceptions = xml.getElementsByTagNameNS('http://sabredav.org/ns', 'exception');
			if (messages.length) {
				result.message = messages[0].textContent;
			}
			if (exceptions.length) {
				result.exception = exceptions[0].textContent;
			}
			return result;
		},

		/**
		 * Returns the default PROPFIND properties to use during a call.
		 *
		 * @return {Array.<Object>} array of properties
		 */
		getPropfindProperties: function() {
			if (!this._propfindProperties) {
				this._propfindProperties = _.map(Client._PROPFIND_PROPERTIES, function(propDef) {
					return '{' + propDef[0] + '}' + propDef[1];
				});
			}
			return this._propfindProperties;
		},

		/**
		 * Lists the contents of a directory
		 *
		 * @param {String} path path to retrieve
		 * @param {Object} [options] options
		 * @param {boolean} [options.includeParent=false] set to true to keep
		 * the parent folder in the result list
		 * @param {Array} [options.properties] list of Webdav properties to retrieve
		 *
		 * @return {Promise} promise
		 */
		getFolderContents: function(path, options) {
			if (!path) {
				path = '';
			}
			options = options || {};
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			var properties;
			if (_.isUndefined(options.properties)) {
				properties = this.getPropfindProperties();
			} else {
				properties = options.properties;
			}

			this._client.propFind(
				this._buildUrl(path),
				properties,
				1
			).then(function(result) {
				if (self._isSuccessStatus(result.status)) {
					var results = self._parseResult(result.body);
					if (!options || !options.includeParent) {
						// remove root dir, the first entry
						results.shift();
					}
					deferred.resolve(result.status, results);
				} else {
					result = _.extend(result, self._getSabreException(result));
					deferred.reject(result.status, result);
				}
			});
			return promise;
		},

		/**
		 * Fetches a flat list of files filtered by a given filter criteria.
		 * (currently system tags and circles are supported)
		 *
		 * @param {Object} filter filter criteria
		 * @param {Object} [filter.systemTagIds] list of system tag ids to filter by
		 * @param {bool} [filter.favorite] set it to filter by favorites
		 * @param {Object} [options] options
		 * @param {Array} [options.properties] list of Webdav properties to retrieve
		 *
		 * @return {Promise} promise
		 */
		getFilteredFiles: function(filter, options) {
			options = options || {};
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			var properties;
			if (_.isUndefined(options.properties)) {
				properties = this.getPropfindProperties();
			} else {
				properties = options.properties;
			}

			if (!filter ||
				(!filter.systemTagIds && _.isUndefined(filter.favorite) && !filter.circlesIds) ) {
				throw 'Missing filter argument';
			}

			// root element with namespaces
            var body = '<oc:filter-files ';
			var namespace;
			for (namespace in this._client.xmlNamespaces) {
				body += ' xmlns:' + this._client.xmlNamespaces[namespace] + '="' + namespace + '"';
			}
			body += '>\n';

			// properties query
			body += '    <' + this._client.xmlNamespaces['DAV:'] + ':prop>\n';
			_.each(properties, function(prop) {
				var property = self._client.parseClarkNotation(prop);
                body += '        <' + self._client.xmlNamespaces[property.namespace] + ':' + property.name + ' />\n';
			});

			body += '    </' + this._client.xmlNamespaces['DAV:'] + ':prop>\n';

			// rules block
			body +=	'    <oc:filter-rules>\n';
			_.each(filter.systemTagIds, function(systemTagIds) {
				body += '        <oc:systemtag>' + escapeHTML(systemTagIds) + '</oc:systemtag>\n';
			});
			_.each(filter.circlesIds, function(circlesIds) {
				body += '        <oc:circle>' + escapeHTML(circlesIds) + '</oc:circle>\n';
			});
			if (filter.favorite) {
				body += '        <oc:favorite>' + (filter.favorite ? '1': '0') + '</oc:favorite>\n';
			}
			body += '    </oc:filter-rules>\n';

			// end of root
			body += '</oc:filter-files>\n';

			this._client.request(
				'REPORT',
				this._buildUrl(),
				{},
				body
			).then(function(result) {
				if (self._isSuccessStatus(result.status)) {
					var results = self._parseResult(result.body);
					deferred.resolve(result.status, results);
				} else {
					result = _.extend(result, self._getSabreException(result));
					deferred.reject(result.status, result);
				}
			});
			return promise;
		},

		/**
		 * Returns the file info of a given path.
		 *
		 * @param {String} path path
		 * @param {Array} [options.properties] list of Webdav properties to retrieve
		 *
		 * @return {Promise} promise
		 */
		getFileInfo: function(path, options) {
			if (!path) {
				path = '';
			}
			options = options || {};
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			var properties;
			if (_.isUndefined(options.properties)) {
				properties = this.getPropfindProperties();
			} else {
				properties = options.properties;
			}

			// TODO: headers
			this._client.propFind(
				this._buildUrl(path),
				properties,
				0
			).then(
				function(result) {
					if (self._isSuccessStatus(result.status)) {
						deferred.resolve(result.status, self._parseResult([result.body])[0]);
					} else {
						result = _.extend(result, self._getSabreException(result));
						deferred.reject(result.status, result);
					}
				}
			);
			return promise;
		},

		/**
		 * Returns the contents of the given file.
		 *
		 * @param {String} path path to file
		 *
		 * @return {Promise}
		 */
		getFileContents: function(path) {
			if (!path) {
				throw 'Missing argument "path"';
			}
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();

			this._client.request(
				'GET',
				this._buildUrl(path)
			).then(
				function(result) {
					if (self._isSuccessStatus(result.status)) {
						deferred.resolve(result.status, result.body);
					} else {
						result = _.extend(result, self._getSabreException(result));
						deferred.reject(result.status, result);
					}
				}
			);
			return promise;
		},

		/**
		 * Puts the given data into the given file.
		 *
		 * @param {String} path path to file
		 * @param {String} body file body
		 * @param {Object} [options]
		 * @param {String} [options.contentType='text/plain'] content type
		 * @param {bool} [options.overwrite=true] whether to overwrite an existing file
		 *
		 * @return {Promise}
		 */
		putFileContents: function(path, body, options) {
			if (!path) {
				throw 'Missing argument "path"';
			}
			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			options = options || {};
			var headers = {};
			var contentType = 'text/plain;charset=utf-8';
			if (options.contentType) {
				contentType = options.contentType;
			}

			headers['Content-Type'] = contentType;

			if (_.isUndefined(options.overwrite) || options.overwrite) {
				// will trigger 412 precondition failed if a file already exists
				headers['If-None-Match'] = '*';
			}

			this._client.request(
				'PUT',
				this._buildUrl(path),
				headers,
				body || ''
			).then(
				function(result) {
					if (self._isSuccessStatus(result.status)) {
						deferred.resolve(result.status);
					} else {
						result = _.extend(result, self._getSabreException(result));
						deferred.reject(result.status, result);
					}
				}
			);
			return promise;
		},

		_simpleCall: function(method, path) {
			if (!path) {
				throw 'Missing argument "path"';
			}

			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();

			this._client.request(
				method,
				this._buildUrl(path)
			).then(
				function(result) {
					if (self._isSuccessStatus(result.status)) {
						deferred.resolve(result.status);
					} else {
						result = _.extend(result, self._getSabreException(result));
						deferred.reject(result.status, result);
					}
				}
			);
			return promise;
		},

		/**
		 * Creates a directory
		 *
		 * @param {String} path path to create
		 *
		 * @return {Promise}
		 */
		createDirectory: function(path) {
			return this._simpleCall('MKCOL', path);
		},

		/**
		 * Deletes a file or directory
		 *
		 * @param {String} path path to delete
		 *
		 * @return {Promise}
		 */
		remove: function(path) {
			return this._simpleCall('DELETE', path);
		},

		/**
		 * Moves path to another path
		 *
		 * @param {String} path path to move
		 * @param {String} destinationPath destination path
		 * @param {boolean} [allowOverwrite=false] true to allow overwriting,
		 * false otherwise
		 * @param {Object} [headers=null] additional headers
		 *
		 * @return {Promise} promise
		 */
		move: function(path, destinationPath, allowOverwrite, headers) {
			if (!path) {
				throw 'Missing argument "path"';
			}
			if (!destinationPath) {
				throw 'Missing argument "destinationPath"';
			}

			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			headers = _.extend({}, headers, {
				'Destination' : this._buildUrl(destinationPath)
			});

			if (!allowOverwrite) {
				headers.Overwrite = 'F';
			}

			this._client.request(
				'MOVE',
				this._buildUrl(path),
				headers
			).then(
				function(result) {
					if (self._isSuccessStatus(result.status)) {
						deferred.resolve(result.status);
					} else {
						result = _.extend(result, self._getSabreException(result));
						deferred.reject(result.status, result);
					}
				}
			);
			return promise;
		},

		/**
		 * Copies path to another path
		 *
		 * @param {String} path path to copy
		 * @param {String} destinationPath destination path
		 * @param {boolean} [allowOverwrite=false] true to allow overwriting,
		 * false otherwise
		 *
		 * @return {Promise} promise
		 */
		copy: function (path, destinationPath, allowOverwrite) {
			if (!path) {
				throw 'Missing argument "path"';
			}
			if (!destinationPath) {
				throw 'Missing argument "destinationPath"';
			}

			var self = this;
			var deferred = $.Deferred();
			var promise = deferred.promise();
			var headers = {
				'Destination' : this._buildUrl(destinationPath)
			};

			if (!allowOverwrite) {
				headers.Overwrite = 'F';
			}

			this._client.request(
				'COPY',
				this._buildUrl(path),
				headers
			).then(
				function(response) {
					if (self._isSuccessStatus(response.status)) {
						deferred.resolve(response.status);
					} else {
						deferred.reject(response.status);
					}
				}
			);
			return promise;
		},

		/**
		 * Add a file info parser function
		 *
		 * @param {OC.Files.Client~parseFileInfo} parserFunction
		 */
		addFileInfoParser: function(parserFunction) {
			this._fileInfoParsers.push(parserFunction);
		},

		/**
		 * Returns the dav.Client instance used internally
		 *
		 * @since 11.0.0
		 * @return {dav.Client}
		 */
		getClient: function() {
			return this._client;
		},

		/**
		 * Returns the user name
		 *
		 * @since 11.0.0
		 * @return {String} userName
		 */
		getUserName: function() {
			return this._client.userName;
		},

		/**
		 * Returns the password
		 *
		 * @since 11.0.0
		 * @return {String} password
		 */
		getPassword: function() {
			return this._client.password;
		},

		/**
		 * Returns the base URL
		 *
		 * @since 11.0.0
		 * @return {String} base URL
		 */
		getBaseUrl: function() {
			return this._client.baseUrl;
		},

		/**
		 * Returns the host
		 *
		 * @since 13.0.0
		 * @return {String} base URL
		 */
		getHost: function() {
			return this._host;
		}
	};

	/**
	 * File info parser function
	 *
	 * This function receives a list of Webdav properties as input and
	 * should return a hash array of parsed properties, if applicable.
	 *
	 * @callback OC.Files.Client~parseFileInfo
	 * @param {Object} XML Webdav properties
     * @return {Array} array of parsed property values
	 */

	if (!OC.Files) {
		/**
		 * @namespace OC.Files
		 *
		 * @since 8.2
		 */
		OC.Files = {};
	}

	/**
	 * Returns the default instance of the files client
	 *
	 * @return {OC.Files.Client} default client
	 *
	 * @since 8.2
	 */
	OC.Files.getClient = function() {
		if (OC.Files._defaultClient) {
			return OC.Files._defaultClient;
		}

		var client = new OC.Files.Client({
			host: OC.getHost(),
			port: OC.getPort(),
			root: OC.linkToRemoteBase('dav') + '/files/' + OC.getCurrentUser().uid,
			useHTTPS: OC.getProtocol() === 'https'
		});
		OC.Files._defaultClient = client;
		return client;
	};

	OC.Files.Client = Client;
})(OC, OC.Files.FileInfo);
