<?php

/**
 *
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

class DialogContext implements Context, ActorAwareInterface {

	use ActorAware;

	/**
	 * @return Locator
	 */
	public static function theDialog() {
		return Locator::forThe()->css(".oc-dialog")->
			describedAs("The dialog");
	}

	/**
	 * @return Locator
	 */
	public static function theDialogButton($text) {
		return Locator::forThe()->xpath("//button[normalize-space() = '$text']")->
			descendantOf(self::theDialog())->
			describedAs($text . " button of the dialog");
	}

	/**
	 * @Given I click the :text button of the confirmation dialog
	 */
	public function iClickTheDialogButton($text) {
		$this->actor->find(self::theDialogButton($text), 10)->click();
	}

	/**
	 * @Then I see that the confirmation dialog is shown
	 */
	public function iSeeThatTheConfirmationDialogIsShown() {
		WaitFor::elementToBeEventuallyShown($this->actor, self::theDialog());
	}

	/**
	 * @Then I see that the confirmation dialog is not shown
	 */
	public function iSeeThatTheConfirmationDialogIsNotShown() {
		WaitFor::elementToBeEventuallyNotShown($this->actor, self::theDialog());
	}

}
