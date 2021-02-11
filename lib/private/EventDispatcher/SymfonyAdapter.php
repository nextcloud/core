<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

use Symfony\Component\EventDispatcher\GenericEvent;
use function is_callable;
use OCP\EventDispatcher\Event;
use OCP\ILogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function is_object;
use function is_string;

/**
 * @deprecated 20.0.0 use \OCP\EventDispatcher\IEventDispatcher
 */
class SymfonyAdapter implements EventDispatcherInterface {

	/** @var EventDispatcher */
	private $eventDispatcher;
	/** @var ILogger */
	private $logger;

	/**
	 * @deprecated 20.0.0
	 */
	public function __construct(EventDispatcher $eventDispatcher, ILogger $logger) {
		$this->eventDispatcher = $eventDispatcher;
		$this->logger = $logger;
	}

	private static function detectEventAndName($a, $b) {
		if (is_object($a) && (is_string($b) || $b === null)) {
			// a is the event, the other one is the optional name
			return [$a, $b];
		}
		if (is_object($b) && (is_string($a) || $a === null)) {
			// b is the event, the other one is the optional name
			return [$b, $a];
		}
		if (is_string($a) && $b === null) {
			// a is a payload-less event
			return [null, $a];
		}
		if (is_string($b) && $a === null) {
			// b is a payload-less event
			return [null, $b];
		}

		// Anything else we can't detect
		return [$a, $b];
	}

	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param string $eventName The name of the event to dispatch. The name of
	 *                              the event is the name of the method that is
	 *                              invoked on listeners.
	 * @param Event|null $event The event to pass to the event handlers/listeners
	 *                              If not supplied, an empty Event instance is created
	 *
	 * @return object the emitted event
	 * @deprecated 20.0.0
	 */
	public function dispatch($eventName, $event = null): object {
		[$event, $eventName] = self::detectEventAndName($event, $eventName);

		// type hinting is not possible, due to usage of GenericEvent
		if ($event instanceof Event && $eventName === null) {
			$this->eventDispatcher->dispatchTyped($event);
			return $event;
		}
		if ($event instanceof Event) {
			$this->eventDispatcher->dispatch($eventName, $event);
			return $event;
		}

		if ($event instanceof GenericEvent && get_class($event) === GenericEvent::class) {
			$newEvent = new GenericEventWrapper($this->logger, $eventName, $event);
		} else {
			$newEvent = $event;

			// Legacy event
			$this->logger->info(
				'Deprecated event type for {name}: {class}',
				['name' => $eventName, 'class' => is_object($event) ? get_class($event) : 'null']
			);
		}

		// Event with no payload (object) need special handling
		if ($newEvent === null) {
			$this->eventDispatcher->getSymfonyDispatcher()->dispatch($eventName);
			return new Event();
		}

		// Flip the argument order for Symfony to prevent a trigger_error
		return $this->eventDispatcher->getSymfonyDispatcher()->dispatch($newEvent, $eventName);
	}

	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string $eventName The event to listen on
	 * @param callable $listener The listener
	 * @param int $priority The higher this value, the earlier an event
	 *                            listener will be triggered in the chain (defaults to 0)
	 * @deprecated 20.0.0
	 */
	public function addListener($eventName, $listener, $priority = 0) {
		if (is_callable($listener)) {
			$this->eventDispatcher->addListener($eventName, $listener, $priority);
		} else {
			// Legacy listener
			$this->eventDispatcher->getSymfonyDispatcher()->addListener($eventName, $listener, $priority);
		}
	}

	/**
	 * Adds an event subscriber.
	 *
	 * The subscriber is asked for all the events it is
	 * interested in and added as a listener for these events.
	 * @deprecated 20.0.0
	 */
	public function addSubscriber(EventSubscriberInterface $subscriber) {
		$this->eventDispatcher->getSymfonyDispatcher()->addSubscriber($subscriber);
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string $eventName The event to remove a listener from
	 * @param callable $listener The listener to remove
	 * @deprecated 20.0.0
	 */
	public function removeListener($eventName, $listener) {
		$this->eventDispatcher->getSymfonyDispatcher()->removeListener($eventName, $listener);
	}

	/**
	 * @deprecated 20.0.0
	 */
	public function removeSubscriber(EventSubscriberInterface $subscriber) {
		$this->eventDispatcher->getSymfonyDispatcher()->removeSubscriber($subscriber);
	}

	/**
	 * Gets the listeners of a specific event or all listeners sorted by descending priority.
	 *
	 * @param string|null $eventName The name of the event
	 *
	 * @return array The event listeners for the specified event, or all event listeners by event name
	 * @deprecated 20.0.0
	 */
	public function getListeners($eventName = null) {
		return $this->eventDispatcher->getSymfonyDispatcher()->getListeners($eventName);
	}

	/**
	 * Gets the listener priority for a specific event.
	 *
	 * Returns null if the event or the listener does not exist.
	 *
	 * @param string $eventName The name of the event
	 * @param callable $listener The listener
	 *
	 * @return int|null The event listener priority
	 * @deprecated 20.0.0
	 */
	public function getListenerPriority($eventName, $listener) {
		return $this->eventDispatcher->getSymfonyDispatcher()->getListenerPriority($eventName, $listener);
	}

	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string|null $eventName The name of the event
	 *
	 * @return bool true if the specified event has any listeners, false otherwise
	 * @deprecated 20.0.0
	 */
	public function hasListeners($eventName = null) {
		return $this->eventDispatcher->getSymfonyDispatcher()->hasListeners($eventName);
	}
}
