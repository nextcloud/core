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

use OC\AppFramework\Bootstrap\RegistrationContext;
use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class RegistrationContextTest extends TestCase {

	/** @var LoggerInterface|MockObject */
	private $logger;

	/** @var RegistrationContext */
	private $context;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);

		$this->context = new RegistrationContext(
			$this->logger
		);
	}

	public function testRegisterCapability(): void {
		$app = $this->createMock(App::class);
		$name = 'abc';
		$container = $this->createMock(IAppContainer::class);
		$app->method('getContainer')
			->willReturn($container);
		$container->expects($this->once())
			->method('registerCapability')
			->with($name);

		$this->context->for('myapp')->registerCapability($name);
		$this->context->delegateCapabilityRegistrations([
			'myapp' => $app,
		]);
	}

	public function testRegisterEventListener(): void {
		$event = 'abc';
		$service = 'def';
		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->expects($this->once())
			->method('addServiceListener')
			->with($event, $service, 0);

		$this->context->for('myapp')->registerEventListener($event, $service);
		$this->context->delegateEventListenerRegistrations($dispatcher);
	}

	/**
	 * @dataProvider dataProvider_TrueFalse
	 */
	public function testRegisterService(bool $shared): void {
		$app = $this->createMock(App::class);
		$service = 'abc';
		$factory = function () {
			return 'def';
		};
		$container = $this->createMock(IAppContainer::class);
		$app->method('getContainer')
			->willReturn($container);
		$container->expects($this->once())
			->method('registerService')
			->with($service, $factory, $shared);

		$this->context->for('myapp')->registerService($service, $factory, $shared);
		$this->context->delegateContainerRegistrations([
			'myapp' => $app,
		]);
	}

	public function testRegisterServiceAlias(): void {
		$app = $this->createMock(App::class);
		$alias = 'abc';
		$target = 'def';
		$container = $this->createMock(IAppContainer::class);
		$app->method('getContainer')
			->willReturn($container);
		$container->expects($this->once())
			->method('registerAlias')
			->with($alias, $target);

		$this->context->for('myapp')->registerServiceAlias($alias, $target);
		$this->context->delegateContainerRegistrations([
			'myapp' => $app,
		]);
	}

	public function testRegisterParameter(): void {
		$app = $this->createMock(App::class);
		$name = 'abc';
		$value = 'def';
		$container = $this->createMock(IAppContainer::class);
		$app->method('getContainer')
			->willReturn($container);
		$container->expects($this->once())
			->method('registerParameter')
			->with($name, $value);

		$this->context->for('myapp')->registerParameter($name, $value);
		$this->context->delegateContainerRegistrations([
			'myapp' => $app,
		]);
	}

	public function testRegisterMiddleware(): void {
		$app = $this->createMock(App::class);
		$name = 'abc';
		$container = $this->createMock(IAppContainer::class);
		$app->method('getContainer')
			->willReturn($container);
		$container->expects($this->once())
			->method('registerMiddleware')
			->with($name);

		$this->context->for('myapp')->registerMiddleware($name);
		$this->context->delegateMiddlewareRegistrations([
			'myapp' => $app,
		]);
	}

	public function dataProvider_TrueFalse() {
		return[
			[true],
			[false]
		];
	}
}
