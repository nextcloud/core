<?php
/**
 * @copyright Copyright (c) 2017 Kyle Fazzari <kyrofa@ubuntu.com>
 *
 * @author Kyle Fazzari <kyrofa@ubuntu.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Template;

use OC\AppConfig;
use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory;
use OC\Template\CSSResourceLocator;
use OC\Template\IconsCacher;
use OC\Template\SCSSCacher;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\IURLGenerator;

class CSSResourceLocatorTest extends \Test\TestCase {
	/** @var IAppData|\PHPUnit\Framework\MockObject\MockObject */
	protected $appData;
	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	protected $urlGenerator;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var ThemingDefaults|\PHPUnit\Framework\MockObject\MockObject */
	protected $themingDefaults;
	/** @var ICacheFactory|\PHPUnit\Framework\MockObject\MockObject */
	protected $cacheFactory;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	protected $logger;
	/** @var IconsCacher|\PHPUnit\Framework\MockObject\MockObject */
	protected $iconsCacher;
	/** @var ITimeFactory|\PHPUnit\Framework\MockObject\MockObject */
	private $timeFactory;
	/** @var AppConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $appConfig;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->appData = $this->createMock(AppData::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->iconsCacher = $this->createMock(IconsCacher::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->appConfig = $this->createMock(AppConfig::class);
	}

	private function cssResourceLocator() {
		/** @var Factory|\PHPUnit\Framework\MockObject\MockObject $factory */
		$factory = $this->createMock(Factory::class);
		$factory->method('get')->with('css')->willReturn($this->appData);
		$scssCacher = new SCSSCacher(
			$this->logger,
			$factory,
			$this->urlGenerator,
			$this->config,
			$this->themingDefaults,
			\OC::$SERVERROOT,
			$this->cacheFactory,
			$this->iconsCacher,
			$this->timeFactory,
			$this->appConfig
		);
		return new CSSResourceLocator(
			$this->logger,
			'theme',
			['core' => 'map'],
			['3rd' => 'party'],
			$scssCacher
		);
	}

	private function rrmdir($directory) {
		$files = array_diff(scandir($directory), ['.','..']);
		foreach ($files as $file) {
			if (is_dir($directory . '/' . $file)) {
				$this->rrmdir($directory . '/' . $file);
			} else {
				unlink($directory . '/' . $file);
			}
		}
		return rmdir($directory);
	}

	private function randomString() {
		return sha1(uniqid(mt_rand(), true));
	}

	public function testFindWithAppPathSymlink() {
		// First create new apps path, and a symlink to it
		$apps_dirname = $this->randomString();
		$new_apps_path = sys_get_temp_dir() . '/' . $apps_dirname;
		$new_apps_path_symlink = $new_apps_path . '_link';
		mkdir($new_apps_path);
		symlink($apps_dirname, $new_apps_path_symlink);

		// Create an app within that path
		mkdir($new_apps_path . '/' . 'test-css-app');

		// Use the symlink as the app path
		\OC::$APPSROOTS[] = [
			'path' => $new_apps_path_symlink,
			'url' => '/css-apps-test',
			'writable' => false,
		];

		$locator = $this->cssResourceLocator();
		$locator->find(['test-css-app/test-file']);

		$resources = $locator->getResources();
		$this->assertCount(1, $resources);
		$resource = $resources[0];
		$this->assertCount(3, $resource);
		$root = $resource[0];
		$webRoot = $resource[1];
		$file = $resource[2];

		$expectedRoot = $new_apps_path . '/test-css-app';
		$expectedWebRoot = \OC::$WEBROOT . '/css-apps-test/test-css-app';
		$expectedFile = 'test-file.css';

		$this->assertEquals($expectedRoot, $root,
			'Ensure the app path symlink is resolved into the real path');
		$this->assertEquals($expectedWebRoot, $webRoot);
		$this->assertEquals($expectedFile, $file);

		array_pop(\OC::$APPSROOTS);
		unlink($new_apps_path_symlink);
		$this->rrmdir($new_apps_path);
	}
}
