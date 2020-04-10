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

class FilesAppContext implements Context, ActorAwareInterface {

	use ActorAware;
	use FileListAncestorSetter;

	/**
	 * @return array
	 */
	public static function sections() {
		return [ "All files" => "files",
			"Recent" => "recent",
			"Favorites" => "favorites",
			"Shared with you" => "sharingin",
			"Shared with others" => "sharingout",
			"Shared by link" => "sharinglinks",
			"Tags" => "systemtagsfilter",
			"Deleted files" => "trashbin" ];
	}

	/**
	 * @return Locator
	 */
	private static function appMenu() {
		return Locator::forThe()->id("appmenu")->
				describedAs("App menu in header");
	}

	/**
	 * @return Locator
	 */
	public static function filesItemInAppMenu() {
		return Locator::forThe()->xpath("/li[@data-id = 'files']")->
				descendantOf(self::appMenu())->
				describedAs("Files item in app menu in header");
	}

	/**
	 * @return Locator
	 */
	public static function mainViewForSection($section) {
		$sectionId = self::sections()[$section];

		return Locator::forThe()->id("app-content-$sectionId")->
				describedAs("Main view for section $section in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function currentSectionMainView() {
		return Locator::forThe()->xpath("//*[starts-with(@id, 'app-content-')  and not(contains(concat(' ', normalize-space(@class), ' '), ' hidden '))]")->
				describedAs("Current section main view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function detailsView() {
		return Locator::forThe()->id("app-sidebar")->
				describedAs("Details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function closeDetailsViewButton() {
		return Locator::forThe()->css(".icon-close")->
				descendantOf(self::detailsView())->
				describedAs("Close details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function fileNameInDetailsView() {
		return Locator::forThe()->css(".app-sidebar-header__title")->
				descendantOf(self::detailsView())->
				describedAs("File name in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function favoriteActionInFileDetailsInDetailsView() {
		return Locator::forThe()->css(".app-sidebar-header__star")->
				descendantOf(self::fileDetailsInDetailsView())->
				describedAs("Favorite action in file details in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function notFavoritedStateIconInFileDetailsInDetailsView() {
		return Locator::forThe()->css(".icon-star")->
				descendantOf(self::favoriteActionInFileDetailsInDetailsView())->
				describedAs("Not favorited state icon in file details in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function favoritedStateIconInFileDetailsInDetailsView() {
		return Locator::forThe()->css(".icon-starred")->
				descendantOf(self::favoriteActionInFileDetailsInDetailsView())->
				describedAs("Favorited state icon in file details in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function fileDetailsInDetailsViewWithText($fileDetailsText) {
		return Locator::forThe()->xpath("//span[normalize-space() = '$fileDetailsText']")->
				descendantOf(self::fileDetailsInDetailsView())->
				describedAs("File details with text \"$fileDetailsText\" in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	private static function fileDetailsInDetailsView() {
		return Locator::forThe()->css(".app-sidebar-header__desc")->
				descendantOf(self::detailsView())->
				describedAs("File details in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function inputFieldForTagsInDetailsView() {
		return Locator::forThe()->css(".systemTagsInfoView")->
				descendantOf(self::detailsView())->
				describedAs("Input field for tags in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function itemInInputFieldForTagsInDetailsViewForTag($tag) {
		return Locator::forThe()->xpath("//span[normalize-space() = '$tag']")->
				descendantOf(self::inputFieldForTagsInDetailsView())->
				describedAs("Item in input field for tags in details view for tag $tag in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function itemInDropdownForTag($tag) {
		return Locator::forThe()->xpath("//*[contains(concat(' ', normalize-space(@class), ' '), ' select2-result-label ')]//span[normalize-space() = '$tag']/ancestor::li")->
				descendantOf(self::select2Dropdown())->
				describedAs("Item in dropdown for tag $tag in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function checkmarkInItemInDropdownForTag($tag) {
		return Locator::forThe()->css(".checkmark")->
				descendantOf(self::itemInDropdownForTag($tag))->
				describedAs("Checkmark in item in dropdown for tag $tag in Files app");
	}

	/**
	 * @return Locator
	 */
	private static function select2Dropdown() {
		return Locator::forThe()->css("#select2-drop")->
				describedAs("Select2 dropdown in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function tabHeaderInDetailsViewNamed($tabHeaderName) {
		return Locator::forThe()->xpath("//li[normalize-space() = '$tabHeaderName']")->
				descendantOf(self::tabHeadersInDetailsView())->
				describedAs("Tab header named $tabHeaderName in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	private static function tabHeadersInDetailsView() {
		return Locator::forThe()->css(".app-sidebar-tabs__nav")->
				descendantOf(self::detailsView())->
				describedAs("Tab headers in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function tabInDetailsViewNamed($tabName) {
		return Locator::forThe()->xpath("//div[contains(concat(' ', normalize-space(@class), ' '), ' app-sidebar-tabs__content ')]/section[@aria-labelledby = '$tabName' and @role = 'tabpanel']")->
				descendantOf(self::detailsView())->
				describedAs("Tab named $tabName in details view in Files app");
	}

	/**
	 * @return Locator
	 */
	public static function loadingIconForTabInDetailsViewNamed($tabName) {
		return Locator::forThe()->css(".icon-loading")->
				descendantOf(self::tabInDetailsViewNamed($tabName))->
				describedAs("Loading icon for tab named $tabName in details view in Files app");
	}

	/**
	 * @Given I open the Files app
	 */
	public function iOpenTheFilesApp() {
		$this->actor->find(self::filesItemInAppMenu(), 10)->click();
	}

	/**
	 * @Given I close the details view
	 */
	public function iCloseTheDetailsView() {
		$this->actor->find(self::closeDetailsViewButton(), 10)->click();
	}

	/**
	 * @Given I open the input field for tags in the details view
	 */
	public function iOpenTheInputFieldForTagsInTheDetailsView() {
		$this->actor->find(self::fileDetailsInDetailsViewWithText("Tags"), 10)->click();
	}

	/**
	 * @Given I open the :tabName tab in the details view
	 */
	public function iOpenTheTabInTheDetailsView($tabName) {
		$this->actor->find(self::tabHeaderInDetailsViewNamed($tabName), 10)->click();
	}

	/**
	 * @When I mark the file as favorite in the details view
	 */
	public function iMarkTheFileAsFavoriteInTheDetailsView() {
		$this->iSeeThatTheFileIsNotMarkedAsFavoriteInTheDetailsView();

		$this->actor->find(self::favoriteActionInFileDetailsInDetailsView(), 10)->click();
	}

	/**
	 * @When I unmark the file as favorite in the details view
	 */
	public function iUnmarkTheFileAsFavoriteInTheDetailsView() {
		$this->iSeeThatTheFileIsMarkedAsFavoriteInTheDetailsView();

		$this->actor->find(self::favoriteActionInFileDetailsInDetailsView(), 10)->click();
	}

	/**
	 * @When I check the tag :tag in the dropdown for tags in the details view
	 */
	public function iCheckTheTagInTheDropdownForTagsInTheDetailsView($tag) {
		$this->iSeeThatTheTagInTheDropdownForTagsInTheDetailsViewIsNotChecked($tag);

		$this->actor->find(self::itemInDropdownForTag($tag), 10)->click();
	}

	/**
	 * @When I uncheck the tag :tag in the dropdown for tags in the details view
	 */
	public function iUncheckTheTagInTheDropdownForTagsInTheDetailsView($tag) {
		$this->iSeeThatTheTagInTheDropdownForTagsInTheDetailsViewIsChecked($tag);

		$this->actor->find(self::itemInDropdownForTag($tag), 10)->click();
	}

	/**
	 * @Then I see that the current page is the Files app
	 */
	public function iSeeThatTheCurrentPageIsTheFilesApp() {
		PHPUnit_Framework_Assert::assertStringStartsWith(
				$this->actor->locatePath("/apps/files/"),
				$this->actor->getSession()->getCurrentUrl());

		$this->setFileListAncestorForActor(self::currentSectionMainView(), $this->actor);
	}

	/**
	 * @Then I see that the details view is open
	 */
	public function iSeeThatTheDetailsViewIsOpen() {
		// The sidebar always exists in the DOM, so it has to be explicitly
		// waited for it to be visible instead of relying on the implicit wait
		// made to find the element.
		if (!WaitFor::elementToBeEventuallyShown(
				$this->actor,
				self::detailsView(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The details view is not open yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the details view is closed
	 */
	public function iSeeThatTheDetailsViewIsClosed() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::detailsView(),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The details view is not closed yet after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the file name shown in the details view is :fileName
	 */
	public function iSeeThatTheFileNameShownInTheDetailsViewIs($fileName) {
		PHPUnit_Framework_Assert::assertEquals(
				$this->actor->find(self::fileNameInDetailsView(), 10)->getText(), $fileName);
	}

	/**
	 * @Then I see that the file is marked as favorite in the details view
	 */
	public function iSeeThatTheFileIsMarkedAsFavoriteInTheDetailsView() {
		PHPUnit_Framework_Assert::assertNotNull(
				$this->actor->find(self::favoritedStateIconInFileDetailsInDetailsView(), 10));
	}

	/**
	 * @Then I see that the file is not marked as favorite in the details view
	 */
	public function iSeeThatTheFileIsNotMarkedAsFavoriteInTheDetailsView() {
		PHPUnit_Framework_Assert::assertNotNull(
				$this->actor->find(self::notFavoritedStateIconInFileDetailsInDetailsView(), 10));
	}

	/**
	 * @Then I see that the input field for tags in the details view is shown
	 */
	public function iSeeThatTheInputFieldForTagsInTheDetailsViewIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::inputFieldForTagsInDetailsView(), 10)->isVisible());
	}

	/**
	 * @Then I see that the input field for tags in the details view contains the tag :tag
	 */
	public function iSeeThatTheInputFieldForTagsInTheDetailsViewContainsTheTag($tag) {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::itemInInputFieldForTagsInDetailsViewForTag($tag), 10)->isVisible());
	}

	/**
	 * @Then I see that the input field for tags in the details view does not contain the tag :tag
	 */
	public function iSeeThatTheInputFieldForTagsInTheDetailsViewDoesNotContainTheTag($tag) {
		$this->iSeeThatTheInputFieldForTagsInTheDetailsViewIsShown();

		try {
			PHPUnit_Framework_Assert::assertFalse(
					$this->actor->find(self::itemInInputFieldForTagsInDetailsViewForTag($tag))->isVisible());
		} catch (NoSuchElementException $exception) {
		}
	}

	/**
	 * @Then I see that the tag :tag in the dropdown for tags in the details view is checked
	 */
	public function iSeeThatTheTagInTheDropdownForTagsInTheDetailsViewIsChecked($tag) {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::checkmarkInItemInDropdownForTag($tag), 10)->isVisible());
	}

	/**
	 * @Then I see that the tag :tag in the dropdown for tags in the details view is not checked
	 */
	public function iSeeThatTheTagInTheDropdownForTagsInTheDetailsViewIsNotChecked($tag) {
		PHPUnit_Framework_Assert::assertTrue(
				$this->actor->find(self::itemInDropdownForTag($tag), 10)->isVisible());

		PHPUnit_Framework_Assert::assertFalse(
				$this->actor->find(self::checkmarkInItemInDropdownForTag($tag))->isVisible());
	}

	/**
	 * @When I see that the :tabName tab in the details view is eventually loaded
	 */
	public function iSeeThatTheTabInTheDetailsViewIsEventuallyLoaded($tabName) {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::loadingIconForTabInDetailsViewNamed($tabName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The $tabName tab in the details view has not been loaded after $timeout seconds");
		}
	}
}
