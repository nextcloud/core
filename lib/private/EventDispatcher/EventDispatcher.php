<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
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

use Psr\Log\LoggerInterface;
use function get_class;
use OC\Broadcast\Events\BroadcastEvent;
use OCP\Broadcast\Events\IBroadcastEvent;
use OCP\EventDispatcher\ABroadcastedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IContainer;
use OCP\IServerContainer;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyDispatcher;

class EventDispatcher implements IEventDispatcher {

	/** @var SymfonyDispatcher */
	private $dispatcher;

	/** @var IContainer */
	private $container;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(SymfonyDispatcher $dispatcher,
								IServerContainer $container,
								LoggerInterface $logger) {
		$this->dispatcher = $dispatcher;
		$this->container = $container;
		$this->logger = $logger;
	}

	public function addListener(string $eventName,
								callable $listener,
								int $priority = 0): void {
		$this->dispatcher->addListener($eventName, $listener, $priority);
	}

	public function removeListener(string $eventName,
								   callable $listener): void {
		$this->dispatcher->removeListener($eventName, $listener);
	}

	public function addServiceListener(string $eventName,
									   string $className,
									   int $priority = 0): void {
		$listener = new ServiceEventListener(
			$this->container,
			$className,
			$this->logger
		);

		$this->addListener($eventName, $listener, $priority);
	}

	/**
	 * @deprecated
	 */
	public function dispatch(string $eventName,
							 Event $event): void {
		$this->dispatcher->dispatch($event, $eventName);

		if ($event instanceof ABroadcastedEvent && !$event->isPropagationStopped()) {
			// Propagate broadcast
			$this->dispatch(
				IBroadcastEvent::class,
				new BroadcastEvent($event)
			);
		}
	}

	public function dispatchTyped(Event $event): void {
		$this->dispatch(get_class($event), $event);
	}

	/**
	 * @return SymfonyDispatcher
	 * @deprecated 20.0.0
	 */
	public function getSymfonyDispatcher(): SymfonyDispatcher {
		return $this->dispatcher;
	}
}
