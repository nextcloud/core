<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

class AppNavigationContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function appNavigation() {
		return Locator::forThe()->id("app-navigation")->
			describedAs("App navigation");
	}

	/**
	 * @return Locator
	 */
	public static function appNavigationSectionItemFor($sectionText) {
		return Locator::forThe()->xpath("//li/a[normalize-space() = '$sectionText']/..")->
			descendantOf(self::appNavigation())->
			describedAs($sectionText . " section item in App Navigation");
	}

	/**
	 * @return Locator
	 */
	public static function appNavigationSectionItemInFor($caption, $sectionText) {
		return Locator::forThe()->xpath("//li[normalize-space() = '$caption']/following-sibling::li/a[normalize-space() = '$sectionText']/..")->
			descendantOf(self::appNavigation())->
			describedAs($sectionText . " section item of the $caption group in App Navigation");
	}

	/**
	 * @return Locator
	 */
	public static function appNavigationCurrentSectionItem() {
		return Locator::forThe()->css(".active")->
			descendantOf(self::appNavigation())->
			describedAs("Current section item in App Navigation");
	}

	/**
	 * @return Locator
	 */
	public static function buttonForTheSection($class, $section) {
		return Locator::forThe()->css("." . $class)->
			descendantOf(self::appNavigationSectionItemFor($section))->
			describedAs("The $class button on the $section section in App Navigation");
	}

	/**
	 * @return Locator
	 */
	public static function counterForTheSection($section) {
		return Locator::forThe()->css(".app-navigation-entry-utils-counter")->
			descendantOf(self::appNavigationSectionItemFor($section))->
			describedAs("The counter for the $section section in App Navigation");
	}

	/**
	 * @Given I open the :section section
	 */
	public function iOpenTheSection($section) {
		$this->actor->find(self::appNavigationSectionItemFor($section), 10)->click();
	}

	/**
	 * @Given I open the :section section of the :caption group
	 */
	public function iOpenTheSectionOf($caption, $section) {
		$this->actor->find(self::appNavigationSectionItemInFor($caption, $section), 10)->click();
	}

	/**
	 * @Given I click the :class button on the :section section
	 */
	public function iClickTheButtonInTheSection($class, $section) {
		$this->actor->find(self::buttonForTheSection($class, $section), 10)->click();
	}

	/**
	 * @Then I see that the current section is :section
	 */
	public function iSeeThatTheCurrentSectionIs($section) {
		PHPUnit_Framework_Assert::assertEquals($this->actor->find(self::appNavigationCurrentSectionItem(), 10)->getText(), $section);
	}

	/**
	 * @Then I see that the section :section is shown
	 */
	public function iSeeThatTheSectionIsShown($section) {
		WaitFor::elementToBeEventuallyShown($this->actor, self::appNavigationSectionItemFor($section));
	}

	/**
	 * @Then I see that the section :section is not shown
	 */
	public function iSeeThatTheSectionIsNotShown($section) {
		WaitFor::elementToBeEventuallyNotShown($this->actor, self::appNavigationSectionItemFor($section));
	}

	/**
	 * @Then I see that the section :section has a count of :count
	 */
	public function iSeeThatTheSectionHasACountOf($section, $count) {
		PHPUnit_Framework_Assert::assertEquals($this->actor->find(self::counterForTheSection($section), 10)->getText(), $count);
	}

	/**
	 * @Then I see that the section :section does not have a count
	 */
	public function iSeeThatTheSectionDoesNotHaveACount($section) {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::counterForTheSection($section),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The counter for section $section is still shown after $timeout seconds");
		}
	}

}
