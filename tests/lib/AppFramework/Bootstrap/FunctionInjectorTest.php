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

use OC\AppFramework\Bootstrap\FunctionInjector;
use OC\AppFramework\Utility\SimpleContainer;
use Test\TestCase;

interface Foo {
}

class FunctionInjectorTest extends TestCase {

	/** @var SimpleContainer */
	private $container;

	protected function setUp(): void {
		parent::setUp();

		$this->container = new SimpleContainer();
	}

	public function testInjectFnNotRegistered(): void {
		$this->expectException(\OCP\AppFramework\QueryException::class);

		(new FunctionInjector($this->container))->injectFn(static function (Foo $p1): void {
		});
	}

	public function testInjectFnNotRegisteredButNullable(): void {
		(new FunctionInjector($this->container))->injectFn(static function (?Foo $p1): void {
		});

		// Nothing to assert. No errors means everything is fine.
		$this->addToAssertionCount(1);
	}

	public function testInjectFnByType(): void {
		$this->container->registerService(Foo::class, function () {
			$this->addToAssertionCount(1);
			return new class implements Foo {
			};
		});

		(new FunctionInjector($this->container))->injectFn(static function (Foo $p1): void {
		});

		// Nothing to assert. No errors means everything is fine.
		$this->addToAssertionCount(1);
	}

	public function testInjectFnByName(): void {
		$this->container->registerParameter('test', 'abc');

		(new FunctionInjector($this->container))->injectFn(static function ($test): void {
		});

		// Nothing to assert. No errors means everything is fine.
		$this->addToAssertionCount(1);
	}

	public function testInjectWithOverride(): void {
		$obj = new class() implements Foo {};

		$injector = new FunctionInjector($this->container, [
			Foo::class => $obj,
		]);
		$injector->injectFn(static function (Foo $f): void {
		});

		// Nothing to assert. No errors means everything is fine.
		$this->addToAssertionCount(1);
	}
}
