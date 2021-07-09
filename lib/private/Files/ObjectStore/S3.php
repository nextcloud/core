<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Files\ObjectStore;

use Aws\Result;
use Exception;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IObjectStoreMultiPartUpload;

class S3 implements IObjectStore, IObjectStoreMultiPartUpload {
	use S3ConnectionTrait;
	use S3ObjectTrait;

	public function __construct($parameters) {
		$this->parseParams($parameters);
	}

	/**
	 * @return string the container or bucket name where objects are stored
	 * @since 7.0.0
	 */
	public function getStorageId() {
		return $this->id;
	}

	public function initiateMultipartUpload(string $urn): string {
		$upload = $this->getConnection()->createMultipartUpload([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
		$uploadId = $upload->get('UploadId');
		if ($uploadId === null) {
			throw new Exception('No upload id returned');
		}
		return (string)$uploadId;
	}

	public function uploadMultipartPart(string $urn, string $uploadId, int $partId, $stream, $size): Result {
		return $this->getConnection()->uploadPart([
			'Body' => $stream,
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'ContentLength' => $size,
			'PartNumber' => $partId,
			'UploadId' => $uploadId,
		]);
	}

	public function completeMultipartUpload(string $urn, string $uploadId, array $result): int {
		$this->getConnection()->completeMultipartUpload([
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'UploadId' => $uploadId,
			'MultipartUpload' => ['Parts' => $result],
		]);
		$stat = $this->getConnection()->headObject([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
		return (int)$stat->get('ContentLength');
	}

	public function abortMultipartUpload($urn, $uploadId): void {
		$this->getConnection()->abortMultipartUpload([
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'UploadId' => $uploadId
		]);
	}
}
