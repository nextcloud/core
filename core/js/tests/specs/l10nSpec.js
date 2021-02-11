/**
 * Copyright (c) 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('OC.L10N tests', function() {
	var TEST_APP = 'jsunittestapp';

	beforeEach(function() {
		OC.appswebroots[TEST_APP] = OC.getRootPath() + '/apps3/jsunittestapp';
	});
	afterEach(function() {
		OC.L10N._unregister(TEST_APP);
		delete OC.appswebroots[TEST_APP];
	});

	describe('text translation', function() {
		beforeEach(function() {
			spyOn(console, 'warn');
			OC.L10N.register(TEST_APP, {
				'Hello world!': 'Hallo Welt!',
				'Hello {name}, the weather is {weather}': 'Hallo {name}, das Wetter ist {weather}',
				'sunny': 'sonnig'
			});
		});
		it('returns untranslated text when no bundle exists', function() {
			OC.L10N._unregister(TEST_APP);
			expect(t(TEST_APP, 'unknown text')).toEqual('unknown text');
		});
		it('returns untranslated text when no key exists', function() {
			expect(t(TEST_APP, 'unknown text')).toEqual('unknown text');
		});
		it('returns translated text when key exists', function() {
			expect(t(TEST_APP, 'Hello world!')).toEqual('Hallo Welt!');
		});
		it('returns translated text with placeholder', function() {
			expect(
				t(TEST_APP, 'Hello {name}, the weather is {weather}', {name: 'Steve', weather: t(TEST_APP, 'sunny')})
			).toEqual('Hallo Steve, das Wetter ist sonnig');
		});
		it('returns text with escaped placeholder', function() {
			expect(
				t(TEST_APP, 'Hello {name}', {name: '<strong>Steve</strong>'})
			).toEqual('Hello &lt;strong&gt;Steve&lt;/strong&gt;');
		});
		it('returns text with not escaped placeholder', function() {
			expect(
				t(TEST_APP, 'Hello {name}', {name: '<strong>Steve</strong>'}, null, {escape: false})
			).toEqual('Hello <strong>Steve</strong>');
		});
		it('uses DOMPurify to escape the text', function() {
			expect(
				t(TEST_APP, '<strong>These are your search results<script>alert(1)</script></strong>', null, {escape: false})
			).toEqual('<strong>These are your search results</strong>');
		});
		it('keeps old texts when registering existing bundle', function() {
			OC.L10N.register(TEST_APP, {
				'sunny': 'sonnig',
				'new': 'neu'
			});
			expect(t(TEST_APP, 'sunny')).toEqual('sonnig');
			expect(t(TEST_APP, 'new')).toEqual('neu');
		});
	});
	describe('plurals', function() {
		function checkPlurals() {
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 0)
			).toEqual('0 Dateien herunterladen');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 1)
			).toEqual('1 Datei herunterladen');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 2)
			).toEqual('2 Dateien herunterladen');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 1024)
			).toEqual('1024 Dateien herunterladen');
		}

		it('generates plural for default text when translation does not exist', function() {
			spyOn(console, 'warn');
			OC.L10N.register(TEST_APP, {
			});
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 0)
			).toEqual('download 0 files');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 1)
			).toEqual('download 1 file');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 2)
			).toEqual('download 2 files');
			expect(
				n(TEST_APP, 'download %n file', 'download %n files', 1024)
			).toEqual('download 1024 files');
		});
		it('generates plural with default function when no forms specified', function() {
			spyOn(console, 'warn');
			OC.L10N.register(TEST_APP, {
				'_download %n file_::_download %n files_':
					['%n Datei herunterladen', '%n Dateien herunterladen']
			});
			checkPlurals();
		});
	});
	describe('async loading of translations', function() {
		it('loads bundle for given app and calls callback', function(done) {
			var localeStub = sinon.stub(OC, 'getLocale').returns('zh_CN');
			var callbackStub = sinon.stub();
			var promiseStub = sinon.stub();
			var loading = OC.L10N.load(TEST_APP, callbackStub);
			expect(callbackStub.notCalled).toEqual(true);
			var req = fakeServer.requests[0];

			loading
				.then(promiseStub)
				.then(function() {
					expect(fakeServer.requests.length).toEqual(1);
					expect(req.url).toEqual(
						OC.getRootPath() + '/apps3/' + TEST_APP + '/l10n/zh_CN.json'
					);

					expect(callbackStub.calledOnce).toEqual(true);
					expect(promiseStub.calledOnce).toEqual(true);
					expect(t(TEST_APP, 'Hello world!')).toEqual('你好世界!');
					localeStub.restore();
				})
				.then(done);

			expect(promiseStub.notCalled).toEqual(true);
			req.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify({
					translations: {'Hello world!': '你好世界!'},
					pluralForm: 'nplurals=2; plural=(n != 1);'
				})
			);
		});
		it('calls callback if translation already available', function(done) {
			var callbackStub = sinon.stub();
			spyOn(console, 'warn');
			OC.L10N.register(TEST_APP, {
				'Hello world!': 'Hallo Welt!'
			});
			OC.L10N.load(TEST_APP, callbackStub)
				.then(function() {
					expect(callbackStub.calledOnce).toEqual(true);
					expect(fakeServer.requests.length).toEqual(0);
				})
				.then(done);

		});
		it('calls callback if locale is en', function(done) {
			var callbackStub = sinon.stub();
			OC.L10N.load(TEST_APP, callbackStub)
				.then(function() {
					expect(callbackStub.calledOnce).toEqual(true);
					expect(fakeServer.requests.length).toEqual(0);
				})
				.then(done)
				.catch(done);
		});
	});
});
