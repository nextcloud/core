/**
* ownCloud
*
* @author Vincent Petry
* @copyright 2014 Vincent Petry <pvince81@owncloud.com>
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the License, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
*
*/

describe('OCA.Files.FileList tests', function() {
	var FileInfo = OC.Files.FileInfo;
	var testFiles, testRoot, notificationStub, fileList, pageSizeStub;
	var bcResizeStub;
	var filesClient;
	var filesConfig;
	var redirectStub;

	/**
	 * Generate test file data
	 */
	function generateFiles(startIndex, endIndex) {
		var files = [];
		var name;
		for (var i = startIndex; i <= endIndex; i++) {
			name = 'File with index ';
			if (i < 10) {
				// do not rely on localeCompare here
				// and make the sorting predictable
				// cross-browser
				name += '0';
			}
			name += i + '.txt';
			files.push(new FileInfo({
				id: i,
				type: 'file',
				name: name,
				mimetype: 'text/plain',
				size: i * 2,
				etag: 'abc'
			}));
		}
		return files;
	}

	beforeEach(function() {
		filesConfig = new OC.Backbone.Model({
			showhidden: true
		});

		filesClient = new OC.Files.Client({
			host: 'localhost',
			port: 80,
			// FIXME: uncomment after fixing the test OC.getRootPath()
			//root: OC.getRootPath() + '/remote.php/webdav',
			root: '/remote.php/webdav',
			useHTTPS: false
		});
		redirectStub = sinon.stub(OC, 'redirect');
		notificationStub = sinon.stub(OC.Notification, 'show');
		// prevent resize algo to mess up breadcrumb order while
		// testing
		bcResizeStub = sinon.stub(OCA.Files.BreadCrumb.prototype, '_resize');

		// init parameters and test table elements
		$('#testArea').append(
			'<div id="app-content-files">' +
			// init horrible parameters
			'<input type="hidden" id="dir" value="/subdir"/>' +
			'<input type="hidden" id="permissions" value="31"/>' +
			// dummy controls
			'<div id="controls">' +
			'   <div class="actions creatable"></div>' +
			'   <div class="notCreatable"></div>' +
			'</div>' +
			// uploader
			'<input type="file" id="file_upload_start" name="files[]" multiple="multiple">' +
			// dummy table
			// TODO: at some point this will be rendered by the fileList class itself!
			'<table id="filestable" class="list-container view-grid">' +
			'<thead><tr>' +
			'<th id="headerName" class="hidden column-name">' +
			'<input type="checkbox" id="select_all_files" class="select-all checkbox">' +
			'<a class="name columntitle" data-sort="name"><span>Name</span><span class="sort-indicator"></span></a>' +
			'<span id="selectedActionsList" class="selectedActions hidden">' +
			'<a href class="actions-selected"><span class="icon icon-more"></span><span>Actions</span></a>' +
			'</th>' +
			'<th class="hidden column-size"><a class="columntitle" data-sort="size"><span class="sort-indicator"></span></a></th>' +
			'<th class="hidden column-mtime"><a class="columntitle" data-sort="mtime"><span class="sort-indicator"></span></a></th>' +
			'</tr></thead>' +
			'<tbody id="fileList"></tbody>' +
			'<tfoot></tfoot>' +
			'</table>' +
			// TODO: move to handlebars template
			'<div id="emptycontent"><h2>Empty content message</h2><p class="uploadmessage">Upload message</p></div>' +
			'<div class="nofilterresults hidden"></div>' +
			'</div>'
		);

		testRoot = new FileInfo({
			// root entry
			id: 99,
			type: 'dir',
			name: '/subdir',
			mimetype: 'httpd/unix-directory',
			size: 1200000,
			etag: 'a0b0c0d0',
			permissions: OC.PERMISSION_ALL
		});
		testFiles = [new FileInfo({
			id: 1,
			type: 'file',
			name: 'One.txt',
			mimetype: 'text/plain',
			mtime: 123456789,
			size: 12,
			etag: 'abc',
			permissions: OC.PERMISSION_ALL
		}), new FileInfo({
			id: 2,
			type: 'file',
			name: 'Two.jpg',
			mimetype: 'image/jpeg',
			mtime: 234567890,
			size: 12049,
			etag: 'def',
			permissions: OC.PERMISSION_ALL
		}), new FileInfo({
			id: 3,
			type: 'file',
			name: 'Three.pdf',
			mimetype: 'application/pdf',
			mtime: 234560000,
			size: 58009,
			etag: '123',
			permissions: OC.PERMISSION_ALL
		}), new FileInfo({
			id: 4,
			type: 'dir',
			name: 'somedir',
			mimetype: 'httpd/unix-directory',
			mtime: 134560000,
			size: 250,
			etag: '456',
			permissions: OC.PERMISSION_ALL
		})];
		pageSizeStub = sinon.stub(OCA.Files.FileList.prototype, 'pageSize').returns(20);
		fileList = new OCA.Files.FileList($('#app-content-files'), {
			filesClient: filesClient,
			config: filesConfig,
			enableUpload: true,
			multiSelectMenu: [{
			    name: 'copyMove',
			    displayName:  t('files', 'Move or copy'),
			    iconClass: 'icon-external',
			},
			{
			    name: 'download',
			    displayName:  t('files', 'Download'),
			    iconClass: 'icon-download',
			},
			{
			    name: 'delete',
			    displayName: t('files', 'Delete'),
			    iconClass: 'icon-delete',
			}]
		});
	});
	afterEach(function() {
		testFiles = undefined;
		if (fileList) {
			fileList.destroy();
		}
		fileList = undefined;

		notificationStub.restore();
		bcResizeStub.restore();
		pageSizeStub.restore();
		redirectStub.restore();
	});
	describe('Getters', function() {
		it('Returns the current directory', function() {
			$('#dir').val('/one/two/three');
			expect(fileList.getCurrentDirectory()).toEqual('/one/two/three');
		});
		it('Returns the directory permissions as int', function() {
			$('#permissions').val('23');
			expect(fileList.getDirectoryPermissions()).toEqual(23);
		});
	});
	describe('Adding files', function() {
		var clock, now;
		beforeEach(function() {
			// to prevent date comparison issues
			clock = sinon.useFakeTimers();
			now = new Date();
		});
		afterEach(function() {
			clock.restore();
		});
		it('generates file element with correct attributes when calling add() with file data', function() {
			var fileData = new FileInfo({
				id: 18,
				name: 'testName.txt',
				mimetype: 'text/plain',
				size: 1234,
				etag: 'a01234c',
				mtime: 123456
			});
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr[0].tagName.toLowerCase()).toEqual('tr');
			expect($tr.attr('data-id')).toEqual('18');
			expect($tr.attr('data-type')).toEqual('file');
			expect($tr.attr('data-file')).toEqual('testName.txt');
			expect($tr.attr('data-size')).toEqual('1234');
			expect($tr.attr('data-etag')).toEqual('a01234c');
			expect($tr.attr('data-permissions')).toEqual('31');
			expect($tr.attr('data-mime')).toEqual('text/plain');
			expect($tr.attr('data-mtime')).toEqual('123456');
			expect($tr.attr('data-e2eencrypted')).toEqual('false');
			expect($tr.find('a.name').attr('href'))
				.toEqual(OC.getRootPath() + '/remote.php/webdav/subdir/testName.txt');
			expect($tr.find('.nametext').text().trim()).toEqual('testName.txt');

			expect($tr.find('.filesize').text()).toEqual('1 KB');
			expect($tr.find('.date').text()).not.toEqual('?');
			expect(fileList.findFileEl('testName.txt')[0]).toEqual($tr[0]);
		});
		it('generates dir element with correct attributes when calling add() with dir data', function() {
			var fileData = new FileInfo({
				id: 19,
				name: 'testFolder',
				mimetype: 'httpd/unix-directory',
				size: 1234,
				etag: 'a01234c',
				mtime: 123456
			});
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr[0].tagName.toLowerCase()).toEqual('tr');
			expect($tr.attr('data-id')).toEqual('19');
			expect($tr.attr('data-type')).toEqual('dir');
			expect($tr.attr('data-file')).toEqual('testFolder');
			expect($tr.attr('data-size')).toEqual('1234');
			expect($tr.attr('data-etag')).toEqual('a01234c');
			expect($tr.attr('data-permissions')).toEqual('31');
			expect($tr.attr('data-mime')).toEqual('httpd/unix-directory');
			expect($tr.attr('data-mtime')).toEqual('123456');
			expect($tr.attr('data-e2eencrypted')).toEqual('false');

			expect($tr.find('.filesize').text()).toEqual('1 KB');
			expect($tr.find('.date').text()).not.toEqual('?');

			expect(fileList.findFileEl('testFolder')[0]).toEqual($tr[0]);
		});
		it('generates file element with default attributes when calling add() with minimal data', function() {
			var fileData = {
				type: 'file',
				name: 'testFile.txt'
			};

			clock.tick(123456);
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr[0].tagName.toLowerCase()).toEqual('tr');
			expect($tr.attr('data-id')).toBeUndefined();
			expect($tr.attr('data-type')).toEqual('file');
			expect($tr.attr('data-file')).toEqual('testFile.txt');
			expect($tr.attr('data-size')).toBeUndefined();
			expect($tr.attr('data-etag')).toBeUndefined();
			expect($tr.attr('data-permissions')).toEqual('31');
			expect($tr.attr('data-mime')).toBeUndefined();
			expect($tr.attr('data-mtime')).toEqual('123456');
			expect($tr.attr('data-e2eencrypted')).toEqual('false');

			expect($tr.find('.filesize').text()).toEqual('Pending');
			expect($tr.find('.date').text()).not.toEqual('?');
		});
		it('generates dir element with default attributes when calling add() with minimal data', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder'
			};
			clock.tick(123456);
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr[0].tagName.toLowerCase()).toEqual('tr');
			expect($tr.attr('data-id')).toBeUndefined();
			expect($tr.attr('data-type')).toEqual('dir');
			expect($tr.attr('data-file')).toEqual('testFolder');
			expect($tr.attr('data-size')).toBeUndefined();
			expect($tr.attr('data-etag')).toBeUndefined();
			expect($tr.attr('data-permissions')).toEqual('31');
			expect($tr.attr('data-mime')).toEqual('httpd/unix-directory');
			expect($tr.attr('data-mtime')).toEqual('123456');
			expect($tr.attr('data-e2eencrypted')).toEqual('false');

			expect($tr.find('.filesize').text()).toEqual('Pending');
			expect($tr.find('.date').text()).not.toEqual('?');
		});
		it('generates dir element with true e2eencrypted attribute when calling add() with minimal data including isEncrypted', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder',
				isEncrypted: true
			};
			var $tr = fileList.add(fileData);
			expect($tr.attr('data-e2eencrypted')).toEqual('true');
		});
		it('generates file element with no permissions when permissions are explicitly none', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder',
				permissions: OC.PERMISSION_NONE
			};
			var $tr = fileList.add(fileData);
			expect($tr.attr('data-permissions')).toEqual('0');
		});
		it('generates file element with zero size when size is explicitly zero', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder',
				size: '0'
			};
			var $tr = fileList.add(fileData);
			expect($tr.find('.filesize').text()).toEqual('0 KB');
		});
		it('generates file element with unknown date when mtime invalid', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder',
				mtime: -1
			};
			var $tr = fileList.add(fileData);
			expect($tr.find('.date .modified').text()).toEqual('?');
		});
		it('adds new file to the end of the list', function() {
			var $tr;
			var fileData = {
				type: 'file',
				name: 'ZZZ.txt'
			};
			fileList.setFiles(testFiles);
			$tr = fileList.add(fileData);
			expect($tr.index()).toEqual(4);
		});
		it('inserts files in a sorted manner when insert option is enabled', function() {
			for (var i = 0; i < testFiles.length; i++) {
				fileList.add(testFiles[i]);
			}
			expect(fileList.files[0].name).toEqual('somedir');
			expect(fileList.files[1].name).toEqual('One.txt');
			expect(fileList.files[2].name).toEqual('Three.pdf');
			expect(fileList.files[3].name).toEqual('Two.jpg');
		});
		it('inserts new file at correct position', function() {
			var $tr;
			var fileData = {
				type: 'file',
				name: 'P comes after O.txt'
			};
			for (var i = 0; i < testFiles.length; i++) {
				fileList.add(testFiles[i]);
			}
			$tr = fileList.add(fileData);
			// after "One.txt"
			expect($tr.index()).toEqual(2);
			expect(fileList.files[2]).toEqual(fileData);
		});
		it('inserts new folder at correct position in insert mode', function() {
			var $tr;
			var fileData = {
				type: 'dir',
				name: 'somedir2 comes after somedir'
			};
			for (var i = 0; i < testFiles.length; i++) {
				fileList.add(testFiles[i]);
			}
			$tr = fileList.add(fileData);
			expect($tr.index()).toEqual(1);
			expect(fileList.files[1]).toEqual(fileData);
		});
		it('inserts new file at the end correctly', function() {
			var $tr;
			var fileData = {
				type: 'file',
				name: 'zzz.txt'
			};
			for (var i = 0; i < testFiles.length; i++) {
				fileList.add(testFiles[i]);
			}
			$tr = fileList.add(fileData);
			expect($tr.index()).toEqual(4);
			expect(fileList.files[4]).toEqual(fileData);
		});
		it('removes empty content message and shows summary when adding first file', function() {
			var $summary;
			var fileData = {
				type: 'file',
				name: 'first file.txt',
				size: 12
			};
			fileList.setFiles([]);
			expect(fileList.isEmpty).toEqual(true);
			fileList.add(fileData);
			$summary = $('#filestable .summary');
			expect($summary.hasClass('hidden')).toEqual(false);
			// yes, ugly...
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.connector').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').hasClass('hidden')).toEqual(false);
			expect($summary.find('.filesize').text()).toEqual('12 B');
			expect($('#filestable thead th').hasClass('hidden')).toEqual(false);
			expect($('#emptycontent').hasClass('hidden')).toEqual(true);
			expect(fileList.isEmpty).toEqual(false);
		});
		it('correctly adds the extension markup and show hidden files completely in gray', function() {
			var $tr;
			var testDataAndExpectedResult = [
				{file: {type: 'file', name: 'ZZZ.txt'}, extension: '.txt'},
				{file: {type: 'file', name: 'ZZZ.tar.gz'}, extension: '.gz'},
				{file: {type: 'file', name: 'test.with.some.dots.in.it.txt'}, extension: '.txt'},
				// we render hidden files completely in gray
				{file: {type: 'file', name: '.test.with.some.dots.in.it.txt'}, extension: '.test.with.some.dots.in.it.txt'},
				{file: {type: 'file', name: '.hidden'}, extension: '.hidden'},
			];
			fileList.setFiles(testFiles);

			for(var i = 0; i < testDataAndExpectedResult.length; i++) {
				var testSet = testDataAndExpectedResult[i];
				var fileData = testSet['file'];
				$tr = fileList.add(fileData);
				expect($tr.find('.nametext .extension').text()).toEqual(testSet['extension']);
			}
		});
	});
	describe('Hidden files', function() {
		it('sets the class hidden-file for hidden files', function() {
			var fileData = {
				type: 'dir',
				name: '.testFolder'
			};
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr.hasClass('hidden-file')).toEqual(true);
		});
		it('does not set the class hidden-file for visible files', function() {
			var fileData = {
				type: 'dir',
				name: 'testFolder'
			};
			var $tr = fileList.add(fileData);

			expect($tr).toBeDefined();
			expect($tr.hasClass('hidden-file')).toEqual(false);
		});
		it('toggles the list\'s class when toggling hidden files', function() {
			expect(fileList.$el.hasClass('hide-hidden-files')).toEqual(false);
			filesConfig.set('showhidden', false);
			expect(fileList.$el.hasClass('hide-hidden-files')).toEqual(true);
			filesConfig.set('showhidden', true);
			expect(fileList.$el.hasClass('hide-hidden-files')).toEqual(false);
		});
	});
	describe('Removing files from the list', function() {
		it('Removes file from list when calling remove() and updates summary', function() {
			var $summary;
			var $removedEl;
			fileList.setFiles(testFiles);
			$removedEl = fileList.remove('One.txt');
			expect($removedEl).toBeDefined();
			expect($removedEl.attr('data-file')).toEqual('One.txt');
			expect($('#fileList tr').length).toEqual(3);
			expect(fileList.files.length).toEqual(3);
			expect(fileList.findFileEl('One.txt').length).toEqual(0);

			$summary = $('#filestable .summary');
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('2 files');
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(false);
			expect($summary.find('.fileinfo').hasClass('hidden')).toEqual(false);
			expect($summary.find('.filesize').text()).toEqual('69 KB');
			expect(fileList.isEmpty).toEqual(false);
		});
		it('Shows empty content when removing last file', function() {
			var $summary;
			fileList.setFiles([testFiles[0]]);
			fileList.remove('One.txt');
			expect($('#fileList tr').length).toEqual(0);
			expect(fileList.files.length).toEqual(0);
			expect(fileList.findFileEl('One.txt').length).toEqual(0);

			$summary = $('#filestable .summary');
			expect($summary.hasClass('hidden')).toEqual(true);
			expect($('#filestable thead th').hasClass('hidden')).toEqual(true);
			expect($('#emptycontent').hasClass('hidden')).toEqual(false);
			expect(fileList.isEmpty).toEqual(true);
		});
	});
	describe('Deleting files', function() {
		var deferredDelete;
		var deleteStub;

		beforeEach(function() {
			deferredDelete = $.Deferred();
			deleteStub = sinon.stub(filesClient, 'remove');
		});
		afterEach(function() {
			deleteStub.restore();
		});

		function doDelete() {
			// note: normally called from FileActions
			return fileList.do_delete(['One.txt', 'Two.jpg']).then(function(){

				expect(deleteStub.calledTwice).toEqual(true);
				expect(deleteStub.getCall(0).args[0]).toEqual('/subdir/One.txt');
				expect(deleteStub.getCall(1).args[0]).toEqual('/subdir/Two.jpg');
			});
		}
		it('calls delete.php, removes the deleted entries and updates summary', function(done) {
			var $summary;
			fileList.setFiles(testFiles);
			deferredDelete1 = $.Deferred();
			deferredDelete2 = $.Deferred();
			deleteStub.onCall(0).callsFake(function(src){
				expect(deleteStub.calledOnce).toEqual(true);
				expect(src).toEqual('/subdir/One.txt');
				return deferredDelete1.promise();
			});
			deleteStub.onCall(1).callsFake(function(src){
				expect(deleteStub.calledTwice).toEqual(true);
				expect(src).toEqual('/subdir/Two.jpg');
				return deferredDelete2.promise();
			});

			var promise = fileList.do_delete(['One.txt', 'Two.jpg']);
			deferredDelete1.resolve(200);
			deferredDelete2.resolve(200);
			return promise.then(function(){

				expect(fileList.findFileEl('One.txt').length).toEqual(0);
				expect(fileList.findFileEl('Two.jpg').length).toEqual(0);
				expect(fileList.findFileEl('Three.pdf').length).toEqual(1);
				expect(fileList.$fileList.find('tr').length).toEqual(2);

				$summary = $('#filestable .summary');
				expect($summary.hasClass('hidden')).toEqual(false);
				expect($summary.find('.dirinfo').text()).toEqual('1 folder');
				expect($summary.find('.fileinfo').text()).toEqual('1 file');
				expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(false);
				expect($summary.find('.fileinfo').hasClass('hidden')).toEqual(false);
				expect($summary.find('.filesize').text()).toEqual('57 KB');
				expect(fileList.isEmpty).toEqual(false);
				expect($('#filestable thead th').hasClass('hidden')).toEqual(false);
				expect($('#emptycontent').hasClass('hidden')).toEqual(true);

				expect(notificationStub.notCalled).toEqual(true);
				done();
			});
		});
		it('shows busy state on files to be deleted', function(done) {
			fileList.setFiles(testFiles);
			deferredDelete1 = $.Deferred();
			deferredDelete2 = $.Deferred();
			deleteStub.onCall(0).callsFake(function(src){
				expect(fileList.findFileEl('One.txt').hasClass('busy')).toEqual(true);
				expect(fileList.findFileEl('Three.pdf').hasClass('busy')).toEqual(false);

				expect(deleteStub.calledOnce).toEqual(true);
				expect(src).toEqual('/subdir/One.txt');
				return deferredDelete1.promise();
			});
			deleteStub.onCall(1).callsFake(function(src){
				expect(fileList.findFileEl('Two.jpg').hasClass('busy')).toEqual(true);
				expect(fileList.findFileEl('Three.pdf').hasClass('busy')).toEqual(false);

				expect(deleteStub.calledTwice).toEqual(true);
				expect(src).toEqual('/subdir/Two.jpg');
				return deferredDelete2.promise();
			});
			var promise = fileList.do_delete(['One.txt', 'Two.jpg']).then(function(){
				expect(deleteStub.calledTwice).toEqual(true);
			});
			deferredDelete1.resolve(200);
			deferredDelete2.resolve(200);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').hasClass('busy')).toEqual(false);
				expect(fileList.findFileEl('Two.jpg').hasClass('busy')).toEqual(false);
				done();
			});
		});
		it('shows busy state on all files when deleting all', function(done) {
			fileList.setFiles(testFiles);
			var deferredDeleteArray = [];
			var count = 0;
			for (var i = 0; i < 4; i++) {
				(function(i, fn){
					deferredDeleteArray.push($.Deferred());
					deleteStub.onCall(i).callsFake(function(src){
						expect(fileList.findFileEl(fn).hasClass('busy')).toEqual(true);
						count++;
						return deferredDeleteArray[i].promise();
					});
				})(i, testFiles[i].name);
			}
			var promise = fileList.do_delete();
			for (var i = 0; i < 4; i++) {
				deferredDeleteArray[i].resolve(200);
			}
			return promise.then(function(){
				expect(count).toEqual(4);
				done();
			});
		});
		it('updates summary when deleting last file', function(done) {
			var $summary;
			fileList.setFiles([testFiles[0], testFiles[1]]);
			deleteStub.returns(deferredDelete.promise());
			deferredDelete.resolve(200);

			return doDelete().then(function(){
				expect(fileList.$fileList.find('tr').length).toEqual(0);
				$summary = $('#filestable .summary');
				expect($summary.hasClass('hidden')).toEqual(true);
				expect(fileList.isEmpty).toEqual(true);
				expect(fileList.files.length).toEqual(0);
				expect($('#filestable thead th').hasClass('hidden')).toEqual(true);
				expect($('#emptycontent').hasClass('hidden')).toEqual(false);
				done();
			});
		});
		it('bring back deleted item when delete call failed', function(done) {
			fileList.setFiles(testFiles);
			deleteStub.returns(deferredDelete.promise());
			var promise = doDelete();
			deferredDelete.reject(403);
			return promise.then(function(){
				// files are still in the list
				expect(fileList.findFileEl('One.txt').length).toEqual(1);
				expect(fileList.findFileEl('Two.jpg').length).toEqual(1);
				expect(fileList.$fileList.find('tr').length).toEqual(4);

				expect(notificationStub.calledTwice).toEqual(true);
				done();
			});
		});
		it('remove file from list if delete call returned 404 not found', function(done) {
			fileList.setFiles(testFiles);
			deleteStub.returns(deferredDelete.promise());
			var promise = doDelete();
			deferredDelete.reject(404);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(0);
				expect(fileList.findFileEl('Two.jpg').length).toEqual(0);
				expect(fileList.$fileList.find('tr').length).toEqual(2);

				expect(notificationStub.notCalled).toEqual(true);
				done();
			});
		});
	});
	describe('Renaming files', function() {
		var deferredRename;
		var renameStub;

		beforeEach(function() {
			deferredRename = $.Deferred();
			renameStub = sinon.stub(filesClient, 'move').returns(deferredRename.promise());

			for (var i = 0; i < testFiles.length; i++) {
				var file = testFiles[i];
				file.path = '/some/subdir';
				fileList.add(file, {silent: true});
			}
		});
		afterEach(function() {
			renameStub.restore();
		});

		function doCancelRename() {
			var $input;

			// trigger rename prompt
			fileList.rename('One.txt');
			$input = fileList.$fileList.find('input.filename');
			// keep same name
			$input.val('One.txt');
			// trigger submit because triggering blur doesn't work in all browsers
			$input.closest('form').trigger('submit');

			expect(renameStub.notCalled).toEqual(true);
		}
		function doRename() {
			var $input;

			// trigger rename prompt
			fileList.rename('One.txt');
			$input = fileList.$fileList.find('input.filename');
			$input.val('Tu_after_three.txt');
			// trigger submit because triggering blur doesn't work in all browsers
			$input.closest('form').trigger('submit');

			expect(renameStub.calledOnce).toEqual(true);
			expect(renameStub.getCall(0).args[0]).toEqual('/some/subdir/One.txt');
			expect(renameStub.getCall(0).args[1]).toEqual('/some/subdir/Tu_after_three.txt');
		}
		it('Inserts renamed file entry at correct position if rename ajax call suceeded', function() {
			doRename();

			deferredRename.resolve(201);

			// element stays renamed
			expect(fileList.findFileEl('One.txt').length).toEqual(0);
			expect(fileList.findFileEl('Tu_after_three.txt').length).toEqual(1);
			expect(fileList.findFileEl('Tu_after_three.txt').index()).toEqual(2); // after Two.jpg

			expect(notificationStub.notCalled).toEqual(true);
		});
		it('Reverts file entry if rename ajax call failed', function() {
			doRename();

			deferredRename.reject(403);

			// element was reverted
			expect(fileList.findFileEl('One.txt').length).toEqual(1);
			expect(fileList.findFileEl('One.txt').index()).toEqual(1); // after somedir
			expect(fileList.findFileEl('Tu_after_three.txt').length).toEqual(0);

			expect(notificationStub.calledOnce).toEqual(true);
		});
		it('Correctly updates file link after rename', function() {
			var $tr;
			doRename();

			deferredRename.resolve(201);

			$tr = fileList.findFileEl('Tu_after_three.txt');
			expect($tr.find('a.name').attr('href'))
				.toEqual(OC.getRootPath() + '/remote.php/webdav/some/subdir/Tu_after_three.txt');
		});
		it('Triggers "fileActionsReady" event after rename', function() {
			var handler = sinon.stub();
			fileList.$fileList.on('fileActionsReady', handler);
			doRename();
			expect(handler.notCalled).toEqual(true);

			deferredRename.resolve(201);

			expect(handler.calledOnce).toEqual(true);
			expect(fileList.$fileList.find('.test').length).toEqual(0);
		});
		it('Leaves the summary alone when reinserting renamed element', function() {
			var $summary = $('#filestable .summary');
			doRename();

			deferredRename.resolve(201);

			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('3 files');
		});
		it('Leaves the summary alone when cancel renaming', function() {
			var $summary = $('#filestable .summary');
			doCancelRename();
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('3 files');
		});
		it('Shows busy state while rename in progress', function() {
			var $tr;
			doRename();

			// element is renamed before the request finishes
			$tr = fileList.findFileEl('Tu_after_three.txt');
			expect($tr.length).toEqual(1);
			expect(fileList.findFileEl('One.txt').length).toEqual(0);
			// file actions are hidden
			expect($tr.hasClass('busy')).toEqual(true);

			// input and form are gone
			expect(fileList.$fileList.find('input.filename').length).toEqual(0);
			expect(fileList.$fileList.find('form').length).toEqual(0);
		});
		it('Validates the file name', function() {
			var $input, $tr;

			$tr = fileList.findFileEl('One.txt');
			expect($tr.find('a.name').css('display')).not.toEqual('none');

			// trigger rename prompt
			fileList.rename('One.txt');

			expect($tr.find('a.name .thumbnail-wrapper').css('display')).not.toEqual('none');
			expect($tr.find('a.name .nametext').css('display')).toEqual('none');

			$input = fileList.$fileList.find('input.filename');
			$input.val('Two.jpg');

			// simulate key to trigger validation
			$input.trigger(new $.Event('keyup', {keyCode: 97}));

			// input is still there with error
			expect(fileList.$fileList.find('input.filename').length).toEqual(1);
			expect(fileList.$fileList.find('input.filename').hasClass('error')).toEqual(true);

			// trigger submit does not send server request
			$input.closest('form').trigger('submit');
			expect(renameStub.notCalled).toEqual(true);

			// simulate escape key
			$input.trigger(new $.Event('keyup', {keyCode: 27}));

			// element is added back with the correct name
			$tr = fileList.findFileEl('One.txt');
			expect($tr.length).toEqual(1);
			expect($tr.find('a .nametext').text().trim()).toEqual('One.txt');
			expect($tr.find('a.name').css('display')).not.toEqual('none');

			$tr = fileList.findFileEl('Two.jpg');
			expect($tr.length).toEqual(1);
			expect($tr.find('a .nametext').text().trim()).toEqual('Two.jpg');
			expect($tr.find('a.name').css('display')).not.toEqual('none');

			// input and form are gone
			expect(fileList.$fileList.find('input.filename').length).toEqual(0);
			expect(fileList.$fileList.find('form').length).toEqual(0);
		});
		it('Restores thumbnail when rename was cancelled', function() {
			doRename();

			expect(fileList.findFileEl('Tu_after_three.txt').find('.thumbnail').parent().attr('class'))
				.toContain('icon-loading-small');

			deferredRename.reject(409);

			expect(fileList.findFileEl('One.txt').length).toEqual(1);
			expect(OC.TestUtil.getImageUrl(fileList.findFileEl('One.txt').find('.thumbnail')))
				.toEqual(OC.imagePath('core', 'filetypes/text.svg'));
		});
	});
	describe('Moving files', function() {
		var deferredMove;
		var moveStub;

		beforeEach(function() {
			deferredMove = $.Deferred();
			moveStub = sinon.stub(filesClient, 'move');

			fileList.setFiles(testFiles);
		});
		afterEach(function() {
			moveStub.restore();
		});

		it('Moves single file to target folder', function(done) {
			var promise = fileList.move('One.txt', '/somedir');

			moveStub.callsFake(function(src, dst){
				expect(moveStub.calledOnce).toEqual(true);
				expect(src).toEqual('/subdir/One.txt');
				expect(dst).toEqual('/somedir/One.txt');
				return deferredMove.promise();
			});

			deferredMove.resolve(201);

			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(0);

				// folder size has increased
				expect(fileList.findFileEl('somedir').data('size')).toEqual(262);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('262 B');

				expect(notificationStub.notCalled).toEqual(true);
				done();
			});
		});
		it('Moves list of files to target folder', function(done) {
			var deferredMove1 = $.Deferred();
			var deferredMove2 = $.Deferred();
			moveStub.onCall(0).callsFake(function(src, dst){
				expect(moveStub.calledOnce).toEqual(true);
				expect(src).toEqual('/subdir/One.txt');
				expect(dst).toEqual('/somedir/One.txt');
				return deferredMove1.promise();
			});
			moveStub.onCall(1).callsFake(function(src, dst){
				expect(fileList.findFileEl('One.txt').length).toEqual(0);

				// folder size has increased during move
				expect(fileList.findFileEl('somedir').data('size')).toEqual(262);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('262 B');

				expect(src).toEqual('/subdir/Two.jpg');
				expect(dst).toEqual('/somedir/Two.jpg');
				return deferredMove2.promise();
			});

			var promise = fileList.move(['One.txt', 'Two.jpg'], '/somedir');
			deferredMove1.resolve(201);
			deferredMove2.resolve(201);
			return promise.then(function(){
				expect(moveStub.calledTwice).toEqual(true);

				expect(fileList.findFileEl('Two.jpg').length).toEqual(0);

				// folder size has increased
				expect(fileList.findFileEl('somedir').data('size')).toEqual(12311);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('12 KB');

				expect(notificationStub.notCalled).toEqual(true);
				done();
			});
		});
		it('Shows notification if a file could not be moved', function(done) {
			moveStub.callsFake(function(){
				expect(moveStub.calledOnce).toEqual(true);
				return deferredMove.promise();
			});
			var promise = fileList.move('One.txt', '/somedir');
			deferredMove.reject(409);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(1);
				expect(notificationStub.calledOnce).toEqual(true);
				expect(notificationStub.getCall(0).args[0]).toEqual('Could not move "One.txt"');
				done();
			});
		});
		it('Restores thumbnail if a file could not be moved', function(done) {
			moveStub.callsFake(function(){
				expect(fileList.findFileEl('One.txt').find('.thumbnail').parent().attr('class'))
					.toContain('icon-loading-small');
				expect(moveStub.calledOnce).toEqual(true);
				return deferredMove.promise();
			});
			var promise = fileList.move('One.txt', '/somedir');
			deferredMove.reject(409);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(1);
				expect(notificationStub.calledOnce).toEqual(true);
				expect(notificationStub.getCall(0).args[0]).toEqual('Could not move "One.txt"');
				expect(OC.TestUtil.getImageUrl(fileList.findFileEl('One.txt').find('.thumbnail')))
					.toEqual(OC.imagePath('core', 'filetypes/text.svg'));
				done();
			});
		});
	});

	describe('Copying files', function() {
		var deferredCopy;
		var copyStub;

		beforeEach(function() {
			deferredCopy = $.Deferred();
			copyStub = sinon.stub(filesClient, 'copy');

			fileList.setFiles(testFiles);
		});
		afterEach(function() {
			copyStub.restore();
		});

		it('Copies single file to target folder', function(done) {
			copyStub.callsFake(function(){
				expect(copyStub.calledOnce).toEqual(true);
				expect(copyStub.getCall(0).args[0]).toEqual('/subdir/One.txt');
				expect(copyStub.getCall(0).args[1]).toEqual('/somedir/One.txt');

				return deferredCopy.promise();
			});

			var promise = fileList.copy('One.txt', '/somedir');
			deferredCopy.resolve(201);
			return promise.then(function(){
				// File is still here
				expect(fileList.findFileEl('One.txt').length).toEqual(1);

				// folder size has increased
				expect(fileList.findFileEl('somedir').data('size')).toEqual(262);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('262 B');

				// Copying sents a notification to tell that we've successfully copied file
				expect(notificationStub.notCalled).toEqual(false);
				done();
			});
		});
		it('Copies list of files to target folder', function(done) {
			var deferredCopy1 = $.Deferred();
			var deferredCopy2 = $.Deferred();
			copyStub.onCall(0).callsFake(function(src, dst){
				expect(src).toEqual('/subdir/One.txt');
				expect(dst).toEqual('/somedir/One.txt');
				return deferredCopy1.promise();
			});
			copyStub.onCall(1).callsFake(function(src, dst){
				// folder size has increased during copy
				expect(fileList.findFileEl('somedir').data('size')).toEqual(262);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('262 B');

				expect(src).toEqual('/subdir/Two.jpg');
				expect(dst).toEqual('/somedir/Two.jpg');
				return deferredCopy2.promise();
			});

			var promise = fileList.copy(['One.txt', 'Two.jpg'], '/somedir');
			deferredCopy1.resolve(201);
			deferredCopy2.resolve(201);

			return promise.then(function(){
				expect(copyStub.calledTwice).toEqual(true);
				expect(fileList.findFileEl('Two.jpg').length).toEqual(1);
				expect(fileList.findFileEl('One.txt').length).toEqual(1);

				// folder size has increased
				expect(fileList.findFileEl('somedir').data('size')).toEqual(12311);
				expect(fileList.findFileEl('somedir').find('.filesize').text()).toEqual('12 KB');

				expect(notificationStub.notCalled).toEqual(false);
				done();
			});
		});
		it('Shows notification if a file could not be copied', function(done) {
			copyStub.callsFake(function(){
				expect(copyStub.calledOnce).toEqual(true);
				return deferredCopy.promise();
			});

			var promise = fileList.copy('One.txt', '/somedir');
			deferredCopy.reject(409);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(1);
				expect(notificationStub.calledOnce).toEqual(true);
				expect(notificationStub.getCall(0).args[0]).toEqual('Could not copy "One.txt"');
				done();
			});
		});
		it('Restores thumbnail if a file could not be copied', function(done) {
			copyStub.callsFake(function(){
				expect(fileList.findFileEl('One.txt').find('.thumbnail').parent().attr('class'))
					.toContain('icon-loading-small');
				expect(copyStub.calledOnce).toEqual(true);
				return deferredCopy.promise();
			});

			var promise = fileList.copy('One.txt', '/somedir');
			deferredCopy.reject(409);
			return promise.then(function(){
				expect(fileList.findFileEl('One.txt').length).toEqual(1);

				expect(notificationStub.calledOnce).toEqual(true);
				expect(notificationStub.getCall(0).args[0]).toEqual('Could not copy "One.txt"');

				expect(OC.TestUtil.getImageUrl(fileList.findFileEl('One.txt').find('.thumbnail')))
					.toEqual(OC.imagePath('core', 'filetypes/text.svg'));
				done();
			});
		});
	});

	describe('Update file', function() {
		it('does not change summary', function() {
			var $summary = $('#filestable .summary');
			var fileData = new FileInfo({
				type: 'file',
				name: 'test file',
			});
			var $tr = fileList.add(fileData);

			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');

			var model = fileList.getModelForFile('test file');
			model.set({size: '100'});

			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
		});
	})
	describe('List rendering', function() {
		it('renders a list of files using add()', function() {
			expect(fileList.files.length).toEqual(0);
			expect(fileList.files).toEqual([]);
			fileList.setFiles(testFiles);
			expect($('#fileList tr').length).toEqual(4);
			expect(fileList.files.length).toEqual(4);
			expect(fileList.files).toEqual(testFiles);
		});
		it('updates summary using the file sizes', function() {
			var $summary;
			fileList.setFiles(testFiles);
			$summary = $('#filestable .summary');
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('3 files');
			expect($summary.find('.filesize').text()).toEqual('69 KB');
		});
		it('shows headers, summary and hide empty content message after setting files', function(){
			fileList.setFiles(testFiles);
			expect($('#filestable thead th').hasClass('hidden')).toEqual(false);
			expect($('#emptycontent').hasClass('hidden')).toEqual(true);
			expect(fileList.$el.find('.summary').hasClass('hidden')).toEqual(false);
		});
		it('hides headers, summary and show empty content message after setting empty file list', function(){
			fileList.setFiles([]);
			expect($('#filestable thead th').hasClass('hidden')).toEqual(true);
			expect($('#emptycontent').hasClass('hidden')).toEqual(false);
			expect($('#emptycontent .uploadmessage').hasClass('hidden')).toEqual(false);
			expect(fileList.$el.find('.summary').hasClass('hidden')).toEqual(true);
		});
		it('hides headers, upload message, and summary when list is empty and user has no creation permission', function(){
			$('#permissions').val(0);
			fileList.setFiles([]);
			expect($('#filestable thead th').hasClass('hidden')).toEqual(true);
			expect($('#emptycontent').hasClass('hidden')).toEqual(false);
			expect($('#emptycontent .uploadmessage').hasClass('hidden')).toEqual(true);
			expect(fileList.$el.find('.summary').hasClass('hidden')).toEqual(true);
		});
		it('calling findFileEl() can find existing file element', function() {
			fileList.setFiles(testFiles);
			expect(fileList.findFileEl('Two.jpg').length).toEqual(1);
		});
		it('calling findFileEl() returns empty when file not found in file', function() {
			fileList.setFiles(testFiles);
			expect(fileList.findFileEl('unexist.dat').length).toEqual(0);
		});
		it('only add file if in same current directory', function() {
			$('#dir').val('/current dir');
			var fileData = {
				type: 'file',
				name: 'testFile.txt',
				directory: '/current dir'
			};
			fileList.add(fileData);
			expect(fileList.findFileEl('testFile.txt').length).toEqual(1);
		});
		it('triggers "fileActionsReady" event after update', function() {
			var handler = sinon.stub();
			fileList.$fileList.on('fileActionsReady', handler);
			fileList.setFiles(testFiles);
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].$files.length).toEqual(testFiles.length);
		});
		it('triggers "fileActionsReady" event after single add', function() {
			var handler = sinon.stub();
			var $tr;
			fileList.setFiles(testFiles);
			fileList.$fileList.on('fileActionsReady', handler);
			$tr = fileList.add({name: 'test.txt'});
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].$files.is($tr)).toEqual(true);
		});
		it('triggers "fileActionsReady" event after next page load with the newly appended files', function() {
			var handler = sinon.stub();
			fileList.setFiles(generateFiles(0, 64));
			fileList.$fileList.on('fileActionsReady', handler);
			fileList._nextPage();
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].$files.length).toEqual(fileList.pageSize());
		});
		it('does not trigger "fileActionsReady" event after single add with silent argument', function() {
			var handler = sinon.stub();
			fileList.setFiles(testFiles);
			fileList.$fileList.on('fileActionsReady', handler);
			fileList.add({name: 'test.txt'}, {silent: true});
			expect(handler.notCalled).toEqual(true);
		});
		it('triggers "updated" event after update', function() {
			var handler = sinon.stub();
			fileList.$fileList.on('updated', handler);
			fileList.setFiles(testFiles);
			expect(handler.calledOnce).toEqual(true);
		});
		it('does not update summary when removing non-existing files', function() {
			var $summary;
			// single file
			fileList.setFiles([testFiles[0]]);
			$summary = $('#filestable .summary');
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
			fileList.remove('unexist.txt');
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
		});
	});
	describe('Filtered list rendering', function() {
		it('filters the list of files using filter()', function() {
			expect(fileList.files.length).toEqual(0);
			expect(fileList.files).toEqual([]);
			fileList.setFiles(testFiles);
			var $summary = $('#filestable .summary');
			var $nofilterresults = fileList.$el.find(".nofilterresults");
			expect($nofilterresults.length).toEqual(1);
			expect($summary.hasClass('hidden')).toEqual(false);

			expect($('#fileList tr:not(.hidden)').length).toEqual(4);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('e');
			expect($('#fileList tr:not(.hidden)').length).toEqual(3);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('2 files');
			expect($summary.find('.filter').text()).toEqual(" match 'e'");
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('ee');
			expect($('#fileList tr:not(.hidden)').length).toEqual(1);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
			expect($summary.find('.filter').text()).toEqual(" matches 'ee'");
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('eee');
			expect($('#fileList tr:not(.hidden)').length).toEqual(0);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(true);
			expect($nofilterresults.hasClass('hidden')).toEqual(false);

			fileList.setFilter('ee');
			expect($('#fileList tr:not(.hidden)').length).toEqual(1);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
			expect($summary.find('.filter').text()).toEqual(" matches 'ee'");
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('e');
			expect($('#fileList tr:not(.hidden)').length).toEqual(3);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('2 files');
			expect($summary.find('.filter').text()).toEqual(" match 'e'");
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('');
			expect($('#fileList tr:not(.hidden)').length).toEqual(4);
			expect(fileList.files.length).toEqual(4);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').text()).toEqual('1 folder');
			expect($summary.find('.fileinfo').text()).toEqual('3 files');
			expect($nofilterresults.hasClass('hidden')).toEqual(true);
		});
		it('filters the list of non-rendered rows using filter()', function() {
			var $summary = $('#filestable .summary');
			var $nofilterresults = fileList.$el.find(".nofilterresults");
			fileList.setFiles(generateFiles(0, 64));

			fileList.setFilter('63');
			expect($('#fileList tr:not(.hidden)').length).toEqual(1);
			expect($summary.hasClass('hidden')).toEqual(false);
			expect($summary.find('.dirinfo').hasClass('hidden')).toEqual(true);
			expect($summary.find('.fileinfo').text()).toEqual('1 file');
			expect($summary.find('.filter').text()).toEqual(" matches '63'");
			expect($nofilterresults.hasClass('hidden')).toEqual(true);
		});
		it('hides the emptyfiles notice when using filter()', function() {
			expect(fileList.files.length).toEqual(0);
			expect(fileList.files).toEqual([]);
			fileList.setFiles([]);
			var $summary = $('#filestable .summary');
			var $emptycontent = fileList.$el.find("#emptycontent");
			var $nofilterresults = fileList.$el.find(".nofilterresults");
			expect($emptycontent.length).toEqual(1);
			expect($nofilterresults.length).toEqual(1);

			expect($('#fileList tr:not(.hidden)').length).toEqual(0);
			expect(fileList.files.length).toEqual(0);
			expect($summary.hasClass('hidden')).toEqual(true);
			expect($emptycontent.hasClass('hidden')).toEqual(false);
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			fileList.setFilter('e');
			expect($('#fileList tr:not(.hidden)').length).toEqual(0);
			expect(fileList.files.length).toEqual(0);
			expect($summary.hasClass('hidden')).toEqual(true);
			expect($emptycontent.hasClass('hidden')).toEqual(true);
			expect($nofilterresults.hasClass('hidden')).toEqual(false);

			fileList.setFilter('');
			expect($('#fileList tr:not(.hidden)').length).toEqual(0);
			expect(fileList.files.length).toEqual(0);
			expect($summary.hasClass('hidden')).toEqual(true);
			expect($emptycontent.hasClass('hidden')).toEqual(false);
			expect($nofilterresults.hasClass('hidden')).toEqual(true);
		});
		it('does not show the emptyfiles or nofilterresults notice when the mask is active', function() {
			expect(fileList.files.length).toEqual(0);
			expect(fileList.files).toEqual([]);
			fileList.showMask();
			fileList.setFiles(testFiles);
			var $emptycontent = fileList.$el.find("#emptycontent");
			var $nofilterresults = fileList.$el.find(".nofilterresults");
			expect($emptycontent.length).toEqual(1);
			expect($nofilterresults.length).toEqual(1);

			expect($emptycontent.hasClass('hidden')).toEqual(true);
			expect($nofilterresults.hasClass('hidden')).toEqual(true);

			/*
			fileList.setFilter('e');
			expect($emptycontent.hasClass('hidden')).toEqual(true);
			expect($nofilterresults.hasClass('hidden')).toEqual(false);
			*/

			fileList.setFilter('');
			expect($emptycontent.hasClass('hidden')).toEqual(true);
			expect($nofilterresults.hasClass('hidden')).toEqual(true);
		});
	});
	describe('Rendering next page on scroll', function() {
		beforeEach(function() {
			fileList.setFiles(generateFiles(0, 64));
		});
		it('renders only the first page', function() {
			expect(fileList.files.length).toEqual(65);
			expect($('#fileList tr').length).toEqual(20);
		});
		it('renders the full first page despite hidden rows', function() {
			filesConfig.set('showhidden', false);
			var files = _.map(generateFiles(0, 23), function(data) {
				return _.extend(data, {
					name: '.' + data.name
				});
			});
			// only hidden files + one visible
			files.push(testFiles[0]);
			fileList.setFiles(files);
			expect(fileList.files.length).toEqual(25);
			// render 24 hidden elements + the visible one
			expect($('#fileList tr').length).toEqual(25);
		});
		it('renders the full first page despite hidden rows', function() {
			filesConfig.set('showhidden', true);
			var files = _.map(generateFiles(0, 23), function(data) {
				return _.extend(data, {
					name: '.' + data.name
				});
			});
			// only hidden files + one visible
			files.push(testFiles[0]);
			fileList.setFiles(files);
			expect(fileList.files.length).toEqual(25);
			// render 20 first hidden elements as visible
			expect($('#fileList tr').length).toEqual(20);
		});
		it('renders the second page when scrolling down (trigger nextPage)', function() {
			// TODO: can't simulate scrolling here, so calling nextPage directly
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(40);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(60);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(65);
			fileList._nextPage(true);
			// stays at 65
			expect($('#fileList tr').length).toEqual(65);
		});
		it('inserts into the DOM if insertion point is in the visible page ', function() {
			fileList.add({
				id: 2000,
				type: 'file',
				name: 'File with index 15b.txt'
			});
			expect($('#fileList tr').length).toEqual(21);
			expect(fileList.findFileEl('File with index 15b.txt').index()).toEqual(16);
		});
		it('does not inserts into the DOM if insertion point is not the visible page ', function() {
			fileList.add({
				id: 2000,
				type: 'file',
				name: 'File with index 28b.txt'
			});
			expect($('#fileList tr').length).toEqual(20);
			expect(fileList.findFileEl('File with index 28b.txt').length).toEqual(0);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(40);
			expect(fileList.findFileEl('File with index 28b.txt').index()).toEqual(29);
		});
		it('appends into the DOM when inserting a file after the last visible element', function() {
			fileList.add({
				id: 2000,
				type: 'file',
				name: 'File with index 19b.txt'
			});
			expect($('#fileList tr').length).toEqual(21);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(41);
		});
		it('appends into the DOM when inserting a file on the last page when visible', function() {
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(40);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(60);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(65);
			fileList._nextPage(true);
			fileList.add({
				id: 2000,
				type: 'file',
				name: 'File with index 88.txt'
			});
			expect($('#fileList tr').length).toEqual(66);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(66);
		});
		it('shows additional page when appending a page of files and scrolling down', function() {
			var newFiles = generateFiles(66, 81);
			for (var i = 0; i < newFiles.length; i++) {
				fileList.add(newFiles[i]);
			}
			expect($('#fileList tr').length).toEqual(20);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(40);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(60);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(80);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(81);
			fileList._nextPage(true);
			expect($('#fileList tr').length).toEqual(81);
		});
		it('automatically renders next page when there are not enough elements visible', function() {
			// delete the 15 first elements
			for (var i = 0; i < 15; i++) {
				fileList.remove(fileList.files[0].name);
			}
			// still makes sure that there are 20 elements visible, if any
			expect($('#fileList tr').length).toEqual(25);
		});
	});
	describe('file previews', function() {
		var previewLoadStub;

		beforeEach(function() {
			previewLoadStub = sinon.stub(OCA.Files.FileList.prototype, 'lazyLoadPreview');
		});
		afterEach(function() {
			previewLoadStub.restore();
		});
		it('renders default file icon when none provided and no mime type is set', function() {
			var fileData = {
				name: 'testFile.txt'
			};
			var $tr = fileList.add(fileData);
			var $imgDiv = $tr.find('td.filename .thumbnail');
			expect(OC.TestUtil.getImageUrl($imgDiv)).toEqual(OC.getRootPath() + '/core/img/filetypes/file.svg');
			// tries to load preview
			expect(previewLoadStub.calledOnce).toEqual(true);
		});
		it('renders default icon for folder when none provided', function() {
			var fileData = {
				name: 'test dir',
				mimetype: 'httpd/unix-directory'
			};

			var $tr = fileList.add(fileData);
			var $imgDiv = $tr.find('td.filename .thumbnail');
			expect(OC.TestUtil.getImageUrl($imgDiv)).toEqual(OC.getRootPath() + '/core/img/filetypes/folder.svg');
			// no preview since it's a directory
			expect(previewLoadStub.notCalled).toEqual(true);
		});
		it('renders provided icon for file when provided', function() {
			var fileData = new FileInfo({
				type: 'file',
				name: 'test file',
				icon: OC.getRootPath() + '/core/img/filetypes/application-pdf.svg',
				mimetype: 'application/pdf'
			});
			var $tr = fileList.add(fileData);
			var $imgDiv = $tr.find('td.filename .thumbnail');
			expect(OC.TestUtil.getImageUrl($imgDiv)).toEqual(OC.getRootPath() + '/core/img/filetypes/application-pdf.svg');
			// try loading preview
			expect(previewLoadStub.calledOnce).toEqual(true);
		});
		it('renders provided icon for file when provided', function() {
			var fileData = new FileInfo({
				name: 'somefile.pdf',
				icon: OC.getRootPath() + '/core/img/filetypes/application-pdf.svg'
			});

			var $tr = fileList.add(fileData);
			var $imgDiv = $tr.find('td.filename .thumbnail');
			expect(OC.TestUtil.getImageUrl($imgDiv)).toEqual(OC.getRootPath() + '/core/img/filetypes/application-pdf.svg');
			// try loading preview
			expect(previewLoadStub.calledOnce).toEqual(true);
		});
		it('renders provided icon for folder when provided', function() {
			var fileData = new FileInfo({
				name: 'some folder',
				mimetype: 'httpd/unix-directory',
				icon: OC.getRootPath() + '/core/img/filetypes/folder-alt.svg'
			});

			var $tr = fileList.add(fileData);
			var $imgDiv = $tr.find('td.filename .thumbnail');
			expect(OC.TestUtil.getImageUrl($imgDiv)).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-alt.svg');
			// do not load preview for folders
			expect(previewLoadStub.notCalled).toEqual(true);
		});
		it('renders preview when no icon was provided', function() {
			var fileData = {
				type: 'file',
				name: 'test file'
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail')))
				.toEqual(OC.getRootPath() + '/core/img/filetypes/file.svg');
			expect(previewLoadStub.calledOnce).toEqual(true);
			// third argument is callback
			previewLoadStub.getCall(0).args[0].callback(OC.getRootPath() + '/somepath.png');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/somepath.png');
		});
		it('does not render preview for directories', function() {
			var fileData = {
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir'
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/core/img/filetypes/folder.svg');
			expect(previewLoadStub.notCalled).toEqual(true);
		});
		it('render encrypted folder icon for encrypted root', function() {
			var fileData = {
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir',
				isEncrypted: true
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-encrypted.svg');
			expect(previewLoadStub.notCalled).toEqual(true);
		});
		it('render encrypted folder icon for encrypted subdir', function() {
			var fileData = {
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir',
				isEncrypted: true
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-encrypted.svg');
			expect(previewLoadStub.notCalled).toEqual(true);
			// default icon override
			expect($tr.attr('data-icon')).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-encrypted.svg');
		});
		it('render external storage icon for external storage root', function() {
			var fileData = {
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir',
				mountType: 'external-root'
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-external.svg');
			expect(previewLoadStub.notCalled).toEqual(true);
		});
		it('render external storage icon for external storage subdir', function() {
			var fileData = {
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir',
				mountType: 'external'
			};
			var $tr = fileList.add(fileData);
			var $td = $tr.find('td.filename');
			expect(OC.TestUtil.getImageUrl($td.find('.thumbnail'))).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-external.svg');
			expect(previewLoadStub.notCalled).toEqual(true);
			// default icon override
			expect($tr.attr('data-icon')).toEqual(OC.getRootPath() + '/core/img/filetypes/folder-external.svg');
		});

	});
	describe('viewer mode', function() {
		it('enabling viewer mode hides files table and action buttons', function() {
			fileList.setViewerMode(true);
			expect($('#filestable').hasClass('hidden')).toEqual(true);
			expect($('.actions').hasClass('hidden')).toEqual(true);
			expect($('.notCreatable').hasClass('hidden')).toEqual(true);
		});
		it('disabling viewer mode restores files table and action buttons', function() {
			fileList.setViewerMode(true);
			fileList.setViewerMode(false);
			expect($('#filestable').hasClass('hidden')).toEqual(false);
			expect($('.actions').hasClass('hidden')).toEqual(false);
			expect($('.notCreatable').hasClass('hidden')).toEqual(true);
		});
		it('disabling viewer mode restores files table and action buttons with correct permissions', function() {
			$('#permissions').val(0);
			fileList.setViewerMode(true);
			fileList.setViewerMode(false);
			expect($('#filestable').hasClass('hidden')).toEqual(false);
			expect($('.actions').hasClass('hidden')).toEqual(true);
			expect($('.notCreatable').hasClass('hidden')).toEqual(false);
		});
		it('toggling viewer mode triggers event', function() {
			var handler = sinon.stub();
			fileList.$el.on('changeViewerMode', handler);
			fileList.setViewerMode(true);
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].viewerModeEnabled).toEqual(true);

			handler.reset();
			fileList.setViewerMode(false);
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].viewerModeEnabled).toEqual(false);
		});
	});
	describe('loading file list', function() {
		var deferredList;
		var getFolderContentsStub;

		beforeEach(function() {
			deferredList = $.Deferred();
			getFolderContentsStub = sinon.stub(filesClient, 'getFolderContents').returns(deferredList.promise());
		});
		afterEach(function() {
			getFolderContentsStub.restore();
		});
		it('fetches file list from server and renders it when reload() is called', function() {
			fileList.reload();
			expect(getFolderContentsStub.calledOnce).toEqual(true);
			expect(getFolderContentsStub.calledWith('/subdir')).toEqual(true);
			deferredList.resolve(200, [testRoot].concat(testFiles));
			expect($('#fileList tr').length).toEqual(4);
			expect(fileList.findFileEl('One.txt').length).toEqual(1);
		});
		it('switches dir and fetches file list when calling changeDirectory()', function() {
			fileList.changeDirectory('/anothersubdir');
			expect(fileList.getCurrentDirectory()).toEqual('/anothersubdir');
			expect(getFolderContentsStub.calledOnce).toEqual(true);
			expect(getFolderContentsStub.calledWith('/anothersubdir')).toEqual(true);
		});
		it('converts backslashes to slashes when calling changeDirectory()', function() {
			fileList.changeDirectory('/another\\subdir');
			expect(fileList.getCurrentDirectory()).toEqual('/another/subdir');
		});
		it('switches to root dir when current directory is invalid', function() {
			_.each([
				'..',
				'/..',
				'../',
				'/../',
				'/../abc',
				'/abc/..',
				'/abc/../',
				'/../abc/',
				'/foo%0Abar/',
				'/foo%00bar/',
				'/another\\subdir/../foo\\../bar\\..\\file/..\\folder/../'
			], function(path) {
				fileList.changeDirectory(decodeURI(path));
				expect(fileList.getCurrentDirectory()).toEqual('/');
			});
		});
		it('allows paths with dotdot at the beginning or end', function() {
			_.each([
				'/..abc',
				'/def..',
				'/...',
				'/abc../def'
			], function(path) {
				fileList.changeDirectory(path);
				expect(fileList.getCurrentDirectory()).toEqual(path);
			});
		});
		it('switches to root dir when current directory does not exist', function() {
			fileList.changeDirectory('/unexist');
			deferredList.reject(404);
			expect(fileList.getCurrentDirectory()).toEqual('/');
		});
		it('switches to root dir when current directory returns 400', function() {
			fileList.changeDirectory('/unexist');
			deferredList.reject(400);
			expect(fileList.getCurrentDirectory()).toEqual('/');
		});
		it('switches to root dir when current directory returns 405', function() {
			fileList.changeDirectory('/unexist');
			deferredList.reject(405);
			expect(fileList.getCurrentDirectory()).toEqual('/');
		});
		it('switches to root dir when current directory is forbidden', function() {
			fileList.changeDirectory('/unexist');
			deferredList.reject(403);
			expect(fileList.getCurrentDirectory()).toEqual('/');
		});
		it('switches to root dir when current directory is unavailable', function() {
			fileList.changeDirectory('/unexist');
			deferredList.reject(500);
			expect(fileList.getCurrentDirectory()).toEqual('/');
		});
		it('shows mask before loading file list then hides it at the end', function() {
			var showMaskStub = sinon.stub(fileList, 'showMask');
			var hideMaskStub = sinon.stub(fileList, 'hideMask');
			fileList.changeDirectory('/anothersubdir');
			expect(showMaskStub.calledOnce).toEqual(true);
			expect(hideMaskStub.calledOnce).toEqual(false);
			deferredList.resolve(200, [testRoot].concat(testFiles));
			expect(showMaskStub.calledOnce).toEqual(true);
			expect(hideMaskStub.calledOnce).toEqual(true);
			showMaskStub.restore();
			hideMaskStub.restore();
		});
		it('triggers "changeDirectory" event when changing directory', function() {
			var handler = sinon.stub();
			$('#app-content-files').on('changeDirectory', handler);
			fileList.changeDirectory('/somedir');
			deferredList.resolve(200, [testRoot].concat(testFiles));
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].dir).toEqual('/somedir');
		});
		it('triggers "afterChangeDirectory" event with fileid after changing directory', function() {
			var handler = sinon.stub();
			$('#app-content-files').on('afterChangeDirectory', handler);
			fileList.changeDirectory('/somedir');
			deferredList.resolve(200, [testRoot].concat(testFiles));
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0].dir).toEqual('/somedir');
			expect(handler.getCall(0).args[0].fileId).toEqual(99);
		});
		it('changes the directory when receiving "urlChanged" event', function() {
			$('#app-content-files').trigger(new $.Event('urlChanged', {view: 'files', dir: '/somedir'}));
			expect(fileList.getCurrentDirectory()).toEqual('/somedir');
		});
		it('reloads the list when leaving hidden state', function() {
			var reloadStub = sinon.stub(fileList, 'reload');

			// First show should not trigger
			$('#app-content-files').trigger(new $.Event('show'));
			expect(reloadStub.calledOnce).toEqual(false);

			// Second show should!
			$('#app-content-files').trigger(new $.Event('show'));
			expect(reloadStub.calledOnce).toEqual(true);
			reloadStub.restore();
		});
		it('refreshes breadcrumb after update', function() {
			var setDirSpy = sinon.spy(fileList.breadcrumb, 'setDirectory');
			fileList.changeDirectory('/anothersubdir');
			deferredList.resolve(200, [testRoot].concat(testFiles));
			// twice because setDirectory gets called by _setCurrentDir which
			// gets called directly by changeDirectory and via reload()
			expect(fileList.breadcrumb.setDirectory.calledTwice).toEqual(true);
			expect(fileList.breadcrumb.setDirectory.calledWith('/anothersubdir')).toEqual(true);
			setDirSpy.restore();
			getFolderContentsStub.restore();
		});
		it('prepends a slash to directory if none was given', function() {
			fileList.changeDirectory('');
			expect(fileList.getCurrentDirectory()).toEqual('/');
			fileList.changeDirectory('noslash');
			expect(fileList.getCurrentDirectory()).toEqual('/noslash');
		});
	});
	describe('breadcrumb events', function() {
		var deferredList;
		var getFolderContentsStub;

		beforeEach(function() {
			deferredList = $.Deferred();
			getFolderContentsStub = sinon.stub(filesClient, 'getFolderContents').returns(deferredList.promise());
		});
		afterEach(function() {
			getFolderContentsStub.restore();
		});
		it('clicking on root breadcrumb changes directory to root', function() {
			fileList.changeDirectory('/subdir/two/three with space/four/five');
			deferredList.resolve(200, [testRoot].concat(testFiles));
			var changeDirStub = sinon.stub(fileList, 'changeDirectory');
			fileList.breadcrumb.$el.find('.crumb:eq(1) > a').trigger({type: 'click', which: 1});

			expect(changeDirStub.calledOnce).toEqual(true);
			expect(changeDirStub.getCall(0).args[0]).toEqual('/');
			changeDirStub.restore();
		});
		it('clicking on breadcrumb changes directory', function() {
			fileList.changeDirectory('/subdir/two/three with space/four/five');
			deferredList.resolve(200, [testRoot].concat(testFiles));
			var changeDirStub = sinon.stub(fileList, 'changeDirectory');
			fileList.breadcrumb.$el.find('.crumb:eq(4) > a').trigger({type: 'click', which: 1});

			expect(changeDirStub.calledOnce).toEqual(true);
			expect(changeDirStub.getCall(0).args[0]).toEqual('/subdir/two/three with space');
			changeDirStub.restore();
		});
		it('dropping files on breadcrumb calls move operation', function(done) {
			var testDir = '/subdir/two/three with space/four/five';
			var moveStub = sinon.stub(filesClient, 'move');
			var resolve1, resolve2;
			var deferredMove1 = $.Deferred();
			var deferredMove2 = $.Deferred();
			moveStub.onCall(0).returns(deferredMove1.promise());
			moveStub.onCall(1).returns(deferredMove2.promise());
			fileList.changeDirectory(testDir);
			deferredList.resolve(200, [testRoot].concat(testFiles));
			var $crumb = fileList.breadcrumb.$el.find('.crumb:eq(4)');
			// no idea what this is but is required by the handler
			var ui = {
				helper: {
					find: sinon.stub()
				}
			};
			// returns a list of tr that were dragged
			ui.helper.find.returns([
				$('<tr data-file="One.txt" data-dir="' + testDir + '"></tr>'),
				$('<tr data-file="Two.jpg" data-dir="' + testDir + '"></tr>')
			]);
			// simulate drop event
			var result = fileList._onDropOnBreadCrumb(new $.Event('drop', {target: $crumb}), ui).then(function(){
				expect(moveStub.callCount).toEqual(2);
				expect(moveStub.getCall(0).args[0]).toEqual(testDir + '/One.txt');
				expect(moveStub.getCall(0).args[1]).toEqual('/subdir/two/three with space/One.txt');
				expect(moveStub.getCall(1).args[0]).toEqual(testDir + '/Two.jpg');
				expect(moveStub.getCall(1).args[1]).toEqual('/subdir/two/three with space/Two.jpg');
				moveStub.restore();
				done();
			});
			deferredMove1.resolve(201);
			deferredMove2.resolve(201);
			return result;
		});
		it('dropping files on same dir breadcrumb does nothing', function() {
			var testDir = '/subdir/two/three with space/four/five';
			var moveStub = sinon.stub(filesClient, 'move').returns($.Deferred().promise());
			fileList.changeDirectory(testDir);
			deferredList.resolve(200, [testRoot].concat(testFiles));
			var $crumb = fileList.breadcrumb.$el.find('.crumb:last');
			// no idea what this is but is required by the handler
			var ui = {
				helper: {
					find: sinon.stub()
				}
			};
			// returns a list of tr that were dragged
			ui.helper.find.returns([
				$('<tr data-file="One.txt" data-dir="' + testDir + '"></tr>'),
				$('<tr data-file="Two.jpg" data-dir="' + testDir + '"></tr>')
			]);
			// simulate drop event
			fileList._onDropOnBreadCrumb(new $.Event('drop', {target: $crumb}), ui);

			// no extra server request
			expect(moveStub.notCalled).toEqual(true);
		});
	});
	describe('Download Url', function() {
		it('returns correct download URL for single files', function() {
			expect(fileList.getDownloadUrl('some file.txt'))
				.toEqual(OC.getRootPath() + '/remote.php/webdav/subdir/some%20file.txt');
			expect(fileList.getDownloadUrl('some file.txt', '/anotherpath/abc'))
				.toEqual(OC.getRootPath() + '/remote.php/webdav/anotherpath/abc/some%20file.txt');
			$('#dir').val('/');
			expect(fileList.getDownloadUrl('some file.txt'))
				.toEqual(OC.getRootPath() + '/remote.php/webdav/some%20file.txt');
		});
		it('returns correct download URL for multiple files', function() {
			expect(fileList.getDownloadUrl(['a b c.txt', 'd e f.txt']))
				.toEqual(OC.getRootPath() + '/index.php/apps/files/ajax/download.php?dir=%2Fsubdir&files=%5B%22a%20b%20c.txt%22%2C%22d%20e%20f.txt%22%5D');
		});
		it('returns the correct ajax URL', function() {
			expect(fileList.getAjaxUrl('test', {a:1, b:'x y'}))
				.toEqual(OC.getRootPath() + '/index.php/apps/files/ajax/test.php?a=1&b=x%20y');
		});
	});
	describe('File selection', function() {
		beforeEach(function() {
			fileList.setFiles(testFiles);
		});
		it('Selects a file when clicking its checkbox', function() {
			var $tr = fileList.findFileEl('One.txt');
			expect($tr.find('input:checkbox').prop('checked')).toEqual(false);
			$tr.find('td.selection input:checkbox').click();

			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
		});
		it('Selects/deselect a file when clicking on the name while holding Ctrl', function() {
			var $tr = fileList.findFileEl('One.txt');
			var $tr2 = fileList.findFileEl('Three.pdf');
			var e;
			expect($tr.find('input:checkbox').prop('checked')).toEqual(false);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(false);
			e = new $.Event('click');
			e.ctrlKey = true;
			$tr.find('td.filename .name').trigger(e);

			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(false);

			// click on second entry, does not clear the selection
			e = new $.Event('click');
			e.ctrlKey = true;
			$tr2.find('td.filename .name').trigger(e);
			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(true);

			expect(_.pluck(fileList.getSelectedFiles(), 'name')).toEqual(['One.txt', 'Three.pdf']);

			// deselect now
			e = new $.Event('click');
			e.ctrlKey = true;
			$tr2.find('td.filename .name').trigger(e);
			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(false);
			expect(_.pluck(fileList.getSelectedFiles(), 'name')).toEqual(['One.txt']);
		});
		it('Selects a range when clicking on one file then Shift clicking on another one', function() {
			var $tr = fileList.findFileEl('One.txt');
			var $tr2 = fileList.findFileEl('Three.pdf');
			var e;
			$tr.find('td.selection input:checkbox').click();
			e = new $.Event('click');
			e.shiftKey = true;
			$tr2.find('td.filename .name').trigger(e);

			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(true);
			expect(fileList.findFileEl('Two.jpg').find('input:checkbox').prop('checked')).toEqual(true);
			var selection = _.pluck(fileList.getSelectedFiles(), 'name');
			expect(selection.length).toEqual(3);
			expect(selection).toContain('One.txt');
			expect(selection).toContain('Two.jpg');
			expect(selection).toContain('Three.pdf');
		});
		it('Selects a range when clicking on one file then Shift clicking on another one that is above the first one', function() {
			var $tr = fileList.findFileEl('One.txt');
			var $tr2 = fileList.findFileEl('Three.pdf');
			var e;
			$tr2.find('td.selection input:checkbox').click();
			e = new $.Event('click');
			e.shiftKey = true;
			$tr.find('td.filename .name').trigger(e);

			expect($tr.find('input:checkbox').prop('checked')).toEqual(true);
			expect($tr2.find('input:checkbox').prop('checked')).toEqual(true);
			expect(fileList.findFileEl('Two.jpg').find('input:checkbox').prop('checked')).toEqual(true);
			var selection = _.pluck(fileList.getSelectedFiles(), 'name');
			expect(selection.length).toEqual(3);
			expect(selection).toContain('One.txt');
			expect(selection).toContain('Two.jpg');
			expect(selection).toContain('Three.pdf');
		});
		it('Selecting all files will automatically check "select all" checkbox', function() {
			expect($('.select-all').prop('checked')).toEqual(false);
			$('#fileList tr td.selection input:checkbox').click();
			expect($('.select-all').prop('checked')).toEqual(true);
		});
		it('Selecting all files on the first visible page will not automatically check "select all" checkbox', function() {
			fileList.setFiles(generateFiles(0, 41));
			expect($('.select-all').prop('checked')).toEqual(false);
			$('#fileList tr td.selection input:checkbox').click();
			expect($('.select-all').prop('checked')).toEqual(false);
		});
		it('Selecting all files also selects hidden files when invisible', function() {
			filesConfig.set('showhidden', false);
			var $tr = fileList.add(new FileInfo({
				name: '.hidden',
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				size: 150
			}));
			$('.select-all').click();
			expect($tr.find('td.selection input:checkbox').prop('checked')).toEqual(true);
			expect(_.pluck(fileList.getSelectedFiles(), 'name')).toContain('.hidden');
		});
		it('Clicking "select all" will select/deselect all files', function() {
			fileList.setFiles(generateFiles(0, 41));
			$('.select-all').click();
			expect($('.select-all').prop('checked')).toEqual(true);
			$('#fileList tr input:checkbox').each(function() {
				expect($(this).prop('checked')).toEqual(true);
			});
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(42);

			$('.select-all').click();
			expect($('.select-all').prop('checked')).toEqual(false);

			$('#fileList tr input:checkbox').each(function() {
				expect($(this).prop('checked')).toEqual(false);
			});
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(0);
		});
		it('Clicking "select all" then deselecting a file will uncheck "select all"', function() {
			$('.select-all').click();
			expect($('.select-all').prop('checked')).toEqual(true);

			var $tr = fileList.findFileEl('One.txt');
			$tr.find('input:checkbox').click();

			expect($('.select-all').prop('checked')).toEqual(false);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(3);
		});
		it('Updates the selection summary when doing a few manipulations with "Select all"', function() {
			$('.select-all').click();
			expect($('.select-all').prop('checked')).toEqual(true);

			var $tr = fileList.findFileEl('One.txt');
			// unselect one
			$tr.find('input:checkbox').click();

			expect($('.select-all').prop('checked')).toEqual(false);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(3);

			// select all
			$('.select-all').click();
			expect($('.select-all').prop('checked')).toEqual(true);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(4);

			// unselect one
			$tr.find('input:checkbox').click();
			expect($('.select-all').prop('checked')).toEqual(false);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(3);

			// re-select it
			$tr.find('input:checkbox').click();
			expect($('.select-all').prop('checked')).toEqual(true);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(4);
		});
		it('Auto-selects files on next page when "select all" is checked', function() {
			fileList.setFiles(generateFiles(0, 41));
			$('.select-all').click();

			expect(fileList.$fileList.find('tr input:checkbox:checked').length).toEqual(20);
			fileList._nextPage(true);
			expect(fileList.$fileList.find('tr input:checkbox:checked').length).toEqual(40);
			fileList._nextPage(true);
			expect(fileList.$fileList.find('tr input:checkbox:checked').length).toEqual(42);
			expect(_.pluck(fileList.getSelectedFiles(), 'name').length).toEqual(42);
		});
		it('Selecting files updates selection summary', function() {
			var $summary = $('#headerName a.name>span:first');
			expect($summary.text()).toEqual('Name');
			fileList.findFileEl('One.txt').find('input:checkbox').click();
			fileList.findFileEl('Three.pdf').find('input:checkbox').click();
			fileList.findFileEl('somedir').find('input:checkbox').click();
			expect($summary.text()).toEqual('1 folder and 2 files');
		});
		it('Unselecting files hides selection summary', function() {
			var $summary = $('#headerName a.name>span:first');
			fileList.findFileEl('One.txt').find('input:checkbox').click().click();
			expect($summary.text()).toEqual('Name');
		});
		it('Displays the number of hidden files in selection summary if hidden files are invisible', function() {
			filesConfig.set('showhidden', false);
			var $tr = fileList.add(new FileInfo({
				name: '.hidden',
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				size: 150
			}));
			$('.select-all').click();
			var $summary = $('#headerName a.name>span:first');
			expect($summary.text()).toEqual('2 folders and 3 files (including 1 hidden)');
		});
		it('Does not displays the number of hidden files in selection summary if hidden files are visible', function() {
			filesConfig.set('showhidden', true);
			var $tr = fileList.add(new FileInfo({
				name: '.hidden',
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				size: 150
			}));
			$('.select-all').click();
			var $summary = $('#headerName a.name>span:first');
			expect($summary.text()).toEqual('2 folders and 3 files');
		});
		it('Toggling hidden file visibility updates selection summary', function() {
			filesConfig.set('showhidden', false);
			var $tr = fileList.add(new FileInfo({
				name: '.hidden',
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				size: 150
			}));
			$('.select-all').click();
			var $summary = $('#headerName a.name>span:first');
			expect($summary.text()).toEqual('2 folders and 3 files (including 1 hidden)');
			filesConfig.set('showhidden', true);
			expect($summary.text()).toEqual('2 folders and 3 files');
		});
		it('Select/deselect files shows/hides file actions', function() {
			var $actions = $('#headerName .selectedActions');
			var $checkbox = fileList.findFileEl('One.txt').find('input:checkbox');
			expect($actions.hasClass('hidden')).toEqual(true);
			$checkbox.click();
			expect($actions.hasClass('hidden')).toEqual(false);
			$checkbox.click();
			expect($actions.hasClass('hidden')).toEqual(true);
		});
		it('Selection is cleared when switching dirs', function() {
			$('.select-all').click();
			var deferredList = $.Deferred();
			var getFolderContentsStub = sinon.stub(filesClient, 'getFolderContents').returns(deferredList.promise());

			fileList.changeDirectory('/');

			deferredList.resolve(200, [testRoot].concat(testFiles));

			expect($('.select-all').prop('checked')).toEqual(false);
			expect(_.pluck(fileList.getSelectedFiles(), 'name')).toEqual([]);

			getFolderContentsStub.restore();
		});
		it('getSelectedFiles returns the selected files even when they are on the next page', function() {
			var selectedFiles;
			fileList.setFiles(generateFiles(0, 41));
			$('.select-all').click();
			// unselect one to not have the "allFiles" case
			fileList.$fileList.find('tr input:checkbox:first').click();

			// only 20 files visible, must still return all the selected ones
			selectedFiles = _.pluck(fileList.getSelectedFiles(), 'name');

			expect(selectedFiles.length).toEqual(41);
		});
		describe('clearing the selection', function() {
			it('clears selected files selected individually calling setFiles()', function() {
				var selectedFiles;

				fileList.setFiles(generateFiles(0, 41));
				fileList.$fileList.find('tr:eq(5) input:checkbox:first').click();
				fileList.$fileList.find('tr:eq(7) input:checkbox:first').click();

				selectedFiles = _.pluck(fileList.getSelectedFiles(), 'name');
				expect(selectedFiles.length).toEqual(2);

				fileList.setFiles(generateFiles(0, 2));

				selectedFiles = _.pluck(fileList.getSelectedFiles(), 'name');
				expect(selectedFiles.length).toEqual(0);
			});
			it('clears selected files selected with select all when calling setFiles()', function() {
				var selectedFiles;

				fileList.setFiles(generateFiles(0, 41));
				$('.select-all').click();

				selectedFiles = _.pluck(fileList.getSelectedFiles(), 'name');
				expect(selectedFiles.length).toEqual(42);

				fileList.setFiles(generateFiles(0, 2));

				selectedFiles = _.pluck(fileList.getSelectedFiles(), 'name');
				expect(selectedFiles.length).toEqual(0);
			});
		});
		describe('Selection overlay', function() {
			it('show doesnt show the copy/move action if one or more files are not copiable/movable', function () {
				fileList.setFiles(testFiles);
				$('#permissions').val(OC.PERMISSION_READ | OC.PERMISSION_UPDATE);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-copyMove').hasClass('hidden')).toEqual(false);
				expect(fileList.$el.find('.selectedActions .item-copyMove .label').text()).toEqual('Move or copy');
				testFiles[0].permissions = OC.PERMISSION_READ;
				$('.select-all').click();
				fileList.setFiles(testFiles);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-copyMove').hasClass('hidden')).toEqual(false);
				expect(fileList.$el.find('.selectedActions .item-copyMove .label').text()).toEqual('Copy');
				testFiles[0].permissions = OC.PERMISSION_NONE;
				$('.select-all').click();
				fileList.setFiles(testFiles);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-copyMove').hasClass('hidden')).toEqual(true);
			});
			it('show doesnt show the download action if one or more files are not downloadable', function () {
				fileList.setFiles(testFiles);
				$('#permissions').val(OC.PERMISSION_READ | OC.PERMISSION_UPDATE);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-download').hasClass('hidden')).toEqual(false);
				testFiles[0].permissions = OC.PERMISSION_UPDATE;
				$('.select-all').click();
				fileList.setFiles(testFiles);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-download').hasClass('hidden')).toEqual(true);
			});
			it('show doesnt show the delete action if one or more files are not deletable', function () {
				fileList.setFiles(testFiles);
				$('#permissions').val(OC.PERMISSION_READ | OC.PERMISSION_DELETE);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-delete').hasClass('hidden')).toEqual(false);
				testFiles[0].permissions = OC.PERMISSION_READ;
				$('.select-all').click();
				fileList.setFiles(testFiles);
				$('.select-all').click();
				expect(fileList.$el.find('.selectedActions .item-delete').hasClass('hidden')).toEqual(true);
			});
		});
		describe('Actions', function() {
			beforeEach(function() {
				fileList.findFileEl('One.txt').find('input:checkbox').click();
				fileList.findFileEl('Three.pdf').find('input:checkbox').click();
				fileList.findFileEl('somedir').find('input:checkbox').click();
			});
			it('getSelectedFiles returns the selected file data', function() {
				var files = fileList.getSelectedFiles();
				expect(files.length).toEqual(3);
				expect(files[0]).toEqual({
					id: 1,
					name: 'One.txt',
					mimetype: 'text/plain',
					mtime: 123456789,
					type: 'file',
					size: 12,
					etag: 'abc',
					permissions: OC.PERMISSION_ALL,
					hasPreview: true,
					isEncrypted: false
				});
				expect(files[1]).toEqual({
					id: 3,
					type: 'file',
					name: 'Three.pdf',
					mimetype: 'application/pdf',
					mtime: 234560000,
					size: 58009,
					etag: '123',
					permissions: OC.PERMISSION_ALL,
					hasPreview: true,
					isEncrypted: false
				});
				expect(files[2]).toEqual({
					id: 4,
					type: 'dir',
					name: 'somedir',
					mimetype: 'httpd/unix-directory',
					mtime: 134560000,
					size: 250,
					etag: '456',
					permissions: OC.PERMISSION_ALL,
					hasPreview: true,
					isEncrypted: false
				});
				expect(files[0].id).toEqual(1);
				expect(files[0].name).toEqual('One.txt');
				expect(files[1].id).toEqual(3);
				expect(files[1].name).toEqual('Three.pdf');
				expect(files[2].id).toEqual(4);
				expect(files[2].name).toEqual('somedir');
			});
			it('Removing a file removes it from the selection', function() {
				fileList.remove('Three.pdf');
				var files = fileList.getSelectedFiles();
				expect(files.length).toEqual(2);
				expect(files[0]).toEqual({
					id: 1,
					name: 'One.txt',
					mimetype: 'text/plain',
					mtime: 123456789,
					type: 'file',
					size: 12,
					etag: 'abc',
					permissions: OC.PERMISSION_ALL,
					hasPreview: true,
					isEncrypted: false
				});
				expect(files[1]).toEqual({
					id: 4,
					type: 'dir',
					name: 'somedir',
					mimetype: 'httpd/unix-directory',
					mtime: 134560000,
					size: 250,
					etag: '456',
					permissions: OC.PERMISSION_ALL,
					hasPreview: true,
					isEncrypted: false
				});
			});
			describe('Download', function() {
				beforeEach(function() {
					fileList.$el.find('.actions-selected').click();
				});

				it('Opens download URL when clicking "Download"', function() {
					$('.selectedActions .filesSelectMenu .download').click();
					expect(redirectStub.calledOnce).toEqual(true);
					expect(redirectStub.getCall(0).args[0]).toContain(OC.getRootPath() + '/index.php/apps/files/ajax/download.php?dir=%2Fsubdir&files=%5B%22One.txt%22%2C%22Three.pdf%22%2C%22somedir%22%5D');
					redirectStub.restore();
				});
				it('Downloads root folder when all selected in root folder', function() {
					$('#dir').val('/');
					$('.select-all').click();
					$('.selectedActions .filesSelectMenu .download').click();
					expect(redirectStub.calledOnce).toEqual(true);
					expect(redirectStub.getCall(0).args[0]).toContain(OC.getRootPath() + '/index.php/apps/files/ajax/download.php?dir=%2F&files=');
				});
				it('Downloads parent folder when all selected in subfolder', function() {
					$('.select-all').click();
					$('.selectedActions .filesSelectMenu .download').click();
					expect(redirectStub.calledOnce).toEqual(true);
					expect(redirectStub.getCall(0).args[0]).toContain(OC.getRootPath() + '/index.php/apps/files/ajax/download.php?dir=%2F&files=subdir');
				});

				afterEach(function() {
					fileList.$el.find('.actions-selected').click();
				});
			});

			describe('Delete', function() {
				var deleteStub, deferredDelete;
				beforeEach(function() {
					deferredDelete = $.Deferred();
					deleteStub = sinon.stub(filesClient, 'remove');
					fileList.$el.find('.actions-selected').click();
				});

				afterEach(function() {
					fileList.$el.find('.actions-selected').click();
					deleteStub.restore();
				});

				it('Deletes selected files when "Delete" clicked', function(done) {
					var deferred = $.Deferred();

					deleteStub.returns(deferredDelete.promise());
					deleteStub.onCall(2).callsFake(function(){
						expect(deleteStub.callCount).toEqual(3);
						expect(deleteStub.getCall(0).args[0]).toEqual('/subdir/One.txt');
						expect(deleteStub.getCall(1).args[0]).toEqual('/subdir/Three.pdf');
						expect(deleteStub.getCall(2).args[0]).toEqual('/subdir/somedir');
						return deferredDelete.promise();
					});

					stub = sinon.stub(fileList._operationProgressBar, 'hideProgressBar').callsFake(function(){
						expect(fileList.findFileEl('One.txt').length).toEqual(0);
						expect(fileList.findFileEl('Three.pdf').length).toEqual(0);
						expect(fileList.findFileEl('somedir').length).toEqual(0);
						expect(fileList.findFileEl('Two.jpg').length).toEqual(1);
						done();
						deferred.resolve();
					});
					$('.selectedActions .filesSelectMenu .delete').click();
					deferredDelete.resolve(204);
					return deferred.promise();

				});
				it('Deletes all files when all selected when "Delete" clicked', function(done) {
					var deferred = $.Deferred();

					deleteStub.returns(deferredDelete.promise());
					deleteStub.onCall(3).callsFake(function(){
						expect(deleteStub.getCall(0).args[0]).toEqual('/subdir/One.txt');
						expect(deleteStub.getCall(1).args[0]).toEqual('/subdir/Two.jpg');
						expect(deleteStub.getCall(2).args[0]).toEqual('/subdir/Three.pdf');
						expect(deleteStub.getCall(3).args[0]).toEqual('/subdir/somedir');
						return deferredDelete.promise();
					});

					stub = sinon.stub(fileList._operationProgressBar, 'hideProgressBar').callsFake(function(){
						expect(fileList.isEmpty).toEqual(true);
						expect(deleteStub.callCount).toEqual(4);
						done();
						deferred.resolve();
					});
					$('.select-all').click();
					$('.selectedActions .filesSelectMenu .delete').click();
					deferredDelete.resolve(204);

					return deferred.promise();
				});
			});
		});
		it('resets the file selection on reload', function() {
			fileList.$el.find('.select-all').click();
			fileList.reload();
			expect(fileList.$el.find('.select-all').prop('checked')).toEqual(false);
			expect(fileList.getSelectedFiles()).toEqual([]);
		});
		describe('Disabled selection', function() {
			beforeEach(function() {
				fileList._allowSelection = false;
				fileList.setFiles(testFiles);
			});
			it('Does not render checkboxes', function() {
				expect(fileList.$fileList.find('.selectCheckBox').length).toEqual(0);
			});
			it('Does not select a file with Ctrl or Shift if selection is not allowed', function() {
				var $tr = fileList.findFileEl('One.txt');
				var $tr2 = fileList.findFileEl('Three.pdf');
				var e;
				e = new $.Event('click');
				e.ctrlKey = true;
				$tr.find('td.filename .name').trigger(e);

				// click on second entry, does not clear the selection
				e = new $.Event('click');
				e.ctrlKey = true;
				$tr2.find('td.filename .name').trigger(e);

				expect(fileList.getSelectedFiles().length).toEqual(0);

				// deselect now
				e = new $.Event('click');
				e.shiftKey = true;
				$tr2.find('td.filename .name').trigger(e);
				expect(fileList.getSelectedFiles().length).toEqual(0);
			});
		});
	});
	describe('File actions', function() {
		it('Clicking on a file name will trigger default action', function() {
			var actionStub = sinon.stub();
			fileList.setFiles(testFiles);
			fileList.fileActions.registerAction({
				mime: 'text/plain',
				name: 'Test',
				type: OCA.Files.FileActions.TYPE_INLINE,
				permissions: OC.PERMISSION_ALL,
				icon: function() {
					// Specify icon for hitory button
					return OC.imagePath('core','actions/history');
				},
				actionHandler: actionStub
			});
			fileList.fileActions.setDefault('text/plain', 'Test');
			var $tr = fileList.findFileEl('One.txt');
			$tr.find('td.filename .nametext').click();
			expect(actionStub.calledOnce).toEqual(true);
			expect(actionStub.getCall(0).args[0]).toEqual('One.txt');
			var context = actionStub.getCall(0).args[1];
			expect(context.$file.is($tr)).toEqual(true);
			expect(context.fileList).toBeDefined();
			expect(context.fileActions).toBeDefined();
			expect(context.dir).toEqual('/subdir');
		});
		it('Clicking on an empty space of the file row will trigger the "Details" action', function() {
			var detailsActionStub = sinon.stub();
			fileList.setFiles(testFiles);
			// Override the "Details" action set internally by the FileList for
			// easier testing.
			fileList.fileActions.registerAction({
				mime: 'all',
				name: 'Details',
				permissions: OC.PERMISSION_NONE,
				actionHandler: detailsActionStub
			});
			// Ensure that the action works even if fileActions.currentFile is
			// not set.
			fileList.fileActions.currentFile = null;
			var $tr = fileList.findFileEl('One.txt');
			$tr.find('td.filesize').click();
			expect(detailsActionStub.calledOnce).toEqual(true);
			expect(detailsActionStub.getCall(0).args[0]).toEqual('One.txt');
			var context = detailsActionStub.getCall(0).args[1];
			expect(context.$file.is($tr)).toEqual(true);
			expect(context.fileList).toBe(fileList);
			expect(context.fileActions).toBe(fileList.fileActions);
			expect(context.dir).toEqual('/subdir');
		});
		it('redisplays actions when new actions have been registered', function() {
			var actionStub = sinon.stub();
			var readyHandler = sinon.stub();
			var clock = sinon.useFakeTimers();
			var debounceStub = sinon.stub(_, 'debounce').callsFake(function(callback) {
				return function() {
					// defer instead of debounce, to make it work with clock
					_.defer(callback);
				};
			});

			// need to reinit the list to make the debounce call
			fileList.destroy();
			fileList = new OCA.Files.FileList($('#app-content-files'));

			fileList.setFiles(testFiles);

			fileList.$fileList.on('fileActionsReady', readyHandler);

			fileList.fileActions.registerAction({
				mime: 'text/plain',
				name: 'Test',
				type: OCA.Files.FileActions.TYPE_INLINE,
				permissions: OC.PERMISSION_ALL,
				icon: function() {
					// Specify icon for hitory button
					return OC.imagePath('core','actions/history');
				},
				actionHandler: actionStub
			});
			var $tr = fileList.findFileEl('One.txt');
			expect($tr.find('.action-test').length).toEqual(0);
			expect(readyHandler.notCalled).toEqual(true);

			// update is delayed
			clock.tick(100);
			expect($tr.find('.action-test').length).toEqual(1);
			expect(readyHandler.calledOnce).toEqual(true);

			clock.restore();
			debounceStub.restore();
		});
	});
	describe('Sorting files', function() {

		var getCurrentUserStub;

		beforeEach(function() {
			getCurrentUserStub = sinon.stub(OC, 'getCurrentUser').returns({
				uid: 1,
				displayName: 'user1'
			});
		});

		afterEach(function() {
			getCurrentUserStub.restore();
		});

		it('Toggles the sort indicator when clicking on a column header', function() {
			var ASC_CLASS = fileList.SORT_INDICATOR_ASC_CLASS;
			var DESC_CLASS = fileList.SORT_INDICATOR_DESC_CLASS;
			var request;
			var sortingUrl = OC.generateUrl('/apps/files/api/v1/sorting');
			fileList.$el.find('.column-size .columntitle').click();
			// moves triangle to size column, check indicator on name is hidden
			expect(
				fileList.$el.find('.column-name .sort-indicator').hasClass('hidden')
			).toEqual(true);
			// check indicator on size is visible and defaults to descending
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass('hidden')
			).toEqual(false);
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass(DESC_CLASS)
			).toEqual(true);
			// check if changes are persisted
			expect(fakeServer.requests.length).toEqual(1);
			request = fakeServer.requests[0];
			expect(request.url).toEqual(sortingUrl);

			// click again on size column, reverses direction
			fileList.$el.find('.column-size .columntitle').click();
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass('hidden')
			).toEqual(false);
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass(ASC_CLASS)
			).toEqual(true);
			// check if changes are persisted
			expect(fakeServer.requests.length).toEqual(2);
			request = fakeServer.requests[1];
			expect(request.url).toEqual(sortingUrl);

			// click again on size column, reverses direction
			fileList.$el.find('.column-size .columntitle').click();
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass('hidden')
			).toEqual(false);
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass(DESC_CLASS)
			).toEqual(true);
			expect(fakeServer.requests.length).toEqual(3);
			request = fakeServer.requests[2];
			expect(request.url).toEqual(sortingUrl);

			// click on mtime column, moves indicator there
			fileList.$el.find('.column-mtime .columntitle').click();
			expect(
				fileList.$el.find('.column-size .sort-indicator').hasClass('hidden')
			).toEqual(true);
			expect(
				fileList.$el.find('.column-mtime .sort-indicator').hasClass('hidden')
			).toEqual(false);
			expect(
				fileList.$el.find('.column-mtime .sort-indicator').hasClass(DESC_CLASS)
			).toEqual(true);
			expect(fakeServer.requests.length).toEqual(4);
			request = fakeServer.requests[3];
			expect(request.url).toEqual(sortingUrl);
		});
		it('Uses correct sort comparator when inserting files', function() {
			testFiles.sort(OCA.Files.FileList.Comparators.size);
			testFiles.reverse();	//default is descending
			fileList.setFiles(testFiles);
			fileList.$el.find('.column-size .columntitle').click();
			var newFileData = new FileInfo({
				id: 999,
				name: 'new file.txt',
				mimetype: 'text/plain',
				size: 40001,
				etag: '999'
			});
			fileList.add(newFileData);
			expect(fileList.findFileEl('Three.pdf').index()).toEqual(0);
			expect(fileList.findFileEl('new file.txt').index()).toEqual(1);
			expect(fileList.findFileEl('Two.jpg').index()).toEqual(2);
			expect(fileList.findFileEl('somedir').index()).toEqual(3);
			expect(fileList.findFileEl('One.txt').index()).toEqual(4);
			expect(fileList.files.length).toEqual(5);
			expect(fileList.$fileList.find('tr').length).toEqual(5);
		});
		it('Uses correct reversed sort comparator when inserting files', function() {
			testFiles.sort(OCA.Files.FileList.Comparators.size);
			fileList.setFiles(testFiles);
			fileList.$el.find('.column-size .columntitle').click();

			// reverse sort
			fileList.$el.find('.column-size .columntitle').click();
			var newFileData = new FileInfo({
				id: 999,
				name: 'new file.txt',
				mimetype: 'text/plain',
				size: 40001,
				etag: '999'
			});
			fileList.add(newFileData);
			expect(fileList.findFileEl('One.txt').index()).toEqual(0);
			expect(fileList.findFileEl('somedir').index()).toEqual(1);
			expect(fileList.findFileEl('Two.jpg').index()).toEqual(2);
			expect(fileList.findFileEl('new file.txt').index()).toEqual(3);
			expect(fileList.findFileEl('Three.pdf').index()).toEqual(4);
			expect(fileList.files.length).toEqual(5);
			expect(fileList.$fileList.find('tr').length).toEqual(5);
		});
		it('does not sort when clicking on header whenever multiselect is enabled', function() {
			var sortStub = sinon.stub(OCA.Files.FileList.prototype, 'setSort');

			fileList.setFiles(testFiles);
			fileList.findFileEl('One.txt').find('input:checkbox:first').click();

			fileList.$el.find('.column-size .columntitle').click();

			expect(sortStub.notCalled).toEqual(true);

			// can sort again after deselecting
			fileList.findFileEl('One.txt').find('input:checkbox:first').click();

			fileList.$el.find('.column-size .columntitle').click();

			expect(sortStub.calledOnce).toEqual(true);

			sortStub.restore();
		});

		describe('if no user logged in', function() {
			beforeEach(function() {
				getCurrentUserStub.returns({
					uid: null,
					displayName: 'Guest'
				});
			});

			it('shouldn\'t send an update sort order request', function() {
				OC.currentUser = false;
				fileList.$el.find('.column-size .columntitle').click();
				// check if there was no request
				expect(fakeServer.requests.length).toEqual(0);
			});
		});

		describe('with favorites', function() {
			it('shows favorite files on top', function() {
				testFiles.push(new FileInfo({
					id: 5,
					type: 'file',
					name: 'ZZY Before last file in ascending order',
					mimetype: 'text/plain',
					mtime: 999999998,
					size: 9999998,
					// Tags would be added by TagsPlugin
					tags: [OC.TAG_FAVORITE],
				}), new FileInfo({
					id: 6,
					type: 'file',
					name: 'ZZZ Last file in ascending order',
					mimetype: 'text/plain',
					mtime: 999999999,
					size: 9999999,
					// Tags would be added by TagsPlugin
					tags: [OC.TAG_FAVORITE],
				}));

				fileList.setFiles(testFiles);

				// Sort by name in ascending order (default sorting is by name
				// in ascending order, but setFiles does not trigger a sort, so
				// the files must be sorted before being set or a sort must be
				// triggered afterwards by clicking on the header).
				fileList.$el.find('.column-name .columntitle').click();
				fileList.$el.find('.column-name .columntitle').click();

				expect(fileList.findFileEl('ZZY Before last file in ascending order').index()).toEqual(0);
				expect(fileList.findFileEl('ZZZ Last file in ascending order').index()).toEqual(1);
				expect(fileList.findFileEl('somedir').index()).toEqual(2);
				expect(fileList.findFileEl('One.txt').index()).toEqual(3);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(4);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(5);

				// Sort by size in ascending order
				fileList.$el.find('.column-size .columntitle').click();
				fileList.$el.find('.column-size .columntitle').click();

				expect(fileList.findFileEl('ZZY Before last file in ascending order').index()).toEqual(0);
				expect(fileList.findFileEl('ZZZ Last file in ascending order').index()).toEqual(1);
				expect(fileList.findFileEl('One.txt').index()).toEqual(2);
				expect(fileList.findFileEl('somedir').index()).toEqual(3);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(4);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(5);

				// Sort by modification time in ascending order
				fileList.$el.find('.column-mtime .columntitle').click();
				fileList.$el.find('.column-mtime .columntitle').click();

				expect(fileList.findFileEl('ZZY Before last file in ascending order').index()).toEqual(0);
				expect(fileList.findFileEl('ZZZ Last file in ascending order').index()).toEqual(1);
				expect(fileList.findFileEl('One.txt').index()).toEqual(2);
				expect(fileList.findFileEl('somedir').index()).toEqual(3);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(4);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(5);
			});
			it('shows favorite files on top also when using descending order', function() {
				testFiles.push(new FileInfo({
					id: 5,
					type: 'file',
					name: 'AAB Before last file in descending order',
					mimetype: 'text/plain',
					mtime: 2,
					size: 2,
					// Tags would be added by TagsPlugin
					tags: [OC.TAG_FAVORITE],
				}), new FileInfo({
					id: 6,
					type: 'file',
					name: 'AAA Last file in descending order',
					mimetype: 'text/plain',
					mtime: 1,
					size: 1,
					// Tags would be added by TagsPlugin
					tags: [OC.TAG_FAVORITE],
				}));

				fileList.setFiles(testFiles);

				// Sort by name in descending order
				fileList.$el.find('.column-name .columntitle').click();

				expect(fileList.findFileEl('AAB Before last file in descending order').index()).toEqual(0);
				expect(fileList.findFileEl('AAA Last file in descending order').index()).toEqual(1);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(2);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(3);
				expect(fileList.findFileEl('One.txt').index()).toEqual(4);
				expect(fileList.findFileEl('somedir').index()).toEqual(5);

				// Sort by size in descending order
				fileList.$el.find('.column-size .columntitle').click();

				expect(fileList.findFileEl('AAB Before last file in descending order').index()).toEqual(0);
				expect(fileList.findFileEl('AAA Last file in descending order').index()).toEqual(1);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(2);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(3);
				expect(fileList.findFileEl('somedir').index()).toEqual(4);
				expect(fileList.findFileEl('One.txt').index()).toEqual(5);

				// Sort by modification time in descending order
				fileList.$el.find('.column-mtime .columntitle').click();

				expect(fileList.findFileEl('AAB Before last file in descending order').index()).toEqual(0);
				expect(fileList.findFileEl('AAA Last file in descending order').index()).toEqual(1);
				expect(fileList.findFileEl('Two.jpg').index()).toEqual(2);
				expect(fileList.findFileEl('Three.pdf').index()).toEqual(3);
				expect(fileList.findFileEl('somedir').index()).toEqual(4);
				expect(fileList.findFileEl('One.txt').index()).toEqual(5);
			});
		});
	});
	describe('create file', function() {
		var deferredCreate;
		var deferredInfo;
		var createStub;
		var getFileInfoStub;

		beforeEach(function() {
			deferredCreate = $.Deferred();
			deferredInfo = $.Deferred();
			createStub = sinon.stub(filesClient, 'putFileContents')
				.returns(deferredCreate.promise());
			getFileInfoStub = sinon.stub(filesClient, 'getFileInfo')
				.returns(deferredInfo.promise());
		});
		afterEach(function() {
			createStub.restore();
			getFileInfoStub.restore();
		});

		it('creates file with given name and adds it to the list', function() {
			fileList.createFile('test.txt');

			expect(createStub.calledOnce).toEqual(true);
			expect(createStub.getCall(0).args[0]).toEqual('/subdir/test.txt');
			expect(createStub.getCall(0).args[2]).toEqual({
				contentType: 'text/plain',
				overwrite: true
			});

			deferredCreate.resolve(200);

			expect(getFileInfoStub.calledOnce).toEqual(true);
			expect(getFileInfoStub.getCall(0).args[0]).toEqual('/subdir/test.txt');

			deferredInfo.resolve(
				200,
				new FileInfo({
					path: '/subdir',
					name: 'test.txt',
					mimetype: 'text/plain'
				})
			);

			var $tr = fileList.findFileEl('test.txt');
			expect($tr.length).toEqual(1);
			expect($tr.attr('data-mime')).toEqual('text/plain');
		});
		// TODO: error cases
		// TODO: unique name cases
	});
	describe('create folder', function() {
		var deferredCreate;
		var deferredInfo;
		var createStub;
		var getFileInfoStub;

		beforeEach(function() {
			deferredCreate = $.Deferred();
			deferredInfo = $.Deferred();
			createStub = sinon.stub(filesClient, 'createDirectory')
				.returns(deferredCreate.promise());
			getFileInfoStub = sinon.stub(filesClient, 'getFileInfo')
				.returns(deferredInfo.promise());
		});
		afterEach(function() {
			createStub.restore();
			getFileInfoStub.restore();
		});

		it('creates folder with given name and adds it to the list', function() {
			fileList.createDirectory('sub dir');

			expect(createStub.calledOnce).toEqual(true);
			expect(createStub.getCall(0).args[0]).toEqual('/subdir/sub dir');

			deferredCreate.resolve(200);

			expect(getFileInfoStub.calledOnce).toEqual(true);
			expect(getFileInfoStub.getCall(0).args[0]).toEqual('/subdir/sub dir');

			deferredInfo.resolve(
				200,
			   	new FileInfo({
					path: '/subdir',
					name: 'sub dir',
					mimetype: 'httpd/unix-directory'
				})
			);

			var $tr = fileList.findFileEl('sub dir');
			expect($tr.length).toEqual(1);
			expect($tr.attr('data-mime')).toEqual('httpd/unix-directory');
		});
		// TODO: error cases
		// TODO: unique name cases
	});
	describe('addAndFetchFileInfo', function() {
		var getFileInfoStub;
		var getFileInfoDeferred;

		beforeEach(function() {
			getFileInfoDeferred = $.Deferred();
			getFileInfoStub = sinon.stub(OC.Files.Client.prototype, 'getFileInfo');
			getFileInfoStub.returns(getFileInfoDeferred.promise());
		});
		afterEach(function() {
			getFileInfoStub.restore();
		});
		it('does not fetch if the given folder is not the current one', function() {
			var promise = fileList.addAndFetchFileInfo('testfile.txt', '/another');
			expect(getFileInfoStub.notCalled).toEqual(true);

			expect(promise.state()).toEqual('resolved');
		});
		it('fetches info when folder is the current one', function() {
			fileList.addAndFetchFileInfo('testfile.txt', '/subdir');
			expect(getFileInfoStub.calledOnce).toEqual(true);
			expect(getFileInfoStub.getCall(0).args[0]).toEqual('/subdir/testfile.txt');
		});
		it('adds file data to list when fetching is done', function() {
			fileList.addAndFetchFileInfo('testfile.txt', '/subdir');
			getFileInfoDeferred.resolve(200, {
				name: 'testfile.txt',
				size: 100
			});
			expect(fileList.findFileEl('testfile.txt').attr('data-size')).toEqual('100');
		});
		it('replaces file data to list when fetching is done', function() {
			fileList.addAndFetchFileInfo('testfile.txt', '/subdir', {replace: true});
			fileList.add({
				name: 'testfile.txt',
				size: 95
			});
			getFileInfoDeferred.resolve(200, {
				name: 'testfile.txt',
				size: 100
			});
			expect(fileList.findFileEl('testfile.txt').attr('data-size')).toEqual('100');
		});
		it('resolves promise with file data when fetching is done', function() {
			var promise = fileList.addAndFetchFileInfo('testfile.txt', '/subdir', {replace: true});
			getFileInfoDeferred.resolve(200, {
				name: 'testfile.txt',
				size: 100
			});
			expect(promise.state()).toEqual('resolved');
			promise.then(function(status, data) {
				expect(status).toEqual(200);
				expect(data.name).toEqual('testfile.txt');
				expect(data.size).toEqual(100);
			});
		});
	});
	/**
	 * Test upload mostly by testing the code inside the event handlers
	 * that were registered on the magic upload object
	 */
	describe('file upload', function() {
		var uploadData;
		var uploader;

		beforeEach(function() {
			fileList.setFiles(testFiles);
			uploader = fileList._uploader;
			// simulate data structure from jquery.upload
			uploadData = {
				files: [{
					name: 'upload.txt'
				}]
			};
		});

		afterEach(function() {
			uploader = null;
			uploadData = null;
		});

		describe('enableupload', function() {
			it('sets up uploader when enableUpload is true', function() {
				expect(fileList._uploader).toBeDefined();
			});
			it('does not sets up uploader when enableUpload is false', function() {
				fileList.destroy();
				fileList = new OCA.Files.FileList($('#app-content-files'), {
					filesClient: filesClient
				});
				expect(fileList._uploader).toBeFalsy();
			});
		});

		describe('adding files for upload', function() {
			/**
			 * Simulate add event on the given target
			 *
			 * @return event object including the result
			 */
			function addFile(data) {
				uploader.trigger('add', {}, data || {});
			}

			it('sets target dir to the current directory', function() {
				addFile(uploadData);
				expect(uploadData.targetDir).toEqual('/subdir');
			});
		});
		describe('dropping external files', function() {

			/**
			 * Simulate drop event on the given target
			 *
			 * @param $target target element to drop on
			 * @return event object including the result
			 */
			function dropOn($target, data) {
				var eventData = {
					delegatedEvent: {
						target: $target
					},
					preventDefault: function () {
					},
					stopPropagation: function() {
					}
				};
				uploader.trigger('drop', eventData, data || {});
				return !!data.targetDir;
			}

				it('drop on a tr or crumb outside file list does not trigger upload', function() {
					var $anotherTable = $('<table><tbody><tr><td>outside<div class="crumb">crumb</div></td></tr></table>');
					var ev;
					$('#testArea').append($anotherTable);
					ev = dropOn($anotherTable.find('tr'), uploadData);
					expect(ev).toEqual(false);

					ev = dropOn($anotherTable.find('.crumb'), uploadData);
					expect(ev).toEqual(false);
				});
			it('drop on an element outside file list container does not trigger upload', function() {
				var $anotherEl = $('<div>outside</div>');
				var ev;
				$('#testArea').append($anotherEl);
				ev = dropOn($anotherEl, uploadData);

				expect(ev).toEqual(false);
			});
			it('drop on an element inside the table triggers upload', function() {
				var ev;
				ev = dropOn(fileList.$fileList.find('th:first'), uploadData);

				expect(ev).not.toEqual(false);
				expect(uploadData.targetDir).toEqual('/subdir');
			});
			it('drop on an element on the table container triggers upload', function() {
				var ev;
				ev = dropOn($('#app-content-files'), uploadData);

				expect(ev).not.toEqual(false);
				expect(uploadData.targetDir).toEqual('/subdir');
			});
			it('drop on an element inside the table does not trigger upload if no upload permission', function() {
				$('#permissions').val(0);
				var ev;
				ev = dropOn(fileList.$fileList.find('th:first'), uploadData);

				expect(ev).toEqual(false);
				expect(notificationStub.calledOnce).toEqual(true);
			});
			it('drop on an folder does not trigger upload if no upload permission on that folder', function() {
				var $tr = fileList.findFileEl('somedir');
				var ev;
				$tr.data('permissions', OC.PERMISSION_READ);
				ev = dropOn($tr, uploadData);

				expect(ev).toEqual(false);
				expect(notificationStub.calledOnce).toEqual(true);
			});
			it('drop on a file row inside the table triggers upload to current folder', function() {
				var ev;
				ev = dropOn(fileList.findFileEl('One.txt').find('td:first'), uploadData);

				expect(ev).not.toEqual(false);
				expect(uploadData.targetDir).toEqual('/subdir');
			});
			it('drop on a folder row inside the table triggers upload to target folder', function() {
				var ev;
				ev = dropOn(fileList.findFileEl('somedir').find('td:eq(2)'), uploadData);

				expect(ev).not.toEqual(false);
				expect(uploadData.targetDir).toEqual('/subdir/somedir');
			});
			it('drop on a breadcrumb inside the table triggers upload to target folder', function() {
				var ev;
				fileList.changeDirectory('a/b/c/d');
				ev = dropOn(fileList.$el.find('.crumb:eq(3)'), uploadData);

				expect(ev).not.toEqual(false);
				expect(uploadData.targetDir).toEqual('/a/b');
			});
			it('renders upload indicator element for folders only', function() {
				fileList.add({
					name: 'afolder',
					type: 'dir',
					mime: 'httpd/unix-directory'
				});
				fileList.add({
					name: 'afile.txt',
					type: 'file',
					mime: 'text/plain'
				});

				expect(fileList.findFileEl('afolder').find('.uploadtext').length).toEqual(1);
				expect(fileList.findFileEl('afile.txt').find('.uploadtext').length).toEqual(0);
			});
		});

		describe('after folder creation due to folder upload', function() {
			it('fetches folder info', function() {
				var fetchInfoStub = sinon.stub(fileList, 'addAndFetchFileInfo');

				uploader.trigger('createdfolder', '/subdir/newfolder');

				expect(fetchInfoStub.calledOnce).toEqual(true);
				expect(fetchInfoStub.getCall(0).args[0]).toEqual('newfolder');
				expect(fetchInfoStub.getCall(0).args[1]).toEqual('/subdir');

				fetchInfoStub.restore();
			});
		});

		describe('after upload', function() {
			var fetchInfoStub;

			beforeEach(function() {
				fetchInfoStub = sinon.stub(fileList, 'addAndFetchFileInfo');

			});
			afterEach(function() {
				fetchInfoStub.restore();
			});


			function createUpload(name, dir) {
				var jqXHR = {
					status: 200
				};
				return {
					getFileName: sinon.stub().returns(name),
					getFullPath: sinon.stub().returns(dir),
					data: {
						jqXHR: jqXHR
					}
				};
			}

			/**
			 * Simulate add event on the given target
			 *
			 * @return event object including the result
			 */
			function addFile(data) {
				var ev = new $.Event('done', {
					jqXHR: {status: 200}
				});
				var deferred = $.Deferred();
				fetchInfoStub.returns(deferred.promise());
				uploader.trigger('done', ev, data || {});
				return deferred;
			}

			it('fetches file info', function() {
				addFile(createUpload('upload.txt', '/subdir'));
				expect(fetchInfoStub.calledOnce).toEqual(true);
				expect(fetchInfoStub.getCall(0).args[0]).toEqual('upload.txt');
				expect(fetchInfoStub.getCall(0).args[1]).toEqual('/subdir');
			});
			it('highlights all uploaded files after all fetches are done', function() {
				var highlightStub = sinon.stub(fileList, 'highlightFiles');
				var def1 = addFile(createUpload('upload.txt', '/subdir'));
				var def2 = addFile(createUpload('upload2.txt', '/subdir'));
				var def3 = addFile(createUpload('upload3.txt', '/another'));
				uploader.trigger('stop', {});

				expect(highlightStub.notCalled).toEqual(true);
				def1.resolve();
				expect(highlightStub.notCalled).toEqual(true);
				def2.resolve();
				def3.resolve();
				expect(highlightStub.calledOnce).toEqual(true);
				expect(highlightStub.getCall(0).args[0]).toEqual(['upload.txt', 'upload2.txt']);

				highlightStub.restore();
			});
			it('queries storage stats after all fetches are done', function() {
				var statStub = sinon.stub(fileList, 'updateStorageStatistics');
				var highlightStub = sinon.stub(fileList, 'highlightFiles');
				var def1 = addFile(createUpload('upload.txt', '/subdir'));
				var def2 = addFile(createUpload('upload2.txt', '/subdir'));
				var def3 = addFile(createUpload('upload3.txt', '/another'));
				uploader.trigger('stop', {});

				expect(statStub.notCalled).toEqual(true);
				def1.resolve();
				expect(statStub.notCalled).toEqual(true);
				def2.resolve();
				def3.resolve();
				expect(statStub.calledOnce).toEqual(true);

				highlightStub.restore();
			});
		});
	});
	describe('Handling errors', function () {
		var deferredList;
		var getFolderContentsStub;

		beforeEach(function() {
			deferredList = $.Deferred();
			getFolderContentsStub =
				sinon.stub(filesClient, 'getFolderContents');
			getFolderContentsStub.onCall(0).returns(deferredList.promise());
			getFolderContentsStub.onCall(1).returns($.Deferred().promise());
			fileList.reload();
		});
		afterEach(function() {
			getFolderContentsStub.restore();
			fileList = undefined;
		});
		it('redirects to root folder in case of forbidden access', function () {
			deferredList.reject(403);

			expect(fileList.getCurrentDirectory()).toEqual('/');
			expect(getFolderContentsStub.calledTwice).toEqual(true);
		});
		it('redirects to root folder and shows notification in case of internal server error', function () {
			expect(notificationStub.notCalled).toEqual(true);
			deferredList.reject(500);

			expect(fileList.getCurrentDirectory()).toEqual('/');
			expect(getFolderContentsStub.calledTwice).toEqual(true);
			expect(notificationStub.calledOnce).toEqual(true);
		});
		it('redirects to root folder and shows notification in case of storage not available', function () {
			expect(notificationStub.notCalled).toEqual(true);
			deferredList.reject(503, 'Storage is temporarily not available');

			expect(fileList.getCurrentDirectory()).toEqual('/');
			expect(getFolderContentsStub.calledTwice).toEqual(true);
			expect(notificationStub.calledOnce).toEqual(true);
		});
	});
	describe('showFileBusyState', function() {
		var $tr;

		beforeEach(function() {
			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('Two.jpg');
		});
		it('shows spinner on busy rows', function() {
			fileList.showFileBusyState('Two.jpg', true);
			expect($tr.hasClass('busy')).toEqual(true);
			expect($tr.find('.thumbnail').parent().attr('class'))
				.toContain('icon-loading-small');


			fileList.showFileBusyState('Two.jpg', false);
			expect($tr.hasClass('busy')).toEqual(false);
			expect(OC.TestUtil.getImageUrl($tr.find('.thumbnail')))
				.toEqual(OC.imagePath('core', 'filetypes/image.svg'));
		});
		it('accepts multiple input formats', function() {
			_.each([
				'Two.jpg',
				['Two.jpg'],
				$tr,
				[$tr]
			], function(testCase) {
				fileList.showFileBusyState(testCase, true);
				expect($tr.hasClass('busy')).toEqual(true);
				fileList.showFileBusyState(testCase, false);
				expect($tr.hasClass('busy')).toEqual(false);
			});
		});
	});
	describe('elementToFile', function() {
		var $tr;

		beforeEach(function() {
			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('One.txt');
		});

		it('converts data attributes to file info structure', function() {
			var fileInfo = fileList.elementToFile($tr);
			expect(fileInfo.id).toEqual(1);
			expect(fileInfo.name).toEqual('One.txt');
			expect(fileInfo.mtime).toEqual(123456789);
			expect(fileInfo.etag).toEqual('abc');
			expect(fileInfo.permissions).toEqual(OC.PERMISSION_ALL);
			expect(fileInfo.size).toEqual(12);
			expect(fileInfo.mimetype).toEqual('text/plain');
			expect(fileInfo.type).toEqual('file');
			expect(fileInfo.path).not.toBeDefined();
			expect(fileInfo.isEncrypted).toEqual(false);
		});
		it('sets isEncrypted attribute if data includes true e2eencrypted', function() {
			testFiles[3].isEncrypted = true;

			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('somedir');

			var fileInfo = fileList.elementToFile($tr);
			expect(fileInfo.isEncrypted).toEqual(true);
		});
		it('adds path attribute if available', function() {
			$tr.attr('data-path', '/subdir');
			var fileInfo = fileList.elementToFile($tr);
			expect(fileInfo.path).toEqual('/subdir');
		});
	});
	describe('new file menu', function() {
		var newFileMenuStub;

		beforeEach(function() {
			newFileMenuStub = sinon.stub(OCA.Files.NewFileMenu.prototype, 'showAt');
		});
		afterEach(function() {
			newFileMenuStub.restore();
		})
		it('renders new button when no legacy upload button exists', function() {
			expect(fileList.$el.find('.button.upload').length).toEqual(0);
			expect(fileList.$el.find('.button.new').length).toEqual(1);
		});
		it('does not render new button when no legacy upload button exists (public page)', function() {
			fileList.destroy();
			$('#controls').append('<input type="button" class="button upload" />');
			fileList = new OCA.Files.FileList($('#app-content-files'));
			expect(fileList.$el.find('.button.upload').length).toEqual(1);
			expect(fileList.$el.find('.button.new').length).toEqual(0);
		});
		it('opens the new file menu when clicking on the "New" button', function() {
			var $button = fileList.$el.find('.button.new');
			$button.click();
			expect(newFileMenuStub.calledOnce).toEqual(true);
		});
		it('does not open the new file menu when button is disabled', function() {
			var $button = fileList.$el.find('.button.new');
			$button.addClass('disabled');
			$button.click();
			expect(newFileMenuStub.notCalled).toEqual(true);
		});
	});
	describe('mount type detection', function() {
		function testMountType(dirInfoId, dirInfoMountType, inputMountType, expectedMountType) {
			var $tr;
			fileList.dirInfo.id = dirInfoId;
			fileList.dirInfo.mountType = dirInfoMountType;
			$tr = fileList.add({
				type: 'dir',
				mimetype: 'httpd/unix-directory',
				name: 'test dir',
				mountType: inputMountType
			});

			expect($tr.attr('data-mounttype')).toEqual(expectedMountType);
		}

		it('leaves mount type as is if no parent exists', function() {
			testMountType(null, null, 'external', 'external');
			testMountType(null, null, 'shared', 'shared');
		});
		it('detects share root if parent exists', function() {
			testMountType(123, null, 'shared', 'shared-root');
			testMountType(123, 'shared', 'shared', 'shared');
			testMountType(123, 'shared-root', 'shared', 'shared');
		});
		it('detects external storage root if parent exists', function() {
			testMountType(123, null, 'external', 'external-root');
			testMountType(123, 'external', 'external', 'external');
			testMountType(123, 'external-root', 'external', 'external');
		});
	});
	describe('file list should not refresh if url does not change', function() {
		var fileListStub;

		beforeEach(function() {
			fileListStub = sinon.stub(OCA.Files.FileList.prototype, 'changeDirectory');
		});
		afterEach(function() {
			fileListStub.restore();
		});
		it('File list must not be refreshed', function() {
			$('#app-content-files').trigger(new $.Event('urlChanged', {dir: '/subdir'}));
			expect(fileListStub.notCalled).toEqual(true);
		});
		it('File list must be refreshed', function() {
			$('#app-content-files').trigger(new $.Event('urlChanged', {dir: '/'}));
			expect(fileListStub.notCalled).toEqual(false);
		});
	});
});
