<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace lib\AppFramework\Bootstrap;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\Support\CrashReport\Registry;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\QueryException;
use OCP\Dashboard\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IServerContainer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CoordinatorTest extends TestCase {

	/** @var IAppManager|MockObject */
	private $appManager;

	/** @var IServerContainer|MockObject */
	private $serverContainer;

	/** @var Registry|MockObject */
	private $crashReporterRegistry;

	/** @var IManager|MockObject */
	private $dashboardManager;

	/** @var IEventDispatcher|MockObject */
	private $eventDispatcher;

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var Coordinator */
	private $coordinator;

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->serverContainer = $this->createMock(IServerContainer::class);
		$this->crashReporterRegistry = $this->createMock(Registry::class);
		$this->dashboardManager = $this->createMock(IManager::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->coordinator = new Coordinator(
			$this->serverContainer,
			$this->crashReporterRegistry,
			$this->dashboardManager,
			$this->eventDispatcher,
			$this->logger
		);
	}

	public function testBootAppNotLoadable(): void {
		$appId = 'settings';
		$this->serverContainer->expects($this->once())
			->method('query')
			->with(\OCA\Settings\AppInfo\Application::class)
			->willThrowException(new QueryException(""));
		$this->logger->expects($this->once())
			->method('error');

		$this->coordinator->bootApp($appId);
	}

	public function testBootAppNotBootable(): void {
		$appId = 'settings';
		$mockApp = $this->createMock(\OCA\Settings\AppInfo\Application::class);
		$this->serverContainer->expects($this->once())
			->method('query')
			->with(\OCA\Settings\AppInfo\Application::class)
			->willReturn($mockApp);

		$this->coordinator->bootApp($appId);
	}

	public function testBootApp(): void {
		$appId = 'settings';
		$mockApp = new class extends App implements IBootstrap {
			public function __construct() {
				parent::__construct('test', []);
			}

			public function register(IRegistrationContext $context): void {
			}

			public function boot(IBootContext $context): void {
			}
		};
		$this->serverContainer->expects($this->once())
			->method('query')
			->with(\OCA\Settings\AppInfo\Application::class)
			->willReturn($mockApp);

		$this->coordinator->bootApp($appId);
	}
}
