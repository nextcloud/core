/* eslint-disable */
/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC) {

	function filterFunction(model, term) {
		return model.get('name').substr(0, term.length).toLowerCase() === term.toLowerCase()
	}

	/**
	 * @class OCA.SystemTags.SystemTagsCollection
	 * @classdesc
	 *
	 * Collection of tags assigned to a file
	 *
	 */
	var SystemTagsCollection = OC.Backbone.Collection.extend(
		/** @lends OC.SystemTags.SystemTagsCollection.prototype */ {

			sync: OC.Backbone.davSync,

			model: OC.SystemTags.SystemTagModel,

			url: function() {
				return OC.linkToRemote('dav') + '/systemtags/'
			},

			filterByName: function(name) {
				return this.filter(function(model) {
					return filterFunction(model, name)
				})
			},

			reset: function() {
				this.fetched = false
				return OC.Backbone.Collection.prototype.reset.apply(this, arguments)
			},

			/**
		 * Lazy fetch.
		 * Only fetches once, subsequent calls will directly call the success handler.
		 *
		 * @param options
		 * @param [options.force] true to force fetch even if cached entries exist
		 *
		 * @see Backbone.Collection#fetch
		 */
			fetch: function(options) {
				var self = this
				options = options || {}
				if (this.fetched || options.force) {
				// directly call handler
					if (options.success) {
						options.success(this, null, options)
					}
					// trigger sync event
					this.trigger('sync', this, null, options)
					return Promise.resolve()
				}

				var success = options.success
				options = _.extend({}, options)
				options.success = function() {
					self.fetched = true
					if (success) {
						return success.apply(this, arguments)
					}
				}

				return OC.Backbone.Collection.prototype.fetch.call(this, options)
			}
		})

	OC.SystemTags = OC.SystemTags || {}
	OC.SystemTags.SystemTagsCollection = SystemTagsCollection

	/**
	 * @type OC.SystemTags.SystemTagsCollection
	 */
	OC.SystemTags.collection = new OC.SystemTags.SystemTagsCollection()
})(OC)
