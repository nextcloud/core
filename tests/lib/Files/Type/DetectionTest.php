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

namespace Test\Files\Type;

use OC\Files\Type\Detection;
use OCP\ILogger;
use OCP\IURLGenerator;

class DetectionTest extends \Test\TestCase {
	/** @var Detection */
	private $detection;

	protected function setUp(): void {
		parent::setUp();
		$this->detection = new Detection(
			\OC::$server->getURLGenerator(),
			\OC::$server->getLogger(),
			\OC::$SERVERROOT . '/config/',
			\OC::$SERVERROOT . '/resources/config/'
		);
	}

	public function dataDetectPath(): array {
		return [
			['foo.txt', 'text/plain'],
			['foo.png', 'image/png'],
			['foo.bar.png', 'image/png'],
			['.hidden.png', 'image/png'],
			['.hidden.foo.png', 'image/png'],
			['.hidden/foo.png', 'image/png'],
			['.hidden/.hidden.png', 'image/png'],
			['test.jpg/foo.png', 'image/png'],
			['.png', 'application/octet-stream'],
			['..hidden', 'application/octet-stream'],
			['foo', 'application/octet-stream'],
			['', 'application/octet-stream'],
			['foo.png.ocTransferId123456789.part', 'image/png'],
			['foo.png.v1234567890', 'image/png'],
		];
	}

	/**
	 * @dataProvider dataDetectPath
	 *
	 * @param string $path
	 * @param string $expected
	 */
	public function testDetectPath(string $path, string $expected): void {
		$this->assertEquals($expected, $this->detection->detectPath($path));
	}

	public function dataDetectContent(): array {
		return [
			['/', 'httpd/unix-directory'],
			//			['/data.tar.gz', 'application/x-gzip'], TODO: fix as it fails hard on php7.4 now
			['/data.zip', 'application/zip'],
			['/testimage.mp3', 'audio/mpeg'],
			['/testimage.png', 'image/png'],
		];
	}

	/**
	 * @dataProvider dataDetectContent
	 *
	 * @param string $path
	 * @param string $expected
	 */
	public function testDetectContent(string $path, string $expected): void {
		$this->assertEquals($expected, $this->detection->detectContent(\OC::$SERVERROOT . '/tests/data' . $path));
	}

	public function dataDetect(): array {
		return [
			['/', 'httpd/unix-directory'],
			['/data.tar.gz', 'application/x-gzip'],
			['/data.zip', 'application/zip'],
			['/testimagelarge.svg', 'image/svg+xml'],
			['/testimage.png', 'image/png'],
		];
	}

	/**
	 * @dataProvider dataDetect
	 *
	 * @param string $path
	 * @param string $expected
	 */
	public function testDetect(string $path, string $expected): void {
		$this->assertEquals($expected, $this->detection->detect(\OC::$SERVERROOT . '/tests/data' . $path));
	}

	public function testDetectString(): void {
		$result = $this->detection->detectString('/data/data.tar.gz');
		$expected = 'text/plain';
		$this->assertEquals($expected, $result);
	}

	public function dataGetSecureMimeType(): array {
		return [
			['image/svg+xml', 'text/plain'],
			['image/png', 'image/png'],
		];
	}

	/**
	 * @dataProvider dataGetSecureMimeType
	 *
	 * @param string $mimeType
	 * @param string $expected
	 */
	public function testGetSecureMimeType(string $mimeType, string $expected): void {
		$this->assertEquals($expected, $this->detection->getSecureMimeType($mimeType));
	}

	public function testMimeTypeIcon() {
		if (!class_exists('org\\bovigo\\vfs\\vfsStream')) {
			$this->markTestSkipped('Package vfsStream not installed');
		}
		$confDir = \org\bovigo\vfs\vfsStream::setup();
		$mimetypealiases_dist = \org\bovigo\vfs\vfsStream::newFile('mimetypealiases.dist.json')->at($confDir);

		//Empty alias file
		$mimetypealiases_dist->setContent(json_encode([], JSON_FORCE_OBJECT));


		/*
		 * Test dir mimetype
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		/** @var ILogger $logger */
		$logger = $this->createMock(ILogger::class);

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/folder.png'))
			->willReturn('folder.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('dir');
		$this->assertEquals('folder.svg', $mimeType);


		/*
		 * Test dir-shareed mimetype
		 */
		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/folder-shared.png'))
			->willReturn('folder-shared.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('dir-shared');
		$this->assertEquals('folder-shared.svg', $mimeType);


		/*
		 * Test dir external
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/folder-external.png'))
			->willReturn('folder-external.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('dir-external');
		$this->assertEquals('folder-external.svg', $mimeType);


		/*
		 * Test complete mimetype
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/my-type.png'))
			->willReturn('my-type.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('my-type');
		$this->assertEquals('my-type.svg', $mimeType);


		/*
		 * Test subtype
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->exactly(2))
			->method('imagePath')
			->withConsecutive(
				[$this->equalTo('core'), $this->equalTo('filetypes/my-type.png')],
				[$this->equalTo('core'), $this->equalTo('filetypes/my.png')]
			)
			->willReturnCallback(
				function ($appName, $file) {
					if ($file === 'filetypes/my.png') {
						return 'my.svg';
					}
					throw new \RuntimeException();
				}
			);

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('my-type');
		$this->assertEquals('my.svg', $mimeType);


		/*
		 * Test default mimetype
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->exactly(3))
			->method('imagePath')
			->withConsecutive(
				[$this->equalTo('core'), $this->equalTo('filetypes/foo-bar.png')],
				[$this->equalTo('core'), $this->equalTo('filetypes/foo.png')],
				[$this->equalTo('core'), $this->equalTo('filetypes/file.png')]
			)
			->willReturnCallback(
				function ($appName, $file) {
					if ($file === 'filetypes/file.png') {
						return 'file.svg';
					}
					throw new \RuntimeException();
				}
			);

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('foo-bar');
		$this->assertEquals('file.svg', $mimeType);

		/*
		 * Test chaching
		 */

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/foo-bar.png'))
			->willReturn('foo-bar.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('foo-bar');
		$this->assertEquals('foo-bar.svg', $mimeType);
		$mimeType = $detection->mimeTypeIcon('foo-bar');
		$this->assertEquals('foo-bar.svg', $mimeType);



		/*
		 * Test aliases
		 */

		//Put alias
		$mimetypealiases_dist->setContent(json_encode(['foo' => 'foobar/baz'], JSON_FORCE_OBJECT));

		//Mock UrlGenerator
		$urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()
			->getMock();

		//Only call the url generator once
		$urlGenerator->expects($this->once())
			->method('imagePath')
			->with($this->equalTo('core'), $this->equalTo('filetypes/foobar-baz.png'))
			->willReturn('foobar-baz.svg');

		$detection = new Detection($urlGenerator, $logger, $confDir->url(), $confDir->url());
		$mimeType = $detection->mimeTypeIcon('foo');
		$this->assertEquals('foobar-baz.svg', $mimeType);
	}
}
