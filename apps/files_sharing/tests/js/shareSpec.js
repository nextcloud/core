/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OCA.Sharing.Util tests', function() {
	var fileList;
	var testFiles;

	function getImageUrl($el) {
		// might be slightly different cross-browser
		var url = $el.css('background-image');
		var r = url.match(/url\(['"]?([^'")]*)['"]?\)/);
		if (!r) {
			return url;
		}
		return r[1];
	}

	beforeEach(function() {
		var $content = $('<div id="content"></div>');
		$('#testArea').append($content);
		// dummy file list
		var $div = $(
			'<div id="listContainer">' +
			'<table id="filestable" class="list-container view-grid">' +
			'<thead></thead>' +
			'<tbody id="fileList"></tbody>' +
			'</table>' +
			'</div>');
		$('#content').append($div);

		var fileActions = new OCA.Files.FileActions();
		fileList = new OCA.Files.FileList(
			$div, {
				fileActions : fileActions
			}
		);
		OCA.Sharing.Util.attach(fileList);

		testFiles = [{
			id: 1,
			type: 'file',
			name: 'One.txt',
			path: '/subdir',
			mimetype: 'text/plain',
			size: 12,
			permissions: OC.PERMISSION_ALL,
			etag: 'abc',
			shareOwner: 'User One',
			isShareMountPoint: false,
			shareTypes: [OC.Share.SHARE_TYPE_USER]
		}];
	});
	afterEach(function() {
		delete OCA.Sharing.sharesLoaded;
		delete OC.Share.droppedDown;
		fileList.destroy();
		fileList = null;
	});

	describe('Sharing data in table row', function() {
		// TODO: test data-permissions, data-share-owner, etc
	});
	describe('Share action icon', function() {
		it('do not shows share text when not shared', function() {
			var $action, $tr;
			OC.Share.statuses = {};
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'httpd/unix-directory',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				etag: 'abc',
				shareTypes: []
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(true);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(false);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder.svg');
		});
		it('shows simple share text with share icon', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				etag: 'abc',
				shareTypes: [OC.Share.SHARE_TYPE_USER]
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.find('>span').text().trim()).toEqual('Shared');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(true);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(false);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder-shared.svg');
		});
		it('shows simple share text with share icon when shared to a room', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				etag: 'abc',
				shareTypes: [OC.Share.SHARE_TYPE_ROOM]
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.find('>span').text().trim()).toEqual('Shared');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(true);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(false);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder-shared.svg');
		});
		it('shows simple share text with public icon when shared with link', function() {
			var $action, $tr;
			OC.Share.statuses = {1: {link: true, path: '/subdir'}};
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				etag: 'abc',
				shareTypes: [OC.Share.SHARE_TYPE_LINK]
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.find('>span').text().trim()).toEqual('Shared');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(false);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(true);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder-public.svg');
		});
		it('shows owner name when owner is available but no icons', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One.txt',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				shareOwner: 'User One',
				shareOwnerId: 'User One',
				etag: 'abc',
				shareTypes: []
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.find('>span').text().trim()).toEqual('Shared by User One');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(false);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(false);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder-shared.svg');
		});
		it('shows recipients when recipients are available', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One.txt',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_ALL,
				recipientsDisplayName: 'User One, User Two',
				recipientData: {
					0: {
						shareWith: 'User One',
						shareWithDisplayName: 'User One'
					},
					1: {
						shareWith: 'User Two',
						shareWithDisplayName: 'User Two'
					}
				},
				etag: 'abc',
				shareTypes: [OC.Share.SHARE_TYPE_USER]
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			$action = $tr.find('.action-share');
			expect($action.text().trim()).toEqual('Shared with User One Shared with User Two');
			expect($action.find('.icon').hasClass('icon-shared')).toEqual(true);
			expect($action.find('.icon').hasClass('icon-public')).toEqual(false);
			expect(OC.basename(getImageUrl($tr.find('.filename .thumbnail')))).toEqual('folder-shared.svg');
		});
		it('shows share action when shared with user who has no share permission', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_CREATE,
				etag: 'abc',
				shareOwner: 'User One'
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			expect($tr.find('.action-share').length).toEqual(1);
		});
		it('do not show share action when share exists but neither permission nor owner is available', function() {
			var $action, $tr;
			fileList.setFiles([{
				id: 1,
				type: 'dir',
				name: 'One',
				path: '/subdir',
				mimetype: 'text/plain',
				size: 12,
				permissions: OC.PERMISSION_CREATE,
				etag: 'abc'
			}]);
			$tr = fileList.$el.find('tbody tr:first');
			expect($tr.find('.action-share').length).toEqual(0);
		});
	});
	describe('Excluded lists', function() {
		function createListThenAttach(listId) {
			var fileActions = new OCA.Files.FileActions();
			fileList.destroy();
			fileList = new OCA.Files.FileList(
				$('#listContainer'), {
					id: listId,
					fileActions: fileActions
				}
			);
			OCA.Sharing.Util.attach(fileList);
			fileList.setFiles(testFiles);
			return fileList;
		}

		it('does not attach to trashbin or public file lists', function() {
			createListThenAttach('trashbin');
			expect($('.action-share').length).toEqual(0);
			expect($('[data-share-recipient]').length).toEqual(0);
			createListThenAttach('files.public');
			expect($('.action-share').length).toEqual(0);
			expect($('[data-share-recipient]').length).toEqual(0);
		});
	});

	describe('ShareTabView interaction', function() {
		var shareTabSpy;
		var fileInfoModel;
		var configModel;
		var shareModel;

		beforeEach(function() {
			shareTabSpy = sinon.spy(OCA.Sharing, 'ShareTabView');

			var attributes = {
				itemType: 'file',
				itemSource: 123,
				possiblePermissions: 31,
				permissions: 31
			};
			fileInfoModel = new OCA.Files.FileInfoModel(testFiles[0]);
			configModel = new OC.Share.ShareConfigModel({
				enforcePasswordForPublicLink: false,
				isResharingAllowed: true,
				isDefaultExpireDateEnabled: false,
				isDefaultExpireDateEnforced: false,
				defaultExpireDate: 7
			});
			shareModel = new OC.Share.ShareItemModel(attributes, {
				configModel: configModel,
				fileInfoModel: fileInfoModel
			});

			/* jshint camelcase: false */
			shareModel.set({
				reshare: {},
				shares: [{
					id: 100,
					item_source: 1,
					permissions: 31,
					share_type: OC.Share.SHARE_TYPE_USER,
					share_with: 'user1',
					share_with_displayname: 'User One'
				}, {
					id: 102,
					item_source: 1,
					permissions: 31,
					share_type: OC.Share.SHARE_TYPE_REMOTE,
					share_with: 'foo@bar.com/baz',
					share_with_displayname: 'foo@bar.com/baz'

				}]
			}, {parse: true});

			fileList.destroy();
			fileList = new OCA.Files.FileList(
				$('#listContainer'), {
					id: 'files',
					fileActions: new OCA.Files.FileActions()
				}
			);
			OCA.Sharing.Util.attach(fileList);
			fileList.setFiles(testFiles);
		});
		afterEach(function() {
			shareTabSpy.restore();
		});
	});
});
