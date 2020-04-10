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

describe('OCA.Files.App tests', function() {
	var App = OCA.Files.App;
	var pushStateStub;
	var replaceStateStub;
	var parseUrlQueryStub;

	beforeEach(function() {
		$('#testArea').append(
			'<div id="content" class="app-files">' +
			'<div id="app-navigation">' +
			'<ul><li data-id="files"><a>Files</a></li>' +
			'<li data-id="other"><a>Other</a></li>' +
			'</div>' +
			'<div id="app-content">' +
			'<div id="app-content-files" class="hidden">' +
			'</div>' +
			'<div id="app-content-other" class="hidden">' +
			'</div>' +
			'</div>' +
			'</div>' +
			'</div>'
		);

		OCA.Files.fileActions = new OCA.Files.FileActions();

		pushStateStub = sinon.stub(OC.Util.History, 'pushState');
		replaceStateStub = sinon.stub(OC.Util.History, 'replaceState');
		parseUrlQueryStub = sinon.stub(OC.Util.History, 'parseUrlQuery');
		parseUrlQueryStub.returns({});

		App.initialize();
	});
	afterEach(function() {
		App.destroy();

		pushStateStub.restore();
		replaceStateStub.restore();
		parseUrlQueryStub.restore();
	});

	describe('initialization', function() {
		it('initializes the default file list with the default file actions', function() {
			expect(App.fileList).toBeDefined();
			expect(App.fileList.fileActions.actions.all).toBeDefined();
			expect(App.fileList.$el.is('#app-content-files')).toEqual(true);
		});
	});

	describe('URL handling', function() {
		it('pushes the state to the URL when current app changed directory', function() {
			$('#app-content-files').trigger(new $.Event('changeDirectory', {dir: 'sub dir'}));
			expect(pushStateStub.calledOnce).toEqual(true);
			var params = OC.parseQueryString(pushStateStub.getCall(0).args[0]);
			expect(params.dir).toEqual('sub dir');
			expect(params.view).not.toBeDefined();

			$('li[data-id=other]>a').click();
			pushStateStub.reset();

			$('#app-content-other').trigger(new $.Event('changeDirectory', {dir: 'sub dir'}));
			expect(pushStateStub.calledOnce).toEqual(true);
			params = OC.parseQueryString(pushStateStub.getCall(0).args[0]);
			expect(params.dir).toEqual('sub dir');
			expect(params.view).toEqual('other');
		});
		it('replaces the state to the URL when fileid is known', function() {
			$('#app-content-files').trigger(new $.Event('changeDirectory', {dir: 'sub dir'}));
			expect(pushStateStub.calledOnce).toEqual(true);
			var params = OC.parseQueryString(pushStateStub.getCall(0).args[0]);
			expect(params.dir).toEqual('sub dir');
			expect(params.view).not.toBeDefined();
			expect(replaceStateStub.notCalled).toEqual(true);

			parseUrlQueryStub.returns({dir: 'sub dir'});

			$('#app-content-files').trigger(new $.Event('afterChangeDirectory', {dir: 'sub dir', fileId: 123}));

			expect(pushStateStub.calledOnce).toEqual(true);
			expect(replaceStateStub.calledOnce).toEqual(true);
			params = OC.parseQueryString(replaceStateStub.getCall(0).args[0]);
			expect(params.dir).toEqual('sub dir');
			expect(params.view).not.toBeDefined();
			expect(params.fileid).toEqual('123');
		});
		describe('onpopstate', function() {
			it('sends "urlChanged" event to current app', function() {
				var handler = sinon.stub();
				$('#app-content-files').on('urlChanged', handler);
				App._onPopState({view: 'files', dir: '/somedir'});
				expect(handler.calledOnce).toEqual(true);
				expect(handler.getCall(0).args[0].view).toEqual('files');
				expect(handler.getCall(0).args[0].dir).toEqual('/somedir');
			});
			it('sends "show" event to current app and sets navigation', function() {
				var showHandlerFiles = sinon.stub();
				var showHandlerOther = sinon.stub();
				var hideHandlerFiles = sinon.stub();
				var hideHandlerOther = sinon.stub();
				$('#app-content-files').on('show', showHandlerFiles);
				$('#app-content-files').on('hide', hideHandlerFiles);
				$('#app-content-other').on('show', showHandlerOther);
				$('#app-content-other').on('hide', hideHandlerOther);
				App._onPopState({view: 'other', dir: '/somedir'});
				expect(showHandlerFiles.notCalled).toEqual(true);
				expect(hideHandlerFiles.calledOnce).toEqual(true);
				expect(showHandlerOther.calledOnce).toEqual(true);
				expect(hideHandlerOther.notCalled).toEqual(true);

				showHandlerFiles.reset();
				showHandlerOther.reset();
				hideHandlerFiles.reset();
				hideHandlerOther.reset();

				App._onPopState({view: 'files', dir: '/somedir'});
				expect(showHandlerFiles.calledOnce).toEqual(true);
				expect(hideHandlerFiles.notCalled).toEqual(true);
				expect(showHandlerOther.notCalled).toEqual(true);
				expect(hideHandlerOther.calledOnce).toEqual(true);

				expect(App.navigation.getActiveItem()).toEqual('files');
				expect($('#app-content-files').hasClass('hidden')).toEqual(false);
				expect($('#app-content-other').hasClass('hidden')).toEqual(true);
			});
			it('does not send "show" or "hide" event to current app when already visible', function() {
				var showHandler = sinon.stub();
				var hideHandler = sinon.stub();
				$('#app-content-files').on('show', showHandler);
				$('#app-content-files').on('hide', hideHandler);
				App._onPopState({view: 'files', dir: '/somedir'});
				expect(showHandler.notCalled).toEqual(true);
				expect(hideHandler.notCalled).toEqual(true);
			});
			it('state defaults to files app with root dir', function() {
				var handler = sinon.stub();
				parseUrlQueryStub.returns({});
				$('#app-content-files').on('urlChanged', handler);
				App._onPopState();
				expect(handler.calledOnce).toEqual(true);
				expect(handler.getCall(0).args[0].view).toEqual('files');
				expect(handler.getCall(0).args[0].dir).toEqual('/');
			});
			it('activates files app if invalid view is passed', function() {
				App._onPopState({view: 'invalid', dir: '/somedir'});

				expect(App.navigation.getActiveItem()).toEqual('files');
				expect($('#app-content-files').hasClass('hidden')).toEqual(false);
			});
		});
		describe('navigation', function() {
			it('switches the navigation item and panel visibility when onpopstate', function() {
				App._onPopState({view: 'other', dir: '/somedir'});
				expect(App.navigation.getActiveItem()).toEqual('other');
				expect($('#app-content-files').hasClass('hidden')).toEqual(true);
				expect($('#app-content-other').hasClass('hidden')).toEqual(false);
				expect($('li[data-id=files] > a').hasClass('active')).toEqual(false);
				expect($('li[data-id=other] > a').hasClass('active')).toEqual(true);

				App._onPopState({view: 'files', dir: '/somedir'});

				expect(App.navigation.getActiveItem()).toEqual('files');
				expect($('#app-content-files').hasClass('hidden')).toEqual(false);
				expect($('#app-content-other').hasClass('hidden')).toEqual(true);
				expect($('li[data-id=files] > a').hasClass('active')).toEqual(true);
				expect($('li[data-id=other] > a').hasClass('active')).toEqual(false);
			});
			it('clicking on navigation switches the panel visibility', function() {
				$('li[data-id=other] > a').click();
				expect(App.navigation.getActiveItem()).toEqual('other');
				expect($('#app-content-files').hasClass('hidden')).toEqual(true);
				expect($('#app-content-other').hasClass('hidden')).toEqual(false);
				expect($('li[data-id=files] > a').hasClass('active')).toEqual(false);
				expect($('li[data-id=other] > a').hasClass('active')).toEqual(true);

				$('li[data-id=files] > a').click();
				expect(App.navigation.getActiveItem()).toEqual('files');
				expect($('#app-content-files').hasClass('hidden')).toEqual(false);
				expect($('#app-content-other').hasClass('hidden')).toEqual(true);
				expect($('li[data-id=files] > a').hasClass('active')).toEqual(true);
				expect($('li[data-id=other] > a').hasClass('active')).toEqual(false);
			});
			it('clicking on navigation sends "show" and "urlChanged" event', function() {
				var handler = sinon.stub();
				var showHandler = sinon.stub();
				$('#app-content-other').on('urlChanged', handler);
				$('#app-content-other').on('show', showHandler);
				$('li[data-id=other] > a').click();
				expect(handler.calledOnce).toEqual(true);
				expect(handler.getCall(0).args[0].view).toEqual('other');
				expect(handler.getCall(0).args[0].dir).toEqual('/');
				expect(showHandler.calledOnce).toEqual(true);
			});
			it('clicking on activate navigation only sends "urlChanged" event', function() {
				var handler = sinon.stub();
				var showHandler = sinon.stub();
				$('#app-content-files').on('urlChanged', handler);
				$('#app-content-files').on('show', showHandler);
				$('li[data-id=files] > a').click();
				expect(handler.calledOnce).toEqual(true);
				expect(handler.getCall(0).args[0].view).toEqual('files');
				expect(handler.getCall(0).args[0].dir).toEqual('/');
				expect(showHandler.notCalled).toEqual(true);
			});
		});
		describe('viewer mode', function() {
			it('toggles the sidebar when viewer mode is enabled', function() {
				$('#app-content-files').trigger(
					new $.Event('changeViewerMode', {viewerModeEnabled: true}
				));
				expect($('#app-navigation').hasClass('hidden')).toEqual(true);
				expect($('.app-files').hasClass('viewer-mode no-sidebar')).toEqual(true);

				$('#app-content-files').trigger(
					new $.Event('changeViewerMode', {viewerModeEnabled: false}
				));

				expect($('#app-navigation').hasClass('hidden')).toEqual(false);
				expect($('.app-files').hasClass('viewer-mode no-sidebar')).toEqual(false);
			});
		});
	});
});
