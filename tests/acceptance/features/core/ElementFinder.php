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
 * Command object to find Mink elements.
 *
 * The element locator is relative to its ancestor (either another locator or an
 * actual element); if it has no ancestor then the base document element is
 * used.
 *
 * Sometimes an element may not be found simply because it has not appeared yet;
 * for those cases ElementFinder supports trying again to find the element
 * several times before giving up. The timeout parameter controls how much time
 * to wait, at most, to find the element; the timeoutStep parameter controls how
 * much time to wait before trying again to find the element. If ancestor
 * locators need to be found the timeout is applied individually to each one,
 * that is, if the timeout is 10 seconds the method will wait up to 10 seconds
 * to find the ancestor of the ancestor and, then, up to 10 seconds to find the
 * ancestor and, then, up to 10 seconds to find the element. By default the
 * timeout is 0, so the element and its ancestor will be looked for just once;
 * the default time to wait before retrying is half a second.
 *
 * In any case, if the element, or its ancestors, can not be found a
 * NoSuchElementException is thrown.
 */
class ElementFinder {

	/**
	 * Finds an element in the given Mink Session.
	 *
	 * @see ElementFinder
	 */
	private static function findInternal(\Behat\Mink\Session $session, Locator $elementLocator, $timeout, $timeoutStep) {
		$element = null;
		$selector = $elementLocator->getSelector();
		$locator = $elementLocator->getLocator();
		$ancestorElement = self::findAncestorElement($session, $elementLocator, $timeout, $timeoutStep);

		$findCallback = function () use (&$element, $selector, $locator, $ancestorElement) {
			$element = $ancestorElement->find($selector, $locator);

			return $element !== null;
		};
		if (!Utils::waitFor($findCallback, $timeout, $timeoutStep)) {
			$message = $elementLocator->getDescription() . " could not be found";
			if ($timeout > 0) {
				$message = $message . " after $timeout seconds";
			}
			throw new NoSuchElementException($message);
		}

		return $element;
	}

	/**
	 * Returns the ancestor element from which the given locator will be looked
	 * for.
	 *
	 * If the ancestor of the given locator is another locator the element for
	 * the ancestor locator is found and returned. If the ancestor of the given
	 * locator is already an element that element is the one returned. If the
	 * given locator has no ancestor then the base document element is returned.
	 *
	 * The timeout is used only when finding the element for the ancestor
	 * locator; if the timeout expires a NoSuchElementException is thrown.
	 *
	 * @param \Behat\Mink\Session $session the Mink Session to get the ancestor
	 *        element from.
	 * @param Locator $elementLocator the locator for the element to get its
	 *        ancestor.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the ancestor element to appear.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before trying to find the ancestor element again.
	 * @return \Behat\Mink\Element\Element the ancestor element found.
	 * @throws NoSuchElementException if the ancestor element can not be found.
	 */
	private static function findAncestorElement(\Behat\Mink\Session $session, Locator $elementLocator, $timeout, $timeoutStep) {
		$ancestorElement = $elementLocator->getAncestor();
		if ($ancestorElement instanceof Locator) {
			try {
				$ancestorElement = self::findInternal($session, $ancestorElement, $timeout, $timeoutStep);
			} catch (NoSuchElementException $exception) {
				// Little hack to show the stack of ancestor elements that could
				// not be found, as Behat only shows the message of the last
				// exception in the chain.
				$message = $exception->getMessage() . "\n" .
						   $elementLocator->getDescription() . " could not be found";
				if ($timeout > 0) {
					$message = $message . " after $timeout seconds";
				}
				throw new NoSuchElementException($message, $exception);
			}
		}

		if ($ancestorElement === null) {
			$ancestorElement = $session->getPage();
		}

		return $ancestorElement;
	}

	/**
	 * @var \Behat\Mink\Session
	 */
	private $session;

	/**
	 * @param Locator
	 */
	private $elementLocator;

	/**
	 * @var float
	 */
	private $timeout;

	/**
	 * @var float
	 */
	private $timeoutStep;

	/**
	 * Creates a new ElementFinder.
	 *
	 * @param \Behat\Mink\Session $session the Mink Session to get the element
	 *        from.
	 * @param Locator $elementLocator the locator for the element.
	 * @param float $timeout the number of seconds (decimals allowed) to wait at
	 *        most for the element to appear.
	 * @param float $timeoutStep the number of seconds (decimals allowed) to
	 *        wait before trying to find the element again.
	 */
	public function __construct(\Behat\Mink\Session $session, Locator $elementLocator, $timeout, $timeoutStep) {
		$this->session = $session;
		$this->elementLocator = $elementLocator;
		$this->timeout = $timeout;
		$this->timeoutStep = $timeoutStep;
	}

	/**
	 * Returns the description of the element to find.
	 *
	 * @return string the description of the element to find.
	 */
	public function getDescription() {
		return $this->elementLocator->getDescription();
	}

	/**
	 * Returns the timeout.
	 *
	 * @return float the number of seconds (decimals allowed) to wait at most
	 *         for the element to appear.
	 */
	public function getTimeout() {
		return $this->timeout;
	}

	/**
	 * Returns the timeout step.
	 *
	 * @return float the number of seconds (decimals allowed) to  wait before
	 *         trying to find the element again.
	 */
	public function getTimeoutStep() {
		return $this->timeoutStep;
	}

	/**
	 * Finds an element using the parameters set in the constructor of this
	 * ElementFinder.
	 *
	 * If the element, or its ancestors, can not be found a
	 * NoSuchElementException is thrown.
	 *
	 * @return \Behat\Mink\Element\Element the element found.
	 * @throws NoSuchElementException if the element, or its ancestor, can not
	 *         be found.
	 */
	public function find() {
		return self::findInternal($this->session, $this->elementLocator, $this->timeout, $this->timeoutStep);
	}

}
