<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Template;

use OC\Template\ResourceNotFoundException;
use OCP\ILogger;

class ResourceLocatorTest extends \Test\TestCase {
	/** @var \PHPUnit\Framework\MockObject\MockObject */
	protected $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(ILogger::class);
	}

	/**
	 * @param string $theme
	 * @param array $core_map
	 * @param array $party_map
	 * @param array $appsRoots
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	public function getResourceLocator($theme, $core_map, $party_map, $appsRoots) {
		return $this->getMockForAbstractClass('OC\Template\ResourceLocator',
			[$this->logger, $theme, $core_map, $party_map, $appsRoots ],
			'', true, true, true, []);
	}

	public function testFind() {
		$locator = $this->getResourceLocator('theme',
			['core' => 'map'], ['3rd' => 'party'], ['foo' => 'bar']);
		$locator->expects($this->once())
			->method('doFind')
			->with('foo');
		$locator->expects($this->once())
			->method('doFindTheme')
			->with('foo');
		/** @var \OC\Template\ResourceLocator $locator */
		$locator->find(['foo']);
	}

	public function testFindNotFound() {
		$locator = $this->getResourceLocator('theme',
			['core' => 'map'], ['3rd' => 'party'], ['foo' => 'bar']);
		$locator->expects($this->once())
			->method('doFind')
			->with('foo')
			->will($this->throwException(new ResourceNotFoundException('foo', 'map')));
		$locator->expects($this->once())
			->method('doFindTheme')
			->with('foo')
			->will($this->throwException(new ResourceNotFoundException('foo', 'map')));
		$this->logger->expects($this->exactly(2))
			->method('debug')
			->with($this->stringContains('map/foo'));
		/** @var \OC\Template\ResourceLocator $locator */
		$locator->find(['foo']);
	}

	public function testAppendIfExist() {
		$locator = $this->getResourceLocator('theme',
			[__DIR__ => 'map'], ['3rd' => 'party'], ['foo' => 'bar']);
		/** @var \OC\Template\ResourceLocator $locator */
		$method = new \ReflectionMethod($locator, 'appendIfExist');
		$method->setAccessible(true);

		$method->invoke($locator, __DIR__, basename(__FILE__), 'webroot');
		$resource1 = [__DIR__, 'webroot', basename(__FILE__)];
		$this->assertEquals([$resource1], $locator->getResources());

		$method->invoke($locator, __DIR__, basename(__FILE__));
		$resource2 = [__DIR__, 'map', basename(__FILE__)];
		$this->assertEquals([$resource1, $resource2], $locator->getResources());

		$method->invoke($locator, __DIR__, 'does-not-exist');
		$this->assertEquals([$resource1, $resource2], $locator->getResources());
	}
}
