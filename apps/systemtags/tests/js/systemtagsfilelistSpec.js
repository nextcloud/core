/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OCA.SystemTags.FileList tests', function() {
	var FileInfo = OC.Files.FileInfo;
	var fileList;

	beforeEach(function() {
		// init parameters and test table elements
		$('#testArea').append(
			'<div id="app-content-container">' +
			// init horrible parameters
			'<input type="hidden" id="dir" value="/"></input>' +
			'<input type="hidden" id="permissions" value="31"></input>' +
			'<div id="controls"></div>' +
			// dummy table
			// TODO: at some point this will be rendered by the fileList class itself!
			'<table id="filestable">' +
			'<thead><tr>' +
			'<th id="headerName" class="hidden column-name">' +
			'<input type="checkbox" id="select_all_files" class="select-all">' +
			'<a class="name columntitle" data-sort="name"><span>Name</span><span class="sort-indicator"></span></a>' +
			'<span class="selectedActions hidden">' +
			'</th>' +
			'<th class="hidden column-mtime">' +
			'<a class="columntitle" data-sort="mtime"><span class="sort-indicator"></span></a>' +
			'</th>' +
			'</tr></thead>' +
			'<tbody id="fileList"></tbody>' +
			'<tfoot></tfoot>' +
			'</table>' +
			'<div id="emptycontent">Empty content message</div>' +
			'</div>'
		);
	});
	afterEach(function() {
		fileList.destroy();
		fileList = undefined;
	});

	describe('filter field', function() {
		var select2Stub, oldCollection, fetchTagsStub;
		var $tagsField;

		beforeEach(function() {
			fetchTagsStub = sinon.stub(OC.SystemTags.SystemTagsCollection.prototype, 'fetch');
			select2Stub = sinon.stub($.fn, 'select2');
			oldCollection = OC.SystemTags.collection;
			OC.SystemTags.collection = new OC.SystemTags.SystemTagsCollection([
				{
					id: '123',
					name: 'abc'
				},
				{
					id: '456',
					name: 'def'
				}
			]);

			fileList = new OCA.SystemTags.FileList(
				$('#app-content-container'), {
					systemTagIds: []
				}
			);
			$tagsField = fileList.$el.find('[name=tags]');
		});
		afterEach(function() {
			select2Stub.restore();
			fetchTagsStub.restore();
			OC.SystemTags.collection = oldCollection;
		});
		it('inits select2 on filter field', function() {
			expect(select2Stub.calledOnce).toEqual(true);
		});
		it('uses global system tags collection', function() {
			var callback = sinon.stub();
			var opts = select2Stub.firstCall.args[0];

			$tagsField.val('123');

			opts.initSelection($tagsField, callback);

			expect(callback.notCalled).toEqual(true);
			expect(fetchTagsStub.calledOnce).toEqual(true);

			fetchTagsStub.yieldTo('success', fetchTagsStub.thisValues[0]);

			expect(callback.calledOnce).toEqual(true);
			expect(callback.lastCall.args[0]).toEqual([
				OC.SystemTags.collection.get('123').toJSON()
			]);
		});
		it('fetches tag list from the global collection', function() {
			var callback = sinon.stub();
			var opts = select2Stub.firstCall.args[0];

			$tagsField.val('123');

			opts.query({
				term: 'de',
				callback: callback
			});

			expect(fetchTagsStub.calledOnce).toEqual(true);
			expect(callback.notCalled).toEqual(true);
			fetchTagsStub.yieldTo('success', fetchTagsStub.thisValues[0]);

			expect(callback.calledOnce).toEqual(true);
			expect(callback.lastCall.args[0]).toEqual({
				results: [
					OC.SystemTags.collection.get('456').toJSON()
				]
			});
		});
		it('reloads file list after selection', function() {
			var reloadStub = sinon.stub(fileList, 'reload');
			$tagsField.val('456,123').change();
			expect(reloadStub.calledOnce).toEqual(true);
			reloadStub.restore();
		});
		it('updates URL after selection', function() {
			var handler = sinon.stub();
			fileList.$el.on('changeDirectory', handler);
			$tagsField.val('456,123').change();

			expect(handler.calledOnce).toEqual(true);
			expect(handler.lastCall.args[0].dir).toEqual('456/123');
		});
		it('updates tag selection when url changed', function() {
			fileList.$el.trigger(new $.Event('urlChanged', {dir: '456/123'}));

			expect(select2Stub.lastCall.args[0]).toEqual('val');
			expect(select2Stub.lastCall.args[1]).toEqual(['456', '123']);
		});
	});

	describe('loading results', function() {
		var getFilteredFilesSpec, requestDeferred;

		beforeEach(function() {
			requestDeferred = new $.Deferred();
			getFilteredFilesSpec = sinon.stub(OC.Files.Client.prototype, 'getFilteredFiles')
				.returns(requestDeferred.promise());
		});
		afterEach(function() {
			getFilteredFilesSpec.restore();
		});

		it('renders empty message when no tags were set', function() {
			fileList = new OCA.SystemTags.FileList(
				$('#app-content-container'), {
					systemTagIds: []
				}
			);

			fileList.reload();

			expect(fileList.$el.find('#emptycontent').hasClass('hidden')).toEqual(false);

			expect(getFilteredFilesSpec.notCalled).toEqual(true);
		});

		it('render files', function(done) {
			fileList = new OCA.SystemTags.FileList(
				$('#app-content-container'), {
					systemTagIds: ['123', '456']
				}
			);

			var reloading = fileList.reload();

			expect(getFilteredFilesSpec.calledOnce).toEqual(true);
			expect(getFilteredFilesSpec.lastCall.args[0].systemTagIds).toEqual(['123', '456']);

			var testFiles = [new FileInfo({
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

			requestDeferred.resolve(207, testFiles);

			return reloading.then(function() {
				expect(fileList.$el.find('#emptycontent').hasClass('hidden')).toEqual(true);
				expect(fileList.$el.find('tbody>tr').length).toEqual(4);
			}).then(done, done);
		});
	});
});
