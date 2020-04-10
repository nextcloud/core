<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
 *
 */

namespace Test\Calendar\Room;

use OC\Calendar\Room\Manager;
use OCP\Calendar\Room\IBackend;
use OCP\IServerContainer;
use Test\TestCase;

class ManagerTest extends TestCase {

	/** @var Manager */
	private $manager;

	/** @var IServerContainer */
	private $server;

	protected function setUp(): void {
		parent::setUp();

		$this->server = $this->createMock(IServerContainer::class);
		$this->manager = new Manager($this->server);
	}

	public function testRegisterUnregisterBackend() {
		$backend1 = $this->createMock(IBackend::class);
		$backend1->method('getBackendIdentifier')->willReturn('backend_1');
		$this->server->expects($this->at(0))
			->method('query')
			->with('calendar_room_backend1')
			->willReturn($backend1);

		$backend2 = $this->createMock(IBackend::class);
		$backend2->method('getBackendIdentifier')->willReturn('backend_2');
		$this->server->expects($this->at(1))
			->method('query')
			->with('calendar_room_backend2')
			->willReturn($backend2);

		$this->manager->registerBackend('calendar_room_backend1');
		$this->manager->registerBackend('calendar_room_backend2');

		$this->assertEquals([
			$backend1, $backend2
		], $this->manager->getBackends());

		$this->manager->unregisterBackend('calendar_room_backend1');

		$this->assertEquals([
			$backend2
		], $this->manager->getBackends());
	}

	public function testGetBackend() {
		$backend1 = $this->createMock(IBackend::class);
		$backend1->method('getBackendIdentifier')->willReturn('backend_1');
		$this->server->expects($this->at(0))
			->method('query')
			->with('calendar_room_backend1')
			->willReturn($backend1);

		$backend2 = $this->createMock(IBackend::class);
		$backend2->method('getBackendIdentifier')->willReturn('backend_2');
		$this->server->expects($this->at(1))
			->method('query')
			->with('calendar_room_backend2')
			->willReturn($backend2);

		$this->manager->registerBackend('calendar_room_backend1');
		$this->manager->registerBackend('calendar_room_backend2');

		$this->assertEquals($backend1, $this->manager->getBackend('backend_1'));
		$this->assertEquals($backend2, $this->manager->getBackend('backend_2'));
	}

	public function testClear() {
		$backend1 = $this->createMock(IBackend::class);
		$backend1->method('getBackendIdentifier')->willReturn('backend_1');
		$this->server->expects($this->at(0))
			->method('query')
			->with('calendar_room_backend1')
			->willReturn($backend1);

		$backend2 = $this->createMock(IBackend::class);
		$backend2->method('getBackendIdentifier')->willReturn('backend_2');
		$this->server->expects($this->at(1))
			->method('query')
			->with('calendar_room_backend2')
			->willReturn($backend2);

		$this->manager->registerBackend('calendar_room_backend1');
		$this->manager->registerBackend('calendar_room_backend2');

		$this->assertEquals([
			$backend1, $backend2
		], $this->manager->getBackends());

		$this->manager->clear();

		$this->assertEquals([], $this->manager->getBackends());
	}
}
