<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

class SettingsContext implements Context, ActorAwareInterface {
	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function acceptSharesByDefaultCheckbox() {
		// forThe()->checkbox("Accept user...") can not be used here; that would
		// return the checkbox itself, but the element that the user interacts
		// with is the label.
		return Locator::forThe()->xpath("//label[normalize-space() = 'Accept user and group shares by default']")->
				describedAs("Accept shares by default checkbox in Sharing section in Personal Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function acceptSharesByDefaultCheckboxInput() {
		return Locator::forThe()->checkbox("Accept user and group shares by default")->
				describedAs("Accept shares by default checkbox input in Sharing section in Personal Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function allowResharingCheckbox() {
		// forThe()->checkbox("Allow resharing") can not be used here; that
		// would return the checkbox itself, but the element that the user
		// interacts with is the label.
		return Locator::forThe()->xpath("//label[normalize-space() = 'Allow resharing']")->
				describedAs("Allow resharing checkbox in Sharing section in Administration Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function allowResharingCheckboxInput() {
		return Locator::forThe()->checkbox("Allow resharing")->
				describedAs("Allow resharing checkbox input in Sharing section in Administration Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function restrictUsernameAutocompletionToGroupsCheckbox() {
		// forThe()->checkbox("Restrict username...") can not be used here; that
		// would return the checkbox itself, but the element that the user
		// interacts with is the label.
		return Locator::forThe()->xpath("//label[normalize-space() = 'Restrict username autocompletion to users within the same groups']")->
				describedAs("Restrict username autocompletion to groups checkbox in Sharing section in Administration Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function restrictUsernameAutocompletionToGroupsCheckboxInput() {
		return Locator::forThe()->checkbox("Restrict username autocompletion to users within the same groups")->
				describedAs("Restrict username autocompletion to groups checkbox input in Sharing section in Administration Sharing Settings");
	}

	/**
	 * @return Locator
	 */
	public static function systemTagsSelectTagButton() {
		return Locator::forThe()->id("s2id_systemtag")->
				describedAs("Select tag button in system tags section in Administration Settings");
	}

	/**
	 * @return Locator
	 */
	public static function systemTagsItemInDropdownForTag($tag) {
		return Locator::forThe()->xpath("//*[contains(concat(' ', normalize-space(@class), ' '), ' select2-result-label ')]//span[normalize-space() = '$tag']/ancestor::li")->
				descendantOf(self::select2Dropdown())->
				describedAs("Item in dropdown for tag $tag in system tags section in Administration Settings");
	}

	/**
	 * @return Locator
	 */
	private static function select2Dropdown() {
		return Locator::forThe()->css("#select2-drop")->
				describedAs("Select2 dropdown in Settings");
	}

	/**
	 * @return Locator
	 */
	private static function select2DropdownMask() {
		return Locator::forThe()->css("#select2-drop-mask")->
				describedAs("Select2 dropdown mask in Settings");
	}

	/**
	 * @return Locator
	 */
	public static function systemTagsTagNameInput() {
		return Locator::forThe()->id("systemtag_name")->
				describedAs("Tag name input in system tags section in Administration Settings");
	}

	/**
	 * @return Locator
	 */
	public static function systemTagsCreateOrUpdateButton() {
		return Locator::forThe()->id("systemtag_submit")->
				describedAs("Create/Update button in system tags section in Administration Settings");
	}

	/**
	 * @return Locator
	 */
	public static function systemTagsResetButton() {
		return Locator::forThe()->id("systemtag_reset")->
				describedAs("Reset button in system tags section in Administration Settings");
	}

	/**
	 * @When I disable accepting the shares by default
	 */
	public function iDisableAcceptingTheSharesByDefault() {
		$this->iSeeThatSharesAreAcceptedByDefault();

		$this->actor->find(self::acceptSharesByDefaultCheckbox(), 2)->click();
	}

	/**
	 * @When I disable resharing
	 */
	public function iDisableResharing() {
		$this->iSeeThatResharingIsEnabled();

		$this->actor->find(self::allowResharingCheckbox(), 2)->click();
	}

	/**
	 * @When I enable restricting username autocompletion to groups
	 */
	public function iEnableRestrictingUsernameAutocompletionToGroups() {
		$this->iSeeThatUsernameAutocompletionIsNotRestrictedToGroups();

		$this->actor->find(self::restrictUsernameAutocompletionToGroupsCheckbox(), 2)->click();
	}

	/**
	 * @When I create the tag :tag in the settings
	 */
	public function iCreateTheTagInTheSettings($tag) {
		$this->actor->find(self::systemTagsResetButton(), 10)->click();
		$this->actor->find(self::systemTagsTagNameInput())->setValue($tag);
		$this->actor->find(self::systemTagsCreateOrUpdateButton())->click();
	}

	/**
	 * @Then I see that shares are accepted by default
	 */
	public function iSeeThatSharesAreAcceptedByDefault() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::acceptSharesByDefaultCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that resharing is enabled
	 */
	public function iSeeThatResharingIsEnabled() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::allowResharingCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that resharing is disabled
	 */
	public function iSeeThatResharingIsDisabled() {
		PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::allowResharingCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that username autocompletion is restricted to groups
	 */
	public function iSeeThatUsernameAutocompletionIsRestrictedToGroups() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::restrictUsernameAutocompletionToGroupsCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that username autocompletion is not restricted to groups
	 */
	public function iSeeThatUsernameAutocompletionIsNotRestrictedToGroups() {
		PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::restrictUsernameAutocompletionToGroupsCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that shares are not accepted by default
	 */
	public function iSeeThatSharesAreNotAcceptedByDefault() {
		PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::acceptSharesByDefaultCheckboxInput(), 10)->isChecked());
	}

	/**
	 * @Then I see that the button to select tags is shown
	 */
	public function iSeeThatTheButtonToSelectTagsIsShown() {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::systemTagsSelectTagButton(), 10)->isVisible());
	}

	/**
	 * @Then I see that the dropdown for tags in the settings eventually contains the tag :tag
	 */
	public function iSeeThatTheDropdownForTagsInTheSettingsEventuallyContainsTheTag($tag) {
		// When the dropdown is opened it is not automatically updated if new
		// tags are added to the server, and when a tag is created, no explicit
		// feedback is provided to the user about the completion of that
		// operation (that is, when the tag is added to the server). Therefore,
		// to verify that creating a tag does in fact add it to the server it is
		// necessary to repeatedly open the dropdown until the tag is shown in
		// the dropdown (or the limit of tries is reached).

		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::systemTagsSelectTagButton(), 10)->isVisible());

		$actor = $this->actor;

		$tagFoundInDropdownCallback = function () use ($actor, $tag) {
			// Open the dropdown to look for the tag.
			$actor->find(self::systemTagsSelectTagButton())->click();

			// When the dropdown is opened it is initially empty, and its
			// contents are updated once received from the server. Therefore, a
			// timeout must be used when looking for the tags.
			try {
				$tagFound = $this->actor->find(self::systemTagsItemInDropdownForTag($tag), 10)->isVisible();
			} catch (NoSuchElementException $exception) {
				$tagFound = false;
			}

			// Close again the dropdown after looking for the tag. When a
			// dropdown is opened Select2 creates a special element that masks
			// every other element but the dropdown to get all mouse clicks;
			// this is used by Select2 to close the dropdown when the user
			// clicks outside it.
			$actor->find(self::select2DropdownMask())->click();

			return $tagFound;
		};

		$numberOfTries = 5;
		for ($i = 0; $i < $numberOfTries; $i++) {
			if ($tagFoundInDropdownCallback()) {
				return;
			}
		}

		PHPUnit_Framework_Assert::fail("The dropdown in system tags section in Administration Settings does not contain the tag $tag after $numberOfTries tries");
	}
}
