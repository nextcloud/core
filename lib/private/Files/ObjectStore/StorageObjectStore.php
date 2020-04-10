<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Files\ObjectStore;

use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\Storage\IStorage;

/**
 * Object store that wraps a storage backend, mostly for testing purposes
 */
class StorageObjectStore implements IObjectStore {
	/** @var IStorage */
	private $storage;

	/**
	 * @param IStorage $storage
	 */
	public function __construct(IStorage $storage) {
		$this->storage = $storage;
	}

	/**
	 * @return string the container or bucket name where objects are stored
	 * @since 7.0.0
	 */
	function getStorageId() {
		$this->storage->getId();
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function readObject($urn) {
		$handle = $this->storage->fopen($urn, 'r');
		if ($handle) {
			return $handle;
		} else {
			throw new \Exception();
		}
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function writeObject($urn, $stream) {
		$handle = $this->storage->fopen($urn, 'w');
		if ($handle) {
			stream_copy_to_stream($stream, $handle);
			fclose($handle);
		} else {
			throw new \Exception();
		}
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return void
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function deleteObject($urn) {
		$this->storage->unlink($urn);
	}

	public function objectExists($urn) {
		return $this->storage->file_exists($urn);
	}
}
