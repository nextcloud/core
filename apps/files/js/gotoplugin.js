/*
 * Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */


(function (OCA) {

	OCA.Files = OCA.Files || {};

	/**
	 * @namespace OCA.Files.GotoPlugin
	 *
	 */
	OCA.Files.GotoPlugin = {
		name: 'Goto',

		disallowedLists: [
			'files',
			'trashbin'
		],

		attach: function (fileList) {
			if (this.disallowedLists.indexOf(fileList.id) !== -1) {
				return;
			}
			// lists where the "Open" default action is disabled should
			// also have the goto action disabled
			if (fileList._defaultFileActionsDisabled) {
				return
			}
			var fileActions = fileList.fileActions;

			fileActions.registerAction({
				name: 'Goto',
				displayName: t('files', 'View in folder'),
				mime: 'all',
				permissions: OC.PERMISSION_ALL,
				iconClass: 'icon-goto nav-icon-extstoragemounts',
				type: OCA.Files.FileActions.TYPE_DROPDOWN,
				actionHandler: function (fileName, context) {
					var fileModel = context.fileInfoModel;
					OC.Apps.hideAppSidebar($('.detailsView'));
					OCA.Files.App.setActiveView('files', {silent: true});
					OCA.Files.App.fileList.changeDirectory(fileModel.get('path'), true, true).then(function() {
						OCA.Files.App.fileList.scrollTo(fileModel.get('name'));
					});
				},
				render: function (actionSpec, isDefault, context) {
					return fileActions._defaultRenderAction.call(fileActions, actionSpec, isDefault, context)
						.removeClass('permanent');
				}
			});
		}
	};
})(OCA);

OC.Plugins.register('OCA.Files.FileList', OCA.Files.GotoPlugin);

