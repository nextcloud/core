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

/**
 * Wrapper to automatically handle failed commands on Mink elements.
 *
 * Commands executed on Mink elements may fail for several reasons. The
 * ElementWrapper frees the caller of the commands from handling the most common
 * reasons of failure.
 *
 * StaleElementReference exceptions are thrown when the command is executed on
 * an element that is no longer attached to the DOM. This can happen even in
 * a chained call like "$actor->find($locator)->click()"; in the milliseconds
 * between finding the element and clicking it the element could have been
 * removed from the page (for example, if a previous interaction with the page
 * started an asynchronous update of the DOM). Every command executed through
 * the ElementWrapper is guarded against StaleElementReference exceptions; if
 * the element is stale it is found again using the same parameters to find it
 * in the first place.
 *
 * NoSuchElement exceptions are sometimes thrown instead of
 * StaleElementReference exceptions. This can happen when the Selenium2 driver
 * for Mink performs an action on an element through the WebDriver session
 * instead of directly through the WebDriver element. In that case, if the
 * element with the given ID does not exist, a NoSuchElement exception would be
 * thrown instead of a StaleElementReference exception, so those cases are
 * handled like StaleElementReference exceptions.
 *
 * ElementNotVisible exceptions are thrown when the command requires the element
 * to be visible but the element is not. Finding an element only guarantees that
 * (at that time) the element is attached to the DOM, but it does not provide
 * any guarantee regarding its visibility. Due to that, a call like
 * "$actor->find($locator)->click()" can fail if the element was hidden and
 * meant to be made visible by a previous interaction with the page, but that
 * interaction triggered an asynchronous update that was not finished when the
 * click command is executed. All commands executed through the ElementWrapper
 * that require the element to be visible are guarded against ElementNotVisible
 * exceptions; if the element is not visible it is waited for it to be visible
 * up to the timeout set to find it.
 *
 * MoveTargetOutOfBounds exceptions are sometimes thrown instead of
 * ElementNotVisible exceptions. This can happen when the Selenium2 driver for
 * Mink moves the cursor on an element using the "moveto" method of the
 * WebDriver session, for example, before clicking on an element. In that case,
 * if the element is not visible, "moveto" would throw a MoveTargetOutOfBounds
 * exception instead of an ElementNotVisible exception, so those cases are
 * handled like ElementNotVisible exceptions.
 *
 * Despite the automatic handling it is possible for the commands to throw those
 * exceptions when they are executed again; this class does not handle cases
 * like an element becoming stale several times in a row (uncommon) or an
 * element not becoming visible before the timeout expires (which would mean
 * that the timeout is too short or that the test has to, indeed, fail). In a
 * similar way, MoveTargetOutOfBounds exceptions would be thrown again if
 * originally they were thrown because the element was visible but "out of
 * reach".
 *
 * If needed, automatically handling failed commands can be disabled calling
 * "doNotHandleFailedCommands()"; as it returns the ElementWrapper it can be
 * chained with the command to execute (but note that automatically handling
 * failed commands will still be disabled if further commands are executed on
 * the ElementWrapper).
 */
class ElementWrapper {

	/**
	 * @var ElementFinder
	 */
	private $elementFinder;

	/**
	 * @var \Behat\Mink\Element\Element
	 */
	private $element;

	/**
	 * @param boolean
	 */
	private $handleFailedCommands;

	/**
	 * Creates a new ElementWrapper.
	 *
	 * The wrapped element is found in the constructor itself using the
	 * ElementFinder.
	 *
	 * @param ElementFinder $elementFinder the command object to find the
	 *        wrapped element.
	 * @throws NoSuchElementException if the element, or its ancestor, can not
	 *         be found.
	 */
	public function __construct(ElementFinder $elementFinder) {
		$this->elementFinder = $elementFinder;
		$this->element = $elementFinder->find();
		$this->handleFailedCommands = true;
	}

	/**
	 * Returns the raw Mink element.
	 *
	 * @return \Behat\Mink\Element\Element the wrapped element.
	 */
	public function getWrappedElement() {
		return $this->element;
	}

	/**
	 * Prevents the automatic handling of failed commands.
	 *
	 * @return ElementWrapper this ElementWrapper.
	 */
	public function doNotHandleFailedCommands() {
		$this->handleFailedCommands = false;

		return $this;
	}

	/**
	 * Returns whether the wrapped element is visible or not.
	 *
	 * @return bool true if the wrapped element is visible, false otherwise.
	 */
	public function isVisible() {
		$commandCallback = function () {
			return $this->element->isVisible();
		};
		return $this->executeCommand($commandCallback, "visibility could not be got");
	}

	/**
	 * Returns whether the wrapped element is checked or not.
	 *
	 * @return bool true if the wrapped element is checked, false otherwise.
	 */
	public function isChecked() {
		$commandCallback = function () {
			return $this->element->isChecked();
		};
		return $this->executeCommand($commandCallback, "check state could not be got");
	}

