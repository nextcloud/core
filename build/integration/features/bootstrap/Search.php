<?php
/**
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

trait Search {

	// BasicStructure trait is expected to be used in the class that uses this
	// trait.

	/**
	 * @When /^searching for "([^"]*)"$/
	 * @param string $query
	 */
	public function searchingFor(string $query) {
		$this->searchForInApp($query, '');
	}

	/**
	 * @When /^searching for "([^"]*)" in app "([^"]*)"$/
	 * @param string $query
	 * @param string $app
	 */
	public function searchingForInApp(string $query, string $app) {
		$url = '/index.php/core/search';

		$parameters[] = 'query=' . $query;
		$parameters[] = 'inApps[]=' . $app;

		$url .= '?' . implode('&', $parameters);

		$this->sendingAToWithRequesttoken('GET', $url);
	}

	/**
	 * @Then /^the list of search results has "(\d+)" results$/
	 */
	public function theListOfSearchResultsHasResults(int $count) {
		$this->theHTTPStatusCodeShouldBe(200);

		$searchResults = json_decode($this->response->getBody());

		Assert::assertEquals($count, count($searchResults));
	}

	/**
	 * @Then /^search result "(\d+)" contains$/
	 *
	 * @param int $number
	 * @param TableNode $body
	 */
	public function searchResultXContains(int $number, TableNode $body) {
		if (!($body instanceof TableNode)) {
			return;
		}

		$searchResults = json_decode($this->response->getBody(), $asAssociativeArray = true);
		$searchResult = $searchResults[$number];

		foreach ($body->getRowsHash() as $expectedField => $expectedValue) {
			if (!array_key_exists($expectedField, $searchResult)) {
				Assert::fail("$expectedField was not found in response");
			}

			Assert::assertEquals($expectedValue, $searchResult[$expectedField], "Field '$expectedField' does not match ({$searchResult[$expectedField]})");
		}
	}

}
