<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @author Morris Jobke
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
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

namespace Test\AppFramework\DependencyInjection;

use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Middleware\Security\SecurityMiddleware;
use OCP\AppFramework\QueryException;
use OCP\IConfig;
use OCP\Security\ISecureRandom;

/**
 * @group DB
 */
class DIContainerTest extends \Test\TestCase {

	/** @var DIContainer|\PHPUnit_Framework_MockObject_MockObject */
	private $container;

	protected function setUp(): void {
		parent::setUp();
		$this->container = $this->getMockBuilder(DIContainer::class)
			->setMethods(['isAdminUser'])
			->setConstructorArgs(['name'])
			->getMock();
	}


	public function testProvidesRequest() {
		$this->assertTrue(isset($this->container['Request']));
	}

	public function testProvidesMiddlewareDispatcher() {
		$this->assertTrue(isset($this->container['MiddlewareDispatcher']));
	}

	public function testProvidesAppName() {
		$this->assertTrue(isset($this->container['AppName']));
	}


	public function testAppNameIsSetCorrectly() {
		$this->assertEquals('name', $this->container['AppName']);
	}

	public function testMiddlewareDispatcherIncludesSecurityMiddleware() {
		$this->container['Request'] = new Request(
			['method' => 'GET'],
			$this->createMock(ISecureRandom::class),
			$this->createMock(IConfig::class)
		);
		$dispatcher = $this->container['MiddlewareDispatcher'];
		$middlewares = $dispatcher->getMiddlewares();

		$found = false;
		foreach ($middlewares as $middleware) {
			if ($middleware instanceof SecurityMiddleware) {
				$found = true;
			}
		}

		$this->assertTrue($found);
	}

	public function testInvalidAppClass() {
		$this->expectException(QueryException::class);
		$this->container->query('\OCA\Name\Foo');
	}
}
