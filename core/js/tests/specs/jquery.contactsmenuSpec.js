/**
 * Copyright (c) 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('jquery.contactsMenu tests', function() {

	var $selector1, $selector2, $appendTo;

	beforeEach(function() {
		$('#testArea').append($('<div id="selector1">'));
		$('#testArea').append($('<div id="selector2">'));
		$('#testArea').append($('<div id="appendTo">'));
		$selector1 = $('#selector1');
		$selector2 = $('#selector2');
		$appendTo = $('#appendTo');
	});

	afterEach(function() {
		$selector1.off();
		$selector1.remove();
		$selector2.off();
		$selector2.remove();
		$appendTo.remove();
	});

	describe('shareType', function() {
		it('stops if type not supported', function() {
			$selector1.contactsMenu('user', 1, $appendTo);
			expect($appendTo.children().length).toEqual(0);

			$selector1.contactsMenu('user', 2, $appendTo);
			expect($appendTo.children().length).toEqual(0);

			$selector1.contactsMenu('user', 3, $appendTo);
			expect($appendTo.children().length).toEqual(0);

			$selector1.contactsMenu('user', 5, $appendTo);
			expect($appendTo.children().length).toEqual(0);
		});

		it('append list if shareType supported', function() {
			$selector1.contactsMenu('user', 0, $appendTo);
			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left hidden contactsmenu-popover"><ul><li><a><span class="icon-loading-small"></span></a></li></ul></div>');
		});
	});

	describe('open on click', function() {
		it('with one selector', function() {
			$selector1.contactsMenu('user', 0, $appendTo);
			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(true);
			$selector1.click();
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(false);
		});

		it('with multiple selectors - 1', function() {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);

			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(true);
			$selector1.click();
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(false);
		});

		it('with multiple selectors - 2', function() {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);

			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(true);
			$selector2.click();
			expect($appendTo.find('div.contactsmenu-popover').hasClass('hidden')).toEqual(false);
		});

		it ('should close when clicking the selector again - 1', function() {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);

			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
			$selector1.click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(false);
			$selector1.click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
		});

		it ('should close when clicking the selector again - 1', function() {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);

			expect($appendTo.children().length).toEqual(1);
			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
			$selector1.click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(false);
			$selector2.click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
		});
	});

	describe('send requests to the server and render', function() {
		it('load a topaction only', function(done) {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);
			$selector1.click();

			expect(fakeServer.requests[0].method).toEqual('POST');
			expect(fakeServer.requests[0].url).toEqual('http://localhost/index.php/contactsmenu/findOne');
			fakeServer.requests[0].respond(
				200,
				{ 'Content-Type': 'application/json; charset=utf-8' },
				JSON.stringify({
					"id": null,
					"fullName": "Name 123",
					"topAction": {
						"title": "bar@baz.wtf",
						"icon": "foo.svg",
						"hyperlink": "mailto:bar%40baz.wtf"},
					"actions": []
				})
			);

			$selector1.on('load', function() {
				expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left contactsmenu-popover loaded" style="display: block;"><ul><li class="hidden"><a><span class="icon-loading-small"></span></a></li><li><a href="mailto:bar%40baz.wtf"><img src="foo.svg"><span>bar@baz.wtf</span></a></li></ul></div>');

				done();
			});
		});

		it('load topaction and more actions', function(done) {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);
			$selector1.click();

			fakeServer.requests[0].respond(
				200,
				{ 'Content-Type': 'application/json; charset=utf-8' },
				JSON.stringify({
					"id": null,
					"fullName": "Name 123",
					"topAction": {
						"title": "bar@baz.wtf",
						"icon": "foo.svg",
						"hyperlink": "mailto:bar%40baz.wtf"},
					"actions": [{
						"title": "Details",
						"icon": "details.svg",
						"hyperlink": "http:\/\/localhost\/index.php\/apps\/contacts"
					}]
				})
			);
			expect(fakeServer.requests[0].method).toEqual('POST');
			expect(fakeServer.requests[0].url).toEqual('http://localhost/index.php/contactsmenu/findOne');

			$selector1.on('load', function() {
				expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left contactsmenu-popover loaded" style="display: block;"><ul><li class="hidden"><a><span class="icon-loading-small"></span></a></li><li><a href="mailto:bar%40baz.wtf"><img src="foo.svg"><span>bar@baz.wtf</span></a></li><li><a href="http://localhost/index.php/apps/contacts"><img src="details.svg"><span>Details</span></a></li></ul></div>');

				done();
			});
		});

		it('load no actions', function(done) {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);
			$selector1.click();

			fakeServer.requests[0].respond(
				200,
				{ 'Content-Type': 'application/json; charset=utf-8' },
				JSON.stringify({
					"id": null,
					"fullName": "Name 123",
					"topAction": null,
					"actions": []
				})
			);
			expect(fakeServer.requests[0].method).toEqual('POST');
			expect(fakeServer.requests[0].url).toEqual('http://localhost/index.php/contactsmenu/findOne');

			$selector1.on('load', function() {
				expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left contactsmenu-popover loaded" style="display: block;"><ul><li class="hidden"><a><span class="icon-loading-small"></span></a></li><li><a href="#"><span>No action available</span></a></li></ul></div>');

				done();
			});
		});

		it('should throw an error', function(done) {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);
			$selector1.click();

			fakeServer.requests[0].respond(
				400,
				{ 'Content-Type': 'application/json; charset=utf-8' },
				JSON.stringify([])
			);
			expect(fakeServer.requests[0].method).toEqual('POST');
			expect(fakeServer.requests[0].url).toEqual('http://localhost/index.php/contactsmenu/findOne');

			$selector1.on('loaderror', function() {
				expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left contactsmenu-popover loaded" style="display: block;"><ul><li class="hidden"><a><span class="icon-loading-small"></span></a></li><li><a href="#"><span>Error fetching contact actions</span></a></li></ul></div>');

				done();
			});
		});

		it('should handle 404', function(done) {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);
			$selector1.click();

			fakeServer.requests[0].respond(
				404,
				{ 'Content-Type': 'application/json; charset=utf-8' },
				JSON.stringify([])
			);
			expect(fakeServer.requests[0].method).toEqual('POST');
			expect(fakeServer.requests[0].url).toEqual('http://localhost/index.php/contactsmenu/findOne');

			$selector1.on('loaderror', function() {
				expect($appendTo.html().replace(/[\r\n\t]?(\ \ +)?/g, '')).toEqual('<div class="menu popovermenu menu-left contactsmenu-popover loaded" style="display: block;"><ul><li class="hidden"><a><span class="icon-loading-small"></span></a></li><li><a href="#"><span>No action available</span></a></li></ul></div>');

				done();
			});
		});

		it('click anywhere else to close the menu', function() {
			$('#selector1, #selector2').contactsMenu('user', 0, $appendTo);

			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
			$selector1.click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(false);
			$(document).click();
			expect($appendTo.find('div').hasClass('hidden')).toEqual(true);
		});
	});
});
