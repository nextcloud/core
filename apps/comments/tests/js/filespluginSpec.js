/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OCA.Comments.FilesPlugin tests', function() {
	var fileList;
	var testFiles;

	beforeEach(function() {
		var $content = $('<div id="content"></div>');
		$('#testArea').append($content);
		// dummy file list
		var $div = $(
			'<div>' +
			'<table id="filestable">' +
			'<thead></thead>' +
			'<tbody id="fileList"></tbody>' +
			'</table>' +
			'</div>');
		$('#content').append($div);

		fileList = new OCA.Files.FileList($div);
		OCA.Comments.FilesPlugin.attach(fileList);

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
			commentsUnread: 3
		}];
	});
	afterEach(function() {
		fileList.destroy();
		fileList = null;
	});

	describe('Comment icon', function() {
		it('does not render icon when no unread comments available', function() {
			testFiles[0].commentsUnread = 0;
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			expect($tr.find('.action-comment').length).toEqual(0);
		});
		it('renders comment icon and extra data', function() {
			var $action, $tr;
			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('One.txt');
			$action = $tr.find('.action-comment');
			expect($action.length).toEqual(1);
			expect($action.hasClass('permanent')).toEqual(true);

			expect($tr.attr('data-comments-unread')).toEqual('3');
		});
		it('clicking icon opens sidebar', function() {
			var sidebarStub = sinon.stub(fileList, 'showDetailsView');
			var $action, $tr;
			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('One.txt');
			$action = $tr.find('.action-comment');
			$action.click();

			expect(sidebarStub.calledOnce).toEqual(true);
			expect(sidebarStub.lastCall.args[0]).toEqual('One.txt');
			expect(sidebarStub.lastCall.args[1]).toEqual('comments');
		});
	});
	describe('elementToFile', function() {
		it('returns comment count', function() {
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var data = fileList.elementToFile($tr);
			expect(data.commentsUnread).toEqual(3);
		});
		it('does not set comment count when not set', function() {
			delete testFiles[0].commentsUnread;
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var data = fileList.elementToFile($tr);
			expect(data.commentsUnread).not.toBeDefined();
		});
		it('does not set comment count when zero', function() {
			testFiles[0].commentsUnread = 0;
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var data = fileList.elementToFile($tr);
			expect(data.commentsUnread).not.toBeDefined();
		});
	});
});
