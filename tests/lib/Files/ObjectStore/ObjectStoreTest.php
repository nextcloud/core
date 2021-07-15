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

use Test\TestCase;

abstract class ObjectStoreTest extends TestCase {

	/** @var string[] */
	private $cleanup = [];

	/**
	 * @return \OCP\Files\ObjectStore\IObjectStore
	 */
	abstract protected function getInstance();

	protected function cleanupAfter(string $urn) {
		$this->cleanup[] = $urn;
	}

	public function tearDown(): void {
		parent::tearDown();

		$instance = $this->getInstance();
		foreach ($this->cleanup as $urn) {
			$instance->deleteObject($urn);
		}
	}

	protected function stringToStream($data) {
		$stream = fopen('php://temp', 'w+');
		fwrite($stream, $data);
		rewind($stream);
		return $stream;
	}

	public function testWriteRead() {
		$stream = $this->stringToStream('foobar');

		$instance = $this->getInstance();

		$instance->writeObject('1', $stream);

		$result = $instance->readObject('1');
		$instance->deleteObject('1');

		$this->assertEquals('foobar', stream_get_contents($result));
	}

	public function testDelete() {
		$stream = $this->stringToStream('foobar');

		$instance = $this->getInstance();

		$instance->writeObject('2', $stream);

		$instance->deleteObject('2');

		try {
			// to to read to verify that the object no longer exists
			$instance->readObject('2');
			$this->fail();
		} catch (\Exception $e) {
			// dummy assert to keep phpunit happy
			$this->assertEquals(1, 1);
		}
	}

	public function testReadNonExisting() {
		$instance = $this->getInstance();

		try {
			$instance->readObject('non-existing');
			$this->fail();
		} catch (\Exception $e) {
			// dummy assert to keep phpunit happy
			$this->assertEquals(1, 1);
		}
	}

	public function testDeleteNonExisting() {
		$instance = $this->getInstance();

		try {
			$instance->deleteObject('non-existing');
			$this->fail();
		} catch (\Exception $e) {
			// dummy assert to keep phpunit happy
			$this->assertEquals(1, 1);
		}
	}

	public function testExists() {
		$stream = $this->stringToStream('foobar');

		$instance = $this->getInstance();
		$this->assertFalse($instance->objectExists('2'));

		$instance->writeObject('2', $stream);

		$this->assertTrue($instance->objectExists('2'));

		$instance->deleteObject('2');

		$this->assertFalse($instance->objectExists('2'));
	}

	public function testCopy() {
		$this->cleanupAfter('source');
		$this->cleanupAfter('target');

		$stream = $this->stringToStream('foobar');

		$instance = $this->getInstance();

		$instance->writeObject('source', $stream);

		$this->assertFalse($instance->objectExists('target'));

		$instance->copyObject('source', 'target');

		$this->assertTrue($instance->objectExists('target'));

		$this->assertEquals('foobar', stream_get_contents($instance->readObject('target')));
	}
}
