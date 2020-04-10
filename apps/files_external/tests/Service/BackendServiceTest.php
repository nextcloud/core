<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_External\Tests\Service;

use OCA\Files_External\Config\IConfigHandler;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\Config\IAuthMechanismProvider;
use OCA\Files_External\Lib\Config\IBackendProvider;
use OCA\Files_External\Service\BackendService;
use OCP\IConfig;

class BackendServiceTest extends \Test\TestCase {

	/** @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	protected function setUp(): void {
		$this->config = $this->createMock(IConfig::class);
	}

	/**
	 * @param string $class
	 *
	 * @return \OCA\Files_External\Lib\Backend\Backend|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getBackendMock($class) {
		$backend = $this->getMockBuilder(Backend::class)
			->disableOriginalConstructor()
			->getMock();
		$backend->method('getIdentifier')->willReturn('identifier:'.$class);
		$backend->method('getIdentifierAliases')->willReturn(['identifier:'.$class]);
		return $backend;
	}

	/**
	 * @param string $class
	 *
	 * @return \OCA\Files_External\Lib\Auth\AuthMechanism|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getAuthMechanismMock($class) {
		$backend = $this->getMockBuilder(AuthMechanism::class)
			->disableOriginalConstructor()
			->getMock();
		$backend->method('getIdentifier')->willReturn('identifier:'.$class);
		$backend->method('getIdentifierAliases')->willReturn(['identifier:'.$class]);
		return $backend;
	}

	public function testRegisterBackend() {
		$service = new BackendService($this->config);

		$backend = $this->getBackendMock('\Foo\Bar');

		/** @var \OCA\Files_External\Lib\Backend\Backend|\PHPUnit_Framework_MockObject_MockObject $backendAlias */
		$backendAlias = $this->getMockBuilder(Backend::class)
			->disableOriginalConstructor()
			->getMock();
		$backendAlias->method('getIdentifierAliases')
			->willReturn(['identifier_real', 'identifier_alias']);
		$backendAlias->method('getIdentifier')
			->willReturn('identifier_real');

		$service->registerBackend($backend);
		$service->registerBackend($backendAlias);

		$this->assertEquals($backend, $service->getBackend('identifier:\Foo\Bar'));
		$this->assertEquals($backendAlias, $service->getBackend('identifier_real'));
		$this->assertEquals($backendAlias, $service->getBackend('identifier_alias'));

