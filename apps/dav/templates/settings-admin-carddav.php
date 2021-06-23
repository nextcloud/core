<?php
/**
 * @copyright 2020, Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

script('dav', [
	'settings-admin-carddav'
]);

/** @var \OCP\IL10N $l */
/** @var array $_ */
?>
<form id="CardDAV" class="section">
	<h2><?php p($l->t('Addressbook server')); ?></h2>
	<p>
		<input type="checkbox" name="carddav_expose_system-address-book" id="carddavExposeSystemAddressBook" class="checkbox"
			<?php ($_['expose_system_address_book'] === 'yes') ? print_unescaped('checked="checked"') : null ?>/>
		<label for="carddavExposeSystemAddressBook"><?php p($l->t('Expose system address book')); ?></label>
		<br>
		<em>
			<?php print_unescaped($l->t('Only information set to "Public" or "Trusted" in the user\'s personal settings will be exposed.')); ?>
		</em>
	</p>
</form>
