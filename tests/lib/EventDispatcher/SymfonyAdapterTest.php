<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace lib\EventDispatcher;

use OC\EventDispatcher\EventDispatcher;
use OC\EventDispatcher\GenericEventWrapper;
use OC\EventDispatcher\SymfonyAdapter;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent as SymfonyGenericEvent;
use Test\TestCase;

class SymfonyAdapterTest extends TestCase {

	/** @var EventDispatcher|MockObject */
	private $eventDispatcher;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var EventDispatcherInterface */
	private $adapter;

	protected function setUp(): void {
		parent::setUp();

		$this->eventDispatcher = $this->createMock(EventDispatcher::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->adapter = new SymfonyAdapter(
			$this->eventDispatcher,
			$this->logger
		);
	}

	public function testDispatchTypedEvent(): void {
		$event = new Event();
		$eventName = 'symfony';
		$this->eventDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$eventName,
				$event
			)
			->willReturnArgument(0);

		$this->adapter->dispatch($eventName, $event);
	}

	public function testDispatchSymfonyGenericEvent(): void {
		$eventName = 'symfony';
		$event = new SymfonyGenericEvent();
		$wrapped = new GenericEventWrapper(
			$this->logger,
			$eventName,
			$event
		);
		$symfonyDispatcher = $this->createMock(SymfonyDispatcher::class);
		$this->eventDispatcher->expects(self::once())
			->method('getSymfonyDispatcher')
			->willReturn($symfonyDispatcher);
		$symfonyDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				self::equalTo($wrapped),
				$eventName
			)
			->willReturnArgument(0);

		$result = $this->adapter->dispatch($eventName, $event);

		self::assertEquals($result, $wrapped);
	}

	public function testDispatchOldSymfonyEventWithFlippedArgumentOrder(): void {
		$event = new SymfonyEvent();
		$eventName = 'symfony';
		$symfonyDispatcher = $this->createMock(SymfonyDispatcher::class);
		$this->eventDispatcher->expects(self::once())
			->method('getSymfonyDispatcher')
			->willReturn($symfonyDispatcher);
		$symfonyDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$event,
				$eventName
			)
			->willReturnArgument(0);

		$result = $this->adapter->dispatch($event, $eventName);

		self::assertSame($result, $event);
	}

	public function testDispatchOldSymfonyEvent(): void {
		$event = new SymfonyEvent();
		$eventName = 'symfony';
		$symfonyDispatcher = $this->createMock(SymfonyDispatcher::class);
		$this->eventDispatcher->expects(self::once())
			->method('getSymfonyDispatcher')
			->willReturn($symfonyDispatcher);
		$symfonyDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$event,
				$eventName
			)
			->willReturnArgument(0);

		$result = $this->adapter->dispatch($eventName, $event);

		self::assertSame($result, $event);
	}

	public function testDispatchCustomGenericEventWithFlippedArgumentOrder(): void {
		$event = new GenericEvent();
		$eventName = 'symfony';
		$this->eventDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$eventName,
				$event
			)
			->willReturnArgument(0);

		$result = $this->adapter->dispatch($event, $eventName);

		self::assertSame($result, $event);
	}

	public function testDispatchCustomGenericEvent(): void {
		$event = new GenericEvent();
		$eventName = 'symfony';
		$this->eventDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$eventName,
				$event
			);

		$result = $this->adapter->dispatch($eventName, $event);

		self::assertSame($result, $event);
	}

	public function testDispatchEventWithoutPayload(): void {
		$eventName = 'symfony';
		$symfonyDispatcher = $this->createMock(SymfonyDispatcher::class);
		$this->eventDispatcher->expects(self::once())
			->method('getSymfonyDispatcher')
			->willReturn($symfonyDispatcher);
		$symfonyDispatcher->expects(self::once())
			->method('dispatch')
			->with(
				$eventName
			)
			->willReturnArgument(0);

		$result = $this->adapter->dispatch($eventName);

		self::assertNotNull($result);
	}
}
