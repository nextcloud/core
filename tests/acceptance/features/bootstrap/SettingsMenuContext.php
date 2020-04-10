<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

class SettingsMenuContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function settingsSectionInHeader() {
		return Locator::forThe()->xpath("//*[@id = 'header']//*[@id = 'settings']")->
				describedAs("Settings menu section in the header");
	}

	/**
	 * @return Locator
	 */
	public static function settingsMenuButton() {
		return Locator::forThe()->id("expand")->
				descendantOf(self::settingsSectionInHeader())->
				describedAs("Settings menu button");
	}

	/**
	 * @return Locator
	 */
	public static function settingsMenu() {
		return Locator::forThe()->id("expanddiv")->
				descendantOf(self::settingsSectionInHeader())->
				describedAs("Settings menu");
	}

	/**
	 * @return Locator
	 */
	public static function usersMenuItem() {
		return self::menuItemFor("Users");
	}

	/**
	 * @return Locator
	 */
	public static function usersAppsItem() {
		return self::menuItemFor("Apps");
	}

	/**
	 * @return Locator
	 */
	public static function logOutMenuItem() {
		return self::menuItemFor("Log out");
	}

	/**
	 * @return Locator
	 */
	private static function menuItemFor($itemText) {
		return Locator::forThe()->xpath("//a[normalize-space() = '$itemText']")->
				descendantOf(self::settingsMenu())->
				describedAs($itemText . " item in Settings menu");
	}

	/**
	 * @param string $itemText
	 * @return Locator
	 */
	private static function settingsPanelFor($itemText) {
		return Locator::forThe()->xpath("//div[@id = 'app-navigation']//ul//li[@class = 'app-navigation-caption' and normalize-space() = '$itemText']")->
		describedAs($itemText . " item in Settings panel");
	}

	/**
	 * @param string $itemText
	 * @return Locator
	 */
	private static function settingsPanelEntryFor($itemText) {
		return Locator::forThe()->xpath("//div[@id = 'app-navigation']//ul//li[normalize-space() = '$itemText']")->
		describedAs($itemText . " entry in Settings panel");
	}

	/**
	 * @return array
	 */
	public function menuItems() {
		return $this->actor->find(self::settingsMenu(), 10)
					->getWrappedElement()->findAll('xpath', '//a');
	}

	/**
	 * @When I open the Settings menu
	 */
	public function iOpenTheSettingsMenu() {
		$this->actor->find(self::settingsMenuButton(), 10)->click();
	}

	/**
	 * @When I open the User settings
	 */
	public function iOpenTheUserSettings() {
		$this->iOpenTheSettingsMenu();

		$this->actor->find(self::usersMenuItem(), 2)->click();
	}

	/**
	 * @When I open the Apps management
	 */
	public function iOpenTheAppsManagement() {
		$this->iOpenTheSettingsMenu();

		$this->actor->find(self::usersAppsItem(), 2)->click();
	}

	/**
	 * @When I visit the settings page
	 */
	public function iVisitTheSettingsPage() {
		$this->iOpenTheSettingsMenu();
		$this->actor->find(self::menuItemFor('Settings'), 2)->click();
	}

	/**
	 * @When I log out
	 */
	public function iLogOut() {
		$this->iOpenTheSettingsMenu();

		$this->actor->find(self::logOutMenuItem(), 2)->click();
	}

	/**
	 * @Then I see that the Settings menu is shown
	 */
	public function iSeeThatTheSettingsMenuIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::settingsMenu(), 10)->isVisible());
	}

	/**
	 * @Then I see that the Settings menu has only :items items
	 */
	public function iSeeThatTheSettingsMenuHasOnlyXItems($items) {
		PHPUnit_Framework_Assert::assertCount(intval($items), self::menuItems());
	}

	/**
	 * @Then I see that the :itemText item in the Settings menu is shown
	 */
	public function iSeeThatTheItemInTheSettingsMenuIsShown($itemText) {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::menuItemFor($itemText), 10)->isVisible());
	}

	/**
	 * @Then I see that the :itemText item in the Settings menu is not shown
	 */
	public function iSeeThatTheItemInTheSettingsMenuIsNotShown($itemText) {
		$this->iSeeThatTheSettingsMenuIsShown();

		try {
			PHPUnit_Framework_Assert::assertFalse(
					$this->actor->find(self::menuItemFor($itemText))->isVisible());
		} catch (NoSuchElementException $exception) {
		}
	}

	/**
	 * @Then I see that the :itemText settings panel is shown
	 */
	public function iSeeThatTheItemSettingsPanelIsShown($itemText) {
		PHPUnit_Framework_Assert::assertTrue(
			$this->actor->find(self::settingsPanelFor($itemText), 10)->isVisible()
		);
	}

	/**
	 * @Then I see that the :itemText entry in the settings panel is shown
	 */
	public function iSeeThatTheItemEntryInTheSettingsPanelIsShown($itemText) {
		PHPUnit_Framework_Assert::assertTrue(
			$this->actor->find(self::settingsPanelEntryFor($itemText), 10)->isVisible()
		);
	}

	/**
	 * @Then I see that the :itemText settings panel is not shown
	 */
	public function iSeeThatTheItemSettingsPanelIsNotShown($itemText) {
		try {
			PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::settingsPanelFor($itemText), 10)->isVisible()
			);
		} catch (NoSuchElementException $exception) {
		}
	}

}
