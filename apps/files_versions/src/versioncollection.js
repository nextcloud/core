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
	 * @memberof OCA.Versions
	 */
	const VersionCollection = OC.Backbone.Collection.extend({
		model: OCA.Versions.VersionModel,
		sync: OC.Backbone.davSync,

		/**
		 * @var OCA.Files.FileInfoModel
		 */
		_fileInfo: null,

		_currentUser: null,

		_client: null,

		setFileInfo: function(fileInfo) {
			this._fileInfo = fileInfo
		},

		getFileInfo: function() {
			return this._fileInfo
		},

		setCurrentUser: function(user) {
			this._currentUser = user
		},

		getCurrentUser: function() {
			return this._currentUser || OC.getCurrentUser().uid
		},

		setClient: function(client) {
			this._client = client
		},

		getClient: function() {
			return this._client || new OC.Files.Client({
				host: OC.getHost(),
				root: OC.linkToRemoteBase('dav') + '/versions/' + this.getCurrentUser(),
				useHTTPS: OC.getProtocol() === 'https',
			})
		},

		url: function() {
			return OC.linkToRemoteBase('dav') + '/versions/' + this.getCurrentUser() + '/versions/' + this._fileInfo.get('id')
		},

		parse: function(result) {
			const fullPath = this._fileInfo.getFullPath()
			const fileId = this._fileInfo.get('id')
			const name = this._fileInfo.get('name')
			const user = this.getCurrentUser()
			const client = this.getClient()
			return _.map(result, function(version) {
				version.fullPath = fullPath
				version.fileId = fileId
				version.name = name
				version.timestamp = parseInt(moment(new Date(version.timestamp)).format('X'), 10)
				version.id = OC.basename(version.href)
				version.size = parseInt(version.size, 10)
				version.user = user
				version.client = client
				return version
			})
		},
	})

	OCA.Versions = OCA.Versions || {}

	OCA.Versions.VersionCollection = VersionCollection
})()
