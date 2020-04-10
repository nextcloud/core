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

class AppSettingsContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function appSettings() {
		return Locator::forThe()->id("app-settings")->
			describedAs("App settings");
	}
	/**
	 * @return Locator
	 */
	public static function appSettingsContent() {
		return Locator::forThe()->id("app-settings-content")->
			descendantOf(self::appSettings())->
			describedAs("App settings");
	}

	/**
	 * @return Locator
	 */
	public static function appSettingsOpenButton() {
		return Locator::forThe()->xpath("//div[@id = 'app-settings-header']/button")->
			descendantOf(self::appSettings())->
			describedAs("The button to open the app settings");
	}

	/**
	 * @return Locator
	 */
	public static function checkboxInTheSettings($id) {
		return Locator::forThe()->xpath("//input[@id = '$id']")->
			descendantOf(self::appSettingsContent())->
			describedAs("The $id checkbox in the settings");
	}

	/**
	 * @return Locator
	 */
	public static function checkboxLabelInTheSettings($id) {
		return Locator::forThe()->xpath("//label[@for = '$id']")->
			descendantOf(self::appSettingsContent())->
			describedAs("The label for the $id checkbox in the settings");
	}

	/**
	 * @Given I open the settings
	 */
	public function iOpenTheSettings() {
		$this->actor->find(self::appSettingsOpenButton())->click();
	}

	/**
	 * @Given I toggle the :id checkbox in the settings
	 */
	public function iToggleTheCheckboxInTheSettingsTo($id) {
		$locator = self::CheckboxInTheSettings($id);

		// If locator is not visible, fallback to label
		if (!$this->actor->find(self::CheckboxInTheSettings($id))->isVisible()) {
			$locator = self::checkboxLabelInTheSettings($id);
		}

		$this->actor->find($locator)->click();
	}

	/**
	 * @Then I see that the settings are opened
	 */
	public function iSeeThatTheSettingsAreOpened() {
		WaitFor::elementToBeEventuallyShown($this->actor, self::appSettingsContent());
	}

}
