/* global expect, sinon, _, spyOn, Promise */

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

describe('Contacts menu', function() {
	var $triggerEl,
			$menuEl,
			menu;

	/**
	 * @private
	 * @returns {Promise}
	 */
	function openMenu() {
		return menu._toggleVisibility(true);
	}

	beforeEach(function(done) {
		$triggerEl = $('<div class="menutoggle">');
		$menuEl = $('<div class="menu">');

		menu = new OC.ContactsMenu({
			el: $menuEl,
			trigger: $triggerEl
		});
		done();
	});

	it('shows a loading message while data is being fetched', function() {
		fakeServer.respondWith('GET', OC.generateUrl('/contactsmenu/contacts'), [
			200,
			{},
			''
		]);

		openMenu();

		expect($menuEl.html()).toContain('Loading your contacts …');
	});

	it('shows an error message when loading the contacts data fails', function(done) {
		spyOn(console, 'error');
		fakeServer.respondWith('GET', OC.generateUrl('/contactsmenu/contacts'), [
			500,
			{},
			''
		]);

		var opening = openMenu();

		expect($menuEl.html()).toContain('Loading your contacts …');
		fakeServer.respond();

		opening.then(function() {
			expect($menuEl.html()).toContain('Could not load your contacts');
			expect(console.error).toHaveBeenCalledTimes(1);
			done();
		}, function(e) {
			done.fail(e);
		});
	});

	it('loads data successfully', function(done) {
		spyOn(menu, '_getContacts').and.returnValue(Promise.resolve({
			contacts: [
				{
					id: null,
					fullName: 'Acosta Lancaster',
					topAction: {
						title: 'Mail',
						icon: 'icon-mail',
						hyperlink: 'mailto:deboraoliver%40centrexin.com'
					},
					actions: [
						{
							title: 'Mail',
							icon: 'icon-mail',
							hyperlink: 'mailto:mathisholland%40virxo.com'
						},
						{
							title: 'Details',
							icon: 'icon-info',
							hyperlink: 'https:\/\/localhost\/index.php\/apps\/contacts'
						}
					],
					lastMessage: ''
				},
				{
					id: null,
					fullName: 'Adeline Snider',
					topAction: {
						title: 'Mail',
						icon: 'icon-mail',
						hyperlink: 'mailto:ceciliasoto%40essensia.com'
					},
					actions: [
						{
							title: 'Mail',
							icon: 'icon-mail',
							hyperlink: 'mailto:pearliesellers%40inventure.com'
						},
						{
							title: 'Details',
							icon: 'icon-info',
							hyperlink: 'https://localhost\/index.php\/apps\/contacts'
						}
					],
					lastMessage: 'cu'
				}
			],
			contactsAppEnabled: true
		}));

		openMenu().then(function() {
			expect(menu._getContacts).toHaveBeenCalled();
			expect($menuEl.html()).toContain('Acosta Lancaster');
			expect($menuEl.html()).toContain('Adeline Snider');
			expect($menuEl.html()).toContain('Show all contacts …');
			done();
		}, function(e) {
			done.fail(e);
		});

	});

	it('doesn\'t show a link to the contacts app if it\'s disabled', function(done) {
		spyOn(menu, '_getContacts').and.returnValue(Promise.resolve({
			contacts: [
				{
					id: null,
					fullName: 'Acosta Lancaster',
					topAction: {
						title: 'Mail',
						icon: 'icon-mail',
						hyperlink: 'mailto:deboraoliver%40centrexin.com'
					},
					actions: [
						{
							title: 'Mail',
							icon: 'icon-mail',
							hyperlink: 'mailto:mathisholland%40virxo.com'
						},
						{
							title: 'Details',
							icon: 'icon-info',
							hyperlink: 'https:\/\/localhost\/index.php\/apps\/contacts'
						}
					],
					lastMessage: ''
				}
			],
			contactsAppEnabled: false
		}));

		openMenu().then(function() {
			expect(menu._getContacts).toHaveBeenCalled();
			expect($menuEl.html()).not.toContain('Show all contacts …');
			done();
		}, function(e) {
			done.fail(e);
		});
	});

	it('shows only one entry\'s action menu at a time', function(done) {
		spyOn(menu, '_getContacts').and.returnValue(Promise.resolve({
			contacts: [
				{
					id: null,
					fullName: 'Acosta Lancaster',
					topAction: {
						title: 'Mail',
						icon: 'icon-mail',
						hyperlink: 'mailto:deboraoliver%40centrexin.com'
					},
					actions: [
						{
							title: 'Info',
							icon: 'icon-info',
							hyperlink: 'https:\/\/localhost\/index.php\/apps\/contacts'
						},
						{
							title: 'Details',
							icon: 'icon-info',
							hyperlink: 'https:\/\/localhost\/index.php\/apps\/contacts'
						}
					],
					lastMessage: ''
				},
				{
					id: null,
					fullName: 'Adeline Snider',
					topAction: {
						title: 'Mail',
						icon: 'icon-mail',
						hyperlink: 'mailto:ceciliasoto%40essensia.com'
					},
					actions: [
						{
							title: 'Info',
							icon: 'icon-info',
							hyperlink: 'https://localhost\/index.php\/apps\/contacts'
						},
						{
							title: 'Details',
							icon: 'icon-info',
							hyperlink: 'https://localhost\/index.php\/apps\/contacts'
						}
					],
					lastMessage: 'cu'
				}
			],
			contactsAppEnabled: true
		}));

		openMenu().then(function() {
			expect(menu._getContacts).toHaveBeenCalled();
			expect($menuEl.html()).toContain('Adeline Snider');
			expect($menuEl.html()).toContain('Show all contacts …');

			// Both menus are closed at the beginning
			expect($menuEl.find('.contact').eq(0).find('.menu').is(':visible')).toBe(false);
			expect($menuEl.find('.contact').eq(1).find('.menu').is(':visible')).toBe(false);

			// Open the first one
			$menuEl.find('.contact').eq(0).find('.other-actions').click();
			expect($menuEl.find('.contact').eq(0).find('.menu').css('display')).toBe('');
			expect($menuEl.find('.contact').eq(1).find('.menu').css('display')).toBe('none');

			// Open the second one
			$menuEl.find('.contact').eq(1).find('.other-actions').click();
			expect($menuEl.find('.contact').eq(0).find('.menu').css('display')).toBe('none');
			expect($menuEl.find('.contact').eq(1).find('.menu').css('display')).toBe('');

			// Close the second one
			$menuEl.find('.contact').eq(1).find('.other-actions').click();
			expect($menuEl.find('.contact').eq(0).find('.menu').css('display')).toBe('none');
			expect($menuEl.find('.contact').eq(1).find('.menu').css('display')).toBe('none');

			done();
		}, function(e) {
			done.fail(e);
		});
	});

});
