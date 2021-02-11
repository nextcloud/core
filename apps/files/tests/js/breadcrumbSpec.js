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

describe('OCA.Files.BreadCrumb tests', function() {
	var BreadCrumb = OCA.Files.BreadCrumb;

	describe('Rendering', function() {
		var bc;
		beforeEach(function() {
			bc = new BreadCrumb({
				getCrumbUrl: function(part, index) {
					// for testing purposes
					return part.dir + '#' + index;
				}
			});
		});
		afterEach(function() {
			bc = null;
		});
		it('Renders its own container', function() {
			bc.render();
			expect(bc.$el.hasClass('breadcrumb')).toEqual(true);
		});
		it('Renders root by default', function() {
			var $crumbs;
			bc.render();
			$crumbs = bc.$el.find('.crumb');
			// menu and home
			expect($crumbs.length).toEqual(2);
			expect($crumbs.eq(0).find('a').hasClass('icon-more')).toEqual(true);
			expect($crumbs.eq(0).find('div.popovermenu').length).toEqual(1);
			expect($crumbs.eq(0).data('dir')).not.toBeDefined();
			expect($crumbs.eq(1).find('a').attr('href')).toEqual('/#1');
			expect($crumbs.eq(1).find('a').hasClass('icon-home')).toEqual(true);
			expect($crumbs.eq(1).data('dir')).toEqual('/');
		});
		it('Renders root when switching to root', function() {
			var $crumbs;
			bc.setDirectory('/somedir');
			bc.setDirectory('/');
			$crumbs = bc.$el.find('.crumb');
			expect($crumbs.length).toEqual(2);
			expect($crumbs.eq(1).data('dir')).toEqual('/');
		});
		it('Renders single path section', function() {
			var $crumbs;
			bc.setDirectory('/somedir');
			$crumbs = bc.$el.find('.crumb');
			expect($crumbs.length).toEqual(3);
			expect($crumbs.eq(0).find('a').hasClass('icon-more')).toEqual(true);
			expect($crumbs.eq(0).find('div.popovermenu').length).toEqual(1);
			expect($crumbs.eq(0).data('dir')).not.toBeDefined();

			expect($crumbs.eq(1).find('a').attr('href')).toEqual('/#1');
			expect($crumbs.eq(1).find('a').hasClass('icon-home')).toEqual(true);
			expect($crumbs.eq(1).data('dir')).toEqual('/');

			expect($crumbs.eq(2).find('a').attr('href')).toEqual('/somedir#2');
			expect($crumbs.eq(2).find('img').length).toEqual(0);
			expect($crumbs.eq(2).data('dir')).toEqual('/somedir');
		});
		it('Renders multiple path sections and special chars', function() {
			var $crumbs;
			bc.setDirectory('/somedir/with space/abc');
			$crumbs = bc.$el.find('.crumb');
			expect($crumbs.length).toEqual(5);
			expect($crumbs.eq(0).find('a').hasClass('icon-more')).toEqual(true);
			expect($crumbs.eq(0).find('div.popovermenu').length).toEqual(1);
			expect($crumbs.eq(0).data('dir')).not.toBeDefined();

			expect($crumbs.eq(1).find('a').attr('href')).toEqual('/#1');
			expect($crumbs.eq(1).find('a').hasClass('icon-home')).toEqual(true);
			expect($crumbs.eq(1).data('dir')).toEqual('/');

			expect($crumbs.eq(2).find('a').attr('href')).toEqual('/somedir#2');
			expect($crumbs.eq(2).find('img').length).toEqual(0);
			expect($crumbs.eq(2).data('dir')).toEqual('/somedir');

			expect($crumbs.eq(3).find('a').attr('href')).toEqual('/somedir/with space#3');
			expect($crumbs.eq(3).find('img').length).toEqual(0);
			expect($crumbs.eq(3).data('dir')).toEqual('/somedir/with space');

			expect($crumbs.eq(4).find('a').attr('href')).toEqual('/somedir/with space/abc#4');
			expect($crumbs.eq(4).find('img').length).toEqual(0);
			expect($crumbs.eq(4).data('dir')).toEqual('/somedir/with space/abc');
		});
		it('Renders backslashes as regular directory separator', function() {
			var $crumbs;
			bc.setDirectory('/somedir\\with/mixed\\separators');
			$crumbs = bc.$el.find('.crumb');
			expect($crumbs.length).toEqual(6);
			expect($crumbs.eq(0).find('a').hasClass('icon-more')).toEqual(true);
			expect($crumbs.eq(0).find('div.popovermenu').length).toEqual(1);
			expect($crumbs.eq(0).data('dir')).not.toBeDefined();

			expect($crumbs.eq(1).find('a').attr('href')).toEqual('/#1');
			expect($crumbs.eq(1).find('a').hasClass('icon-home')).toEqual(true);
			expect($crumbs.eq(1).data('dir')).toEqual('/');

			expect($crumbs.eq(2).find('a').attr('href')).toEqual('/somedir#2');
			expect($crumbs.eq(2).find('img').length).toEqual(0);
			expect($crumbs.eq(2).data('dir')).toEqual('/somedir');

			expect($crumbs.eq(3).find('a').attr('href')).toEqual('/somedir/with#3');
			expect($crumbs.eq(3).find('img').length).toEqual(0);
			expect($crumbs.eq(3).data('dir')).toEqual('/somedir/with');

			expect($crumbs.eq(4).find('a').attr('href')).toEqual('/somedir/with/mixed#4');
			expect($crumbs.eq(4).find('img').length).toEqual(0);
			expect($crumbs.eq(4).data('dir')).toEqual('/somedir/with/mixed');

			expect($crumbs.eq(5).find('a').attr('href')).toEqual('/somedir/with/mixed/separators#5');
			expect($crumbs.eq(5).find('img').length).toEqual(0);
			expect($crumbs.eq(5).data('dir')).toEqual('/somedir/with/mixed/separators');
		});
	});
	describe('Events', function() {
		it('Calls onClick handler when clicking on a crumb', function() {
			var handler = sinon.stub();
			var bc = new BreadCrumb({
				onClick: handler
			});
			bc.setDirectory('/one/two/three/four');
			// Click on crumb does not work, only link
			bc.$el.find('.crumb:eq(4)').click();
			expect(handler.calledOnce).toEqual(false);

			handler.reset();
			// Click on crumb link works
			bc.$el.find('.crumb:eq(1) a').click();
			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).thisValue).toEqual(bc.$el.find('.crumb > a').get(1));
		});
		it('Calls onDrop handler when dropping on a crumb', function() {
			var droppableStub = sinon.stub($.fn, 'droppable');
			var handler = sinon.stub();
			var bc = new BreadCrumb({
				onDrop: handler
			});
			bc.setDirectory('/one/two/three/four');
			expect(droppableStub.calledOnce).toEqual(true);

			expect(droppableStub.getCall(0).args[0].drop).toBeDefined();
			// simulate drop
			droppableStub.getCall(0).args[0].drop({dummy: true});

			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0]).toEqual({dummy: true});

			droppableStub.restore();
		});
	});

	describe('Menu tests', function() {
		var bc, dummyDir, $crumbmenuLink, $popovermenu;

		beforeEach(function() {
			dummyDir = '/one/two/three/four/five'

			bc = new BreadCrumb();
			// append dummy navigation and controls
			// as they are currently used for measurements
			$('#testArea').append(
				'<div id="controls"></div>'
			);
			$('#controls').append(bc.$el);

			bc.setDirectory(dummyDir);

			$('div.crumb').each(function(index){
				$(this).css('width', 50);
				$(this).css('padding', 0);
				$(this).css('margin', 0);
			});
			$('div.crumbhome').css('width', 51);
			$('div.crumbmenu').css('width', 51);

			$('#controls').width(1000);
			bc._resize();

			// Shrink to show popovermenu
			$('#controls').width(300);
			bc._resize();

			$crumbmenuLink = bc.$el.find('.crumbmenu > a');
			$popovermenu = $crumbmenuLink.next('.popovermenu');
		});
		afterEach(function() {
			bc = null;
		});

		it('Shows only items not in the breadcrumb', function() {
			var hiddenCrumbs = bc.$el.find('.crumb:not(.crumbmenu).hidden');
			expect($popovermenu.find('li:not(.in-breadcrumb)').length).toEqual(hiddenCrumbs.length);
		});
	});

	describe('Resizing', function() {
		var bc, dummyDir, widths, paddings, margins;

		// cit() will skip tests if running on PhantomJS because it does not
		// have proper support for flexboxes.
		var cit = window.isPhantom?xit:it;

		beforeEach(function() {
			dummyDir = '/short name/longer name/looooooooooooonger/' +
				'even longer long long long longer long/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/last one';

			bc = new BreadCrumb();
			// append dummy navigation and controls
			// as they are currently used for measurements
			$('#testArea').append(
				'<div id="controls"></div>'
			);
			$('#controls').append(bc.$el);

			// triggers resize implicitly
			bc.setDirectory(dummyDir);

			// using hard-coded widths (pre-measured) to avoid getting different
			// results on different browsers due to font engine differences
			// 51px is default size for menu and home
			widths = [51, 51, 106, 112, 160, 257, 251, 91];
			// using hard-coded paddings and margins to avoid depending on the
			// current CSS values used in the server
			paddings = [0, 0, 0, 0, 0, 0, 0, 0];
			margins = [0, 0, 0, 0, 0, 0, 0, 0];

			$('div.crumb').each(function(index){
				$(this).css('width', widths[index]);
				$(this).css('padding', paddings[index]);
				$(this).css('margin', margins[index]);
			});
		});
		afterEach(function() {
			bc = null;
		});
		it('Hides breadcrumbs to fit available width', function() {
			var $crumbs;

			$('#controls').width(500);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Second, third, fourth and fifth crumb are hidden and everything
			// else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Hides breadcrumbs to fit available width', function() {
			var $crumbs;

			$('#controls').width(700);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Hides breadcrumbs to fit available width taking paddings into account', function() {
			var $crumbs;

			// Each element is 20px wider
			paddings = [10, 10, 10, 10, 10, 10, 10, 10];

			$('div.crumb').each(function(index){
				$(this).css('padding', paddings[index]);
			});

			$('#controls').width(700);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Second, third and fourth crumb are hidden and everything else is
			// visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Hides breadcrumbs to fit available width taking margins into account', function() {
			var $crumbs;

			// Each element is 20px wider
			margins = [10, 10, 10, 10, 10, 10, 10, 10];

			$('div.crumb').each(function(index){
				$(this).css('margin', margins[index]);
			});

			$('#controls').width(700);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Second, third and fourth crumb are hidden and everything else is
			// visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Hides breadcrumbs to fit available width left by siblings', function() {
			var $crumbs;

			$('#controls').width(700);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// Visible sibling widths add up to 200px
			var $previousSibling = $('<div class="otherSibling"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$previousSibling.css('width', '50px');
			$previousSibling.css('min-width', '50px');
			$('#controls').prepend($previousSibling);

			var $creatableActions = $('<div class="actions creatable"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$creatableActions.css('width', '100px');
			$creatableActions.css('min-width', '100px');
			$('#controls').append($creatableActions);

			var $nextHiddenSibling = $('<div class="otherSibling hidden"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$nextHiddenSibling.css('width', '200px');
			$nextHiddenSibling.css('min-width', '200px');
			$('#controls').append($nextHiddenSibling);

			var $nextSibling = $('<div class="otherSibling"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$nextSibling.css('width', '50px');
			$nextSibling.css('min-width', '50px');
			$('#controls').append($nextSibling);

			bc._resize();

			// Second, third, fourth and fifth crumb are hidden and everything
			// else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Hides breadcrumbs to fit available width left by siblings with paddings and margins', function() {
			var $crumbs;

			$('#controls').width(700);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// Visible sibling widths add up to 200px
			var $previousSibling = $('<div class="otherSibling"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$previousSibling.css('width', '10px');
			$previousSibling.css('min-width', '10px');
			$previousSibling.css('margin', '20px');
			$('#controls').prepend($previousSibling);

			var $creatableActions = $('<div class="actions creatable"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$creatableActions.css('width', '20px');
			$creatableActions.css('min-width', '20px');
			$creatableActions.css('margin-left', '40px');
			$creatableActions.css('padding-right', '40px');
			$('#controls').append($creatableActions);

			var $nextHiddenSibling = $('<div class="otherSibling hidden"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$nextHiddenSibling.css('width', '200px');
			$nextHiddenSibling.css('min-width', '200px');
			$('#controls').append($nextHiddenSibling);

			var $nextSibling = $('<div class="otherSibling"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$nextSibling.css('width', '10px');
			$nextSibling.css('min-width', '10px');
			$nextSibling.css('padding', '20px');
			$('#controls').append($nextSibling);

			bc._resize();

			// Second, third, fourth and fifth crumb are hidden and everything
			// else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Updates the breadcrumbs when reducing available width', function() {
			var $crumbs;

			// enough space
			$('#controls').width(1800);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Menu is hidden
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(true);

			// simulate decrease
			$('#controls').width(950);
			bc._resize();

			// Third crumb is hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Updates the breadcrumbs when reducing available width taking into account the menu width', function() {
			var $crumbs;

			// enough space
			$('#controls').width(1800);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Menu is hidden
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// simulate decrease
			// 650 is enough for all the crumbs except the third and fourth
			// ones, but not enough for the menu and all the crumbs except the
			// third and fourth ones; the second one has to be hidden too.
			$('#controls').width(650);
			bc._resize();

			// Second, third and fourth crumb are hidden and everything else is
			// visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Updates the breadcrumbs when increasing available width', function() {
			var $crumbs;

			// limited space
			$('#controls').width(850);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// simulate increase
			$('#controls').width(1000);
			bc._resize();

			// Third crumb is hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		it('Updates the breadcrumbs when increasing available width taking into account the menu width', function() {
			var $crumbs;

			// limited space
			$('#controls').width(850);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// simulate increase
			// 1030 is enough for all the crumbs if the menu is hidden.
			$('#controls').width(1030);
			bc._resize();

			// Menu is hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
		cit('Updates the breadcrumbs when increasing available width with an expanding sibling', function() {
			var $crumbs;

			// The sibling expands to fill all the width left by the breadcrumbs
			var $nextSibling = $('<div class="sibling"></div>');
			// Set both the width and the min-width to even differences in width
			// handling in the browsers used to run the tests.
			$nextSibling.css('width', '10px');
			$nextSibling.css('min-width', '10px');
			$nextSibling.css('display', 'flex');
			$nextSibling.css('flex', '1 1');
			var $nextSiblingChild = $('<div class="siblingChild"></div>');
			$nextSiblingChild.css('margin-left', 'auto');
			$nextSibling.append($nextSiblingChild);
			$('#controls').append($nextSibling);

			// limited space
			$('#controls').width(850);
			bc._resize();

			$crumbs = bc.$el.find('.crumb');

			// Third and fourth crumb are hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);

			// simulate increase
			$('#controls').width(1000);
			bc._resize();

			// Third crumb is hidden and everything else is visible
			expect($crumbs.eq(0).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(1).hasClass('hidden')).toEqual(false);

			expect($crumbs.eq(2).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(3).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(4).hasClass('hidden')).toEqual(true);
			expect($crumbs.eq(5).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(6).hasClass('hidden')).toEqual(false);
			expect($crumbs.eq(7).hasClass('hidden')).toEqual(false);
		});
	});
});
