<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Files\ObjectStore;

use Aws\S3\Exception\S3MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use Icewind\Streams\CallbackWrapper;
use OC\Files\Stream\SeekableHttpStream;

const S3_UPLOAD_PART_SIZE = 524288000; // 500MB

trait S3ObjectTrait {
	/**
	 * Returns the connection
	 *
	 * @return S3Client connected client
	 * @throws \Exception if connection could not be made
	 */
	abstract protected function getConnection();

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return resource stream with the read data
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function readObject($urn) {
		return SeekableHttpStream::open(function ($range) use ($urn) {
			$command = $this->getConnection()->getCommand('GetObject', [
				'Bucket' => $this->bucket,
				'Key' => $urn,
				'Range' => 'bytes=' . $range,
			]);
			$request = \Aws\serialize($command);
			$headers = [];
			foreach ($request->getHeaders() as $key => $values) {
				foreach ($values as $value) {
					$headers[] = "$key: $value";
				}
			}
			$opts = [
				'http' => [
					'protocol_version' => 1.1,
					'header' => $headers,
				],
			];

			$context = stream_context_create($opts);
			return fopen($request->getUri(), 'r', false, $context);
		});
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function writeObject($urn, $stream) {
		$count = 0;
		$countStream = CallbackWrapper::wrap($stream, function ($read) use (&$count) {
			$count += $read;
		});

		$uploader = new MultipartUploader($this->getConnection(), $countStream, [
			'bucket' => $this->bucket,
			'key' => $urn,
			'part_size' => S3_UPLOAD_PART_SIZE,
		]);

		try {
			$uploader->upload();
		} catch (S3MultipartUploadException $e) {
			// This is an empty file so just touch it then
			if ($count === 0 && feof($countStream)) {
				$uploader = new ObjectUploader($this->getConnection(), $this->bucket, $urn, '');
				$uploader->upload();
			} else {
				throw $e;
			}
		}

		fclose($countStream);
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return void
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	function deleteObject($urn) {
		$this->getConnection()->deleteObject([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
	}

	public function objectExists($urn) {
		return $this->getConnection()->doesObjectExist($this->bucket, $urn);
	}
}
