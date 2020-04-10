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

namespace OC\Broadcast\Events;

use JsonSerializable;
use OCP\Broadcast\Events\IBroadcastEvent;
use OCP\EventDispatcher\ABroadcastedEvent;
use OCP\EventDispatcher\Event;

class BroadcastEvent extends Event implements IBroadcastEvent {

	/** @var ABroadcastedEvent */
	private $event;

	public function __construct(ABroadcastedEvent $event) {
		parent::__construct();

		$this->event = $event;
	}

	public function getName(): string {
		return $this->event->broadcastAs();
	}

	public function getUids(): array {
		return $this->event->getUids();
	}

	public function getPayload(): JsonSerializable {
		return $this->event;
	}

	public function setBroadcasted(): void {
		$this->event->setBroadcasted();
	}

}