	/**
	 * Returns the text of the wrapped element.
	 *
	 * If the wrapped element is not visible the returned text is an empty
	 * string.
	 *
	 * @return string the text of the wrapped element, or an empty string if it
	 *         is not visible.
	 */
	public function getText() {
		$commandCallback = function () {
			return $this->element->getText();
		};
		return $this->executeCommand($commandCallback, "text could not be got");
	}

	/**
	 * Returns the value of the wrapped element.
	 *
	 * The value can be got even if the wrapped element is not visible.
	 *
	 * @return string the value of the wrapped element.
	 */
	public function getValue() {
		$commandCallback = function () {
			return $this->element->getValue();
		};
		return $this->executeCommand($commandCallback, "value could not be got");
	}

	/**
	 * Sets the given value on the wrapped element.
	 *
	 * If automatically waits for the wrapped element to be visible (up to the
	 * timeout set when finding it).
	 *
	 * @param string $value the value to set.
	 */
	public function setValue($value) {
		$commandCallback = function () use ($value) {
			$this->element->setValue($value);
		};
		$this->executeCommandOnVisibleElement($commandCallback, "value could not be set");
	}

	/**
	 * Clicks on the wrapped element.
	 *
	 * If automatically waits for the wrapped element to be visible (up to the
	 * timeout set when finding it).
	 */
	public function click() {
		$commandCallback = function () {
			$this->element->click();
		};
		$this->executeCommandOnVisibleElement($commandCallback, "could not be clicked");
	}

	/**
	 * Check the wrapped element.
	 *
	 * If automatically waits for the wrapped element to be visible (up to the
	 * timeout set when finding it).
	 */
	public function check() {
		$commandCallback = function () {
			$this->element->check();
		};
		$this->executeCommand($commandCallback, "could not be checked");
	}

	/**
	 * uncheck the wrapped element.
	 *
	 * If automatically waits for the wrapped element to be visible (up to the
	 * timeout set when finding it).
	 */
	public function uncheck() {
		$commandCallback = function () {
			$this->element->uncheck();
		};
		$this->executeCommand($commandCallback, "could not be unchecked");
	}

	/**
	 * Executes the given command.
	 *
	 * If a StaleElementReference or a NoSuchElement exception is thrown the
	 * wrapped element is found again and, then, the command is executed again.
	 *
	 * @param \Closure $commandCallback the command to execute.
	 * @param string $errorMessage an error message that describes the failed
	 *        command (appended to the description of the element).
	 */
	private function executeCommand(\Closure $commandCallback, $errorMessage) {
		if (!$this->handleFailedCommands) {
			return $commandCallback();
		}

		try {
			return $commandCallback();
		} catch (\WebDriver\Exception\StaleElementReference $exception) {
			$this->printFailedCommandMessage($exception, $errorMessage);
		} catch (\WebDriver\Exception\NoSuchElement $exception) {
			$this->printFailedCommandMessage($exception, $errorMessage);
		}

		$this->element = $this->elementFinder->find();

		return $commandCallback();
	}

	/**
	 * Executes the given command on a visible element.
	 *
	 * If a StaleElementReference or a NoSuchElement exception is thrown the
	 * wrapped element is found again and, then, the command is executed again.
	 * If an ElementNotVisible or a MoveTargetOutOfBounds exception is thrown it
	 * is waited for the wrapped element to be visible and, then, the command is
	 * executed again.
	 *
	 * @param \Closure $commandCallback the command to execute.
	 * @param string $errorMessage an error message that describes the failed
	 *        command (appended to the description of the element).
	 */
	private function executeCommandOnVisibleElement(\Closure $commandCallback, $errorMessage) {
		if (!$this->handleFailedCommands) {
			return $commandCallback();
		}

		try {
			return $this->executeCommand($commandCallback, $errorMessage);
		} catch (\WebDriver\Exception\ElementNotVisible $exception) {
			$this->printFailedCommandMessage($exception, $errorMessage);
		} catch (\WebDriver\Exception\MoveTargetOutOfBounds $exception) {
			$this->printFailedCommandMessage($exception, $errorMessage);
		}

		$this->waitForElementToBeVisible();

		return $commandCallback();
	}

	/**
	 * Prints information about the failed command.
	 *
	 * @param \Exception exception the exception thrown by the command.
	 * @param string $errorMessage an error message that describes the failed
	 *        command (appended to the description of the locator of the element).
	 */
	private function printFailedCommandMessage(\Exception $exception, $errorMessage) {
		echo $this->elementFinder->getDescription() . " " . $errorMessage . "\n";
		echo "Exception message: " . $exception->getMessage() . "\n";
		echo "Trying again\n";
	}

	/**
	 * Waits for the wrapped element to be visible.
	 *
	 * This method waits up to the timeout used when finding the wrapped
	 * element; therefore, it may return when the element is still not visible.
	 *
	 * @return boolean true if the element is visible after the wait, false
	 *         otherwise.
	 */
	private function waitForElementToBeVisible() {
		$isVisibleCallback = function () {
			return $this->isVisible();
		};
		$timeout = $this->elementFinder->getTimeout();
		$timeoutStep = $this->elementFinder->getTimeoutStep();

		return Utils::waitFor($isVisibleCallback, $timeout, $timeoutStep);
	}

}
