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

describe('OCA.Sharing.App tests', function() {
	var App = OCA.Sharing.App;
	var fileListIn;
	var fileListOut;

	beforeEach(function() {
		$('#testArea').append(
			'<div id="app-navigation">' +
			'<ul><li data-id="files"><a>Files</a></li>' +
			'<li data-id="sharingin"><a></a></li>' +
			'<li data-id="sharingout"><a></a></li>' +
			'</ul></div>' +
			'<div id="app-content">' +
			'<div id="app-content-files" class="hidden">' +
			'</div>' +
			'<div id="app-content-sharingin" class="hidden">' +
			'</div>' +
			'<div id="app-content-sharingout" class="hidden">' +
			'</div>' +
			'</div>' +
			'</div>'
		);
		fileListIn = App.initSharingIn($('#app-content-sharingin'));
		fileListOut = App.initSharingOut($('#app-content-sharingout'));
	});
	afterEach(function() {
		App.destroy();
	});

	describe('initialization', function() {
		it('inits sharing-in list on show', function() {
			expect(fileListIn._sharedWithUser).toEqual(true);
		});
		it('inits sharing-out list on show', function() {
			expect(fileListOut._sharedWithUser).toBeFalsy();
		});
	});
	describe('file actions', function() {
		it('provides default file actions', function() {
			_.each([fileListIn, fileListOut], function(fileList) {
				var fileActions = fileList.fileActions;

				expect(fileActions.actions.all).toBeDefined();
				expect(fileActions.actions.all.Delete).toBeDefined();
				expect(fileActions.actions.all.Rename).toBeDefined();
				expect(fileActions.actions.all.Download).toBeDefined();

				expect(fileActions.defaults.dir).toEqual('Open');
			});
		});
		it('provides custom file actions', function() {
			var actionStub = sinon.stub();
			// regular file action
			OCA.Files.fileActions.register(
					'all',
					'RegularTest',
					OC.PERMISSION_READ,
					OC.imagePath('core', 'actions/shared'),
					actionStub
			);

			App._inFileList = null;
			fileListIn = App.initSharingIn($('#app-content-sharingin'));

			expect(fileListIn.fileActions.actions.all.RegularTest).toBeDefined();
		});
		it('redirects to files app when opening a directory', function() {
			var oldList = OCA.Files.App.fileList;
			// dummy new list to make sure it exists
			OCA.Files.App.fileList = new OCA.Files.FileList($('<table><thead></thead><tbody></tbody></table>'));

			var setActiveViewStub = sinon.stub(OCA.Files.App, 'setActiveView');
			// create dummy table so we can click the dom
			var $table = '<table><thead></thead><tbody id="fileList"></tbody></table>';
			$('#app-content-sharingin').append($table);

			App._inFileList = null;
			fileListIn = App.initSharingIn($('#app-content-sharingin'));

			fileListIn.setFiles([{
				name: 'testdir',
				type: 'dir',
				path: '/somewhere/inside/subdir',
				counterParts: ['user2'],
				shareOwner: 'user2'
			}]);

			fileListIn.findFileEl('testdir').find('td .nametext').click();

			expect(OCA.Files.App.fileList.getCurrentDirectory()).toEqual('/somewhere/inside/subdir/testdir');

			expect(setActiveViewStub.calledOnce).toEqual(true);
			expect(setActiveViewStub.calledWith('files')).toEqual(true);

			setActiveViewStub.restore();

			// restore old list
			OCA.Files.App.fileList = oldList;
		});
	});
});
