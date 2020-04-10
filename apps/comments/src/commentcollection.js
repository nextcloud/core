/* eslint-disable */
/*
 * Copyright (c) 2016
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC, OCA) {

	/**
	 * @class OCA.Comments.CommentCollection
	 * @classdesc
	 *
	 * Collection of comments assigned to a file
	 *
	 */
	var CommentCollection = OC.Backbone.Collection.extend(
		/** @lends OCA.Comments.CommentCollection.prototype */ {

			sync: OC.Backbone.davSync,

			model: OCA.Comments.CommentModel,

			/**
		 * Object type
		 *
		 * @type string
		 */
			_objectType: 'files',

			/**
		 * Object id
		 *
		 * @type string
		 */
			_objectId: null,

			/**
		 * True if there are no more page results left to fetch
		 *
		 * @type bool
		 */
			_endReached: false,

			/**
		 * Number of comments to fetch per page
		 *
		 * @type int
		 */
			_limit: 20,

			/**
		 * Initializes the collection
		 *
		 * @param {string} [options.objectType] object type
		 * @param {string} [options.objectId] object id
		 */
			initialize: function(models, options) {
				options = options || {}
				if (options.objectType) {
					this._objectType = options.objectType
				}
				if (options.objectId) {
					this._objectId = options.objectId
				}
			},

			url: function() {
				return OC.linkToRemote('dav') + '/comments/'
				+ encodeURIComponent(this._objectType) + '/'
				+ encodeURIComponent(this._objectId) + '/'
			},

			setObjectId: function(objectId) {
				this._objectId = objectId
			},

			hasMoreResults: function() {
				return !this._endReached
			},

			reset: function() {
				this._endReached = false
				this._summaryModel = null
				return OC.Backbone.Collection.prototype.reset.apply(this, arguments)
			},

			/**
		 * Fetch the next set of results
		 */
			fetchNext: function(options) {
				var self = this
				if (!this.hasMoreResults()) {
					return null
				}

				var body = '<?xml version="1.0" encoding="utf-8" ?>\n'
				+ '<oc:filter-comments xmlns:D="DAV:" xmlns:oc="http://owncloud.org/ns">\n'
				// load one more so we know there is more
				+ '    <oc:limit>' + (this._limit + 1) + '</oc:limit>\n'
				+ '    <oc:offset>' + this.length + '</oc:offset>\n'
				+ '</oc:filter-comments>\n'

				options = options || {}
				var success = options.success
				options = _.extend({
					remove: false,
					parse: true,
					data: body,
					davProperties: CommentCollection.prototype.model.prototype.davProperties,
					success: function(resp) {
						if (resp.length <= self._limit) {
						// no new entries, end reached
							self._endReached = true
						} else {
						// remove last entry, for next page load
							resp = _.initial(resp)
						}
						if (!self.set(resp, options)) {
							return false
						}
						if (success) {
							success.apply(null, arguments)
						}
						self.trigger('sync', 'REPORT', self, options)
					}
				}, options)

				return this.sync('REPORT', this, options)
			},

			/**
		 * Returns the matching summary model
		 *
		 * @returns {OCA.Comments.CommentSummaryModel} summary model
		 */
			getSummaryModel: function() {
				if (!this._summaryModel) {
					this._summaryModel = new OCA.Comments.CommentSummaryModel({
						id: this._objectId,
						objectType: this._objectType
					})
				}
				return this._summaryModel
			},

			/**
		 * Updates the read marker for this comment thread
		 *
		 * @param {Date} [date] optional date, defaults to now
		 * @param {Object} [options] backbone options
		 */
			updateReadMarker: function(date, options) {
				options = options || {}

				return this.getSummaryModel().save({
					readMarker: (date || new Date()).toUTCString()
				}, options)
			}
		})

	OCA.Comments.CommentCollection = CommentCollection
})(OC, OCA)
