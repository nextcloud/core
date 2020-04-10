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
	const VersionModel = OC.Backbone.Model.extend({
		sync: OC.Backbone.davSync,

		davProperties: {
			'size': '{DAV:}getcontentlength',
			'mimetype': '{DAV:}getcontenttype',
			'timestamp': '{DAV:}getlastmodified',
		},

		/**
		 * Restores the original file to this revision
		 *
		 * @param {Object} [options] options
		 * @returns {Promise}
		 */
		revert: function(options) {
			options = options ? _.clone(options) : {}
			const model = this

			const client = this.get('client')

			return client.move('/versions/' + this.get('fileId') + '/' + this.get('id'), '/restore/target', true)
				.done(function() {
					if (options.success) {
						options.success.call(options.context, model, {}, options)
					}
					model.trigger('revert', model, options)
				})
				.fail(function() {
					if (options.error) {
						options.error.call(options.context, model, {}, options)
					}
					model.trigger('error', model, {}, options)
				})
		},

		getFullPath: function() {
			return this.get('fullPath')
		},

		getPreviewUrl: function() {
			const url = OC.generateUrl('/apps/files_versions/preview')
			const params = {
				file: this.get('fullPath'),
				version: this.get('id'),
			}
			return url + '?' + OC.buildQueryString(params)
		},

		getDownloadUrl: function() {
			return OC.linkToRemoteBase('dav') + '/versions/' + this.get('user') + '/versions/' + this.get('fileId') + '/' + this.get('id')
		},
	})

	OCA.Versions = OCA.Versions || {}

	OCA.Versions.VersionModel = VersionModel
})()
