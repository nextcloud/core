<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\WorkflowEngine;

/**
 * Class GenericEntityEvent
 *
 *
 * @since 18.0.0
 */
class GenericEntityEvent implements IEntityEvent {

	/** @var string */
	private $displayName;
	/** @var string */
	private $eventName;

	/**
	 * GenericEntityEvent constructor.
	 *
	 * @since 18.0.0
	 */
	public function __construct(string $displayName, string $eventName) {
		if (trim($displayName) === '') {
			throw new \InvalidArgumentException('DisplayName must not be empty');
		}
		if (trim($eventName) === '') {
			throw new \InvalidArgumentException('EventName must not be empty');
		}

		$this->displayName = trim($displayName);
		$this->eventName = trim($eventName);
	}

	/**
	 * returns a translated name to be presented in the web interface.
	 *
	 * Example: "created" (en), "kreita" (eo)
	 *
	 * @since 18.0.0
	 */
	public function getDisplayName(): string {
		return $this->displayName;
	}

	/**
	 * returns the event name that is emitted by the EventDispatcher, e.g.:
	 *
	 * Example: "OCA\MyApp\Factory\Cats::postCreated"
	 *
	 * @since 18.0.0
	 */
	public function getEventName(): string {
		return $this->eventName;
	}
}
