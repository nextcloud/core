<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
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

namespace Test\Files\ObjectStore;

use Icewind\Streams\Wrapper;
use OC\Files\ObjectStore\S3;

class MultiPartUploadS3 extends S3 {
	public function writeObject($urn, $stream, string $mimetype = null) {
		$this->getConnection()->upload($this->bucket, $urn, $stream, 'private', [
			'mup_threshold' => 1,
		]);
	}
}

class NonSeekableStream extends Wrapper {
	public static function wrap($source) {
		$context = stream_context_create([
			'nonseek' => [
				'source' => $source,
			],
		]);
		return Wrapper::wrapSource($source, $context, 'nonseek', self::class);
	}

	public function dir_opendir($path, $options) {
		return false;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->loadContext('nonseek');
		return true;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		return false;
	}
}

/**
 * @group PRIMARY-s3
 */
class S3Test extends ObjectStoreTest {
	public function setUp(): void {
		parent::setUp();
		$s3 = $this->getInstance();
		$s3->deleteObject('multiparttest');
	}

	protected function getInstance() {
		$config = \OC::$server->getConfig()->getSystemValue('objectstore');
		if (!is_array($config) || $config['class'] !== S3::class) {
			$this->markTestSkipped('objectstore not configured for s3');
		}

		return new S3($config['arguments']);
	}

	public function testUploadNonSeekable() {
		$this->cleanupAfter('multiparttest');

		$s3 = $this->getInstance();

		$s3->writeObject('multiparttest', NonSeekableStream::wrap(fopen(__FILE__, 'r')));

		$result = $s3->readObject('multiparttest');

		$this->assertEquals(file_get_contents(__FILE__), stream_get_contents($result));
	}

	public function testSeek() {
		$this->cleanupAfter('seek');

		$data = file_get_contents(__FILE__);

		$instance = $this->getInstance();
		$instance->writeObject('seek', $this->stringToStream($data));

		$read = $instance->readObject('seek');
		$this->assertEquals(substr($data, 0, 100), fread($read, 100));

		fseek($read, 10);
		$this->assertEquals(substr($data, 10, 100), fread($read, 100));

		fseek($read, 100, SEEK_CUR);
		$this->assertEquals(substr($data, 210, 100), fread($read, 100));
	}

	public function assertNoUpload($objectUrn) {
		$s3 = $this->getInstance();
		$s3client = $s3->getConnection();
		$uploads = $s3client->listMultipartUploads([
			'Bucket' => $s3->getBucket(),
			'Prefix' => $objectUrn,
		]);
		$this->assertArrayNotHasKey('Uploads', $uploads);
	}

	public function testEmptyUpload() {
		$s3 = $this->getInstance();

		$emptyStream = fopen("php://memory", "r");
		fwrite($emptyStream, null);

		$s3->writeObject('emptystream', $emptyStream);

		$this->assertNoUpload('emptystream');
		$this->assertTrue($s3->objectExists('emptystream'));

		$thrown = false;
		try {
			self::assertFalse($s3->readObject('emptystream'));
		} catch (\Exception $e) {
			// An exception is expected here since 0 byte files are wrapped
			// to be read from an empty memory stream in the ObjectStoreStorage
			$thrown = true;
		}
		self::assertTrue($thrown, 'readObject with range requests are not expected to work on empty objects');

		$s3->deleteObject('emptystream');
	}

	/** File size to upload in bytes */
	public function dataFileSizes() {
		return [
			[1000000], [2000000], [5242879], [5242880], [5242881], [10000000]
		];
	}

	/** @dataProvider dataFileSizes */
	public function testFileSizes($size) {
		$this->cleanupAfter('testfilesizes');
		$s3 = $this->getInstance();

		$sourceStream = fopen('php://memory', 'wb+');
		$writeChunkSize = 1024;
		$chunkCount = $size / $writeChunkSize;
		for ($i = 0; $i < $chunkCount; $i++) {
			fwrite($sourceStream, str_repeat('A',
				($i < $chunkCount - 1) ? $writeChunkSize : $size - ($i * $writeChunkSize)
			));
		}
		rewind($sourceStream);
		$s3->writeObject('testfilesizes', $sourceStream);

		$this->assertNoUpload('testfilesizes');
		self::assertTrue($s3->objectExists('testfilesizes'));

		$result = $s3->readObject('testfilesizes');

		// compare first 100 bytes
		self::assertEquals(str_repeat('A', 100), fread($result, 100));

		// compare 100 bytes
		fseek($result, $size - 100);
		self::assertEquals(str_repeat('A', 100), fread($result, 100));

		// end of file reached
		fseek($result, $size);
		self:self::assertTrue(feof($result));

		$this->assertNoUpload('testfilesizes');
	}
}
