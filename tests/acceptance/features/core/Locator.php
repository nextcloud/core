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
 * Data object for the information needed to locate an element in a web page
 * using Mink.
 *
 * Locators can be created directly using the constructor, or through a more
 * fluent interface with Locator::forThe().
 */
class Locator {

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var string
	 */
	private $selector;

	/**
	 * @var string|array
	 */
	private $locator;

	/**
	 * @var null|Locator|\Behat\Mink\Element\ElementInterface
	 */
	private $ancestor;

	/**
	 * Starting point for the fluent interface to create Locators.
	 *
	 * @return LocatorBuilder
	 */
	public static function forThe() {
		return new LocatorBuilder();
	}

	/**
	 * @param string $description
	 * @param string $selector
	 * @param string|array $locator
	 * @param null|Locator|\Behat\Mink\Element\ElementInterface $ancestor
	 */
	public function __construct($description, $selector, $locator, $ancestor = null) {
		$this->description = $description;
		$this->selector = $selector;
		$this->locator = $locator;
		$this->ancestor = $ancestor;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @return string
	 */
	public function getSelector() {
		return $this->selector;
	}

	/**
	 * @return string|array
	 */
	public function getLocator() {
		return $this->locator;
	}

	/**
	 * @return null|Locator|\Behat\Mink\Element\ElementInterface
	 */
	public function getAncestor() {
		return $this->ancestor;
	}

}

class LocatorBuilder {

	/**
	 * @param string $selector
	 * @param string|array $locator
	 * @return LocatorBuilderSecondStep
	 */
	public function customSelector($selector, $locator) {
		return new LocatorBuilderSecondStep($selector, $locator);
	}

	/**
	 * @param string $cssExpression
	 * @return LocatorBuilderSecondStep
	 */
	public function css($cssExpression) {
		return $this->customSelector("css", $cssExpression);
	}

	/**
	 * @param string $xpathExpression
	 * @return LocatorBuilderSecondStep
	 */
	public function xpath($xpathExpression) {
		return $this->customSelector("xpath", $xpathExpression);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function id($value) {
		return $this->customSelector("named_exact", ["id", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function idOrName($value) {
		return $this->customSelector("named_exact", ["id_or_name", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function link($value) {
		return $this->customSelector("named_exact", ["link", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function button($value) {
		return $this->customSelector("named_exact", ["button", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function linkOrButton($value) {
		return $this->customSelector("named_exact", ["link_or_button", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function field($value) {
		return $this->customSelector("named_exact", ["field", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function selectField($value) {
		return $this->customSelector("named_exact", ["select", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function checkbox($value) {
		return $this->customSelector("named_exact", ["checkbox", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function radioButton($value) {
		return $this->customSelector("named_exact", ["radio", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function fileInput($value) {
		return $this->customSelector("named_exact", ["file", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function optionGroup($value) {
		return $this->customSelector("named_exact", ["optgroup", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function option($value) {
		return $this->customSelector("named_exact", ["option", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function fieldSet($value) {
		return $this->customSelector("named_exact", ["fieldset", $value]);
	}

	/**
	 * @param string $value
	 * @return LocatorBuilderSecondStep
	 */
	public function table($value) {
		return $this->customSelector("named_exact", ["table", $value]);
	}

}

class LocatorBuilderSecondStep {

	/**
	 * @var string
	 */
	private $selector;

	/**
	 * @var string|array
	 */
	private $locator;

	/**
	 * @param string $selector
	 * @param string|array $locator
	 */
	public function __construct($selector, $locator) {
		$this->selector = $selector;
		$this->locator = $locator;
	}

	/**
	 * @param Locator|\Behat\Mink\Element\ElementInterface $ancestor
	 * @return LocatorBuilderThirdStep
	 */
	public function descendantOf($ancestor) {
		return new LocatorBuilderThirdStep($this->selector, $this->locator, $ancestor);
	}

	/**
	 * @param string $description
	 * @return Locator
	 */
	public function describedAs($description) {
		return new Locator($description, $this->selector, $this->locator);
	}

}

class LocatorBuilderThirdStep {

	/**
	 * @var string
	 */
	private $selector;

	/**
	 * @var string|array
	 */
	private $locator;

	/**
	 * @var Locator|\Behat\Mink\Element\ElementInterface
	 */
	private $ancestor;

	/**
	 * @param string $selector
	 * @param string|array $locator
	 * @param Locator|\Behat\Mink\Element\ElementInterface $ancestor
	 */
	public function __construct($selector, $locator, $ancestor) {
		$this->selector = $selector;
		$this->locator = $locator;
		$this->ancestor = $ancestor;
	}

	/**
	 * @param string $description
	 * @return Locator
	 */
	public function describedAs($description) {
		return new Locator($description, $this->selector, $this->locator, $this->ancestor);
	}

}
