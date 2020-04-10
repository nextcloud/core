<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\files_versions\tests\Versions;

use OC\Files\Storage\Local;
use OCA\Files_Versions\Versions\IVersionBackend;
use OCA\Files_Versions\Versions\VersionManager;
use OCP\Files\Storage\IStorage;
use Test\TestCase;

class VersionManagerTest extends TestCase {
	private function getBackend(bool $shouldUse = true): IVersionBackend {
		$backend = $this->createMock(IVersionBackend::class);
		$backend->method('useBackendForStorage')
			->willReturn($shouldUse);
		return $backend;
	}

	private function getStorage(string $class): IStorage {
		return $this->getMockBuilder($class)
			->disableOriginalConstructor()
			->setMethodsExcept(['instanceOfStorage'])
			->getMock();
	}

	public function testGetBackendSingle() {
		$manager = new VersionManager();
		$backend = $this->getBackend();
		$manager->registerBackend(IStorage::class, $backend);

		$this->assertEquals($backend, $manager->getBackendForStorage($this->getStorage(Local::class)));
	}

	public function testGetBackendMoreSpecific() {
		$manager = new VersionManager();
		$backend1 = $this->getBackend();
		$backend2 = $this->getBackend();
		$manager->registerBackend(IStorage::class, $backend1);
		$manager->registerBackend(Local::class, $backend2);

		$this->assertEquals($backend2, $manager->getBackendForStorage($this->getStorage(Local::class)));
	}

	public function testGetBackendNoUse() {
		$manager = new VersionManager();
		$backend1 = $this->getBackend();
		$backend2 = $this->getBackend(false);
		$manager->registerBackend(IStorage::class, $backend1);
		$manager->registerBackend(Local::class, $backend2);

		$this->assertEquals($backend1, $manager->getBackendForStorage($this->getStorage(Local::class)));
	}

	public function testGetBackendMultiple() {
		$manager = new VersionManager();
		$backend1 = $this->getBackend();
		$backend2 = $this->getBackend(false);
		$backend3 = $this->getBackend();
		$manager->registerBackend(IStorage::class, $backend1);
		$manager->registerBackend(Local::class, $backend2);
		$manager->registerBackend(Local::class, $backend3);

		$this->assertEquals($backend3, $manager->getBackendForStorage($this->getStorage(Local::class)));
	}
}
