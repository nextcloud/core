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

describe('OCA.Trashbin.FileList tests', function () {
	var testFiles, alertStub, notificationStub, fileList, client;

	beforeEach(function () {
		alertStub = sinon.stub(OC.dialogs, 'alert');
		notificationStub = sinon.stub(OC.Notification, 'show');

		client = new OC.Files.Client({
			host: 'localhost',
			port: 80,
			root: '/remote.php/dav/trashbin/user',
			useHTTPS: OC.getProtocol() === 'https'
		});

		// init parameters and test table elements
		$('#testArea').append(
			'<div id="app-content-trashbin">' +
			// init horrible parameters
			'<input type="hidden" id="dir" value="/"></input>' +
			// set this but it shouldn't be used (could be the one from the
			// files app)
			'<input type="hidden" id="permissions" value="31"></input>' +
			// dummy controls
			'<div id="controls">' +
			'   <div class="actions creatable"></div>' +
			'   <div class="notCreatable"></div>' +
			'</div>' +
			// dummy table
			// TODO: at some point this will be rendered by the fileList class itself!
			'<table id="filestable" class="list-container view-grid">' +
			'<thead><tr><th id="headerName" class="hidden">' +
			'<input type="checkbox" id="select_all_trash" class="select-all">' +
			'<span class="name">Name</span>' +
			'<span class="selectedActions hidden">' +
			'<a href="" class="actions-selected"><span class="icon icon-more"></span><span>Actions</span>' +
			'</span>' +
			'</th></tr></thead>' +
			'<tbody id="fileList"></tbody>' +
			'<tfoot></tfoot>' +
			'</table>' +
			'<div id="emptycontent">Empty content message</div>' +
			'</div>'
		);

		testFiles = [{
			id: 1,
			type: 'file',
			name: 'One.txt.d11111',
			displayName: 'One.txt',
			mtime: 11111000,
			mimetype: 'text/plain',
			etag: 'abc'
		}, {
			id: 2,
			type: 'file',
			name: 'Two.jpg.d22222',
			displayName: 'Two.jpg',
			mtime: 22222000,
			mimetype: 'image/jpeg',
			etag: 'def',
		}, {
			id: 3,
			type: 'file',
			name: 'Three.pdf.d33333',
			displayName: 'Three.pdf',
			mtime: 33333000,
			mimetype: 'application/pdf',
			etag: '123',
		}, {
			id: 4,
			type: 'dir',
			mtime: 99999000,
			name: 'somedir.d99999',
			displayName: 'somedir',
			mimetype: 'httpd/unix-directory',
			etag: '456'
		}];

		// register file actions like the trashbin App does
		var fileActions = OCA.Trashbin.App._createFileActions(fileList);
		fileList = new OCA.Trashbin.FileList(
			$('#app-content-trashbin'), {
				fileActions: fileActions,
				multiSelectMenu: [{
					name: 'restore',
					displayName: t('files', 'Restore'),
					iconClass: 'icon-history',
				},
					{
						name: 'delete',
						displayName: t('files', 'Delete'),
						iconClass: 'icon-delete',
					}
				],
				client: client
			}
		);
	});
	afterEach(function () {
		testFiles = undefined;
		fileList.destroy();
		fileList = undefined;

		$('#dir').remove();
		notificationStub.restore();
		alertStub.restore();
	});
	describe('Initialization', function () {
		it('Sorts by mtime by default', function () {
			expect(fileList._sort).toEqual('mtime');
			expect(fileList._sortDirection).toEqual('asc');
		});
		it('Always returns read and delete permission', function () {
			expect(fileList.getDirectoryPermissions()).toEqual(OC.PERMISSION_READ | OC.PERMISSION_DELETE);
		});
	});
	describe('Breadcrumbs', function () {
		beforeEach(function () {
			var data = {
				status: 'success',
				data: {
					files: testFiles,
					permissions: 1
				}
			};
			fakeServer.respondWith(/\/index\.php\/apps\/files_trashbin\/ajax\/list.php\?dir=%2Fsubdir/, [
				200, {
					"Content-Type": "application/json"
				},
				JSON.stringify(data)
			]);
		});
		it('links the breadcrumb to the trashbin view', function () {
			fileList.changeDirectory('/subdir', false, true);
			fakeServer.respond();
			var $crumbs = fileList.$el.find('#controls .crumb');
			expect($crumbs.length).toEqual(3);
			expect($crumbs.eq(1).find('a').text()).toEqual('Home');
			expect($crumbs.eq(1).find('a').attr('href'))
				.toEqual(OC.getRootPath() + '/index.php/apps/files?view=trashbin&dir=/');
			expect($crumbs.eq(2).find('a').text()).toEqual('subdir');
			expect($crumbs.eq(2).find('a').attr('href'))
				.toEqual(OC.getRootPath() + '/index.php/apps/files?view=trashbin&dir=/subdir');
		});
	});
	describe('Rendering rows', function () {
		it('renders rows with the correct data when in root', function () {
			// dir listing is false when in root
			$('#dir').val('/');
			fileList.setFiles(testFiles);
			var $rows = fileList.$el.find('tbody tr');
			var $tr = $rows.eq(0);
			expect($rows.length).toEqual(4);
			expect($tr.attr('data-id')).toEqual('1');
			expect($tr.attr('data-type')).toEqual('file');
			expect($tr.attr('data-file')).toEqual('One.txt.d11111');
			expect($tr.attr('data-size')).not.toBeDefined();
			expect($tr.attr('data-etag')).toEqual('abc');
			expect($tr.attr('data-permissions')).toEqual('9'); // read and delete
			expect($tr.attr('data-mime')).toEqual('text/plain');
			expect($tr.attr('data-mtime')).toEqual('11111000');
			expect($tr.find('a.name').attr('href')).toEqual('#');

			expect($tr.find('.nametext').text().trim()).toEqual('One.txt');

			expect(fileList.findFileEl('One.txt.d11111')[0]).toEqual($tr[0]);
		});
		it('renders rows with the correct data when in root after calling setFiles with the same data set', function () {
			// dir listing is false when in root
			$('#dir').val('/');
			fileList.setFiles(testFiles);
			fileList.setFiles(fileList.files);
			var $rows = fileList.$el.find('tbody tr');
			var $tr = $rows.eq(0);
			expect($rows.length).toEqual(4);
			expect($tr.attr('data-id')).toEqual('1');
			expect($tr.attr('data-type')).toEqual('file');
			expect($tr.attr('data-file')).toEqual('One.txt.d11111');
			expect($tr.attr('data-size')).not.toBeDefined();
			expect($tr.attr('data-etag')).toEqual('abc');
			expect($tr.attr('data-permissions')).toEqual('9'); // read and delete
			expect($tr.attr('data-mime')).toEqual('text/plain');
			expect($tr.attr('data-mtime')).toEqual('11111000');
			expect($tr.find('a.name').attr('href')).toEqual('#');

			expect($tr.find('.nametext').text().trim()).toEqual('One.txt');

			expect(fileList.findFileEl('One.txt.d11111')[0]).toEqual($tr[0]);
		});
		it('renders rows with the correct data when in subdirectory', function () {
			// dir listing is true when in a subdir
			$('#dir').val('/subdir');

			fileList.setFiles(testFiles.map(function (file) {
				file.name = file.displayName;
				return file;
			}));
			var $rows = fileList.$el.find('tbody tr');
			var $tr = $rows.eq(0);
			expect($rows.length).toEqual(4);
			expect($tr.attr('data-id')).toEqual('1');
			expect($tr.attr('data-type')).toEqual('file');
			expect($tr.attr('data-file')).toEqual('One.txt');
			expect($tr.attr('data-size')).not.toBeDefined();
			expect($tr.attr('data-etag')).toEqual('abc');
			expect($tr.attr('data-permissions')).toEqual('9'); // read and delete
			expect($tr.attr('data-mime')).toEqual('text/plain');
			expect($tr.attr('data-mtime')).toEqual('11111000');
			expect($tr.find('a.name').attr('href')).toEqual('#');

			expect($tr.find('.nametext').text().trim()).toEqual('One.txt');

			expect(fileList.findFileEl('One.txt')[0]).toEqual($tr[0]);
		});
		it('does not render a size column', function () {
			expect(fileList.$el.find('tbody tr .filesize').length).toEqual(0);
		});
	});
	describe('File actions', function () {
		describe('Deleting single files', function () {
			// TODO: checks ajax call
			// TODO: checks spinner
			// TODO: remove item after delete
			// TODO: bring back item if delete failed
		});
		describe('Restoring single files', function () {
			// TODO: checks ajax call
			// TODO: checks spinner
			// TODO: remove item after restore
			// TODO: bring back item if restore failed
		});
	});
	describe('file previews', function () {
		// TODO: check that preview URL is going through files_trashbin
	});
	describe('loading file list', function () {
		// TODO: check that ajax URL is going through files_trashbin
	});
	describe('breadcrumbs', function () {
		// TODO: test label + URL
	});
	describe('elementToFile', function () {
		var $tr;

		beforeEach(function () {
			fileList.setFiles(testFiles);
			$tr = fileList.findFileEl('One.txt.d11111');
		});

		it('converts data attributes to file info structure', function () {
			var fileInfo = fileList.elementToFile($tr);
			expect(fileInfo.id).toEqual(1);
			expect(fileInfo.name).toEqual('One.txt.d11111');
			expect(fileInfo.displayName).toEqual('One.txt');
			expect(fileInfo.mtime).toEqual(11111000);
			expect(fileInfo.etag).toEqual('abc');
			expect(fileInfo.permissions).toEqual(OC.PERMISSION_READ | OC.PERMISSION_DELETE);
			expect(fileInfo.mimetype).toEqual('text/plain');
			expect(fileInfo.type).toEqual('file');
		});
	});
	describe('Global Actions', function () {
		beforeEach(function () {
			fileList.setFiles(testFiles);
			fileList.findFileEl('One.txt.d11111').find('input:checkbox').click();
			fileList.findFileEl('Three.pdf.d33333').find('input:checkbox').click();
			fileList.findFileEl('somedir.d99999').find('input:checkbox').click();
			fileList.$el.find('.actions-selected').click();
		});

		afterEach(function () {
			fileList.$el.find('.actions-selected').click();
		});

		describe('Delete', function () {
			it('Shows trashbin actions', function () {
				// visible because a few files were selected
				expect($('.selectedActions').is(':visible')).toEqual(true);
				expect($('.selectedActions .item-delete').is(':visible')).toEqual(true);
				expect($('.selectedActions .item-restore').is(':visible')).toEqual(true);

				// check
				fileList.$el.find('.select-all').click();

				// stays visible
				expect($('.selectedActions').is(':visible')).toEqual(true);
				expect($('.selectedActions .item-delete').is(':visible')).toEqual(true);
				expect($('.selectedActions .item-restore').is(':visible')).toEqual(true);

				// uncheck
				fileList.$el.find('.select-all').click();

				// becomes hidden now
				expect($('.selectedActions').is(':visible')).toEqual(false);
				expect($('.selectedActions .item-delete').is(':visible')).toEqual(false);
				expect($('.selectedActions .item-restore').is(':visible')).toEqual(false);
			});
			it('Deletes selected files when "Delete" clicked', function (done) {
				var request;
				var promise = fileList._onClickDeleteSelected({
					preventDefault: function () {
					}
				});
				var files = ["One.txt.d11111", "Three.pdf.d33333", "somedir.d99999"];
				expect(fakeServer.requests.length).toEqual(files.length);
				for (var i = 0; i < files.length; i++) {
					request = fakeServer.requests[i];
					expect(request.url).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/trash/' + files[i]);
					request.respond(200);
				}
				return promise.then(function () {
					expect(fileList.findFileEl('One.txt.d11111').length).toEqual(0);
					expect(fileList.findFileEl('Three.pdf.d33333').length).toEqual(0);
					expect(fileList.findFileEl('somedir.d99999').length).toEqual(0);
					expect(fileList.findFileEl('Two.jpg.d22222').length).toEqual(1);
				}).then(done, done);
			});
			it('Deletes all files when all selected when "Delete" clicked', function (done) {
				var request;
				$('.select-all').click();
				var promise = fileList._onClickDeleteSelected({
					preventDefault: function () {
					}
				});
				expect(fakeServer.requests.length).toEqual(1);
				request = fakeServer.requests[0];
				expect(request.url).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/trash');
				request.respond(200);
				return promise.then(function () {
					expect(fileList.isEmpty).toEqual(true);
				}).then(done, done);
			});
		});
		describe('Restore', function () {
			it('Restores selected files when "Restore" clicked', function (done) {
				var request;
				var promise = fileList._onClickRestoreSelected({
					preventDefault: function () {
					}
				});
				var files = ["One.txt.d11111", "Three.pdf.d33333", "somedir.d99999"];
				expect(fakeServer.requests.length).toEqual(files.length);
				for (var i = 0; i < files.length; i++) {
					request = fakeServer.requests[i];
					expect(request.url).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/trash/' + files[i]);
					expect(request.requestHeaders.Destination).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/restore/' + files[i]);
					request.respond(200);
				}
				return promise.then(function() {
					expect(fileList.findFileEl('One.txt.d11111').length).toEqual(0);
					expect(fileList.findFileEl('Three.pdf.d33333').length).toEqual(0);
					expect(fileList.findFileEl('somedir.d99999').length).toEqual(0);
					expect(fileList.findFileEl('Two.jpg.d22222').length).toEqual(1);
				}).then(done, done);
			});
			it('Restores all files when all selected when "Restore" clicked', function (done) {
				var request;
				$('.select-all').click();
				var promise = fileList._onClickRestoreSelected({
					preventDefault: function () {
					}
				});
				var files = ["One.txt.d11111", "Two.jpg.d22222", "Three.pdf.d33333", "somedir.d99999"];
				expect(fakeServer.requests.length).toEqual(files.length);
				for (var i = 0; i < files.length; i++) {
					request = fakeServer.requests[i];
					expect(request.url).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/trash/' + files[i]);
					expect(request.requestHeaders.Destination).toEqual(OC.getRootPath() + '/remote.php/dav/trashbin/user/restore/' + files[i]);
					request.respond(200);
				}
				return promise.then(function() {
					expect(fileList.isEmpty).toEqual(true);
				}).then(done, done);
			});
		});
	});
});
