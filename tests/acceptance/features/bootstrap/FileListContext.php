<?php

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

class FileListContext implements Context, ActorAwareInterface {

	/**
	 * @var Actor
	 */
	private $actor;

	/**
	 * @var array
	 */
	private $fileListAncestorsByActor;

	/**
	 * @var Locator
	 */
	private $fileListAncestor;

	/**
	 * @BeforeScenario
	 */
	public function initializeFileListAncestors() {
		$this->fileListAncestorsByActor = [];
		$this->fileListAncestor = null;
	}

	/**
	 * @param Actor $actor
	 */
	public function setCurrentActor(Actor $actor) {
		$this->actor = $actor;

		if (array_key_exists($actor->getName(), $this->fileListAncestorsByActor)) {
			$this->fileListAncestor = $this->fileListAncestorsByActor[$actor->getName()];
		} else {
			$this->fileListAncestor = null;
		}
	}

	/**
	 * Sets the file list ancestor to be used in the steps performed by the
	 * given actor from that point on (until changed again).
	 *
	 * This is meant to be called from other contexts, for example, when the
	 * Files app or the public page for a shared folder are opened.
	 *
	 * The FileListAncestorSetter trait can be used to reduce the boilerplate
	 * needed to set the file list ancestor from other contexts.
	 *
	 * @param null|Locator $fileListAncestor the file list ancestor
	 * @param Actor $actor the actor
	 */
	public function setFileListAncestorForActor($fileListAncestor, Actor $actor) {
		$this->fileListAncestorsByActor[$actor->getName()] = $fileListAncestor;
	}

	/**
	 * @return Locator
	 */
	public static function mainWorkingIcon($fileListAncestor) {
		return Locator::forThe()->css(".mask.icon-loading")->
				descendantOf($fileListAncestor)->
				describedAs("Main working icon in file list");
	}

	/**
	 * @return Locator
	 */
	public static function breadcrumbs($fileListAncestor) {
		return Locator::forThe()->css("#controls .breadcrumb")->
				descendantOf($fileListAncestor)->
				describedAs("Breadcrumbs in file list");
	}

	/**
	 * @return Locator
	 */
	public static function createMenuButton($fileListAncestor) {
		return Locator::forThe()->css("#controls .button.new")->
				descendantOf($fileListAncestor)->
				describedAs("Create menu button in file list");
	}

	/**
	 * @return Locator
	 */
	private static function createMenuItemFor($fileListAncestor, $newType) {
		return Locator::forThe()->xpath("//div[contains(concat(' ', normalize-space(@class), ' '), ' newFileMenu ')]//span[normalize-space() = '$newType']/ancestor::li")->
				descendantOf($fileListAncestor)->
				describedAs("Create $newType menu item in file list");
	}

	/**
	 * @return Locator
	 */
	public static function createNewFolderMenuItem($fileListAncestor) {
		return self::createMenuItemFor($fileListAncestor, "New folder");
	}

	/**
	 * @return Locator
	 */
	public static function createNewFolderMenuItemNameInput($fileListAncestor) {
		return Locator::forThe()->css(".filenameform input")->
				descendantOf(self::createNewFolderMenuItem($fileListAncestor))->
				describedAs("Name input in create new folder menu item in file list");
	}

	/**
	 * @return Locator
	 */
	public static function fileListHeader($fileListAncestor) {
		return Locator::forThe()->css("thead")->
				descendantOf($fileListAncestor)->
				describedAs("Header in file list");
	}

	/**
	 * @return Locator
	 */
	public static function selectedFilesActionsMenuButton($fileListAncestor) {
		return Locator::forThe()->css(".actions-selected")->
				descendantOf(self::fileListHeader($fileListAncestor))->
				describedAs("Selected files actions menu button in file list");
	}

	/**
	 * @return Locator
	 */
	public static function selectedFilesActionsMenu() {
		return Locator::forThe()->css(".filesSelectMenu")->
				describedAs("Selected files actions menu in file list");
	}

	/**
	 * @return Locator
	 */
	private static function selectedFilesActionsMenuItemFor($itemText) {
		return Locator::forThe()->xpath("//a[normalize-space() = '$itemText']")->
				descendantOf(self::selectedFilesActionsMenu())->
				describedAs($itemText . " item in selected files actions menu in file list");
	}

