/* global OC */

/**
 * Copyright (c) 2016, Christoph Wurst <christoph@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

(function() {
	'use strict';

	/**
	 * Model for storing and saving user settings
	 *
	 * @class UserSettings
	 */
	var UserSettings = OC.Backbone.Model.extend({
		url: OC.generateUrl('/settings/users/{id}/settings', {id: OC.currentUser}),
		isNew: function() {
			return false; // Force PUT on .save()
		},
		parse: function(data) {
			if (_.isUndefined(data)) {
				return null;
			}
			if (_.isUndefined(data.data)) {
				return null;
			}
			data = data.data;

			var ignored = [
				'userId',
				'message'
			];

			_.each(ignored, function(ign) {
				if (!_.isUndefined(data[ign])) {
					delete data[ign];
				}
			});

			return data;
		}
	});

	OC.Settings = OC.Settings || {};

	OC.Settings.UserSettings = UserSettings;
})();