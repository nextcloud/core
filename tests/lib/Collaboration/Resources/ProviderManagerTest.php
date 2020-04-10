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

use OC\Collaboration\Resources\ProviderManager;
use OCA\Files\Collaboration\Resources\ResourceProvider;
use OCP\AppFramework\QueryException;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\ILogger;
use OCP\IServerContainer;
use Test\TestCase;

class ProviderManagerTest extends TestCase {

	/** @var IServerContainer */
	protected $serverContainer;
	/** @var ILogger */
	protected $logger;
	/** @var IProviderManager */
	protected $providerManager;

	protected function setUp(): void {
		parent::setUp();

		$this->serverContainer = $this->createMock(IServerContainer::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->providerManager = new class($this->serverContainer, $this->logger) extends ProviderManager {
			public function countProviders(): int {
				return count($this->providers);
			}
		};
	}

	public function testRegisterResourceProvider(): void {
		$this->providerManager->registerResourceProvider('AwesomeResourceProvider');
		$this->assertSame(1, $this->providerManager->countProviders());
	}

	public function testGetResourceProvidersNoProvider(): void {
		$this->assertCount(0, $this->providerManager->getResourceProviders());
	}

	public function testGetResourceProvidersValidProvider(): void {
		$this->serverContainer->expects($this->once())
			->method('query')
			->with($this->equalTo(ResourceProvider::class))
			->willReturn($this->createMock(ResourceProvider::class));

		$this->providerManager->registerResourceProvider(ResourceProvider::class);
		$resourceProviders = $this->providerManager->getResourceProviders();

		$this->assertCount(1, $resourceProviders);
		$this->assertInstanceOf(ResourceProvider::class, $resourceProviders[0]);
	}

	public function testGetResourceProvidersInvalidProvider(): void {
		$this->serverContainer->expects($this->once())
			->method('query')
			->with($this->equalTo('InvalidResourceProvider'))
			->willThrowException(new QueryException('A meaningful error message'));

		$this->logger->expects($this->once())
			->method('logException');

		$this->providerManager->registerResourceProvider('InvalidResourceProvider');
		$resourceProviders = $this->providerManager->getResourceProviders();

		$this->assertCount(0, $resourceProviders);
	}

	public function testGetResourceProvidersValidAndInvalidProvider(): void {
		$this->serverContainer->expects($this->at(0))
			->method('query')
			->with($this->equalTo('InvalidResourceProvider'))
			->willThrowException(new QueryException('A meaningful error message'));
		$this->serverContainer->expects($this->at(1))
			->method('query')
			->with($this->equalTo(ResourceProvider::class))
			->willReturn($this->createMock(ResourceProvider::class));

		$this->logger->expects($this->once())
			->method('logException');

		$this->providerManager->registerResourceProvider('InvalidResourceProvider');
		$this->providerManager->registerResourceProvider(ResourceProvider::class);
		$resourceProviders = $this->providerManager->getResourceProviders();

		$this->assertCount(1, $resourceProviders);
	}
}
