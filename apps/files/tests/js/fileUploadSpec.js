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

describe('OC.Upload tests', function() {
	var $dummyUploader;
	var testFile;
	var uploader;
	var failStub;
	var progressBarStub;

	beforeEach(function() {
		testFile = {
			name: 'test.txt',
			size: 5000, // 5 KB
			type: 'text/plain',
			lastModifiedDate: new Date()
		};
		// need a dummy button because file-upload checks on it
		$('#testArea').append(
			'<input type="file" id="file_upload_start" name="files[]" multiple="multiple">' +
			'<input type="hidden" id="free_space" name="free_space" value="50000000">' + // 50 MB
			// TODO: handlebars!
			'<div id="new">' +
			'<a>New</a>' +
			'<ul>' +
			'<li data-type="file" data-newname="New text file.txt"><p>Text file</p></li>' +
			'</ul>' +
			'</div>'
		);
		$dummyUploader = $('#file_upload_start');
		progressBarStub = {on: function(){}};
		uploader = new OC.Uploader($dummyUploader, {progressBar: progressBarStub});
		failStub = sinon.stub();
		uploader.on('fail', failStub);
	});
	afterEach(function() {
		$dummyUploader = undefined;
		failStub = undefined;
	});

	/**
	 * Add file for upload
	 * @param {Array.<File>} files array of file data to simulate upload
	 * @return {Array.<Object>} array of uploadinfo or null if add() returned false
	 */
	function addFiles(uploader, files) {
		return _.map(files, function(file) {
			var jqXHR = {status: 200};
			var uploadInfo = {
				originalFiles: files,
				files: [file],
				jqXHR: jqXHR,
				response: sinon.stub().returns(jqXHR),
				submit: sinon.stub(),
				abort: sinon.stub()
			};
			if (uploader.fileUploadParam.add.call(
					$dummyUploader[0],
					{},
					uploadInfo
				)) {
				return uploadInfo;
			}
			return null;
		});
	}

	describe('Adding files for upload', function() {
		it('adds file when size is below limits', function(done) {
			var result = addFiles(uploader, [testFile]);
			expect(result[0]).not.toEqual(null);
			result[0].submit.callsFake(function(){
				expect(result[0].submit.calledOnce).toEqual(true);
				done();
			});
		});
		it('adds file when free space is unknown', function(done) {
			var result;
			$('#free_space').val(-2);

			result = addFiles(uploader, [testFile]);
			expect(result[0]).not.toEqual(null);
			result[0].submit.callsFake(function(){
				expect(result[0].submit.calledOnce).toEqual(true);
				expect(failStub.notCalled).toEqual(true);
				done();
			});
		});
		it('does not add file if it exceeds free space', function(done) {
			var result;
			$('#free_space').val(1000);

			failStub.callsFake(function(){
				expect(failStub.calledOnce).toEqual(true);
				expect(failStub.getCall(0).args[1].textStatus).toEqual('notenoughspace');
				expect(failStub.getCall(0).args[1].errorThrown).toEqual(
					'Not enough free space, you are uploading 5 KB but only 1000 B is left'
				);
				setTimeout(done, 0);
			});
			result = addFiles(uploader, [testFile]);

			expect(result[0]).toEqual(null);
		});
	});
	describe('Upload conflicts', function() {
		var conflictDialogStub;
		var clock;
		var fileList;

		beforeEach(function() {
			$('#testArea').append(
				'<div id="tableContainer">' +
				'<table id="filestable" class="list-container view-grid">' +
				'<thead><tr>' +
				'<th id="headerName" class="hidden column-name">' +
				'<input type="checkbox" id="select_all_files" class="select-all">' +
				'<a class="name columntitle" data-sort="name"><span>Name</span><span class="sort-indicator"></span></a>' +
				'<span id="selectedActionsList" class="selectedActions hidden">' +
				'<a href class="download"><img src="actions/download.svg">Download</a>' +
				'<a href class="delete-selected">Delete</a></span>' +
				'</th>' +
				'<th class="hidden column-size"><a class="columntitle" data-sort="size"><span class="sort-indicator"></span></a></th>' +
				'<th class="hidden column-mtime"><a class="columntitle" data-sort="mtime"><span class="sort-indicator"></span></a></th>' +
				'</tr></thead>' +
				'<tbody id="fileList"></tbody>' +
				'<tfoot></tfoot>' +
				'</table>' +
				'</div>'
			);
			fileList = new OCA.Files.FileList($('#tableContainer'));

			fileList.add({name: 'conflict.txt', mimetype: 'text/plain'});
			fileList.add({name: 'conflict2.txt', mimetype: 'text/plain'});

			conflictDialogStub = sinon.stub(OC.dialogs, 'fileexists');

			uploader = new OC.Uploader($dummyUploader, {
				progressBar: progressBarStub,
				fileList: fileList
			});

			var deferred = $.Deferred();
			conflictDialogStub.returns(deferred.promise());
			deferred.resolve();
		});
		afterEach(function() {
			if (clock) {
				clock.restore();
				clock = undefined
			}

			conflictDialogStub.restore();

			fileList.destroy();
		});
		it('does not show conflict dialog when no client side conflict', function(done) {
			$('#free_space').val(200000);
			var counter = 0;
			var fun = function() {
				counter++;
				if(counter != 2) {
					return;
				}
				expect(result[0].submit.calledOnce).toEqual(true);
				expect(result[1].submit.calledOnce).toEqual(true);
				setTimeout(done, 0);
			};
			var result = addFiles(uploader, [{name: 'noconflict.txt'}, {name: 'noconflict2.txt'}]);
			result[0].submit.callsFake(fun);
			result[1].submit.callsFake(fun);

			expect(conflictDialogStub.notCalled).toEqual(true);

		});
		it('shows conflict dialog when no client side conflict', function(done) {
			var counter = 0;
			conflictDialogStub.callsFake(function(){
				counter++;
				if(counter != 3) {
					return $.Deferred().resolve().promise();
				}
				setTimeout(function() {
					expect(conflictDialogStub.callCount).toEqual(3);
					expect(conflictDialogStub.getCall(1).args[0].getFileName())
						.toEqual('conflict.txt');
					expect(conflictDialogStub.getCall(1).args[1])
						.toEqual({ name: 'conflict.txt', mimetype: 'text/plain', directory: '/' });
					expect(conflictDialogStub.getCall(1).args[2]).toEqual({ name: 'conflict.txt' });

					// yes, the dialog must be called several times...
					expect(conflictDialogStub.getCall(2).args[0].getFileName()).toEqual('conflict2.txt');
					expect(conflictDialogStub.getCall(2).args[1])
						.toEqual({ name: 'conflict2.txt', mimetype: 'text/plain', directory: '/' });
					expect(conflictDialogStub.getCall(2).args[2]).toEqual({ name: 'conflict2.txt' });

					expect(result[0].submit.calledOnce).toEqual(false);
					expect(result[1].submit.calledOnce).toEqual(false);
					expect(result[2].submit.calledOnce).toEqual(true);
					done();
				}, 10);
			});
			var result = addFiles(uploader, [
				{name: 'conflict.txt'},
				{name: 'conflict2.txt'},
				{name: 'noconflict.txt'}
			]);

		});
		it('cancels upload when skipping file in conflict mode', function(done) {
			var fileData = {name: 'conflict.txt'};
			var uploadData = addFiles(uploader, [
				fileData
			]);

			var upload = new OC.FileUpload(uploader, uploadData[0]);
			var deleteStub = sinon.stub(upload, 'deleteUpload');
			deleteStub.callsFake(function(){
				expect(deleteStub.calledOnce).toEqual(true);
				done();
			});

			uploader.onSkip(upload);
		});
		it('overwrites file when choosing replace in conflict mode', function(done) {
			var fileData = {name: 'conflict.txt'};
			var uploadData = addFiles(uploader, [
				fileData
			]);

			expect(uploadData[0].submit.notCalled).toEqual(true);

			var upload = new OC.FileUpload(uploader, uploadData[0]);
			uploadData[0].submit.callsFake(function(){
				expect(upload.getConflictMode()).toEqual(OC.FileUpload.CONFLICT_MODE_OVERWRITE);
				expect(uploadData[0].submit.callCount).toEqual(1);
				done();
			});
			uploader.onReplace(upload);
		});
		it('autorenames file when choosing replace in conflict mode', function(done) {
			var fileData = {name: 'conflict.txt'};
			var uploadData = addFiles(uploader, [
				fileData
			]);

			expect(uploadData[0].submit.notCalled).toEqual(true);

			var upload = new OC.FileUpload(uploader, uploadData[0]);
			var getResponseStatusStub = sinon.stub(upload, 'getResponseStatus');
			var counter = 0;
			uploadData[0].submit.callsFake(function(){
				counter++;
				if(counter===1)
				{
					expect(upload.getConflictMode()).toEqual(OC.FileUpload.CONFLICT_MODE_AUTORENAME);
					expect(upload.getFileName()).toEqual('conflict (2).txt');
					expect(uploadData[0].submit.calledOnce).toEqual(true);
					getResponseStatusStub.returns(412);
					uploader.fileUploadParam.fail.call($dummyUploader[0], {}, uploadData[0]);
				}
				if(counter===2)
				{
					_.defer(function() {
						expect(upload.getFileName()).toEqual('conflict (3).txt');
						expect(uploadData[0].submit.calledTwice).toEqual(true);

						done();
					})
				}
			});

			uploader.onAutorename(upload);

			// in case of server-side conflict, tries to rename again
		});
	});
});
