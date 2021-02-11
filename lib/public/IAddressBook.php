<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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
 * IAddressBook interface
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP {
	/**
	 * Interface IAddressBook
	 *
	 * @since 5.0.0
	 */
	interface IAddressBook {

		/**
		 * @return string defining the technical unique key
		 * @since 5.0.0
		 */
		public function getKey();

		/**
		 * @return string defining the unique uri
		 * @since 16.0.0
		 * @return string
		 */
		public function getUri(): string;

		/**
		 * In comparison to getKey() this function returns a human readable (maybe translated) name
		 * @return mixed
		 * @since 5.0.0
		 */
		public function getDisplayName();

		/**
		 * @param string $pattern which should match within the $searchProperties
		 * @param array $searchProperties defines the properties within the query pattern should match
		 * @param array $options Options to define the output format and search behavior
		 * 	- 'types' boolean (since 15.0.0) If set to true, fields that come with a TYPE property will be an array
		 *    example: ['id' => 5, 'FN' => 'Thomas Tanghus', 'EMAIL' => ['type => 'HOME', 'value' => 'g@h.i']]
		 * 	- 'escape_like_param' - If set to false wildcards _ and % are not escaped
		 * 	- 'limit' - Set a numeric limit for the search results
		 * 	- 'offset' - Set the offset for the limited search results
		 * @return array an array of contacts which are arrays of key-value-pairs
		 *  example result:
		 *  [
		 *		['id' => 0, 'FN' => 'Thomas Müller', 'EMAIL' => 'a@b.c', 'GEO' => '37.386013;-122.082932'],
		 *		['id' => 5, 'FN' => 'Thomas Tanghus', 'EMAIL' => ['d@e.f', 'g@h.i']]
		 *	]
		 * @since 5.0.0
		 */
		public function search($pattern, $searchProperties, $options);

		/**
		 * @param array $properties this array if key-value-pairs defines a contact
		 * @return array an array representing the contact just created or updated
		 * @since 5.0.0
		 */
		public function createOrUpdate($properties);
		//	// dummy
		//	return array('id'    => 0, 'FN' => 'Thomas Müller', 'EMAIL' => 'a@b.c',
		//		     'PHOTO' => 'VALUE=uri:http://www.abc.com/pub/photos/jqpublic.gif',
		//		     'ADR'   => ';;123 Main Street;Any Town;CA;91921-1234'
		//	);

		/**
		 * @return mixed
		 * @since 5.0.0
		 */
		public function getPermissions();

		/**
		 * @param object $id the unique identifier to a contact
		 * @return bool successful or not
		 * @since 5.0.0
		 */
		public function delete($id);

		/**
		 * Returns true if this address-book is not owned by the current user,
		 * but shared with them.
		 *
		 * @return bool
		 * @since 20.0.0
		 */
		public function isShared(): bool;

		/**
		 * @return bool
		 * @since 20.0.0
		 */
		public function isSystemAddressBook(): bool;
	}
}
