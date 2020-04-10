<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace Test\AppFramework\DependencyInjection;

use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Utility\SimpleContainer;
use OC\ServerContainer;
use Test\TestCase;

interface Interface1 {}

class ClassA1 implements Interface1 {}

class ClassA2 implements Interface1 {}

class ClassB {
	/** @var Interface1 */
	public $interface1;

	/**
	 * ClassB constructor.
	 *
	 * @param Interface1 $interface1
	 */
	public function __construct(Interface1 $interface1) {
		$this->interface1 = $interface1;
	}
}

class DIIntergrationTests extends TestCase {

	/** @var DIContainer */
	private $container;

	/** @var ServerContainer */
	private $server;

	protected function setUp(): void {
		parent::setUp();

		$this->server = new ServerContainer();
		$this->container = new DIContainer('App1', [], $this->server);
	}

	public function testInjectFromServer() {
		$this->server->registerService(Interface1::class, function () {
			return new ClassA1();
		});

		$this->server->registerService(ClassB::class, function (SimpleContainer $c) {
			return new ClassB(
				$c->query(Interface1::class)
			);
		});

		/** @var ClassB $res */
		$res = $this->container->query(ClassB::class);
		$this->assertSame(ClassA1::class, get_class($res->interface1));
	}

	public function testInjectDepFromServer() {
		$this->server->registerService(Interface1::class, function () {
			return new ClassA1();
		});

		$this->container->registerService(ClassB::class, function (SimpleContainer $c) {
			return new ClassB(
				$c->query(Interface1::class)
			);
		});

		/** @var ClassB $res */
		$res = $this->container->query(ClassB::class);
		$this->assertSame(ClassA1::class, get_class($res->interface1));
	}

	public function testOverwriteDepFromServer() {
		$this->server->registerService(Interface1::class, function () {
			return new ClassA1();
		});

		$this->container->registerService(Interface1::class, function () {
			return new ClassA2();
		});

		$this->container->registerService(ClassB::class, function (SimpleContainer $c) {
			return new ClassB(
				$c->query(Interface1::class)
			);
		});

		/** @var ClassB $res */
		$res = $this->container->query(ClassB::class);
		$this->assertSame(ClassA2::class, get_class($res->interface1));
	}

	public function testIgnoreOverwriteInServerClass() {
		$this->server->registerService(Interface1::class, function () {
			return new ClassA1();
		});

		$this->container->registerService(Interface1::class, function () {
			return new ClassA2();
		});

		$this->server->registerService(ClassB::class, function (SimpleContainer $c) {
			return new ClassB(
				$c->query(Interface1::class)
			);
		});

		/** @var ClassB $res */
		$res = $this->container->query(ClassB::class);
		$this->assertSame(ClassA1::class, get_class($res->interface1));
	}
}
