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

	_.extend(OC.Files.Client, {
		PROPERTY_FILEID:	'{' + OC.Files.Client.NS_OWNCLOUD + '}id',
		PROPERTY_CAN_ASSIGN: '{' + OC.Files.Client.NS_OWNCLOUD + '}can-assign',
		PROPERTY_DISPLAYNAME:	'{' + OC.Files.Client.NS_OWNCLOUD + '}display-name',
		PROPERTY_USERVISIBLE:	'{' + OC.Files.Client.NS_OWNCLOUD + '}user-visible',
		PROPERTY_USERASSIGNABLE: '{' + OC.Files.Client.NS_OWNCLOUD + '}user-assignable',
	})

	/**
	 * @class OCA.SystemTags.SystemTagsCollection
	 * @classdesc
	 *
	 * System tag
	 *
	 */
	const SystemTagModel = OC.Backbone.Model.extend(
		/** @lends OCA.SystemTags.SystemTagModel.prototype */ {
			sync: OC.Backbone.davSync,

			defaults: {
				userVisible: true,
				userAssignable: true,
				canAssign: true,
			},

			davProperties: {
				'id':	OC.Files.Client.PROPERTY_FILEID,
				'name': OC.Files.Client.PROPERTY_DISPLAYNAME,
				'userVisible': OC.Files.Client.PROPERTY_USERVISIBLE,
				'userAssignable': OC.Files.Client.PROPERTY_USERASSIGNABLE,
				// read-only, effective permissions computed by the server,
				'canAssign': OC.Files.Client.PROPERTY_CAN_ASSIGN,
			},

			parse: function(data) {
				return {
					id: data.id,
					name: data.name,
					userVisible: data.userVisible === true || data.userVisible === 'true',
					userAssignable: data.userAssignable === true || data.userAssignable === 'true',
					canAssign: data.canAssign === true || data.canAssign === 'true',
				}
			},
		})

	OC.SystemTags = OC.SystemTags || {}
	OC.SystemTags.SystemTagModel = SystemTagModel
})(OC)
