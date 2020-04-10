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

/**
 * Helper class with common "wait for" functions.
 */
class WaitFor {

	/**
	 * Waits for the element to be visible.
	 *
	 * @param Actor $actor the Actor used to find the element.
	 * @param Locator $elementLocator the locator for the element.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the element to be visible.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before checking the visibility again.
	 * @return boolean true if the element is visible before (or exactly when)
	 *         the timeout expires, false otherwise.
	 */
	public static function elementToBeEventuallyShown(Actor $actor, Locator $elementLocator, $timeout = 10, $timeoutStep = 1) {
		$elementShownCallback = function () use ($actor, $elementLocator) {
			try {
				return $actor->find($elementLocator)->isVisible();
			} catch (NoSuchElementException $exception) {
				return false;
			}
		};

		return Utils::waitFor($elementShownCallback, $timeout, $timeoutStep);
	}

	/**
	 * Waits for the element to be hidden (either not visible or not found in
	 * the DOM).
	 *
	 * @param Actor $actor the Actor used to find the element.
	 * @param Locator $elementLocator the locator for the element.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the element to be hidden.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before checking the visibility again.
	 * @return boolean true if the element is hidden before (or exactly when)
	 *         the timeout expires, false otherwise.
	 */
	public static function elementToBeEventuallyNotShown(Actor $actor, Locator $elementLocator, $timeout = 10, $timeoutStep = 1) {
		$elementNotShownCallback = function () use ($actor, $elementLocator) {
			try {
				return !$actor->find($elementLocator)->isVisible();
			} catch (NoSuchElementException $exception) {
				return true;
			}
		};

		return Utils::waitFor($elementNotShownCallback, $timeout, $timeoutStep);
	}

}
