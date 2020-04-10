<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2019, Greta Doci <gretadoci@gmail.com>
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

class UsersSettingsContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function newUserForm() {
		return Locator::forThe()->id("new-user")->
			describedAs("New user form in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function userNameFieldForNewUser() {
		return Locator::forThe()->field("newusername")->
			describedAs("User name field for new user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function displayNameFieldForNewUser() {
		return Locator::forThe()->field("newdisplayname")->
			describedAs("Display name field for new user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function passwordFieldForNewUser() {
		return Locator::forThe()->field("newuserpassword")->
			describedAs("Password field for new user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function newUserButton() {
		return Locator::forThe()->id("new-user-button")->
			describedAs("New user button in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function createNewUserButton() {
		return Locator::forThe()->xpath("//form[@id = 'new-user']//input[@type = 'submit']")->
			describedAs("Create user button in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function rowForUser($user) {
		return Locator::forThe()->css("div.user-list-grid div.row[data-id=$user]")->
			describedAs("Row for user $user in Users Settings");
	}

	/**
	 * Warning: you need to watch out for the proper classes order
	 *
	 * @return Locator
	 */
	public static function classCellForUser($class, $user) {
		return Locator::forThe()->xpath("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]")->
			descendantOf(self::rowForUser($user))->
			describedAs("$class cell for user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function inputForUserInCell($cell, $user) {
		return Locator::forThe()->css("input")->
			descendantOf(self::classCellForUser($cell, $user))->
			describedAs("$cell input for user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function displayNameCellForUser($user) {
		return self::inputForUserInCell("displayName", $user);
	}

	/**
	 * @return Locator
	 */
	public static function optionInInputForUser($cell, $user) {
		return Locator::forThe()->css(".multiselect__option--highlight")->
			descendantOf(self::classCellForUser($cell, $user))->
			describedAs("Selected $cell option in $cell input for user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function actionsMenuOf($user) {
		return Locator::forThe()->css(".icon-more")->
			descendantOf(self::rowForUser($user))->
			describedAs("Actions menu for user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function theAction($action, $user) {
		return Locator::forThe()->xpath("//button[normalize-space() = '$action']")->
			descendantOf(self::rowForUser($user))->
			describedAs("$action action for the user $user row in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function theColumn($column) {
		return Locator::forThe()->xpath("//div[@class='user-list-grid']//div[normalize-space() = '$column']")->
			describedAs("The $column column in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function selectedSelectOption($cell, $user) {
		return Locator::forThe()->css(".multiselect__single")->
			descendantOf(self::classCellForUser($cell, $user))->
			describedAs("The selected option of the $cell select for the user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function editModeToggle($user) {
		return Locator::forThe()->css(".toggleUserActions button.icon-rename")->
			descendantOf(self::rowForUser($user))->
			describedAs("The edit toggle button for the user $user in Users Settings");
	}

	/**
	 * @return Locator
	 */
	public static function editModeOn($user) {
		return Locator::forThe()->css("div.user-list-grid div.row.row--editable[data-id=$user]")->
			describedAs("I see the edit mode is on for the user $user in Users Settings");
	}

	/**
	 * @When I click the New user button
	 */
	public function iClickTheNewUserButton() {
		$this->actor->find(self::newUserButton())->click();
	}

	/**
	 * @When I click the :action action in the :user actions menu
	 */
	public function iClickTheAction($action, $user) {
		$this->actor->find(self::theAction($action, $user))->click();
	}

	/**
	 * @When I open the actions menu for the user :user
	 */
	public function iOpenTheActionsMenuOf($user) {
		$this->actor->find(self::actionsMenuOf($user))->click();
	}

	/**
	 * @When I set the user name for the new user to :user
	 */
	public function iSetTheUserNameForTheNewUserTo($user) {
		$this->actor->find(self::userNameFieldForNewUser(), 10)->setValue($user);
	}

	/**
	 * @When I set the display name for the new user to :displayName
	 */
	public function iSetTheDisplayNameForTheNewUserTo($displayName) {
		$this->actor->find(self::displayNameFieldForNewUser(), 10)->setValue($displayName);
	}

	/**
	 * @When I set the password for the new user to :password
	 */
	public function iSetThePasswordForTheNewUserTo($password) {
		$this->actor->find(self::passwordFieldForNewUser(), 10)->setValue($password);
	}

	/**
	 * @When I create the new user
	 */
	public function iCreateTheNewUser() {
		$this->actor->find(self::createNewUserButton(), 10)->click();
	}

	/**
	 * @When I toggle the edit mode for the user :user
	 */
	public function iToggleTheEditModeForUser($user) {
		$this->actor->find(self::editModeToggle($user), 10)->click();
	}

	/**
	 * @When I create user :user with password :password
	 */
	public function iCreateUserWithPassword($user, $password) {
		$this->actor->find(self::userNameFieldForNewUser(), 10)->setValue($user);
		$this->actor->find(self::passwordFieldForNewUser())->setValue($password);
		$this->actor->find(self::createNewUserButton())->click();
	}

	/**
	 * @When I set the :field for :user to :value
	 */
	public function iSetTheFieldForUserTo($field, $user, $value) {
		$this->actor->find(self::inputForUserInCell($field, $user), 2)->setValue($value . "\r");
	}

	/**
	 * Assigning/withdrawing is the same action (it toggles).
	 *
	 * @When I assign the user :user to the group :group
	 * @When I withdraw the user :user from the group :group
	 */
	public function iAssignTheUserToTheGroup($user, $group) {
		$this->actor->find(self::inputForUserInCell('groups', $user))->setValue($group);
		$this->actor->find(self::optionInInputForUser('groups', $user))->click();
	}

	/**
	 * @When I set the user :user quota to :quota
	 */
	public function iSetTheUserQuotaTo($user, $quota) {
		$this->actor->find(self::inputForUserInCell('quota', $user))->setValue($quota);
		$this->actor->find(self::optionInInputForUser('quota', $user))->click();
	}

	/**
	 * @Then I see that the list of users contains the user :user
	 */
	public function iSeeThatTheListOfUsersContainsTheUser($user) {
		WaitFor::elementToBeEventuallyShown($this->actor, self::rowForUser($user));
	}

	/**
	 * @Then I see that the list of users does not contains the user :user
	 */
	public function iSeeThatTheListOfUsersDoesNotContainsTheUser($user) {
		WaitFor::elementToBeEventuallyNotShown($this->actor, self::rowForUser($user));
	}

	/**
	 * @Then I see that the new user form is shown
	 */
	public function iSeeThatTheNewUserFormIsShown() {
		PHPUnit_Framework_Assert::assertTrue(
			$this->actor->find(self::newUserForm(), 10)->isVisible());
	}

	/**
	 * @Then I see that the :action action in the :user actions menu is shown
	 */
	public function iSeeTheAction($action, $user) {
		PHPUnit_Framework_Assert::assertTrue(
			$this->actor->find(self::theAction($action, $user), 10)->isVisible());
	}

	/**
	 * @Then I see that the :column column is shown
	 */
	public function iSeeThatTheColumnIsShown($column) {
		PHPUnit_Framework_Assert::assertTrue(
			$this->actor->find(self::theColumn($column), 10)->isVisible());
	}

	/**
	 * @Then I see that the :field of :user is :value
	 */
	public function iSeeThatTheFieldOfUserIs($field, $user, $value) {
		PHPUnit_Framework_Assert::assertEquals(
			$this->actor->find(self::inputForUserInCell($field, $user), 10)->getValue(), $value);
	}

	/**
	 * @Then I see that the display name for the user :user is :displayName
	 */
	public function iSeeThatTheDisplayNameForTheUserIs($user, $displayName) {
		PHPUnit_Framework_Assert::assertEquals(
			$displayName, $this->actor->find(self::displayNameCellForUser($user), 10)->getValue());
	}

	/**
	 * @Then I see that the :cell cell for user :user is done loading
	 */
	public function iSeeThatTheCellForUserIsDoneLoading($cell, $user) {
		WaitFor::elementToBeEventuallyShown($this->actor, self::classCellForUser($cell . ' icon-loading-small', $user));
		WaitFor::elementToBeEventuallyNotShown($this->actor, self::classCellForUser($cell . ' icon-loading-small', $user));
	}

	/**
	 * @Then I see that the user quota of :user is :quota
	 */
	public function iSeeThatTheuserQuotaIs($user, $quota) {
		PHPUnit_Framework_Assert::assertEquals(
			$this->actor->find(self::selectedSelectOption('quota', $user), 2)->getText(), $quota);
	}

	/**
	 * @Then I see that the edit mode is on for user :user
	 */
	public function iSeeThatTheEditModeIsOn($user) {
		WaitFor::elementToBeEventuallyShown($this->actor, self::editModeOn($user));
	}
}
