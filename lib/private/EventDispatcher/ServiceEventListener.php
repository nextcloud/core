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

namespace OC\EventDispatcher;

use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IContainer;
use OCP\ILogger;

/**
 * Lazy service event listener
 *
 * Makes it possible to lazy-route a dispatched event to a service instance
 * created by the service container
 */
final class ServiceEventListener {

	/** @var IContainer */
	private $container;

	/** @var string */
	private $class;

	/** @var ILogger */
	private $logger;

	/** @var null|IEventListener */
	private $service;

	public function __construct(IContainer $container,
								string $class,
								ILogger $logger) {
		$this->container = $container;
		$this->class = $class;
		$this->logger = $logger;
	}

	public function __invoke(Event $event) {
		if ($this->service === null) {
			try {
				$this->service = $this->container->query($this->class);
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'level' => ILogger::ERROR,
					'message' => "Could not load event listener service " . $this->class,
				]);
				return;
			}
		}

		$this->service->handle($event);
	}

}
