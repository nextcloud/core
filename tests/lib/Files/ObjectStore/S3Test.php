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
	function writeObject($urn, $stream) {
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
	protected function getInstance() {
		$config = \OC::$server->getConfig()->getSystemValue('objectstore');
		if (!is_array($config) || $config['class'] !== 'OC\\Files\\ObjectStore\\S3') {
			$this->markTestSkipped('objectstore not configured for s3');
		}

		return new S3($config['arguments']);
	}

	public function testUploadNonSeekable() {
		$config = \OC::$server->getConfig()->getSystemValue('objectstore');
		if (!is_array($config) || $config['class'] !== 'OC\\Files\\ObjectStore\\S3') {
			$this->markTestSkipped('objectstore not configured for s3');
		}

		$s3 = $this->getInstance();

		$s3->writeObject('multiparttest', NonSeekableStream::wrap(fopen(__FILE__, 'r')));

		$result = $s3->readObject('multiparttest');

		$this->assertEquals(file_get_contents(__FILE__), stream_get_contents($result));
	}

	public function testSeek() {
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
}
