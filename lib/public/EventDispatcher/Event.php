<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Base event class for the event dispatcher service
 *
 * Typically this class isn't instantiated directly but sub classed for specific
 * event types
 *
 * This class extended \Symfony\Contracts\EventDispatcher\Event until 21.0, since
 * 22.0.0 this class directly implements the PSR StoppableEventInterface and no
 * longer relies on Symfony. This transition does not come with any changes in API,
 * the class has the same methods and behavior before and after this change.
 *
 * @since 17.0.0
 */
class Event implements StoppableEventInterface {

	/**
	 * @var bool
	 *
	 * @since 22.0.0
	 */
	private $propagationStopped = false;

	/**
	 * Compatibility constructor
	 *
	 * In Nextcloud 17.0.0 this event class used a now deprecated/removed Symfony base
	 * class that had a constructor (with default arguments). To lower the risk of
	 * a breaking change (PHP won't allow parent constructor calls if there is none),
	 * this empty constructor's only purpose is to hopefully not break existing sub-
	 * classes of this class.
	 *
	 * @since 18.0.0
	 */
	public function __construct() {
	}

	/**
	 * Stops the propagation of the event to further event listeners
	 *
	 * @return void
	 *
	 * @since 22.0.0
	 */
	public function stopPropagation(): void {
		$this->propagationStopped = true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 22.0.0
	 * @see \Psr\EventDispatcher\StoppableEventInterface
	 */
	public function isPropagationStopped(): bool {
		return $this->propagationStopped;
	}
}
