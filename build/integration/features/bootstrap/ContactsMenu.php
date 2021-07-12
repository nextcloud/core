<?php
/**
 * @copyright Copyright (c) 2021 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

use PHPUnit\Framework\Assert;

trait ContactsMenu {

	// BasicStructure trait is expected to be used in the class that uses this
	// trait.

	/**
	 * @When /^searching for contacts matching with "([^"]*)"$/
	 *
	 * @param string $filter
	 */
	public function searchingForContactsMatchingWith(string $filter) {
		$url = '/index.php/contactsmenu/contacts';

		$parameters[] = 'filter=' . $filter;

		$url .= '?' . implode('&', $parameters);

		$this->sendingAToWithRequesttoken('POST', $url);
	}

	/**
	 * @Then /^the list of searched contacts has "(\d+)" contacts$/
	 */
	public function theListOfSearchedContactsHasContacts(int $count) {
		$this->theHTTPStatusCodeShouldBe(200);

		$searchedContacts = json_decode($this->response->getBody(), $asAssociativeArray = true)['contacts'];

		Assert::assertEquals($count, count($searchedContacts));
	}

	/**
	 * @Then /^searched contact "(\d+)" is named "([^"]*)"$/
	 *
	 * @param int $index
	 * @param string $expectedName
	 */
	public function searchedContactXIsNamed(int $index, string $expectedName) {
		$searchedContacts = json_decode($this->response->getBody(), $asAssociativeArray = true)['contacts'];
		$searchedContact = $searchedContacts[$index];

		Assert::assertEquals($expectedName, $searchedContact['fullName']);
	}
}
