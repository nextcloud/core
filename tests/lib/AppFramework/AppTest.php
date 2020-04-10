<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework;

use OC\AppFramework\App;
use OC\AppFramework\Http\Dispatcher;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;

function rrmdir($directory) {
	$files = array_diff(scandir($directory), ['.','..']);
	foreach ($files as $file) {
		if (is_dir($directory . '/' . $file)) {
			rrmdir($directory . '/' . $file);
		} else {
			unlink($directory . '/' . $file);
		}
	}
	return rmdir($directory);
}


class AppTest extends \Test\TestCase {

	private $container;
	private $io;
	private $api;
	private $controller;
	private $dispatcher;
	private $params;
	private $headers;
	private $output;
	private $controllerName;
	private $controllerMethod;
	private $appPath;

	protected function setUp(): void {
		parent::setUp();

		$this->container = new \OC\AppFramework\DependencyInjection\DIContainer('test', []);
		$this->controller = $this->createMock(Controller::class);
		$this->dispatcher = $this->createMock(Dispatcher::class);
		$this->io = $this->createMock(Http\IOutput::class);

		$this->headers = ['key' => 'value'];
		$this->output = 'hi';
		$this->controllerName = 'Controller';
		$this->controllerMethod = 'method';

		$this->container[$this->controllerName] = $this->controller;
		$this->container['Dispatcher'] = $this->dispatcher;
		$this->container['OCP\\AppFramework\\Http\\IOutput'] = $this->io;
		$this->container['urlParams'] = [];

		$this->appPath = __DIR__ . '/../../../apps/namespacetestapp';
		$infoXmlPath = $this->appPath . '/appinfo/info.xml';
		mkdir($this->appPath . '/appinfo', 0777, true);

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' .
		'<info>' .
			'<id>namespacetestapp</id>' .
			'<namespace>NameSpaceTestApp</namespace>' .
		'</info>';
		file_put_contents($infoXmlPath, $xml);
	}


	public function testControllerNameAndMethodAreBeingPassed() {
		$return = ['HTTP/2.0 200 OK', [], [], null, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);

		$this->io->expects($this->never())
			->method('setOutput');

		App::main($this->controllerName, $this->controllerMethod,
			$this->container);
	}


	public function testBuildAppNamespace() {
		$ns = App::buildAppNamespace('someapp');
		$this->assertEquals('OCA\Someapp', $ns);
	}


	public function testBuildAppNamespaceCore() {
		$ns = App::buildAppNamespace('someapp', 'OC\\');
		$this->assertEquals('OC\Someapp', $ns);
	}


	public function testBuildAppNamespaceInfoXml() {
		$ns = App::buildAppNamespace('namespacetestapp', 'OCA\\');
		$this->assertEquals('OCA\NameSpaceTestApp', $ns);
	}


	protected function tearDown(): void {
		rrmdir($this->appPath);
		parent::tearDown();
	}


	public function testOutputIsPrinted() {
		$return = ['HTTP/2.0 200 OK', [], [], $this->output, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);
		$this->io->expects($this->once())
			->method('setOutput')
			->with($this->equalTo($this->output));
		App::main($this->controllerName, $this->controllerMethod, $this->container, []);
	}

	public function dataNoOutput() {
		return [
			['HTTP/2.0 204 No content'],
			['HTTP/2.0 304 Not modified'],
		];
	}

	/**
	 * @dataProvider dataNoOutput
	 */
	public function testNoOutput(string $statusCode) {
		$return = [$statusCode, [], [], $this->output, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);
		$this->io->expects($this->once())
			->method('setHeader')
			->with($this->equalTo($statusCode));
		$this->io->expects($this->never())
			->method('setOutput');
		App::main($this->controllerName, $this->controllerMethod, $this->container, []);
	}


	public function testCallbackIsCalled() {
		$mock = $this->getMockBuilder('OCP\AppFramework\Http\ICallbackResponse')
			->getMock();

		$return = ['HTTP/2.0 200 OK', [], [], $this->output, $mock];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);
		$mock->expects($this->once())
			->method('callback');
		App::main($this->controllerName, $this->controllerMethod, $this->container, []);
	}

	public function testCoreApp() {
		$this->container['AppName'] = 'core';
		$this->container['OC\Core\Controller\Foo'] = $this->controller;

		$return = ['HTTP/2.0 200 OK', [], [], null, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);

		$this->io->expects($this->never())
			->method('setOutput');

		App::main('Foo', $this->controllerMethod, $this->container);
	}

	public function testSettingsApp() {
		$this->container['AppName'] = 'settings';
		$this->container['OCA\Settings\Controller\Foo'] = $this->controller;

		$return = ['HTTP/2.0 200 OK', [], [], null, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);

		$this->io->expects($this->never())
			->method('setOutput');

		App::main('Foo', $this->controllerMethod, $this->container);
	}

	public function testApp() {
		$this->container['AppName'] = 'bar';
		$this->container['OCA\Bar\Controller\Foo'] = $this->controller;

		$return = ['HTTP/2.0 200 OK', [], [], null, new Response()];
		$this->dispatcher->expects($this->once())
			->method('dispatch')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod))
			->willReturn($return);

		$this->io->expects($this->never())
			->method('setOutput');

		App::main('Foo', $this->controllerMethod, $this->container);
	}

}
