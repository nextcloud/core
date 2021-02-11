<?php

declare(strict_types=1);

/*
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

namespace Test\Http\WellKnown;

use OC\AppFramework\Bootstrap\Coordinator;
use OC\AppFramework\Bootstrap\RegistrationContext;
use OC\AppFramework\Bootstrap\ServiceRegistration;
use OC\Http\WellKnown\RequestManager;
use OCP\AppFramework\QueryException;
use OCP\Http\WellKnown\IHandler;
use OCP\Http\WellKnown\IRequestContext;
use OCP\Http\WellKnown\IResponse;
use OCP\Http\WellKnown\JrdResponse;
use OCP\IRequest;
use OCP\IServerContainer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Test\TestCase;
use function get_class;

class RequestManagerTest extends TestCase {

	/** @var Coordinator|MockObject */
	private $coordinator;

	/** @var IServerContainer|MockObject */
	private $container;

	/** @var MockObject|LoggerInterface */
	private $logger;

	/** @var RequestManager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->coordinator = $this->createMock(Coordinator::class);
		$this->container = $this->createMock(IServerContainer::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->manager = new RequestManager(
			$this->coordinator,
			$this->container,
			$this->logger,
		);
	}

	public function testProcessAppsNotRegistered(): void {
		$request = $this->createMock(IRequest::class);
		$this->expectException(RuntimeException::class);

		$this->manager->process("webfinger", $request);
	}

	public function testProcessNoHandlersRegistered(): void {
		$request = $this->createMock(IRequest::class);
		$registrationContext = $this->createMock(RegistrationContext::class);
		$this->coordinator->expects(self::once())
			->method('getRegistrationContext')
			->willReturn($registrationContext);
		$registrationContext->expects(self::once())
			->method('getWellKnownHandlers')
			->willReturn([]);

		$response = $this->manager->process("webfinger", $request);

		self::assertNull($response);
	}

	public function testProcessHandlerNotLoadable(): void {
		$request = $this->createMock(IRequest::class);
		$registrationContext = $this->createMock(RegistrationContext::class);
		$this->coordinator->expects(self::once())
			->method('getRegistrationContext')
			->willReturn($registrationContext);
		$handler = new class {
		};
		$registrationContext->expects(self::once())
			->method('getWellKnownHandlers')
			->willReturn([
				new ServiceRegistration('test', get_class($handler)),
			]);
		$this->container->expects(self::once())
			->method('get')
			->with(get_class($handler))
			->willThrowException(new QueryException(""));
		$this->logger->expects(self::once())
			->method('error');

		$response = $this->manager->process("webfinger", $request);

		self::assertNull($response);
	}

	public function testProcessHandlerOfWrongType(): void {
		$request = $this->createMock(IRequest::class);
		$registrationContext = $this->createMock(RegistrationContext::class);
		$this->coordinator->expects(self::once())
			->method('getRegistrationContext')
			->willReturn($registrationContext);
		$handler = new class {
		};
		$registrationContext->expects(self::once())
			->method('getWellKnownHandlers')
			->willReturn([
				new ServiceRegistration('test', get_class($handler)),
			]);
		$this->container->expects(self::once())
			->method('get')
			->with(get_class($handler))
			->willReturn($handler);
		$this->logger->expects(self::once())
			->method('error');

		$response = $this->manager->process("webfinger", $request);

		self::assertNull($response);
	}

	public function testProcess(): void {
		$request = $this->createMock(IRequest::class);
		$registrationContext = $this->createMock(RegistrationContext::class);
		$this->coordinator->expects(self::once())
			->method('getRegistrationContext')
			->willReturn($registrationContext);
		$handler = new class implements IHandler {
			public function handle(string $service, IRequestContext $context, ?IResponse $previousResponse): ?IResponse {
				return (new JrdResponse($service))->addAlias('alias');
			}
		};
		$registrationContext->expects(self::once())
			->method('getWellKnownHandlers')
			->willReturn([
				new ServiceRegistration('test', get_class($handler)),
			]);
		$this->container->expects(self::once())
			->method('get')
			->with(get_class($handler))
			->willReturn($handler);

		$response = $this->manager->process("webfinger", $request);

		self::assertNotNull($response);
		self::assertInstanceOf(JrdResponse::class, $response);
	}
}