	/**
	 * @return Locator
	 */
	public static function moveOrCopySelectedFilesMenuItem() {
		return self::selectedFilesActionsMenuItemFor("Move or copy");
	}

	/**
	 * @return Locator
	 */
	public static function rowForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->xpath("//*[@id = 'fileList']//span[contains(concat(' ', normalize-space(@class), ' '), ' nametext ') and normalize-space() = '$fileName']/ancestor::tr")->
				descendantOf($fileListAncestor)->
				describedAs("Row for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function rowForFilePreceding($fileListAncestor, $fileName1, $fileName2) {
		return Locator::forThe()->xpath("//preceding-sibling::tr//span[contains(concat(' ', normalize-space(@class), ' '), ' nametext ') and normalize-space() = '$fileName1']/ancestor::tr")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName2))->
				describedAs("Row for file $fileName1 preceding $fileName2 in file list");
	}

	/**
	 * @return Locator
	 */
	public static function selectionCheckboxForFile($fileListAncestor, $fileName) {
		// Note that the element that the user interacts with is the label, not
		// the checbox itself.
		return Locator::forThe()->css(".selection label")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Selection checkbox for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function selectionCheckboxInputForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".selection input[type=checkbox]")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Selection checkbox input for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function favoriteMarkForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".favorite-mark")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Favorite mark for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function notFavoritedStateIconForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".icon-star")->
				descendantOf(self::favoriteMarkForFile($fileListAncestor, $fileName))->
				describedAs("Not favorited state icon for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function favoritedStateIconForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".icon-starred")->
				descendantOf(self::favoriteMarkForFile($fileListAncestor, $fileName))->
				describedAs("Favorited state icon for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function mainLinkForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".name")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Main link for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function renameInputForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css("input.filename")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Rename input for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function commentActionForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".action-comment")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Comment action for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function shareActionForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".action-share")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("Share action for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function fileActionsMenuButtonForFile($fileListAncestor, $fileName) {
		return Locator::forThe()->css(".action-menu")->
				descendantOf(self::rowForFile($fileListAncestor, $fileName))->
				describedAs("File actions menu button for file $fileName in file list");
	}

	/**
	 * @return Locator
	 */
	public static function fileActionsMenu() {
		return Locator::forThe()->css(".fileActionsMenu")->
				describedAs("File actions menu in file list");
	}

	/**
	 * @return Locator
	 */
	private static function fileActionsMenuItemFor($itemText) {
		return Locator::forThe()->xpath("//a[normalize-space() = '$itemText']")->
				descendantOf(self::fileActionsMenu())->
				describedAs($itemText . " item in file actions menu in file list");
	}

	/**
	 * @return Locator
	 */
	public static function addToFavoritesMenuItem() {
		return self::fileActionsMenuItemFor("Add to favorites");
	}

	/**
	 * @return Locator
	 */
	public static function removeFromFavoritesMenuItem() {
		return self::fileActionsMenuItemFor("Remove from favorites");
	}

	/**
	 * @return Locator
	 */
	public static function detailsMenuItem() {
		return self::fileActionsMenuItemFor("Details");
	}

	/**
	 * @return Locator
	 */
	public static function renameMenuItem() {
		return self::fileActionsMenuItemFor("Rename");
	}

	/**
	 * @return Locator
	 */
	public static function moveOrCopyMenuItem() {
		return self::fileActionsMenuItemFor("Move or copy");
	}

	/**
	 * @return Locator
	 */
	public static function viewFileInFolderMenuItem() {
		return self::fileActionsMenuItemFor("View in folder");
	}

	/**
	 * @return Locator
	 */
	public static function deleteMenuItem() {
		return self::fileActionsMenuItemFor("Delete");
	}

	/**
	 * @Given I create a new folder named :folderName
	 */
	public function iCreateANewFolderNamed($folderName) {
		$this->actor->find(self::createMenuButton($this->fileListAncestor), 10)->click();

		$this->actor->find(self::createNewFolderMenuItem($this->fileListAncestor), 2)->click();
		$this->actor->find(self::createNewFolderMenuItemNameInput($this->fileListAncestor), 2)->setValue($folderName . "\r");
	}

	/**
	 * @Given I enter in the folder named :folderName
	 */
	public function iEnterInTheFolderNamed($folderName) {
		$this->actor->find(self::mainLinkForFile($this->fileListAncestor, $folderName), 10)->click();
	}

	/**
	 * @Given I select :fileName
	 */
	public function iSelect($fileName) {
		$this->iSeeThatIsNotSelected($fileName);

		$this->actor->find(self::selectionCheckboxForFile($this->fileListAncestor, $fileName), 10)->click();
	}

	/**
	 * @Given I start the move or copy operation for the selected files
	 */
	public function iStartTheMoveOrCopyOperationForTheSelectedFiles() {
		$this->actor->find(self::selectedFilesActionsMenuButton($this->fileListAncestor), 10)->click();

		$this->actor->find(self::moveOrCopySelectedFilesMenuItem(), 2)->click();
	}

	/**
	 * @Given I open the details view for :fileName
	 */
	public function iOpenTheDetailsViewFor($fileName) {
		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::detailsMenuItem(), 2)->click();
	}

	/**
	 * @Given I rename :fileName1 to :fileName2
	 */
	public function iRenameTo($fileName1, $fileName2) {
		$this->openFileActionsMenuForFile($fileName1);

		$this->actor->find(self::renameMenuItem(), 2)->click();

		// For reference, due to a bug in the Firefox driver of Selenium and/or
		// maybe in Firefox itself, as a range is selected in the rename input
		// (the name of the file, without its extension) when the value is set
		// the window must be in the foreground. Otherwise, if the window is in
		// the background, instead of setting the value in the whole field it
		// would be set only in the selected range.
		// This should not be a problem, though, as the default behaviour is to
		// bring the browser window to the foreground when switching to a
		// different actor.
		$this->actor->find(self::renameInputForFile($this->fileListAncestor, $fileName1), 10)->setValue($fileName2 . "\r");
	}

	/**
	 * @Given I start the move or copy operation for :fileName
	 */
	public function iStartTheMoveOrCopyOperationFor($fileName) {
		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::moveOrCopyMenuItem(), 2)->click();
	}

	/**
	 * @Given I mark :fileName as favorite
	 */
	public function iMarkAsFavorite($fileName) {
		$this->iSeeThatIsNotMarkedAsFavorite($fileName);

		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::addToFavoritesMenuItem(), 2)->click();
	}

	/**
	 * @Given I unmark :fileName as favorite
	 */
	public function iUnmarkAsFavorite($fileName) {
		$this->iSeeThatIsMarkedAsFavorite($fileName);

		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::removeFromFavoritesMenuItem(), 2)->click();
	}

	/**
	 * @When I view :fileName in folder
	 */
	public function iViewInFolder($fileName) {
		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::viewFileInFolderMenuItem(), 2)->click();
	}

	/**
	 * @When I delete :fileName
	 */
	public function iDelete($fileName) {
		$this->openFileActionsMenuForFile($fileName);

		$this->actor->find(self::deleteMenuItem(), 2)->click();
	}

	/**
	 * @When I open the unread comments for :fileName
	 */
	public function iOpenTheUnreadCommentsFor($fileName) {
		$this->actor->find(self::commentActionForFile($this->fileListAncestor, $fileName), 10)->click();
	}

	/**
	 * @Then I see that the file list is eventually loaded
	 */
	public function iSeeThatTheFileListIsEventuallyLoaded() {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::mainWorkingIcon($this->fileListAncestor),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The main working icon for the file list is still shown after $timeout seconds");
		}
	}

	/**
	 * @Then I see that the file list is currently in :path
	 */
	public function iSeeThatTheFileListIsCurrentlyIn($path) {
		// The text of the breadcrumbs is the text of all the crumbs separated
		// by white spaces.
		PHPUnit_Framework_Assert::assertEquals(
			str_replace('/', ' ', $path), $this->actor->find(self::breadcrumbs($this->fileListAncestor), 10)->getText());
	}

	/**
	 * @Then I see that it is not possible to create new files
	 */
	public function iSeeThatItIsNotPossibleToCreateNewFiles() {
		// Once a file list is loaded the "Create" menu button is always in the
		// DOM, so it is checked if it is visible or not.
		PHPUnit_Framework_Assert::assertFalse($this->actor->find(self::createMenuButton($this->fileListAncestor))->isVisible());
	}

	/**
	 * @Then I see that the file list contains a file named :fileName
	 */
	public function iSeeThatTheFileListContainsAFileNamed($fileName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::rowForFile($this->fileListAncestor, $fileName), 10));
	}

	/**
	 * @Then I see that the file list does not contain a file named :fileName
	 */
	public function iSeeThatTheFileListDoesNotContainAFileNamed($fileName) {
		if (!WaitFor::elementToBeEventuallyNotShown(
				$this->actor,
				self::rowForFile($this->fileListAncestor, $fileName),
				$timeout = 10 * $this->actor->getFindTimeoutMultiplier())) {
			PHPUnit_Framework_Assert::fail("The file list still contains a file named $fileName after $timeout seconds");
		}
	}

	/**
	 * @Then I see that :fileName1 precedes :fileName2 in the file list
	 */
	public function iSeeThatPrecedesInTheFileList($fileName1, $fileName2) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::rowForFilePreceding($this->fileListAncestor, $fileName1, $fileName2), 10));
	}

	/**
	 * @Then I see that :fileName is not selected
	 */
	public function iSeeThatIsNotSelected($fileName) {
		PHPUnit_Framework_Assert::assertFalse($this->actor->find(self::selectionCheckboxInputForFile($this->fileListAncestor, $fileName), 10)->isChecked());
	}

	/**
	 * @Then I see that :fileName is marked as favorite
	 */
	public function iSeeThatIsMarkedAsFavorite($fileName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::favoritedStateIconForFile($this->fileListAncestor, $fileName), 10));
	}

	/**
	 * @Then I see that :fileName is not marked as favorite
	 */
	public function iSeeThatIsNotMarkedAsFavorite($fileName) {
		PHPUnit_Framework_Assert::assertNotNull($this->actor->find(self::notFavoritedStateIconForFile($this->fileListAncestor, $fileName), 10));
	}

	/**
	 * @Then I see that :fileName has unread comments
	 */
	public function iSeeThatHasUnreadComments($fileName) {
		PHPUnit_Framework_Assert::assertTrue($this->actor->find(self::commentActionForFile($this->fileListAncestor, $fileName), 10)->isVisible());
	}

	private function waitForRowForFileToBeFullyOpaque($fileName) {
		$actor = $this->actor;
		$fileRowXpathExpression = $this->actor->find(self::rowForFile($this->fileListAncestor, $fileName), 10)->getWrappedElement()->getXpath();

		$fileRowIsFullyOpaqueCallback = function () use ($actor, $fileRowXpathExpression) {
			$opacity = $actor->getSession()->evaluateScript("return window.getComputedStyle(document.evaluate(\"" . $fileRowXpathExpression . "\", document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue).opacity;");
			if ($opacity === "1") {
				return true;
			}

			return false;
		};

		if (!Utils::waitFor($fileRowIsFullyOpaqueCallback, $timeout = 2 * $this->actor->getFindTimeoutMultiplier(), $timeoutStep = 1)) {
			PHPUnit_Framework_Assert::fail("The row for file $fileName in file list is not fully opaque after $timeout seconds");
		}
	}

	private function openFileActionsMenuForFile($fileName) {
		// When a row is added to the file list the opacity of the file row is
		// animated from transparent to fully opaque. As the file actions menu
		// is a descendant of the row but overflows it when the row is not fully
		// opaque clicks on the menu entries "fall-through" and are received
		// instead by the rows behind. Therefore it should be waited until the
		// row of the file is fully opaque before using the menu.
		$this->waitForRowForFileToBeFullyOpaque($fileName);

		$this->actor->find(self::fileActionsMenuButtonForFile($this->fileListAncestor, $fileName), 10)->click();
	}
}
