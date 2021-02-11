<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\EventDispatcher;

use OCP\ILogger;
use Symfony\Component\EventDispatcher\GenericEvent;

class GenericEventWrapper extends GenericEvent {

	/** @var ILogger */
	private $logger;

	/** @var GenericEvent */
	private $event;

	/** @var string */
	private $eventName;

	/** @var bool */
	private $deprecationNoticeLogged = false;

	public function __construct(ILogger $logger, string $eventName, ?GenericEvent $event) {
		parent::__construct($eventName);
		$this->logger = $logger;
		$this->event = $event;
		$this->eventName = $eventName;
	}

	private function log() {
		if ($this->deprecationNoticeLogged) {
			return;
		}

		$class = ($this->event !== null && is_object($this->event)) ? get_class($this->event) : 'null';
		$this->logger->info(
			'Deprecated event type for {name}: {class} is used',
			[ 'name' => $this->eventName, 'class' => $class]
		);
		$this->deprecationNoticeLogged = true;
	}

	public function isPropagationStopped(): bool {
		$this->log();
		return $this->event->isPropagationStopped();
	}

	public function stopPropagation(): void {
		$this->log();
		$this->event->stopPropagation();
	}

	public function getSubject() {
		$this->log();
		return $this->event->getSubject();
	}

	public function getArgument($key) {
		$this->log();
		return $this->event->getArgument($key);
	}

	public function setArgument($key, $value) {
		$this->log();
		return $this->event->setArgument($key, $value);
	}

	public function getArguments() {
		return $this->event->getArguments();
	}

	public function setArguments(array $args = []) {
		return $this->event->setArguments($args);
	}

	public function hasArgument($key) {
		return $this->event->hasArgument($key);
	}

	public function offsetGet($key) {
		return $this->event->offsetGet($key);
	}

	public function offsetSet($key, $value) {
		return $this->event->offsetSet($key, $value);
	}

	public function offsetUnset($key) {
		return $this->event->offsetUnset($key);
	}

	public function offsetExists($key) {
		return $this->event->offsetExists($key);
	}

	public function getIterator() {
		return$this->event->getIterator();
	}
}
