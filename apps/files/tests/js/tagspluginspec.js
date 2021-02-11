/*
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OCA.Files.TagsPlugin tests', function() {
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
		OCA.Files.TagsPlugin.attach(fileList);

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
			tags: ['tag1', 'tag2']
		}];
	});
	afterEach(function() {
		fileList.destroy();
		fileList = null;
	});

	describe('Favorites icon', function() {
		it('renders favorite icon and extra data', function() {
			var $favoriteMark, $tr;
			fileList.setFiles(testFiles);
			$tr = fileList.$el.find('tbody tr:first');
			$favoriteMark = $tr.find('.favorite-mark');
			expect($favoriteMark.length).toEqual(1);
			expect($favoriteMark.hasClass('permanent')).toEqual(false);

			expect($tr.attr('data-tags').split('|')).toEqual(['tag1', 'tag2']);
			expect($tr.attr('data-favorite')).not.toBeDefined();
		});
		it('renders permanent favorite icon and extra data', function() {
			var $favoriteMark, $tr;
			testFiles[0].tags.push(OC.TAG_FAVORITE);
			fileList.setFiles(testFiles);
			$tr = fileList.$el.find('tbody tr:first');
			$favoriteMark = $tr.find('.favorite-mark');
			expect($favoriteMark.length).toEqual(1);
			expect($favoriteMark.hasClass('permanent')).toEqual(true);

			expect($tr.attr('data-tags').split('|')).toEqual(['tag1', 'tag2', OC.TAG_FAVORITE]);
			expect($tr.attr('data-favorite')).toEqual('true');
		});
	});
	describe('Applying tags', function() {
		it('through FileActionsMenu sends request to server and updates icon', function(done) {
			var request;
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var $favoriteMark = $tr.find('.favorite-mark');
			var $showMenuAction = $tr.find('.action-menu');
			$showMenuAction.click();
			var $favoriteActionInMenu = $tr.find('.fileActionsMenu .action-favorite');
			$favoriteActionInMenu.click();

			expect(fakeServer.requests.length).toEqual(1);
			request = fakeServer.requests[0];
			expect(JSON.parse(request.requestBody)).toEqual({
				tags: ['tag1', 'tag2', OC.TAG_FAVORITE]
			});
			request.respond(200, {'Content-Type': 'application/json'}, JSON.stringify({
				tags: ['tag1', 'tag2', 'tag3', OC.TAG_FAVORITE]
			}));

			setTimeout(function () {
				// re-read the element as it was re-inserted
				$tr = fileList.findFileEl('One.txt');
				$favoriteMark = $tr.find('.favorite-mark');
				$showMenuAction = $tr.find('.action-menu');

				expect($tr.attr('data-favorite')).toEqual('true');
				expect($tr.attr('data-tags').split('|')).toEqual(['tag1', 'tag2', 'tag3', OC.TAG_FAVORITE]);
				expect(fileList.files[0].tags).toEqual(['tag1', 'tag2', 'tag3', OC.TAG_FAVORITE]);
				expect($favoriteMark.find('.icon').hasClass('icon-star')).toEqual(false);
				expect($favoriteMark.find('.icon').hasClass('icon-starred')).toEqual(true);

				// show again the menu and get the new action, as the menu was
				// closed and removed (and with it, the previous action) when that
				// action was clicked
				$showMenuAction.click();
				$favoriteActionInMenu = $tr.find('.fileActionsMenu .action-favorite');
				$favoriteActionInMenu.click();

				setTimeout(function() {
					expect(fakeServer.requests.length).toEqual(2);
					request = fakeServer.requests[1];
					expect(JSON.parse(request.requestBody)).toEqual({
						tags: ['tag1', 'tag2', 'tag3']
					});

					request.respond(200, {'Content-Type': 'application/json'}, JSON.stringify({
						tags: ['tag1', 'tag2', 'tag3']
					}));

					setTimeout(function() {
						// re-read the element as it was re-inserted
						$tr = fileList.findFileEl('One.txt');
						$favoriteMark = $tr.find('.favorite-mark');

						expect($tr.attr('data-favorite')).toBeFalsy();
						expect($tr.attr('data-tags').split('|')).toEqual(['tag1', 'tag2', 'tag3']);
						expect(fileList.files[0].tags).toEqual(['tag1', 'tag2', 'tag3']);
						expect($favoriteMark.find('.icon').hasClass('icon-star')).toEqual(true);
						expect($favoriteMark.find('.icon').hasClass('icon-starred')).toEqual(false);

						done();
					}, 1);
				}, 1);
			}, 1);
		});
	});
	describe('elementToFile', function() {
		it('returns tags', function() {
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var data = fileList.elementToFile($tr);
			expect(data.tags).toEqual(['tag1', 'tag2']);
		});
		it('returns empty array when no tags present', function() {
			delete testFiles[0].tags;
			fileList.setFiles(testFiles);
			var $tr = fileList.findFileEl('One.txt');
			var data = fileList.elementToFile($tr);
			expect(data.tags).toEqual([]);
		});
	});
});
