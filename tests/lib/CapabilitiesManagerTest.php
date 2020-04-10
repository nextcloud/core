<?php
/**
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test;

use OC\CapabilitiesManager;
use OCP\AppFramework\QueryException;
use OCP\Capabilities\ICapability;
use OCP\Capabilities\IPublicCapability;
use OCP\ILogger;

class CapabilitiesManagerTest extends TestCase {

	/** @var CapabilitiesManager */
	private $manager;

	/** @var ILogger */
	private $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->manager = new CapabilitiesManager($this->logger);
	}

	/**
	 * Test no capabilities
	 */
	public function testNoCapabilities() {
		$res = $this->manager->getCapabilities();
		$this->assertEmpty($res);
	}

	/**
	 * Test a valid capabilitie
	 */
	public function testValidCapability() {
		$this->manager->registerCapability(function () {
			return new SimpleCapability();
		});

		$res = $this->manager->getCapabilities();
		$this->assertEquals(['foo' => 1], $res);
	}

	/**
	 * Test a public capabilitie
	 */
	public function testPublicCapability() {
		$this->manager->registerCapability(function () {
			return new PublicSimpleCapability1();
		});
		$this->manager->registerCapability(function () {
			return new SimpleCapability2();
		});
		$this->manager->registerCapability(function () {
			return new SimpleCapability3();
		});

		$res = $this->manager->getCapabilities(true);
		$this->assertEquals(['foo' => 1], $res);
	}

	/**
	 * Test that we need something that implents ICapability
	 */
	public function testNoICapability() {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('The given Capability (Test\\NoCapability) does not implement the ICapability interface');

		$this->manager->registerCapability(function () {
			return new NoCapability();
		});

		$res = $this->manager->getCapabilities();
		$this->assertEquals([], $res);
	}

	/**
	 * Test a bunch of merged Capabilities
	 */
	public function testMergedCapabilities() {
		$this->manager->registerCapability(function () {
			return new SimpleCapability();
		});
		$this->manager->registerCapability(function () {
			return new SimpleCapability2();
		});
		$this->manager->registerCapability(function () {
			return new SimpleCapability3();
		});

		$res = $this->manager->getCapabilities();
		$expected = [
			'foo' => 1,
			'bar' => [
				'x' => 1,
				'y' => 2
			]
		];

		$this->assertEquals($expected, $res);
	}

	/**
	 * Test deep identical capabilities
	 */
	public function testDeepIdenticalCapabilities() {
		$this->manager->registerCapability(function () {
			return new DeepCapability();
		});
		$this->manager->registerCapability(function () {
			return new DeepCapability();
		});

		$res = $this->manager->getCapabilities();
		$expected = [
			'foo' => [
				'bar' => [
					'baz' => true
				]
			]
		];
		
		$this->assertEquals($expected, $res);
	}

	public function testInvalidCapability() {
		$this->manager->registerCapability(function () {
			throw new QueryException();
		});

		$this->logger->expects($this->once())
			->method('logException');

		$res = $this->manager->getCapabilities();

		$this->assertEquals([], $res);
	}
}

class SimpleCapability implements ICapability {
	public function getCapabilities() {
		return [
			'foo' => 1
		];
	}
}

class SimpleCapability2 implements ICapability {
	public function getCapabilities() {
		return [
			'bar' => ['x' => 1]
		];
	}
}

class SimpleCapability3 implements ICapability {
	public function getCapabilities() {
		return [
			'bar' => ['y' => 2]
		];
	}
}

class PublicSimpleCapability1 implements IPublicCapability {
	public function getCapabilities() {
		return [
			'foo' => 1
		];
	}
}

class NoCapability {
	public function getCapabilities() {
		return [
			'baz' => 'z'
		];
	}
}

class DeepCapability implements ICapability {
	public function getCapabilities() {
		return [
			'foo' => [
				'bar' => [
					'baz' => true
				]
			]
		];
	}
}
