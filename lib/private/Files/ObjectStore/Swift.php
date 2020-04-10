<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Files\ObjectStore;

use function GuzzleHttp\Psr7\stream_for;
use Icewind\Streams\RetryWrapper;
use OCP\Files\NotFoundException;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\StorageAuthException;
use OpenStack\Common\Error\BadResponseError;

class Swift implements IObjectStore {
	/**
	 * @var array
	 */
	private $params;

	/** @var SwiftFactory */
	private $swiftFactory;

	public function __construct($params, SwiftFactory $connectionFactory = null) {
		$this->swiftFactory = $connectionFactory ?: new SwiftFactory(
			\OC::$server->getMemCacheFactory()->createDistributed('swift::'),
			$params,
			\OC::$server->getLogger()
		);
		$this->params = $params;
	}

	/**
	 * @return \OpenStack\ObjectStore\v1\Models\Container
	 * @throws StorageAuthException
	 * @throws \OCP\Files\StorageNotAvailableException
	 */
	private function getContainer() {
		return $this->swiftFactory->getContainer();
	}

	/**
	 * @return string the container name where objects are stored
	 */
	public function getStorageId() {
		if (isset($this->params['bucket'])) {
			return $this->params['bucket'];
		}

		return $this->params['container'];
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws \Exception from openstack lib when something goes wrong
	 */
	public function writeObject($urn, $stream) {
		$tmpFile = \OC::$server->getTempManager()->getTemporaryFile('swiftwrite');
		file_put_contents($tmpFile, $stream);
		$handle = fopen($tmpFile, 'rb');

		$this->getContainer()->createObject([
			'name' => $urn,
			'stream' => stream_for($handle)
		]);
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws \Exception from openstack lib when something goes wrong
	 * @throws NotFoundException if file does not exist
	 */
	public function readObject($urn) {
		try {
			$object = $this->getContainer()->getObject($urn);

			// we need to keep a reference to objectContent or
			// the stream will be closed before we can do anything with it
			$objectContent = $object->download();
		} catch (BadResponseError $e) {
			if ($e->getResponse()->getStatusCode() === 404) {
				throw new NotFoundException("object $urn not found in object store");
			} else {
				throw $e;
			}
		}
		$objectContent->rewind();

		$stream = $objectContent->detach();
		// save the object content in the context of the stream to prevent it being gc'd until the stream is closed
		stream_context_set_option($stream, 'swift', 'content', $objectContent);

		return RetryWrapper::wrap($stream);
	}

	/**
	 * @param string $urn Unified Resource Name
	 * @return void
	 * @throws \Exception from openstack lib when something goes wrong
	 */
	public function deleteObject($urn) {
		$this->getContainer()->getObject($urn)->delete();
	}

	/**
	 * @return void
	 * @throws \Exception from openstack lib when something goes wrong
	 */
	public function deleteContainer() {
		$this->getContainer()->delete();
	}

	public function objectExists($urn) {
		return $this->getContainer()->objectExists($urn);
	}
}
