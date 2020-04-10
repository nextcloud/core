<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
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

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use OCP\Files\ObjectStore\IObjectStore;

class Azure implements IObjectStore {
	/** @var string */
	private $containerName;
	/** @var string */
	private $accountName;
	/** @var string */
	private $accountKey;
	/** @var BlobRestProxy|null */
	private $blobClient = null;
	/** @var string|null */
	private $endpoint = null;
	/** @var bool  */
	private $autoCreate = false;

	/**
	 * @param array $parameters
	 */
	public function __construct($parameters) {
		$this->containerName = $parameters['container'];
		$this->accountName = $parameters['account_name'];
		$this->accountKey = $parameters['account_key'];
		if (isset($parameters['endpoint'])) {
			$this->endpoint = $parameters['endpoint'];
		}
		if (isset($parameters['autocreate'])) {
			$this->autoCreate = $parameters['autocreate'];
		}
	}

	/**
	 * @return BlobRestProxy
	 */
	private function getBlobClient() {
		if (!$this->blobClient) {
			$protocol = $this->endpoint ? substr($this->endpoint, 0, strpos($this->endpoint, ':')) : 'https';
			$connectionString = "DefaultEndpointsProtocol=" . $protocol . ";AccountName=" . $this->accountName . ";AccountKey=" . $this->accountKey;
			if ($this->endpoint) {
				$connectionString .= ';BlobEndpoint=' . $this->endpoint;
			}
			$this->blobClient = BlobRestProxy::createBlobService($connectionString);

			if ($this->autoCreate) {
				try {
					$this->blobClient->createContainer($this->containerName);
				} catch (ServiceException $e) {
					if ($e->getCode() === 409) {
						// already exists
					} else {
						throw $e;
					}
				}
			}
		}
		return $this->blobClient;
	}

	/**
	 * @return string the container or bucket name where objects are stored
	 */
	public function getStorageId() {
		return 'azure::blob::' . $this->containerName;
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws \Exception when something goes wrong, message will be logged
	 */
	public function readObject($urn) {
		$blob = $this->getBlobClient()->getBlob($this->containerName, $urn);
		return $blob->getContentStream();
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws \Exception when something goes wrong, message will be logged
	 */
	public function writeObject($urn, $stream) {
		$this->getBlobClient()->createBlockBlob($this->containerName, $urn, $stream);
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return void
	 * @throws \Exception when something goes wrong, message will be logged
	 */
	public function deleteObject($urn) {
		$this->getBlobClient()->deleteBlob($this->containerName, $urn);
	}

	public function objectExists($urn) {
		try {
			$this->getBlobClient()->getBlobMetadata($this->containerName, $urn);
			return true;
		} catch (ServiceException $e) {
			if ($e->getCode() === 404) {
				return false;
			} else {
				throw $e;
			}
		}
	}
}
