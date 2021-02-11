<?php
/**
 * Copyright (c) 2013 Christopher Schäpers <christopher@schaepers.it>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC;
use OCP\IConfig;

class ImageTest extends \Test\TestCase {
	public static function tearDownAfterClass(): void {
		@unlink(OC::$SERVERROOT.'/tests/data/testimage2.png');
		@unlink(OC::$SERVERROOT.'/tests/data/testimage2.jpg');

		parent::tearDownAfterClass();
	}

	public function testConstructDestruct() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertInstanceOf('\OC_Image', $img);
		$this->assertInstanceOf('\OCP\IImage', $img);
		unset($img);

		$imgcreate = imagecreatefromjpeg(OC::$SERVERROOT.'/tests/data/testimage.jpg');
		$img = new \OC_Image();
		$img->setResource($imgcreate);
		$this->assertInstanceOf('\OC_Image', $img);
		$this->assertInstanceOf('\OCP\IImage', $img);
		unset($img);

		$base64 = base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif'));
		$img = new \OC_Image();
		$img->loadFromBase64($base64);
		$this->assertInstanceOf('\OC_Image', $img);
		$this->assertInstanceOf('\OCP\IImage', $img);
		unset($img);

		$img = new \OC_Image();
		$this->assertInstanceOf('\OC_Image', $img);
		$this->assertInstanceOf('\OCP\IImage', $img);
		unset($img);
	}

	public function testValid() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertTrue($img->valid());

		$text = base64_encode("Lorem ipsum dolor sir amet …");
		$img = new \OC_Image();
		$img->loadFromBase64($text);
		$this->assertFalse($img->valid());

		$img = new \OC_Image();
		$this->assertFalse($img->valid());
	}

	public function testMimeType() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertEquals('image/png', $img->mimeType());

		$img = new \OC_Image();
		$this->assertEquals('', $img->mimeType());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertEquals('image/jpeg', $img->mimeType());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertEquals('image/gif', $img->mimeType());
	}

	public function testWidth() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertEquals(128, $img->width());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertEquals(1680, $img->width());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertEquals(64, $img->width());

		$img = new \OC_Image();
		$this->assertEquals(-1, $img->width());
	}

	public function testHeight() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertEquals(128, $img->height());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertEquals(1050, $img->height());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertEquals(64, $img->height());

		$img = new \OC_Image();
		$this->assertEquals(-1, $img->height());
	}

	public function testSave() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$img->resize(16);
		$img->save(OC::$SERVERROOT.'/tests/data/testimage2.png');
		$this->assertEquals(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage2.png'), $img->data());

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.jpg');
		$img->resize(128);
		$img->save(OC::$SERVERROOT.'/tests/data/testimage2.jpg');
		$this->assertEquals(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage2.jpg'), $img->data());
	}

	public function testData() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$raw = imagecreatefromstring(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.png'));
		// Preserve transparency
		imagealphablending($raw, true);
		imagesavealpha($raw, true);
		ob_start();
		imagepng($raw);
		$expected = ob_get_clean();
		$this->assertEquals($expected, $img->data());

		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('getAppValue')
			->with('preview', 'jpeg_quality', 90)
			->willReturn(null);
		$img = new \OC_Image(null, null, $config);
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.jpg');
		$raw = imagecreatefromstring(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		ob_start();
		imagejpeg($raw);
		$expected = ob_get_clean();
		$this->assertEquals($expected, $img->data());

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.gif');
		$raw = imagecreatefromstring(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif'));
		ob_start();
		imagegif($raw);
		$expected = ob_get_clean();
		$this->assertEquals($expected, $img->data());
	}

	public function testDataNoResource() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$this->assertNull($img->data());
	}

	/**
	 * @depends testData
	 */
	public function testToString() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$expected = base64_encode($img->data());
		$this->assertEquals($expected, (string)$img);

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$expected = base64_encode($img->data());
		$this->assertEquals($expected, (string)$img);

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.gif');
		$expected = base64_encode($img->data());
		$this->assertEquals($expected, (string)$img);
	}

	public function testResize() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertTrue($img->resize(32));
		$this->assertEquals(32, $img->width());
		$this->assertEquals(32, $img->height());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertTrue($img->resize(840));
		$this->assertEquals(840, $img->width());
		$this->assertEquals(525, $img->height());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertTrue($img->resize(100));
		$this->assertEquals(100, $img->width());
		$this->assertEquals(100, $img->height());
	}

	public function testPreciseResize() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertTrue($img->preciseResize(128, 512));
		$this->assertEquals(128, $img->width());
		$this->assertEquals(512, $img->height());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertTrue($img->preciseResize(64, 840));
		$this->assertEquals(64, $img->width());
		$this->assertEquals(840, $img->height());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertTrue($img->preciseResize(1000, 1337));
		$this->assertEquals(1000, $img->width());
		$this->assertEquals(1337, $img->height());
	}

	public function testCenterCrop() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$img->centerCrop();
		$this->assertEquals(128, $img->width());
		$this->assertEquals(128, $img->height());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$img->centerCrop();
		$this->assertEquals(1050, $img->width());
		$this->assertEquals(1050, $img->height());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$img->centerCrop(512);
		$this->assertEquals(512, $img->width());
		$this->assertEquals(512, $img->height());
	}

	public function testCrop() {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$this->assertTrue($img->crop(0, 0, 50, 20));
		$this->assertEquals(50, $img->width());
		$this->assertEquals(20, $img->height());

		$img = new \OC_Image();
		$img->loadFromData(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.jpg'));
		$this->assertTrue($img->crop(500, 700, 550, 300));
		$this->assertEquals(550, $img->width());
		$this->assertEquals(300, $img->height());

		$img = new \OC_Image();
		$img->loadFromBase64(base64_encode(file_get_contents(OC::$SERVERROOT.'/tests/data/testimage.gif')));
		$this->assertTrue($img->crop(10, 10, 15, 15));
		$this->assertEquals(15, $img->width());
		$this->assertEquals(15, $img->height());
	}

	public static function sampleProvider() {
		return [
			['testimage.png', [200, 100], [100, 100]],
			['testimage.jpg', [840, 840], [840, 525]],
			['testimage.gif', [200, 250], [200, 200]]
		];
	}

	/**
	 * @dataProvider sampleProvider
	 *
	 * @param string $filename
	 * @param int[] $asked
	 * @param int[] $expected
	 */
	public function testFitIn($filename, $asked, $expected) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT . '/tests/data/' . $filename);
		$this->assertTrue($img->fitIn($asked[0], $asked[1]));
		$this->assertEquals($expected[0], $img->width());
		$this->assertEquals($expected[1], $img->height());
	}

	public static function sampleFilenamesProvider() {
		return [
			['testimage.png'],
			['testimage.jpg'],
			['testimage.gif']
		];
	}

	/**
	 * Image should not be resized if it's already smaller than what is required
	 *
	 * @dataProvider sampleFilenamesProvider
	 *
	 * @param string $filename
	 */
	public function testScaleDownToFitWhenSmallerAlready($filename) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/' . $filename);
		$currentWidth = $img->width();
		$currentHeight = $img->height();
		// We pick something larger than the image we want to scale down
		$this->assertFalse($img->scaleDownToFit(4000, 4000));
		// The dimensions of the image should not have changed since it's smaller already
		$resizedWidth = $img->width();
		$resizedHeight = $img->height();
		$this->assertEquals(
			$currentWidth, $img->width(), "currentWidth $currentWidth resizedWidth $resizedWidth \n"
		);
		$this->assertEquals(
			$currentHeight, $img->height(),
			"currentHeight $currentHeight resizedHeight $resizedHeight \n"
		);
	}

	public static function largeSampleProvider() {
		return [
			['testimage.png', [200, 100], [100, 100]],
			['testimage.jpg', [840, 840], [840, 525]],
		];
	}

	/**
	 * @dataProvider largeSampleProvider
	 *
	 * @param string $filename
	 * @param int[] $asked
	 * @param int[] $expected
	 */
	public function testScaleDownWhenBigger($filename, $asked, $expected) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/' . $filename);
		//$this->assertTrue($img->scaleDownToFit($asked[0], $asked[1]));
		$img->scaleDownToFit($asked[0], $asked[1]);
		$this->assertEquals($expected[0], $img->width());
		$this->assertEquals($expected[1], $img->height());
	}

	public function convertDataProvider() {
		return [
			[ 'image/gif'],
			[ 'image/jpeg'],
			[ 'image/png'],
		];
	}

	/**
	 * @dataProvider convertDataProvider
	 */
	public function testConvert($mimeType) {
		if (PHP_MAJOR_VERSION > 7) {
			$this->markTestSkipped('Only run on php7');
		}

		$img = new \OC_Image();
		$img->loadFromFile(OC::$SERVERROOT.'/tests/data/testimage.png');
		$tempFile = tempnam(sys_get_temp_dir(), 'img-test');

		$img->save($tempFile, $mimeType);
		$this->assertEquals($mimeType, image_type_to_mime_type(exif_imagetype($tempFile)));
	}
}
