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

use JsonSerializable;

/**
 * @since 18.0.0
 */
abstract class ABroadcastedEvent extends Event implements JsonSerializable {

	/**
	 * @since 18.0.0
	 */
	private $broadcasted = false;

	/**
	 * Get the name of the event, as received on the client-side
	 *
	 * Uses the fully qualified event class name by default
	 *
	 * @return string
	 * @since 18.0.0
	 */
	public function broadcastAs(): string {
		return get_class($this);
	}

	/**
	 * @return string[]
	 * @since 18.0.0
	 */
	abstract public function getUids(): array;

	/**
	 * @since 18.0.0
	 */
	public function setBroadcasted(): void {
		$this->broadcasted = true;
	}

	/**
	 * @since 18.0.0
	 */
	public function isBroadcasted(): bool {
		return $this->broadcasted;
	}

}
