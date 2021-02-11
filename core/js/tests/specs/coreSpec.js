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

describe('Core base tests', function() {
	afterEach(function() {
		// many tests call window.initCore so need to unregister global events
		// ideally in the future we'll need a window.unloadCore() function
		$(document).off('ajaxError.main');
		$(document).off('unload.main');
		$(document).off('beforeunload.main');
		OC._userIsNavigatingAway = false;
		OC._reloadCalled = false;
	});
	describe('Base values', function() {
		it('Sets webroots', function() {
			expect(OC.getRootPath()).toBeDefined();
			expect(OC.appswebroots).toBeDefined();
		});
	});
	describe('filePath', function() {
		beforeEach(function() {
			OC.webroot = 'http://localhost';
			OC.appswebroots.files = OC.getRootPath() + '/apps3/files';
		});
		afterEach(function() {
			delete OC.appswebroots.files;
		});

		it('Uses a direct link for css and images,' , function() {
			expect(OC.filePath('core', 'css', 'style.css')).toEqual('http://localhost/core/css/style.css');
			expect(OC.filePath('files', 'css', 'style.css')).toEqual('http://localhost/apps3/files/css/style.css');
			expect(OC.filePath('core', 'img', 'image.png')).toEqual('http://localhost/core/img/image.png');
			expect(OC.filePath('files', 'img', 'image.png')).toEqual('http://localhost/apps3/files/img/image.png');
		});
		it('Routes PHP files via index.php,' , function() {
			expect(OC.filePath('core', 'ajax', 'test.php')).toEqual('http://localhost/index.php/core/ajax/test.php');
			expect(OC.filePath('files', 'ajax', 'test.php')).toEqual('http://localhost/index.php/apps/files/ajax/test.php');
		});
	});
	describe('Link functions', function() {
		var TESTAPP = 'testapp';
		var TESTAPP_ROOT = OC.getRootPath() + '/appsx/testapp';

		beforeEach(function() {
			OC.appswebroots[TESTAPP] = TESTAPP_ROOT;
		});
		afterEach(function() {
			// restore original array
			delete OC.appswebroots[TESTAPP];
		});
		it('Generates correct links for core apps', function() {
			expect(OC.linkTo('core', 'somefile.php')).toEqual(OC.getRootPath() + '/core/somefile.php');
			expect(OC.linkTo('admin', 'somefile.php')).toEqual(OC.getRootPath() + '/admin/somefile.php');
		});
		it('Generates correct links for regular apps', function() {
			expect(OC.linkTo(TESTAPP, 'somefile.php')).toEqual(OC.getRootPath() + '/index.php/apps/' + TESTAPP + '/somefile.php');
		});
		it('Generates correct remote links', function() {
			expect(OC.linkToRemote('webdav')).toEqual(window.location.protocol + '//' + window.location.host + OC.getRootPath() + '/remote.php/webdav');
		});
		describe('Images', function() {
			it('Generates image path with given extension', function() {
				expect(OC.imagePath('core', 'somefile.jpg')).toEqual(OC.getRootPath() + '/core/img/somefile.jpg');
				expect(OC.imagePath(TESTAPP, 'somefile.jpg')).toEqual(TESTAPP_ROOT + '/img/somefile.jpg');
			});
			it('Generates image path with svg extension', function() {
				expect(OC.imagePath('core', 'somefile')).toEqual(OC.getRootPath() + '/core/img/somefile.svg');
				expect(OC.imagePath(TESTAPP, 'somefile')).toEqual(TESTAPP_ROOT + '/img/somefile.svg');
			});
		});
	});
	describe('Query string building', function() {
		it('Returns empty string when empty params', function() {
			expect(OC.buildQueryString()).toEqual('');
			expect(OC.buildQueryString({})).toEqual('');
		});
		it('Encodes regular query strings', function() {
			expect(OC.buildQueryString({
				a: 'abc',
				b: 'def'
			})).toEqual('a=abc&b=def');
		});
		it('Encodes special characters', function() {
			expect(OC.buildQueryString({
				unicode: '汉字'
			})).toEqual('unicode=%E6%B1%89%E5%AD%97');
			expect(OC.buildQueryString({
				b: 'spaace value',
				'space key': 'normalvalue',
				'slash/this': 'amp&ersand'
			})).toEqual('b=spaace%20value&space%20key=normalvalue&slash%2Fthis=amp%26ersand');
		});
		it('Encodes data types and empty values', function() {
			expect(OC.buildQueryString({
				'keywithemptystring': '',
				'keywithnull': null,
				'keywithundefined': null,
				something: 'else'
			})).toEqual('keywithemptystring=&keywithnull&keywithundefined&something=else');
			expect(OC.buildQueryString({
				'booleanfalse': false,
				'booleantrue': true
			})).toEqual('booleanfalse=false&booleantrue=true');
			expect(OC.buildQueryString({
				'number': 123
			})).toEqual('number=123');
		});
	});
	describe('Session heartbeat', function() {
		var clock,
			oldConfig,
			counter;

		beforeEach(function() {
			clock = sinon.useFakeTimers();
			oldConfig = OC.config;
			counter = 0;

			fakeServer.autoRespond = true;
			fakeServer.autoRespondAfter = 0;
			fakeServer.respondWith(/\/csrftoken/, function(xhr) {
				counter++;
				xhr.respond(200, {'Content-Type': 'application/json'}, '{"token": "pgBEsb3MzTb1ZPd2mfDZbQ6/0j3OrXHMEZrghHcOkg8=:3khw5PSa+wKQVo4f26exFD3nplud9ECjJ8/Y5zk5/k4="}');
			});
			$(document).off('ajaxComplete'); // ignore previously registered heartbeats
		});
		afterEach(function() {
			clock.restore();
			/* jshint camelcase: false */
			OC.config = oldConfig;
			$(document).off('ajaxError');
			$(document).off('ajaxComplete');
		});
		it('sends heartbeat half the session lifetime when heartbeat enabled', function() {
			/* jshint camelcase: false */
			OC.config = {
				session_keepalive: true,
				session_lifetime: 300
			};
			window.initCore();

			expect(counter).toEqual(0);

			// less than half, still nothing
			clock.tick(100 * 1000);
			expect(counter).toEqual(0);

			// reach past half (160), one call
			clock.tick(55 * 1000);
			expect(counter).toEqual(1);

			// almost there to the next, still one
			clock.tick(140 * 1000);
			expect(counter).toEqual(1);

			// past it, second call
			clock.tick(20 * 1000);
			expect(counter).toEqual(2);
		});
		it('does not send heartbeat when heartbeat disabled', function() {
			/* jshint camelcase: false */
			OC.config = {
				session_keepalive: false,
				session_lifetime: 300
			};
			window.initCore();

			expect(counter).toEqual(0);

			clock.tick(1000000);

			// still nothing
			expect(counter).toEqual(0);
		});
		it('limits the heartbeat between one minute and one day', function() {
			/* jshint camelcase: false */
			var setIntervalStub = sinon.stub(window, 'setInterval');
			OC.config = {
				session_keepalive: true,
				session_lifetime: 5
			};
			window.initCore();
			expect(setIntervalStub.getCall(0).args[1]).toEqual(60 * 1000);
			setIntervalStub.reset();

			OC.config = {
				session_keepalive: true,
				session_lifetime: 48 * 3600
			};
			window.initCore();
			expect(setIntervalStub.getCall(0).args[1]).toEqual(24 * 3600 * 1000);

			setIntervalStub.restore();
		});
	});
	describe('Parse query string', function() {
		it('Parses query string from full URL', function() {
			var query = OC.parseQueryString('http://localhost/stuff.php?q=a&b=x');
			expect(query).toEqual({q: 'a', b: 'x'});
		});
		it('Parses query string from query part alone', function() {
			var query = OC.parseQueryString('q=a&b=x');
			expect(query).toEqual({q: 'a', b: 'x'});
		});
		it('Returns null hash when empty query', function() {
			var query = OC.parseQueryString('');
			expect(query).toEqual(null);
		});
		it('Returns empty hash when empty query with question mark', function() {
			var query = OC.parseQueryString('?');
			expect(query).toEqual({});
		});
		it('Decodes regular query strings', function() {
			var query = OC.parseQueryString('a=abc&b=def');
			expect(query).toEqual({
				a: 'abc',
				b: 'def'
			});
		});
		it('Ignores empty parts', function() {
			var query = OC.parseQueryString('&q=a&&b=x&');
			expect(query).toEqual({q: 'a', b: 'x'});
		});
		it('Ignores lone equal signs', function() {
			var query = OC.parseQueryString('&q=a&=&b=x&');
			expect(query).toEqual({q: 'a', b: 'x'});
		});
		it('Includes extra equal signs in value', function() {
			var query = OC.parseQueryString('u=a=x&q=a=b');
			expect(query).toEqual({u: 'a=x', q: 'a=b'});
		});
		it('Decodes plus as space', function() {
			var query = OC.parseQueryString('space+key=space+value');
			expect(query).toEqual({'space key': 'space value'});
		});
		it('Decodes special characters', function() {
			var query = OC.parseQueryString('unicode=%E6%B1%89%E5%AD%97');
			expect(query).toEqual({unicode: '汉字'});
			query = OC.parseQueryString('b=spaace%20value&space%20key=normalvalue&slash%2Fthis=amp%26ersand');
			expect(query).toEqual({
				b: 'spaace value',
				'space key': 'normalvalue',
				'slash/this': 'amp&ersand'
			});
		});
		it('Decodes empty values', function() {
			var query = OC.parseQueryString('keywithemptystring=&keywithnostring');
			expect(query).toEqual({
				'keywithemptystring': '',
				'keywithnostring': null
			});
		});
		it('Does not interpret data types', function() {
			var query = OC.parseQueryString('booleanfalse=false&booleantrue=true&number=123');
			expect(query).toEqual({
				'booleanfalse': 'false',
				'booleantrue': 'true',
				'number': '123'
			});
		});
	});
	describe('Generate Url', function() {
		it('returns absolute urls', function() {
			expect(OC.generateUrl('csrftoken')).toEqual(OC.getRootPath() + '/index.php/csrftoken');
			expect(OC.generateUrl('/csrftoken')).toEqual(OC.getRootPath() + '/index.php/csrftoken');
		});
		it('substitutes parameters which are escaped by default', function() {
			expect(OC.generateUrl('apps/files/download/{file}', {file: '<">ImAnUnescapedString/!'})).toEqual(OC.getRootPath() + '/index.php/apps/files/download/%3C%22%3EImAnUnescapedString%2F!');
		});
		it('substitutes parameters which can also be unescaped via option flag', function() {
			expect(OC.generateUrl('apps/files/download/{file}', {file: 'subfolder/Welcome.txt'}, {escape: false})).toEqual(OC.getRootPath() + '/index.php/apps/files/download/subfolder/Welcome.txt');
		});
		it('substitutes multiple parameters which are escaped by default', function() {
			expect(OC.generateUrl('apps/files/download/{file}/{id}', {file: '<">ImAnUnescapedString/!', id: 5})).toEqual(OC.getRootPath() + '/index.php/apps/files/download/%3C%22%3EImAnUnescapedString%2F!/5');
		});
		it('substitutes multiple parameters which can also be unescaped via option flag', function() {
			expect(OC.generateUrl('apps/files/download/{file}/{id}', {file: 'subfolder/Welcome.txt', id: 5}, {escape: false})).toEqual(OC.getRootPath() + '/index.php/apps/files/download/subfolder/Welcome.txt/5');
		});
		it('doesnt error out with no params provided', function  () {
			expect(OC.generateUrl('apps/files/download{file}')).toEqual(OC.getRootPath() + '/index.php/apps/files/download%7Bfile%7D');
		});
	});
	describe('Main menu mobile toggle', function() {
		var clock;
		var $toggle;
		var $navigation;

		beforeEach(function() {
			jQuery.fx.off = true;
			clock = sinon.useFakeTimers();
			$('#testArea').append('<div id="header">' +
				'<a class="menutoggle header-appname-container" href="#">' +
				'<h1 class="header-appname"></h1>' +
				'<div class="icon-caret"></div>' +
				'</a>' +
				'</div>' +
				'<div id="navigation"></div>');
			$toggle = $('#header').find('.menutoggle');
			$navigation = $('#navigation');
		});
		afterEach(function() {
			jQuery.fx.off = false;
			clock.restore();
			$(document).off('ajaxError');
		});
		it('Sets up menu toggle', function() {
			window.initCore();
			expect($navigation.hasClass('menu')).toEqual(true);
		});
		it('Clicking menu toggle toggles navigation in', function() {
			window.initCore();
			// fore show more apps icon since otherwise it would be hidden since no icons are available
			clock.tick(1 * 1000);
			$('#more-apps').show();

			expect($navigation.is(':visible')).toEqual(false);
			$toggle.click();
			clock.tick(1 * 1000);
			expect($navigation.is(':visible')).toEqual(true);
			$toggle.click();
			clock.tick(1 * 1000);
			expect($navigation.is(':visible')).toEqual(false);
		});
	});
	describe('Util', function() {
		describe('computerFileSize', function() {
			it('correctly parses file sizes from a human readable formated string', function() {
				var data = [
					['125', 125],
					['125.25', 125],
					['125.25B', 125],
					['125.25 B', 125],
					['0 B', 0],
					['99999999999999999999999999999999999999999999 B', 99999999999999999999999999999999999999999999],
					['0 MB', 0],
					['0 kB', 0],
					['0kB', 0],
					['125 B', 125],
					['125b', 125],
					['125 KB', 128000],
					['125kb', 128000],
					['122.1 MB', 128031130],
					['122.1mb', 128031130],
					['119.2 GB', 127990025421],
					['119.2gb', 127990025421],
					['116.4 TB', 127983153473126],
					['116.4tb', 127983153473126],
					['8776656778888777655.4tb', 9.650036181387265e+30],
					[1234, null],
					[-1234, null],
					['-1234 B', null],
					['B', null],
					['40/0', null],
					['40,30 kb', null],
					[' 122.1 MB ', 128031130],
					['122.1 MB ', 128031130],
					[' 122.1 MB ', 128031130],
					['	122.1 MB ', 128031130],
					['122.1    MB ', 128031130],
					[' 125', 125],
					[' 125 ', 125],
				];
				for (var i = 0; i < data.length; i++) {
					expect(OC.Util.computerFileSize(data[i][0])).toEqual(data[i][1]);
				}
			});
			it('returns null if the parameter is not a string', function() {
				expect(OC.Util.computerFileSize(NaN)).toEqual(null);
				expect(OC.Util.computerFileSize(125)).toEqual(null);
			});
			it('returns null if the string is unparsable', function() {
				expect(OC.Util.computerFileSize('')).toEqual(null);
				expect(OC.Util.computerFileSize('foobar')).toEqual(null);
			});
		});
		describe('stripTime', function() {
			it('strips time from dates', function() {
				expect(OC.Util.stripTime(new Date(2014, 2, 24, 15, 4, 45, 24)))
					.toEqual(new Date(2014, 2, 24, 0, 0, 0, 0));
			});
		});
	});
	describe('naturalSortCompare', function() {
		// cit() will skip tests if running on PhantomJS because it has issues with
		// localeCompare(). See https://github.com/ariya/phantomjs/issues/11063
		//
		// Please make sure to run these tests in Chrome/Firefox manually
		// to make sure they run.
		var cit = window.isPhantom?xit:it;

		// must provide the same results as \OC_Util::naturalSortCompare
		it('sorts alphabetically', function() {
			var a = [
				'def',
				'xyz',
				'abc'
			];
			a.sort(OC.Util.naturalSortCompare);
			expect(a).toEqual([
				'abc',
				'def',
				'xyz'
			]);
		});
		cit('sorts with different casing', function() {
			var a = [
				'aaa',
				'bbb',
				'BBB',
				'AAA'
			];
			a.sort(OC.Util.naturalSortCompare);
			expect(a).toEqual([
				'aaa',
				'AAA',
				'bbb',
				'BBB'
			]);
		});
		it('sorts with numbers', function() {
			var a = [
				'124.txt',
				'abc1',
				'123.txt',
				'abc',
				'abc2',
				'def (2).txt',
				'ghi 10.txt',
				'abc12',
				'def.txt',
				'def (1).txt',
				'ghi 2.txt',
				'def (10).txt',
				'abc10',
				'def (12).txt',
				'z',
				'ghi.txt',
				'za',
				'ghi 1.txt',
				'ghi 12.txt',
				'zz',
				'15.txt',
				'15b.txt'
			];
			a.sort(OC.Util.naturalSortCompare);
			expect(a).toEqual([
				'15.txt',
				'15b.txt',
				'123.txt',
				'124.txt',
				'abc',
				'abc1',
				'abc2',
				'abc10',
				'abc12',
				'def.txt',
				'def (1).txt',
				'def (2).txt',
				'def (10).txt',
				'def (12).txt',
				'ghi.txt',
				'ghi 1.txt',
				'ghi 2.txt',
				'ghi 10.txt',
				'ghi 12.txt',
				'z',
				'za',
				'zz'
			]);
		});
		it('sorts with chinese characters', function() {
			var a = [
				'十.txt',
				'一.txt',
				'二.txt',
				'十 2.txt',
				'三.txt',
				'四.txt',
				'abc.txt',
				'五.txt',
				'七.txt',
				'八.txt',
				'九.txt',
				'六.txt',
				'十一.txt',
				'波.txt',
				'破.txt',
				'莫.txt',
				'啊.txt',
				'123.txt'
			];
			a.sort(OC.Util.naturalSortCompare);
			expect(a).toEqual([
				'123.txt',
				'abc.txt',
				'一.txt',
				'七.txt',
				'三.txt',
				'九.txt',
				'二.txt',
				'五.txt',
				'八.txt',
				'六.txt',
				'十.txt',
				'十 2.txt',
				'十一.txt',
				'啊.txt',
				'四.txt',
				'波.txt',
				'破.txt',
				'莫.txt'
			]);
		});
		cit('sorts with umlauts', function() {
			var a = [
				'öh.txt',
				'Äh.txt',
				'oh.txt',
				'Üh 2.txt',
				'Üh.txt',
				'ah.txt',
				'Öh.txt',
				'uh.txt',
				'üh.txt',
				'äh.txt'
			];
			a.sort(OC.Util.naturalSortCompare);
			expect(a).toEqual([
				'ah.txt',
				'äh.txt',
				'Äh.txt',
				'oh.txt',
				'öh.txt',
				'Öh.txt',
				'uh.txt',
				'üh.txt',
				'Üh.txt',
				'Üh 2.txt'
			]);
		});
	});
	describe('Plugins', function() {
		var plugin;

		beforeEach(function() {
			plugin = {
				name: 'Some name',
				attach: function(obj) {
					obj.attached = true;
				},

				detach: function(obj) {
					obj.attached = false;
				}
			};
			OC.Plugins.register('OC.Test.SomeName', plugin);
		});
		it('attach plugin to object', function() {
			var obj = {something: true};
			OC.Plugins.attach('OC.Test.SomeName', obj);
			expect(obj.attached).toEqual(true);
			OC.Plugins.detach('OC.Test.SomeName', obj);
			expect(obj.attached).toEqual(false);
		});
		it('only call handler for target name', function() {
			var obj = {something: true};
			OC.Plugins.attach('OC.Test.SomeOtherName', obj);
			expect(obj.attached).not.toBeDefined();
			OC.Plugins.detach('OC.Test.SomeOtherName', obj);
			expect(obj.attached).not.toBeDefined();
		});
	});
	describe('Notifications', function() {
		var showHtmlSpy;
		var clock;

		/**
		 * Returns the HTML or plain text of the given notification row.
		 *
		 * This is needed to ignore the close button that is added to the
		 * notification row after the text.
		 */
		var getNotificationText = function($node) {
			return $node.contents()[0].outerHTML ||
					$node.contents()[0].nodeValue;
		}

		beforeEach(function() {
			clock = sinon.useFakeTimers();
		});
		afterEach(function() {
			// jump past animations
			clock.tick(10000);
			clock.restore();
			$('body .toastify').remove();
		});
		describe('showTemporary', function() {
			it('shows a plain text notification with default timeout', function() {
				OC.Notification.showTemporary('My notification test');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);
				expect(getNotificationText($row)).toEqual('My notification test');
			});
			it('shows a HTML notification with default timeout', function() {
				OC.Notification.showTemporary('<a>My notification test</a>', { isHTML: true });

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);
				expect(getNotificationText($row)).toEqual('<a>My notification test</a>');
			});
			it('hides itself after 7 seconds', function() {
				OC.Notification.showTemporary('');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				// travel in time +7000 milliseconds
				clock.tick(7500);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);
			});
			it('hides itself after a given time', function() {
				OC.Notification.showTemporary('', {timeout: 10000});

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				// travel in time +7000 milliseconds
				clock.tick(7500);

				$row = $('body .toastify');
				expect($row.length).toEqual(1);

				// travel in time another 4000 milliseconds
				clock.tick(4000);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);
			});
		});
		describe('show', function() {
			it('hides itself after a given time', function() {
				OC.Notification.show('', {timeout: 10000});

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				clock.tick(11500);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);
			});
			it('does not hide itself if no timeout given to show', function() {
				OC.Notification.show('');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				// travel in time +1000 seconds
				clock.tick(1000000);

				$row = $('body .toastify');
				expect($row.length).toEqual(1);
			});
		});
		describe('showHtml', function() {
			it('hides itself after a given time', function() {
				OC.Notification.showHtml('<p></p>', {timeout: 10000});

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				clock.tick(11500);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);
			});
			it('does not hide itself if no timeout given to show', function() {
				OC.Notification.showHtml('<p></p>');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				// travel in time +1000 seconds
				clock.tick(1000000);

				$row = $('body .toastify');
				expect($row.length).toEqual(1);
			});
		});
		describe('hide', function() {
			it('hides a temporary notification before its timeout expires', function() {
				var hideCallback = sinon.spy();

				var notification = OC.Notification.showTemporary('');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				OC.Notification.hide(notification, hideCallback);

				// Give time to the hide animation to finish
				clock.tick(1000);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);

				expect(hideCallback.calledOnce).toEqual(true);
			});
			it('hides a notification before its timeout expires', function() {
				var hideCallback = sinon.spy();

				var notification = OC.Notification.show('', {timeout: 10000});

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				OC.Notification.hide(notification, hideCallback);

				// Give time to the hide animation to finish
				clock.tick(1000);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);

				expect(hideCallback.calledOnce).toEqual(true);
			});
			it('hides a notification without timeout', function() {
				var hideCallback = sinon.spy();

				var notification = OC.Notification.show('');

				var $row = $('body .toastify');
				expect($row.length).toEqual(1);

				OC.Notification.hide(notification, hideCallback);

				// Give time to the hide animation to finish
				clock.tick(1000);

				$row = $('body .toastify');
				expect($row.length).toEqual(0);

				expect(hideCallback.calledOnce).toEqual(true);
			});
		});
		it('cumulates several notifications', function() {
			var $row1 = OC.Notification.showTemporary('One');
			var $row2 = OC.Notification.showTemporary('Two', {timeout: 2000});
			var $row3 = OC.Notification.showTemporary('Three');

			var $el = $('body');
			var $rows = $el.find('.toastify');
			expect($rows.length).toEqual(3);

			expect($rows.eq(0).is($row3)).toEqual(true);
			expect($rows.eq(1).is($row2)).toEqual(true);
			expect($rows.eq(2).is($row1)).toEqual(true);

			clock.tick(3000);

			$rows = $el.find('.toastify');
			expect($rows.length).toEqual(2);

			expect($rows.eq(0).is($row3)).toEqual(true);
			expect($rows.eq(1).is($row1)).toEqual(true);
		});
		it('hides the given notification when calling hide with argument', function() {
			var $row1 = OC.Notification.show('One');
			var $row2 = OC.Notification.show('Two');

			var $el = $('body');
			var $rows = $el.find('.toastify');
			expect($rows.length).toEqual(2);

			OC.Notification.hide($row2);
			clock.tick(3000);

			$rows = $el.find('.toastify');
			expect($rows.length).toEqual(1);
			expect($rows.eq(0).is($row1)).toEqual(true);
		});
	});
	describe('global ajax errors', function() {
		var reloadStub, ajaxErrorStub, clock;
		var notificationStub;
		var waitTimeMs = 6500;
		var oldCurrentUser;

		beforeEach(function() {
			oldCurrentUser = OC.currentUser;
			OC.currentUser = 'dummy';
			clock = sinon.useFakeTimers();
			reloadStub = sinon.stub(OC, 'reload');
			notificationStub = sinon.stub(OC.Notification, 'show');
			// unstub the error processing method
			ajaxErrorStub = OC._processAjaxError;
			ajaxErrorStub.restore();
			window.initCore();
		});
		afterEach(function() {
			OC.currentUser = oldCurrentUser;
			reloadStub.restore();
			notificationStub.restore();
			clock.restore();
		});

		it('reloads current page in case of auth error', function() {
			var dataProvider = [
				[200, false],
				[400, false],
				[0, false],
				[401, true],
				[302, true],
				[303, true],
				[307, true]
			];

			for (var i = 0; i < dataProvider.length; i++) {
				var xhr = { status: dataProvider[i][0] };
				var expectedCall = dataProvider[i][1];

				reloadStub.reset();
				OC._reloadCalled = false;

				$(document).trigger(new $.Event('ajaxError'), xhr);

				// trigger timers
				clock.tick(waitTimeMs);

				if (expectedCall) {
					expect(reloadStub.calledOnce).toEqual(true);
				} else {
					expect(reloadStub.notCalled).toEqual(true);
				}
			}
		});
		it('reload only called once in case of auth error', function() {
			var xhr = { status: 401 };

			$(document).trigger(new $.Event('ajaxError'), xhr);
			$(document).trigger(new $.Event('ajaxError'), xhr);

			// trigger timers
			clock.tick(waitTimeMs);

			expect(reloadStub.calledOnce).toEqual(true);
		});
		it('does not reload the page if the user was navigating away', function() {
			var xhr = { status: 0 };
			OC._userIsNavigatingAway = true;
			clock.tick(100);

			$(document).trigger(new $.Event('ajaxError'), xhr);

			clock.tick(waitTimeMs);
			expect(reloadStub.notCalled).toEqual(true);
		});
		it('displays notification', function() {
			var xhr = { status: 401 };

			notificationUpdateStub = sinon.stub(OC.Notification, 'showUpdate');

			$(document).trigger(new $.Event('ajaxError'), xhr);

			clock.tick(waitTimeMs);
			expect(notificationUpdateStub.notCalled).toEqual(false);
		});
		it('shows a temporary notification if the connection is lost', function() {
			var xhr = { status: 0 };
			spyOn(OC, '_ajaxConnectionLostHandler');

			$(document).trigger(new $.Event('ajaxError'), xhr);
			clock.tick(101);

			expect(OC._ajaxConnectionLostHandler.calls.count()).toBe(1);
		});
	});
	describe('Snapper', function() {
		var snapConstructorStub;
		var snapperStub;
		var clock;

		beforeEach(function() {
			snapConstructorStub = sinon.stub(window, 'Snap');
			snapperStub = {};

			snapperStub.enable = sinon.stub();
			snapperStub.disable = sinon.stub();
			snapperStub.close = sinon.stub();
			snapperStub.on = sinon.stub();
			snapperStub.state = sinon.stub().returns({
				state: sinon.stub()
			});

			snapConstructorStub.returns(snapperStub);

			clock = sinon.useFakeTimers();

			// _.now could have been set to Date.now before Sinon replaced it
			// with a fake version, so _.now must be stubbed to ensure that the
			// fake Date.now will be called instead of the original one.
			_.now = sinon.stub(_, 'now').callsFake(function() {
				return new Date().getTime();
			});

			$('#testArea').append('<div id="app-navigation">The navigation bar</div><div id="app-content">Content</div>');
		});
		afterEach(function() {
			snapConstructorStub.restore();

			clock.restore();

			_.now.restore();

			// Remove the event handler for the resize event added to the window
			// due to calling window.initCore() when there is an #app-navigation
			// element.
			$(window).off('resize');

			viewport.reset();
		});

		it('is enabled on a narrow screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapConstructorStub.calledOnce).toBe(true);
			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
		});
		it('is disabled when disallowing the gesture on a narrow screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.disable.alwaysCalledWithExactly(true)).toBe(true);
			expect(snapperStub.close.called).toBe(false);
		});
		it('is not disabled again when disallowing the gesture twice on a narrow screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.disable.alwaysCalledWithExactly(true)).toBe(true);
			expect(snapperStub.close.called).toBe(false);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.called).toBe(false);
		});
		it('is enabled when allowing the gesture after disallowing it on a narrow screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.disable.alwaysCalledWithExactly(true)).toBe(true);
			expect(snapperStub.close.called).toBe(false);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledTwice).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.called).toBe(false);
		});
		it('is not enabled again when allowing the gesture twice after disallowing it on a narrow screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.disable.alwaysCalledWithExactly(true)).toBe(true);
			expect(snapperStub.close.called).toBe(false);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledTwice).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.called).toBe(false);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledTwice).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.called).toBe(false);
		});
		it('is disabled on a wide screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapConstructorStub.calledOnce).toBe(true);
			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
		});
		it('is not disabled again when disallowing the gesture on a wide screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);
		});
		it('is not enabled when allowing the gesture after disallowing it on a wide screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);
		});
		it('is enabled when resizing to a narrow screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
		});
		it('is not enabled when resizing to a narrow screen after disallowing the gesture', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
		});
		it('is enabled when resizing to a narrow screen after disallowing the gesture and allowing it', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
		});
		it('is enabled when allowing the gesture after disallowing it and resizing to a narrow screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
		});
		it('is disabled when disallowing the gesture after disallowing it, resizing to a narrow screen and allowing it', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledTwice).toBe(true);
			expect(snapperStub.disable.getCall(1).calledWithExactly(true)).toBe(true);
		});
		it('is disabled when resizing to a wide screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			viewport.set(1280);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);
		});
		it('is not disabled again when disallowing the gesture after resizing to a wide screen', function() {
			viewport.set(480);

			window.initCore();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.called).toBe(false);
			expect(snapperStub.close.called).toBe(false);

			viewport.set(1280);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.calledOnce).toBe(true);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);
		});
		it('is not enabled when allowing the gesture after disallowing it, resizing to a narrow screen and resizing to a wide screen', function() {
			viewport.set(1280);

			window.initCore();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			OC.disallowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			viewport.set(480);

			// Setting the viewport width does not automatically trigger a
			// resize.
			$(window).resize();

			// The resize handler is debounced to be executed a few milliseconds
			// after the resize event.
			clock.tick(1000);

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledOnce).toBe(true);
			expect(snapperStub.close.calledOnce).toBe(true);

			viewport.set(1280);

			$(window).resize();

			clock.tick(1000);

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledTwice).toBe(true);
			expect(snapperStub.close.calledTwice).toBe(true);

			OC.allowNavigationBarSlideGesture();

			expect(snapperStub.enable.called).toBe(false);
			expect(snapperStub.disable.calledTwice).toBe(true);
			expect(snapperStub.close.calledTwice).toBe(true);
		});
	});
	describe('Requires password confirmation', function () {
		var stubMomentNow;
		var stubJsPageLoadTime;

		afterEach(function () {
			delete window.nc_pageLoad;
			delete window.nc_lastLogin;
			delete window.backendAllowsPasswordConfirmation;

			stubMomentNow.restore();
			stubJsPageLoadTime.restore();
		});

		it('should not show the password confirmation dialog when server time is earlier than local time', function () {
			// add server variables
			window.nc_pageLoad = parseInt(new Date(2018, 0, 3, 1, 15, 0).getTime() / 1000);
			window.nc_lastLogin = parseInt(new Date(2018, 0, 3, 1, 0, 0).getTime() / 1000);
			window.backendAllowsPasswordConfirmation = true;

			stubJsPageLoadTime = sinon.stub(OC.PasswordConfirmation, 'pageLoadTime').value(new Date(2018, 0, 3, 12, 15, 0).getTime());
			stubMomentNow = sinon.stub(moment, 'now').returns(new Date(2018, 0, 3, 12, 20, 0).getTime());

			expect(OC.PasswordConfirmation.requiresPasswordConfirmation()).toBeFalsy();
		});

		it('should show the password confirmation dialog when server time is earlier than local time', function () {
			// add server variables
			window.nc_pageLoad = parseInt(new Date(2018, 0, 3, 1, 15, 0).getTime() / 1000);
			window.nc_lastLogin = parseInt(new Date(2018, 0, 3, 1, 0, 0).getTime() / 1000);
			window.backendAllowsPasswordConfirmation = true;

			stubJsPageLoadTime = sinon.stub(OC.PasswordConfirmation, 'pageLoadTime').value(new Date(2018, 0, 3, 12, 15, 0).getTime());
			stubMomentNow = sinon.stub(moment, 'now').returns(new Date(2018, 0, 3, 12, 31, 0).getTime());

			expect(OC.PasswordConfirmation.requiresPasswordConfirmation()).toBeTruthy();
		});

		it('should not show the password confirmation dialog when server time is later than local time', function () {
			// add server variables
			window.nc_pageLoad = parseInt(new Date(2018, 0, 3, 23, 15, 0).getTime() / 1000);
			window.nc_lastLogin = parseInt(new Date(2018, 0, 3, 23, 0, 0).getTime() / 1000);
			window.backendAllowsPasswordConfirmation = true;

			stubJsPageLoadTime = sinon.stub(OC.PasswordConfirmation, 'pageLoadTime').value(new Date(2018, 0, 3, 12, 15, 0).getTime());
			stubMomentNow = sinon.stub(moment, 'now').returns(new Date(2018, 0, 3, 12, 20, 0).getTime());

			expect(OC.PasswordConfirmation.requiresPasswordConfirmation()).toBeFalsy();
		});

		it('should show the password confirmation dialog when server time is later than local time', function () {
			// add server variables
			window.nc_pageLoad = parseInt(new Date(2018, 0, 3, 23, 15, 0).getTime() / 1000);
			window.nc_lastLogin = parseInt(new Date(2018, 0, 3, 23, 0, 0).getTime() / 1000);
			window.backendAllowsPasswordConfirmation = true;

			stubJsPageLoadTime = sinon.stub(OC.PasswordConfirmation, 'pageLoadTime').value(new Date(2018, 0, 3, 12, 15, 0).getTime());
			stubMomentNow = sinon.stub(moment, 'now').returns(new Date(2018, 0, 3, 12, 31, 0).getTime());

			expect(OC.PasswordConfirmation.requiresPasswordConfirmation()).toBeTruthy();
		});
	});
});