		$backends = $service->getBackends();
		$this->assertCount(2, $backends);
		$this->assertArrayHasKey('identifier:\Foo\Bar', $backends);
		$this->assertArrayHasKey('identifier_real', $backends);
		$this->assertArrayNotHasKey('identifier_alias', $backends);
	}

	public function testBackendProvider() {
		$service = new BackendService($this->config);

		$backend1 = $this->getBackendMock('\Foo\Bar');
		$backend2 = $this->getBackendMock('\Bar\Foo');

		/** @var IBackendProvider|\PHPUnit_Framework_MockObject_MockObject $providerMock */
		$providerMock = $this->createMock(IBackendProvider::class);
		$providerMock->expects($this->once())
			->method('getBackends')
			->willReturn([$backend1, $backend2]);
		$service->registerBackendProvider($providerMock);

		$this->assertEquals($backend1, $service->getBackend('identifier:\Foo\Bar'));
		$this->assertEquals($backend2, $service->getBackend('identifier:\Bar\Foo'));

		$this->assertCount(2, $service->getBackends());
	}

	public function testAuthMechanismProvider() {
		$service = new BackendService($this->config);

		$backend1 = $this->getAuthMechanismMock('\Foo\Bar');
		$backend2 = $this->getAuthMechanismMock('\Bar\Foo');

		/** @var IAuthMechanismProvider|\PHPUnit_Framework_MockObject_MockObject $providerMock */
		$providerMock = $this->createMock(IAuthMechanismProvider::class);
		$providerMock->expects($this->once())
			->method('getAuthMechanisms')
			->willReturn([$backend1, $backend2]);
		$service->registerAuthMechanismProvider($providerMock);

		$this->assertEquals($backend1, $service->getAuthMechanism('identifier:\Foo\Bar'));
		$this->assertEquals($backend2, $service->getAuthMechanism('identifier:\Bar\Foo'));

		$this->assertCount(2, $service->getAuthMechanisms());
	}

	public function testMultipleBackendProviders() {
		$service = new BackendService($this->config);

		$backend1a = $this->getBackendMock('\Foo\Bar');
		$backend1b = $this->getBackendMock('\Bar\Foo');

		$backend2 = $this->getBackendMock('\Dead\Beef');

		/** @var IBackendProvider|\PHPUnit_Framework_MockObject_MockObject $provider1Mock */
		$provider1Mock = $this->createMock(IBackendProvider::class);
		$provider1Mock->expects($this->once())
			->method('getBackends')
			->willReturn([$backend1a, $backend1b]);
		$service->registerBackendProvider($provider1Mock);
		/** @var IBackendProvider|\PHPUnit_Framework_MockObject_MockObject $provider2Mock */
		$provider2Mock = $this->createMock(IBackendProvider::class);
		$provider2Mock->expects($this->once())
			->method('getBackends')
			->willReturn([$backend2]);
		$service->registerBackendProvider($provider2Mock);

		$this->assertEquals($backend1a, $service->getBackend('identifier:\Foo\Bar'));
		$this->assertEquals($backend1b, $service->getBackend('identifier:\Bar\Foo'));
		$this->assertEquals($backend2, $service->getBackend('identifier:\Dead\Beef'));

		$this->assertCount(3, $service->getBackends());
	}

	public function testUserMountingBackends() {
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->willReturnMap([
				['files_external', 'allow_user_mounting', 'yes', 'yes'],
				['files_external', 'user_mounting_backends', '', 'identifier:\User\Mount\Allowed,identifier_alias']
			]);

		$service = new BackendService($this->config);

		$backendAllowed = $this->getBackendMock('\User\Mount\Allowed');
		$backendAllowed->expects($this->never())
			->method('removeVisibility');
		$backendNotAllowed = $this->getBackendMock('\User\Mount\NotAllowed');
		$backendNotAllowed->expects($this->once())
			->method('removeVisibility')
			->with(BackendService::VISIBILITY_PERSONAL);

		$backendAlias = $this->getMockBuilder(Backend::class)
			->disableOriginalConstructor()
			->getMock();
		$backendAlias->method('getIdentifierAliases')
			->willReturn(['identifier_real', 'identifier_alias']);
		$backendAlias->expects($this->never())
			->method('removeVisibility');

		$service->registerBackend($backendAllowed);
		$service->registerBackend($backendNotAllowed);
		$service->registerBackend($backendAlias);
	}

	public function testGetAvailableBackends() {
		$service = new BackendService($this->config);

		$backendAvailable = $this->getBackendMock('\Backend\Available');
		$backendAvailable->expects($this->once())
			->method('checkDependencies')
			->willReturn([]);
		$backendNotAvailable = $this->getBackendMock('\Backend\NotAvailable');
		$backendNotAvailable->expects($this->once())
			->method('checkDependencies')
			->willReturn([
				$this->getMockBuilder('\OCA\Files_External\Lib\MissingDependency')
					->disableOriginalConstructor()
					->getMock()
			]);

		$service->registerBackend($backendAvailable);
		$service->registerBackend($backendNotAvailable);

		$availableBackends = $service->getAvailableBackends();
		$this->assertArrayHasKey('identifier:\Backend\Available', $availableBackends);
		$this->assertArrayNotHasKey('identifier:\Backend\NotAvailable', $availableBackends);
	}

	public function invalidConfigPlaceholderProvider() {
		return [
			[['@user']],
			[['$user']],
			[['hællo']],
			[['spa ce']],
			[['yo\o']],
			[['<script>…</script>']],
			[['xxyoloxx', 'invÆlid']],
			[['tautology', 'tautology']],
			[['tautology2', 'TAUTOLOGY2']],
		];
	}

	/**
	 * @dataProvider invalidConfigPlaceholderProvider
	 */
	public function testRegisterConfigHandlerInvalid(array $placeholders) {
		$this->expectException(\RuntimeException::class);

		$service = new BackendService($this->config);
		$mock = $this->createMock(IConfigHandler::class);
		$cb = function () use ($mock) { return $mock; };
		foreach ($placeholders as $placeholder) {
			$service->registerConfigHandler($placeholder, $cb);
		}
	}

	public function testConfigHandlers() {
		$service = new BackendService($this->config);
		$mock = $this->createMock(IConfigHandler::class);
		$mock->expects($this->exactly(3))
			->method('handle');
		$cb = function () use ($mock) { return $mock; };
		$service->registerConfigHandler('one', $cb);
		$service->registerConfigHandler('2', $cb);
		$service->registerConfigHandler('Three', $cb);

		/** @var IConfigHandler[] $handlers */
		$handlers = $service->getConfigHandlers();

		foreach ($handlers as $handler) {
			$handler->handle('Something');
		}
	}

}
