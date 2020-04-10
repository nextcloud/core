<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Public interface of ownCloud for apps to use.
 * Contacts Class
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Contacts;

/**
 * This class provides access to the contacts app. Use this class exclusively if you want to access contacts.
 *
 * Contacts in general will be expressed as an array of key-value-pairs.
 * The keys will match the property names defined in https://tools.ietf.org/html/rfc2426#section-1
 *
 * Proposed workflow for working with contacts:
 *  - search for the contacts
 *  - manipulate the results array
 *  - createOrUpdate will save the given contacts overwriting the existing data
 *
 * For updating it is mandatory to keep the id.
 * Without an id a new contact will be created.
 *
 * @since 6.0.0
 */
interface IManager {

	/**
	 * This function is used to search and find contacts within the users address books.
	 * In case $pattern is empty all contacts will be returned.
	 *
	 * Example:
	 *  Following function shows how to search for contacts for the name and the email address.
	 *
	 *		public static function getMatchingRecipient($term) {
	 *			$cm = \OC::$server->getContactsManager();
	 *			// The API is not active -> nothing to do
	 *			if (!$cm->isEnabled()) {
	 *				return array();
	 *			}
	 *
	 *			$result = $cm->search($term, array('FN', 'EMAIL'));
	 *			$receivers = array();
	 *			foreach ($result as $r) {
	 *				$id = $r['id'];
	 *				$fn = $r['FN'];
	 *				$email = $r['EMAIL'];
	 *				if (!is_array($email)) {
	 *					$email = array($email);
	 *				}
	 *
	 *				// loop through all email addresses of this contact
	 *				foreach ($email as $e) {
	 *				$displayName = $fn . " <$e>";
	 *				$receivers[] = array(
	 *					'id'    => $id,
	 *					'label' => $displayName,
	 *					'value' => $displayName);
	 *				}
	 *			}
	 *
	 *			return $receivers;
	 *		}
	 *
	 *
	 * @param string $pattern which should match within the $searchProperties
	 * @param array $searchProperties defines the properties within the query pattern should match
	 * @param array $options = array() to define the search behavior
	 * 	- 'escape_like_param' - If set to false wildcards _ and % are not escaped
	 * @return array an array of contacts which are arrays of key-value-pairs
	 * @since 6.0.0
	 */
	public function search($pattern, $searchProperties = [], $options = []);

	/**
	 * This function can be used to delete the contact identified by the given id
	 *
	 * @param object $id the unique identifier to a contact
	 * @param string $address_book_key identifier of the address book in which the contact shall be deleted
	 * @return bool successful or not
	 * @since 6.0.0
	 */
	public function delete($id, $address_book_key);

	/**
	 * This function is used to create a new contact if 'id' is not given or not present.
	 * Otherwise the contact will be updated by replacing the entire data set.
	 *
	 * @param array $properties this array if key-value-pairs defines a contact
	 * @param string $address_book_key identifier of the address book in which the contact shall be created or updated
	 * @return array an array representing the contact just created or updated
	 * @since 6.0.0
	 */
	public function createOrUpdate($properties, $address_book_key);

	/**
	 * Check if contacts are available (e.g. contacts app enabled)
	 *
	 * @return bool true if enabled, false if not
	 * @since 6.0.0
	 */
	public function isEnabled();

	/**
	 * Registers an address book
	 *
	 * @param \OCP\IAddressBook $address_book
	 * @return void
	 * @since 6.0.0
	 */
	public function registerAddressBook(\OCP\IAddressBook $address_book);

	/**
	 * Unregisters an address book
	 *
	 * @param \OCP\IAddressBook $address_book
	 * @return void
	 * @since 6.0.0
	 */
	public function unregisterAddressBook(\OCP\IAddressBook $address_book);

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * address books are actually requested
	 *
	 * @param \Closure $callable
	 * @return void
	 * @since 6.0.0
	 */
	public function register(\Closure $callable);

	/**
	 * Return a list of the user's addressbooks display names
	 *
	 * @return array
	 * @since 6.0.0
	 * @deprecated 16.0.0 - Use `$this->getUserAddressBooks()` instead
	 */
	public function getAddressBooks();

	/**
	 * Return a list of the user's addressbooks
	 *
	 * @return IAddressBook[]
	 * @since 16.0.0
	 */
	public function getUserAddressBooks();

	/**
	 * removes all registered address book instances
	 *
	 * @return void
	 * @since 6.0.0
	 */
	public function clear();
}
