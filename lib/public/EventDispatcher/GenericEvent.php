<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\EventDispatcher;

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;
use function array_key_exists;

/**
 * Class GenericEvent
 *
 * convenience reimplementation of \Symfony\Component\GenericEvent against
 * \OCP\EventDispatcher\Event
 *
 * @package OCP\EventDispatcher
 * @since 18.0.0
 */
class GenericEvent extends Event implements ArrayAccess, IteratorAggregate {
	protected $subject;
	protected $arguments;

	/**
	 * Encapsulate an event with $subject and $args.
	 *
	 * @since 18.0.0
	 */
	public function __construct($subject = null, array $arguments = []) {
		$this->subject = $subject;
		$this->arguments = $arguments;
	}

	/**
	 * Getter for subject property.
	 *
	 * @since 18.0.0
	 */
	public function getSubject() {
		return $this->subject;
	}

	/**
	 * Get argument by key.
	 *
	 * @throws InvalidArgumentException if key is not found
	 * @since 18.0.0
	 */
	public function getArgument(string $key) {
		if ($this->hasArgument($key)) {
			return $this->arguments[$key];
		}

		throw new InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
	}

	/**
	 * Add argument to event.
	 *
	 * @since 18.0.0
	 */
	public function setArgument($key, $value): GenericEvent {
		$this->arguments[$key] = $value;
		return $this;
	}

	/**
	 * Getter for all arguments.
	 *
	 * @since 18.0.0
	 */
	public function getArguments(): array {
		return $this->arguments;
	}

	/**
	 * Set args property.
	 *
	 * @since 18.0.0
	 */
	public function setArguments(array $args = []): GenericEvent {
		$this->arguments = $args;
		return $this;
	}

	/**
	 * Has argument.
	 *
	 * @since 18.0.0
	 */
	public function hasArgument($key): bool {
		return array_key_exists($key, $this->arguments);
	}

	/**
	 * Retrieve an external iterator
	 *
	 * @link https://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @since 18.0.0
	 */
	public function getIterator(): Traversable {
		return new ArrayIterator($this->arguments);
	}

	/**
	 * Whether a offset exists
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetexists.php
	 * @since 18.0.0
	 */
	public function offsetExists($offset): bool {
		return $this->hasArgument($offset);
	}

	/**
	 * Offset to retrieve
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetget.php
	 * @since 18.0.0
	 */
	public function offsetGet($offset) {
		return $this->arguments[$offset];
	}

	/**
	 * Offset to set
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetset.php
	 * @since 18.0.0
	 */
	public function offsetSet($offset, $value): void {
		$this->setArgument($offset, $value);
	}

	/**
	 * Offset to unset
	 *
	 * @link https://php.net/manual/en/arrayaccess.offsetunset.php
	 * @since 18.0.0
	 */
	public function offsetUnset($offset): void {
		if ($this->hasArgument($offset)) {
			unset($this->arguments[$offset]);
		}
	}
}
