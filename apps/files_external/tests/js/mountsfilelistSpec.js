/*
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OCA.Files_External.FileList tests', function() {
	var testFiles, alertStub, notificationStub, fileList;

	beforeEach(function() {
		alertStub = sinon.stub(OC.dialogs, 'alert');
		notificationStub = sinon.stub(OC.Notification, 'show');

		// init parameters and test table elements
		$('#testArea').append(
			'<div id="app-content-container">' +
			// init horrible parameters
			'<input type="hidden" id="dir" value="/"></input>' +
			'<input type="hidden" id="permissions" value="31"></input>' +
			// dummy controls
			'<div id="controls">' +
			'   <div class="actions creatable"></div>' +
			'   <div class="notCreatable"></div>' +
			'</div>' +
			// dummy table
			// TODO: at some point this will be rendered by the fileList class itself!
			'<table id="filestable">' +
			'<thead><tr>' +
			'<th id="headerName" class="hidden column-name">' +
			'	<div id="headerName-container">' +
			'		<a class="name sort columntitle" data-sort="name"><span>Name</span><span class="sort-indicator"></span></a>' +
			'	</div>' +
			'</th>' +
			'<th id="headerBackend" class="hidden column-backend">' +
			'	<a class="backend sort columntitle" data-sort="backend"><span>Storage type</span><span class="sort-indicator"></span></a>' +
			'</th>' +
			'<th id="headerScope" class="hidden column-scope column-last">' +
			'	<a class="scope sort columntitle" data-sort="scope"><span>Scope</span><span class="sort-indicator"></span></a>' +
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
		testFiles = undefined;
		fileList.destroy();
		fileList = undefined;

		notificationStub.restore();
		alertStub.restore();
	});

	describe('loading file list for external storages', function() {
		var ocsResponse;
		var reloading;

		beforeEach(function() {
			fileList = new OCA.Files_External.FileList(
				$('#app-content-container')
			);

			reloading = fileList.reload();

			/* jshint camelcase: false */
			ocsResponse = {
				ocs: {
					meta: {
						status: 'ok',
						statuscode: 100,
						message: null
					},
					data: [{
						name: 'smb mount',
						path: '/mount points',
						type: 'dir',
						backend: 'SMB',
						scope: 'personal',
						permissions: OC.PERMISSION_READ | OC.PERMISSION_DELETE
					}, {
						name: 'sftp mount',
						path: '/another mount points',
						type: 'dir',
						backend: 'SFTP',
						scope: 'system',
						permissions: OC.PERMISSION_READ
					}]
				}
			};
		});
		it('render storage list', function(done) {
			var request;
			var $rows;
			var $tr;

			expect(fakeServer.requests.length).toEqual(1);
			request = fakeServer.requests[0];
			expect(request.url).toEqual(
				OC.linkToOCS('apps/files_external/api/v1') +
				'mounts?format=json'
			);

			fakeServer.requests[0].respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify(ocsResponse)
			);

			return reloading.then(function() {
				$rows = fileList.$el.find('tbody tr');
				expect($rows.length).toEqual(2);

				$tr = $rows.eq(0);
				expect($tr.attr('data-id')).not.toBeDefined();
				expect($tr.attr('data-type')).toEqual('dir');
				expect($tr.attr('data-file')).toEqual('sftp mount');
				expect($tr.attr('data-path')).toEqual('/another mount points');
				expect($tr.attr('data-size')).not.toBeDefined();
				expect($tr.attr('data-permissions')).toEqual('1'); // read only
				expect($tr.find('a.name').attr('href')).toEqual(
					OC.getRootPath() +
					'/index.php/apps/files' +
					'?dir=/another%20mount%20points/sftp%20mount'
				);
				expect($tr.find('.nametext').text().trim()).toEqual('sftp mount');
				expect($tr.find('.column-scope > span').text().trim()).toEqual('System');
				expect($tr.find('.column-backend').text().trim()).toEqual('SFTP');

				$tr = $rows.eq(1);
				expect($tr.attr('data-id')).not.toBeDefined();
				expect($tr.attr('data-type')).toEqual('dir');
				expect($tr.attr('data-file')).toEqual('smb mount');
				expect($tr.attr('data-path')).toEqual('/mount points');
				expect($tr.attr('data-size')).not.toBeDefined();
				expect($tr.attr('data-permissions')).toEqual('9'); // read and delete
				expect($tr.find('a.name').attr('href')).toEqual(
					OC.getRootPath() +
					'/index.php/apps/files' +
					'?dir=/mount%20points/smb%20mount'
				);
				expect($tr.find('.nametext').text().trim()).toEqual('smb mount');
				expect($tr.find('.column-scope > span').text().trim()).toEqual('Personal');
				expect($tr.find('.column-backend').text().trim()).toEqual('SMB');
			}).then(done, done);
		});
	});
});
