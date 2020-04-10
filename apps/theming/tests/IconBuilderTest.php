<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Theming\Tests;

use OC\Files\AppData\AppData;
use OCA\Theming\IconBuilder;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\App\IAppManager;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use PHPUnit\Framework\Error\Warning;
use Test\TestCase;

class IconBuilderTest extends TestCase {

	/** @var IConfig */
	protected $config;
	/** @var AppData */
	protected $appData;
	/** @var ThemingDefaults */
	protected $themingDefaults;
	/** @var Util */
	protected $util;
	/** @var ImageManager */
	protected $imageManager;
	/** @var IconBuilder */
	protected $iconBuilder;
	/** @var IAppManager */
	protected $appManager;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->appData = $this->createMock(AppData::class);
		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->imageManager = $this->createMock(ImageManager::class);
		$this->util = new Util($this->config, $this->appManager, $this->appData);
		$this->iconBuilder = new IconBuilder($this->themingDefaults, $this->util, $this->imageManager);
	}

	private function checkImagick() {
		if(!extension_loaded('imagick')) {
			$this->markTestSkipped('Imagemagick is required for dynamic icon generation.');
		}
		$checkImagick = new \Imagick();
		if (count($checkImagick->queryFormats('SVG')) < 1) {
			$this->markTestSkipped('No SVG provider present.');
		}
		if (count($checkImagick->queryFormats('PNG')) < 1) {
			$this->markTestSkipped('No PNG provider present.');
		}
	}

	public function dataRenderAppIcon() {
		return [
			['core', '#0082c9', 'touch-original.png'],
			['core', '#FF0000', 'touch-core-red.png'],
			['testing', '#FF0000', 'touch-testing-red.png'],
			['comments', '#0082c9', 'touch-comments.png'],
			['core', '#0082c9', 'touch-original-png.png'],
		];
	}

	/**
	 * @dataProvider dataRenderAppIcon
	 * @param $app
	 * @param $color
	 * @param $file
	 */
	public function testRenderAppIcon($app, $color, $file) {
		$this->checkImagick();
		$this->themingDefaults->expects($this->once())
			->method('getColorPrimary')
			->willReturn($color);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('images')
			->willThrowException(new NotFoundException());

		$expectedIcon = new \Imagick(realpath(dirname(__FILE__)). "/data/" . $file);
		$icon = $this->iconBuilder->renderAppIcon($app, 512);

		$this->assertEquals(true, $icon->valid());
		$this->assertEquals(512, $icon->getImageWidth());
		$this->assertEquals(512, $icon->getImageHeight());
		$this->assertEquals($icon, $expectedIcon);
		$icon->destroy();
		$expectedIcon->destroy();
		// FIXME: We may need some comparison of the generated and the test images
		// cloud be something like $expectedIcon->compareImages($icon, Imagick::METRIC_MEANABSOLUTEERROR)[1])
	}

	/**
	 * @dataProvider dataRenderAppIcon
	 * @param $app
	 * @param $color
	 * @param $file
	 */
	public function testGetTouchIcon($app, $color, $file) {
		$this->checkImagick();
		$this->themingDefaults->expects($this->once())
			->method('getColorPrimary')
			->willReturn($color);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('images')
			->willThrowException(new NotFoundException());

		$expectedIcon = new \Imagick(realpath(dirname(__FILE__)). "/data/" . $file);
		$icon = new \Imagick();
		$icon->readImageBlob($this->iconBuilder->getTouchIcon($app));

		$this->assertEquals(true, $icon->valid());
		$this->assertEquals(512, $icon->getImageWidth());
		$this->assertEquals(512, $icon->getImageHeight());
		$this->assertEquals($icon, $expectedIcon);
		$icon->destroy();
		$expectedIcon->destroy();
		// FIXME: We may need some comparison of the generated and the test images
		// cloud be something like $expectedIcon->compareImages($icon, Imagick::METRIC_MEANABSOLUTEERROR)[1])
	}

	/**
	 * @dataProvider dataRenderAppIcon
	 * @param $app
	 * @param $color
	 * @param $file
	 */
	public function testGetFavicon($app, $color, $file) {
		$this->checkImagick();
		$this->imageManager->expects($this->once())
			->method('shouldReplaceIcons')
			->willReturn(true);
		$this->themingDefaults->expects($this->once())
			->method('getColorPrimary')
			->willReturn($color);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('images')
			->willThrowException(new NotFoundException());

		$expectedIcon = new \Imagick(realpath(dirname(__FILE__)). "/data/" . $file);
		$actualIcon = $this->iconBuilder->getFavicon($app);

		$icon = new \Imagick();
		$icon->setFormat('ico');
		$icon->readImageBlob($actualIcon);

		$this->assertEquals(true, $icon->valid());
		$this->assertEquals(128, $icon->getImageWidth());
		$this->assertEquals(128, $icon->getImageHeight());
		$icon->destroy();
		$expectedIcon->destroy();
		// FIXME: We may need some comparison of the generated and the test images
		// cloud be something like $expectedIcon->compareImages($icon, Imagick::METRIC_MEANABSOLUTEERROR)[1])
	}

	public function testGetFaviconNotFound() {
		$this->checkImagick();
		$this->expectException(Warning::class);
		$util = $this->getMockBuilder(Util::class)->disableOriginalConstructor()->getMock();
		$iconBuilder = new IconBuilder($this->themingDefaults, $util, $this->imageManager);
		$this->imageManager->expects($this->once())
			->method('shouldReplaceIcons')
			->willReturn(true);
		$util->expects($this->once())
			->method('getAppIcon')
			->willReturn('notexistingfile');
		$this->assertFalse($iconBuilder->getFavicon('noapp'));
	}

	public function testGetTouchIconNotFound() {
		$this->checkImagick();
		$this->expectException(Warning::class);
		$util = $this->getMockBuilder(Util::class)->disableOriginalConstructor()->getMock();
		$iconBuilder = new IconBuilder($this->themingDefaults, $util, $this->imageManager);
		$util->expects($this->once())
			->method('getAppIcon')
			->willReturn('notexistingfile');
		$this->assertFalse($iconBuilder->getTouchIcon('noapp'));
	}

	public function testColorSvgNotFound() {
		$this->checkImagick();
		$this->expectException(Warning::class);
		$util = $this->getMockBuilder(Util::class)->disableOriginalConstructor()->getMock();
		$iconBuilder = new IconBuilder($this->themingDefaults, $util, $this->imageManager);
		$util->expects($this->once())
			->method('getAppImage')
			->willReturn('notexistingfile');
		$this->assertFalse($iconBuilder->colorSvg('noapp','noimage'));
	}
}
