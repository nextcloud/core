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
	OCA.Versions = OCA.Versions || {}

	/**
	 * @namespace
	 */
	OCA.Versions.Util = {
		/**
		 * Initialize the versions plugin.
		 *
		 * @param {OCA.Files.FileList} fileList file list to be extended
		 */
		attach: function(fileList) {
			if (fileList.id === 'trashbin' || fileList.id === 'files.public') {
				return
			}

			fileList.registerTabView(new OCA.Versions.VersionsTabView('versionsTabView', { order: -10 }))
		},
	}
})()

OC.Plugins.register('OCA.Files.FileList', OCA.Versions.Util)
