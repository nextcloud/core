<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Theming\Tests\Controller;

use OC\Files\SimpleFS\SimpleFile;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OCA\Theming\Controller\IconController;
use OCA\Theming\IconBuilder;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use Test\TestCase;

class IconControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var ThemingDefaults|\PHPUnit\Framework\MockObject\MockObject */
	private $themingDefaults;
	/** @var \OCP\AppFramework\Utility\ITimeFactory */
	private $timeFactory;
	/** @var IconController|\PHPUnit\Framework\MockObject\MockObject */
	private $iconController;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IconBuilder|\PHPUnit\Framework\MockObject\MockObject */
	private $iconBuilder;
	/** @var FileAccessHelper|\PHPUnit\Framework\MockObject\MockObject */
	private $fileAccessHelper;
	/** @var ImageManager */
	private $imageManager;

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->themingDefaults = $this->createMock(ThemingDefaults::class);
		$this->iconBuilder = $this->createMock(IconBuilder::class);
		$this->imageManager = $this->createMock(ImageManager::class);
		$this->fileAccessHelper = $this->createMock(FileAccessHelper::class);

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->timeFactory->expects($this->any())
			->method('getTime')
			->willReturn(123);

		$this->overwriteService(ITimeFactory::class, $this->timeFactory);

		$this->iconController = new IconController(
			'theming',
			$this->request,
			$this->themingDefaults,
			$this->iconBuilder,
			$this->imageManager,
			$this->fileAccessHelper
		);

		parent::setUp();
	}

	private function iconFileMock($filename, $data) {
		$icon = $this->getMockBuilder('OCP\Files\File')->getMock();
		$icon->expects($this->any())->method('getContent')->willReturn($data);
		$icon->expects($this->any())->method('getMimeType')->willReturn('image type');
		$icon->expects($this->any())->method('getEtag')->willReturn('my etag');
		$icon->method('getName')->willReturn($filename);
		return new SimpleFile($icon);
	}

	public function testGetThemedIcon() {
		$file = $this->iconFileMock('icon-core-filetypes_folder.svg', 'filecontent');
		$this->imageManager->expects($this->once())
			->method('getCachedImage')
			->with('icon-core-filetypes_folder.svg')
			->willReturn($file);
		$expected = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'image/svg+xml']);
		$expected->cacheFor(86400);
		$this->assertEquals($expected, $this->iconController->getThemedIcon('core', 'filetypes/folder.svg'));
	}

	public function testGetFaviconDefault() {
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('Imagemagick is required for dynamic icon generation.');
		}
		$checkImagick = new \Imagick();
		if (count($checkImagick->queryFormats('SVG')) < 1) {
			$this->markTestSkipped('No SVG provider present.');
		}
		$file = $this->iconFileMock('filename', 'filecontent');
		$this->imageManager->expects($this->once())
			->method('getImage', false)
			->with('favicon')
			->will($this->throwException(new NotFoundException()));
		$this->imageManager->expects($this->any())
			->method('shouldReplaceIcons')
			->willReturn(true);
		$this->imageManager->expects($this->once())
			->method('getCachedImage')
			->will($this->throwException(new NotFoundException()));
		$this->iconBuilder->expects($this->once())
			->method('getFavicon')
			->with('core')
			->willReturn('filecontent');
		$this->imageManager->expects($this->once())
			->method('setCachedImage')
			->willReturn($file);

		$expected = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		$expected->cacheFor(86400);
		$this->assertEquals($expected, $this->iconController->getFavicon());
	}

	public function testGetFaviconFail() {
		$this->imageManager->expects($this->once())
			->method('getImage')
			->with('favicon', false)
			->will($this->throwException(new NotFoundException()));
		$this->imageManager->expects($this->any())
			->method('shouldReplaceIcons')
			->willReturn(false);
		$fallbackLogo = \OC::$SERVERROOT . '/core/img/favicon.png';
		$this->fileAccessHelper->expects($this->once())
			->method('file_get_contents')
			->with($fallbackLogo)
			->willReturn(file_get_contents($fallbackLogo));
		$expected = new DataDisplayResponse(file_get_contents($fallbackLogo), Http::STATUS_OK, ['Content-Type' => 'image/x-icon']);
		$expected->cacheFor(86400);
		$this->assertEquals($expected, $this->iconController->getFavicon());
	}

	public function testGetTouchIconDefault() {
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('Imagemagick is required for dynamic icon generation.');
		}
		$checkImagick = new \Imagick();
		if (count($checkImagick->queryFormats('SVG')) < 1) {
			$this->markTestSkipped('No SVG provider present.');
		}

		$this->imageManager->expects($this->once())
			->method('getImage')
			->will($this->throwException(new NotFoundException()));
		$this->imageManager->expects($this->any())
			->method('shouldReplaceIcons')
			->willReturn(true);
		$this->iconBuilder->expects($this->once())
			->method('getTouchIcon')
			->with('core')
			->willReturn('filecontent');
		$file = $this->iconFileMock('filename', 'filecontent');
		$this->imageManager->expects($this->once())
			->method('getCachedImage')
			->will($this->throwException(new NotFoundException()));
		$this->imageManager->expects($this->once())
			->method('setCachedImage')
			->willReturn($file);

		$expected = new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'image/png']);
		$expected->cacheFor(86400);
		$this->assertEquals($expected, $this->iconController->getTouchIcon());
	}

	public function testGetTouchIconFail() {
		$this->imageManager->expects($this->once())
			->method('getImage')
			->with('favicon')
			->will($this->throwException(new NotFoundException()));
		$this->imageManager->expects($this->any())
			->method('shouldReplaceIcons')
			->willReturn(false);
		$fallbackLogo = \OC::$SERVERROOT . '/core/img/favicon-touch.png';
		$this->fileAccessHelper->expects($this->once())
			->method('file_get_contents')
			->with($fallbackLogo)
			->willReturn(file_get_contents($fallbackLogo));
		$expected = new DataDisplayResponse(file_get_contents($fallbackLogo), Http::STATUS_OK, ['Content-Type' => 'image/png']);
		$expected->cacheFor(86400);
		$this->assertEquals($expected, $this->iconController->getTouchIcon());
	}
}
