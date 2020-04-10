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
	/**
	 * @class OC.SystemTags.SystemTagsMappingCollection
	 * @classdesc
	 *
	 * Collection of tags assigned to a an object
	 *
	 */
	const SystemTagsMappingCollection = OC.Backbone.Collection.extend(
		/** @lends OC.SystemTags.SystemTagsMappingCollection.prototype */ {

			sync: OC.Backbone.davSync,

			/**
		 * Use PUT instead of PROPPATCH
		 */
			usePUT: true,

			/**
		 * Id of the file for which to filter activities by
		 *
		 * @var int
		 */
			_objectId: null,

			/**
		 * Type of the object to filter by
		 *
		 * @var string
		 */
			_objectType: 'files',

			model: OC.SystemTags.SystemTagModel,

			url: function() {
				return OC.linkToRemote('dav') + '/systemtags-relations/' + this._objectType + '/' + this._objectId
			},

			/**
		 * Sets the object id to filter by or null for all.
		 *
		 * @param {int} objectId file id or null
		 */
			setObjectId: function(objectId) {
				this._objectId = objectId
			},

			/**
		 * Sets the object type to filter by or null for all.
		 *
		 * @param {int} objectType file id or null
		 */
			setObjectType: function(objectType) {
				this._objectType = objectType
			},

			initialize: function(models, options) {
				options = options || {}
				if (!_.isUndefined(options.objectId)) {
					this._objectId = options.objectId
				}
				if (!_.isUndefined(options.objectType)) {
					this._objectType = options.objectType
				}
			},

			getTagIds: function() {
				return this.map(function(model) {
					return model.id
				})
			},
		})

	OC.SystemTags = OC.SystemTags || {}
	OC.SystemTags.SystemTagsMappingCollection = SystemTagsMappingCollection
})(OC)
