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

/**
 * Simulate the variables that are normally set by PHP code
 */

// from core/js/config.php
window.TESTING = true;
window.datepickerFormatDate = 'MM d, yy';
window.dayNames = [
	'Sunday',
	'Monday',
	'Tuesday',
	'Wednesday',
	'Thursday',
	'Friday',
	'Saturday'
];
window.dayNamesShort = [
	'Sun.',
	'Mon.',
	'Tue.',
	'Wed.',
	'Thu.',
	'Fri.',
	'Sat.'
];
window.dayNamesMin = [
	'Su',
	'Mo',
	'Tu',
	'We',
	'Th',
	'Fr',
	'Sa'
];
window.monthNames = [
	'January',
	'February',
	'March',
	'April',
	'May',
	'June',
	'July',
	'August',
	'September',
	'October',
	'November',
	'December'
];
window.monthNamesShort = [
	'Jan.',
	'Feb.',
	'Mar.',
	'Apr.',
	'May.',
	'Jun.',
	'Jul.',
	'Aug.',
	'Sep.',
	'Oct.',
	'Nov.',
	'Dec.'
];
window.firstDay = 0;

// setup dummy webroots
/* jshint camelcase: false */
window.oc_debug = true;
// FIXME: OC.webroot is supposed to be only the path!!!
OC.webroot = location.href + '/';
OC.appswebroots = {
	"files": window.webroot + '/apps/files/',
	"files_sharing": window.webroot + '/apps/files_sharing/'
};
OC.config = {
	session_lifetime: 600 * 1000,
	session_keepalive: false,
	blacklist_files_regex: '\.(part|filepart)$',
};
OC.appConfig = {
	core: {}
};
OC.theme = {
	docPlaceholderUrl: 'https://docs.example.org/PLACEHOLDER'
};
window.oc_capabilities = {
}

/* jshint camelcase: true */

// mock for Snap.js plugin
window.Snap = function() {};
window.Snap.prototype = {
	enable: function() {},
	disable: function() {},
	close: function() {}
};

window.isPhantom = /phantom/i.test(navigator.userAgent);
document.documentElement.lang = navigator.language;

// global setup for all tests
(function setupTests() {
	var fakeServer = null,
		$testArea = null,
		ajaxErrorStub = null;

	/**
	 * Utility functions for testing
	 */
	var TestUtil = {
		/**
		 * Returns the image URL set on the given element
		 * @param $el element
		 * @return {String} image URL
		 */
		getImageUrl: function($el) {
			// might be slightly different cross-browser
			var url = $el.css('background-image');
			var r = url.match(/url\(['"]?([^'")]*)['"]?\)/);
			if (!r) {
				return url;
			}
			return r[1];
		}
	};

	beforeEach(function() {
		// test area for elements that need absolute selector access or measure widths/heights
		// which wouldn't work for detached or hidden elements
		$testArea = $('<div id="testArea" style="position: absolute; width: 1280px; height: 800px; top: -3000px; left: -3000px; opacity: 0;"></div>');
		$('body').append($testArea);
		// enforce fake XHR, tests should not depend on the server and
		// must use fake responses for expected calls
		fakeServer = sinon.fakeServer.create();

		// make it globally available, so that other tests can define
		// custom responses
		window.fakeServer = fakeServer;

		if (!OC.TestUtil) {
			OC.TestUtil = TestUtil;
		}

		moment.locale('en');

		// reset plugins
		OC.Plugins._plugins = [];

		// dummy select2 (which isn't loaded during the tests)
		$.fn.select2 = function() { return this; };

		ajaxErrorStub = sinon.stub(OC, '_processAjaxError');
	});

	afterEach(function() {
		// uncomment this to log requests
		// console.log(window.fakeServer.requests);
		fakeServer.restore();

		$testArea.remove();

		delete($.fn.select2);

		ajaxErrorStub.restore();

		// reset pop state handlers
		OC.Util.History._handlers = [];

	});
})();

