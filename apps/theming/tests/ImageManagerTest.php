<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Theming\Tests;

use OCA\Theming\ImageManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\ITempManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class ImageManagerTest extends TestCase {

	/** @var IConfig|MockObject */
	protected $config;
	/** @var IAppData|MockObject */
	protected $appData;
	/** @var ImageManager */
	protected $imageManager;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var ICacheFactory|MockObject */
	private $cacheFactory;
	/** @var ILogger|MockObject */
	private $logger;
	/** @var ITempManager|MockObject */
	private $tempManager;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->imageManager = new ImageManager(
			$this->config,
			$this->appData,
			$this->urlGenerator,
			$this->cacheFactory,
			$this->logger,
			$this->tempManager
		);
	}

	private function checkImagick() {
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('Imagemagick is required for dynamic icon generation.');
		}
		$checkImagick = new \Imagick();
		if (empty($checkImagick->queryFormats('SVG'))) {
			$this->markTestSkipped('No SVG provider present.');
		}
		if (empty($checkImagick->queryFormats('PNG'))) {
			$this->markTestSkipped('No PNG provider present.');
		}
	}

	public function mockGetImage($key, $file) {
		/** @var MockObject $folder */
		$folder = $this->createMock(ISimpleFolder::class);
		if ($file === null) {
			$folder->expects($this->once())
				->method('getFile')
				->with('logo')
				->willThrowException(new NotFoundException());
		} else {
			$file->expects($this->once())
				->method('getContent')
				->willReturn(file_get_contents(__DIR__ . '/../../../tests/data/testimage.png'));
			$folder->expects($this->at(0))
				->method('fileExists')
				->with('logo')
				->willReturn(true);
			$folder->expects($this->at(1))
				->method('fileExists')
				->with('logo.png')
				->willReturn(false);
			$folder->expects($this->at(2))
				->method('getFile')
				->with('logo')
				->willReturn($file);
			$newFile = $this->createMock(ISimpleFile::class);
			$folder->expects($this->at(3))
				->method('newFile')
				->with('logo.png')
				->willReturn($newFile);
			$newFile->expects($this->once())
				->method('putContent');
			$this->appData->expects($this->once())
				->method('getFolder')
				->with('images')
				->willReturn($folder);
		}
	}

	public function testGetImageUrl() {
		$this->checkImagick();
		$file = $this->createMock(ISimpleFile::class);
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->withConsecutive(
				['theming', 'cachebuster', '0'],
				['theming', 'logoMime', '']
				)
			->willReturn(0);
		$this->mockGetImage('logo', $file);
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->willReturn('url-to-image');
		$this->assertEquals('url-to-image?v=0', $this->imageManager->getImageUrl('logo', false));
	}

	public function testGetImageUrlDefault() {
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->withConsecutive(
				['theming', 'cachebuster', '0'],
				['theming', 'logoMime', false]
			)
			->willReturnOnConsecutiveCalls(0, false);
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'logo/logo.png')
			->willReturn('logo/logo.png');
		$this->assertEquals('logo/logo.png?v=0', $this->imageManager->getImageUrl('logo'));
	}

	public function testGetImageUrlAbsolute() {
		$this->checkImagick();
		$file = $this->createMock(ISimpleFile::class);
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->withConsecutive(
				['theming', 'cachebuster', '0'],
				['theming', 'logoMime', '']
			)
			->willReturn(0);
		$this->mockGetImage('logo', $file);
		$this->urlGenerator->expects($this->at(0))
			->method('getBaseUrl')
			->willReturn('baseurl');
		$this->urlGenerator->expects($this->at(1))
			->method('getAbsoluteUrl')
			->willReturn('url-to-image-absolute?v=0');
		$this->urlGenerator->expects($this->at(2))
			->method('getAbsoluteUrl')
			->willReturn('url-to-image-absolute?v=0');
		$this->assertEquals('url-to-image-absolute?v=0', $this->imageManager->getImageUrlAbsolute('logo', false));
	}

	public function testGetImage() {
		$this->checkImagick();
		$this->config->expects($this->once())
			->method('getAppValue')->with('theming', 'logoMime', false)
			->willReturn('png');
		$file = $this->createMock(ISimpleFile::class);
		$this->mockGetImage('logo', $file);
		$this->assertEquals($file, $this->imageManager->getImage('logo', false));
	}


	public function testGetImageUnset() {
		$this->expectException(\OCP\Files\NotFoundException::class);

		$this->config->expects($this->once())
			->method('getAppValue')->with('theming', 'logoMime', false)
			->willReturn(false);
		$this->imageManager->getImage('logo');
	}

	public function testGetCacheFolder() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('theming', 'cachebuster', '0')
			->willReturn('0');
		$this->appData->expects($this->at(0))
			->method('getFolder')
			->with('0')
			->willReturn($folder);
		$this->assertEquals($folder, $this->imageManager->getCacheFolder());
	}
	public function testGetCacheFolderCreate() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->config->expects($this->exactly(2))
			->method('getAppValue')
			->with('theming', 'cachebuster', '0')
			->willReturn('0');
		$this->appData->expects($this->at(0))
			->method('getFolder')
			->willThrowException(new NotFoundException());
		$this->appData->expects($this->at(1))
			->method('newFolder')
			->with('0')
			->willReturn($folder);
		$this->appData->expects($this->at(2))
			->method('getFolder')
			->with('0')
			->willReturn($folder);
		$this->appData->expects($this->once())
			->method('getDirectoryListing')
			->willReturn([]);
		$this->assertEquals($folder, $this->imageManager->getCacheFolder());
	}

	public function testGetCachedImage() {
		$expected = $this->createMock(ISimpleFile::class);
		$folder = $this->setupCacheFolder();
		$folder->expects($this->once())
			->method('getFile')
			->with('filename')
			->willReturn($expected);
		$this->assertEquals($expected, $this->imageManager->getCachedImage('filename'));
	}


	public function testGetCachedImageNotFound() {
		$this->expectException(\OCP\Files\NotFoundException::class);

		$folder = $this->setupCacheFolder();
		$folder->expects($this->once())
			->method('getFile')
			->with('filename')
			->will($this->throwException(new \OCP\Files\NotFoundException()));
		$image = $this->imageManager->getCachedImage('filename');
	}

	public function testSetCachedImage() {
		$folder = $this->setupCacheFolder();
		$file = $this->createMock(ISimpleFile::class);
		$folder->expects($this->once())
			->method('fileExists')
			->with('filename')
			->willReturn(true);
		$folder->expects($this->once())
			->method('getFile')
			->with('filename')
			->willReturn($file);
		$file->expects($this->once())
			->method('putContent')
			->with('filecontent');
		$this->assertEquals($file, $this->imageManager->setCachedImage('filename', 'filecontent'));
	}

	public function testSetCachedImageCreate() {
		$folder = $this->setupCacheFolder();
		$file = $this->createMock(ISimpleFile::class);
		$folder->expects($this->once())
			->method('fileExists')
			->with('filename')
			->willReturn(false);
		$folder->expects($this->once())
			->method('newFile')
			->with('filename')
			->willReturn($file);
		$file->expects($this->once())
			->method('putContent')
			->with('filecontent');
		$this->assertEquals($file, $this->imageManager->setCachedImage('filename', 'filecontent'));
	}

	private function setupCacheFolder() {
		$folder = $this->createMock(ISimpleFolder::class);
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('theming', 'cachebuster', '0')
			->willReturn('0');
		$this->appData->expects($this->at(0))
			->method('getFolder')
			->with('0')
			->willReturn($folder);
		return $folder;
	}

	public function testCleanup() {
		$folders = [
			$this->createMock(ISimpleFolder::class),
			$this->createMock(ISimpleFolder::class),
			$this->createMock(ISimpleFolder::class)
		];
		foreach ($folders as $index => $folder) {
			$folder->expects($this->any())
				->method('getName')
				->willReturn($index);
		}
		$folders[0]->expects($this->once())->method('delete');
		$folders[1]->expects($this->once())->method('delete');
		$folders[2]->expects($this->never())->method('delete');
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('theming','cachebuster','0')
			->willReturn('2');
		$this->appData->expects($this->once())
			->method('getDirectoryListing')
			->willReturn($folders);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('2')
			->willReturn($folders[2]);
		$this->imageManager->cleanup();
	}


	public function dataUpdateImage() {
		return [
			['background', __DIR__ . '/../../../tests/data/testimage.png', true, true],
			['background', __DIR__ . '/../../../tests/data/testimage.png', false, true],
			['background', __DIR__ . '/../../../tests/data/testimage.jpg', true, true],
			['logo', __DIR__ . '/../../../tests/data/testimagelarge.svg', true, false],
		];
	}

	/**
	 * @dataProvider dataUpdateImage
	 */
	public function testUpdateImage($key, $tmpFile, $folderExists, $shouldConvert) {
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$oldFile = $this->createMock(ISimpleFile::class);
		$folder->expects($this->any())
			->method('getFile')
			->willReturn($oldFile);
		if ($folderExists) {
			$this->appData
				->expects($this->any())
				->method('getFolder')
				->with('images')
				->willReturn($folder);
		} else {
			$this->appData
				->expects($this->any())
				->method('getFolder')
				->with('images')
				->willThrowException(new NotFoundException());
			$this->appData
				->expects($this->any())
				->method('newFolder')
				->with('images')
				->willReturn($folder);
		}
		$folder->expects($this->once())
			->method('newFile')
			->with($key)
			->willReturn($file);

		if ($shouldConvert) {
			$this->tempManager->expects($this->once())
				->method('getTemporaryFile')
				->willReturn('/tmp/randomtempfile-theming');
		}

		$this->imageManager->updateImage($key, $tmpFile);
	}
}
