<?php

/**
 *
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv) (skjnldsv@protonmail.com)
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

use Behat\Behat\Context\Context;

class ContactsMenuContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function contactsMenuButton() {
		return Locator::forThe()->xpath("//*[@id = 'header']//*[@id = 'contactsmenu']")->
				describedAs("Contacts menu button");
	}

	/**
	 * @return Locator
	 */
	public static function contactsMenu() {
		return Locator::forThe()->css(".menu")->
				descendantOf(self::contactsMenuButton())->
				describedAs("Contacts menu");
	}

	/**
	 * @return Locator
	 */
	public static function contactsMenuSearchInput() {
		return Locator::forThe()->id("contactsmenu-search")->
				descendantOf(self::contactsMenu())->
				describedAs("Contacts menu search input");
	}

	/**
	 * @return Locator
	 */
	public static function noResultsMessage() {
		return Locator::forThe()->xpath("//*[@class = 'emptycontent' and normalize-space() = 'No contacts found']")->
				descendantOf(self::contactsMenu())->
				describedAs("No results message in Contacts menu");
	}

	/**
	 * @return Locator
	 */
	private static function menuItemFor($contactName) {
		return Locator::forThe()->xpath("//*[@class = 'full-name' and normalize-space() = '$contactName']")->
				descendantOf(self::contactsMenu())->
				describedAs($contactName . " contact in Contacts menu");
	}

	/**
	 * @When I open the Contacts menu
	 */
	public function iOpenTheContactsMenu() {
		$this->actor->find(self::contactsMenuButton(), 10)->click();
	}

	/**
	 * @When I search for the user :user
	 */
	public function iSearchForTheUser($user) {
		$this->actor->find(self::contactsMenuSearchInput(), 10)->setValue($user . "\r");
	}

	/**
	 * @Then I see that the Contacts menu is shown
	 */
	public function iSeeThatTheContactsMenuIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::contactsMenu(), 10)->isVisible());
	}

	/**
	 * @Then I see that the Contacts menu search input is shown
	 */
	public function iSeeThatTheContactsMenuSearchInputIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::contactsMenuSearchInput(), 10)->isVisible());
	}

	/**
	 * @Then I see that the no results message in the Contacts menu is shown
	 */
	public function iSeeThatTheNoResultsMessageInTheContactsMenuIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::noResultsMessage(), 10)->isVisible());
	}

	/**
	 * @Then I see that the contact :contactName in the Contacts menu is shown
	 */
	public function iSeeThatTheContactInTheContactsMenuIsShown($contactName) {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::menuItemFor($contactName), 10)->isVisible());
	}

	/**
	 * @Then I see that the contact :contactName in the Contacts menu is not shown
	 */
	public function iSeeThatTheContactInTheContactsMenuIsNotShown($contactName) {
		$this->iSeeThatThecontactsMenuIsShown();

		try {
			PHPUnit_Framework_Assert::assertFalse(
					$this->actor->find(self::menuItemFor($contactName))->isVisible());
		} catch (NoSuchElementException $exception) {
		}
	}

	/**
	 * @Then I see that the contact :contactName in the Contacts menu is eventually not shown
	 */
	public function iSeeThatTheContactInTheContactsMenuIsEventuallyNotShown($contactName) {
		$this->iSeeThatThecontactsMenuIsShown();

		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::menuItemFor($contactName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $contactName contact in Contacts menu is still shown after $timeout seconds");
		}
	}

}
