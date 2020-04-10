<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace Test\Collaboration\Resources;

use OC\Collaboration\Resources\Manager;
use OCP\Collaboration\Resources\IManager;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\IDBConnection;
use OCP\ILogger;
use Test\TestCase;

class ManagerTest extends TestCase {

	/** @var ILogger */
	protected $logger;
	/** @var IProviderManager */
	protected $providerManager;
	/** @var IManager */
	protected $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->providerManager = $this->createMock(IProviderManager::class);

		/** @var IDBConnection $connection */
		$connection = $this->createMock(IDBConnection::class);
		$this->manager = new Manager($connection, $this->providerManager, $this->logger);
	}
	
	public function testRegisterResourceProvider(): void {
		$this->logger->expects($this->once())
			->method('debug')
			->with($this->equalTo('\OC\Collaboration\Resources\Manager::registerResourceProvider is deprecated'), $this->equalTo(['provider' => 'AwesomeResourceProvider']));
		$this->providerManager->expects($this->once())
			->method('registerResourceProvider')
			->with($this->equalTo('AwesomeResourceProvider'));

		$this->manager->registerResourceProvider('AwesomeResourceProvider');
	}
}
