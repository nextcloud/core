<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Florent <florent@coppint.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 */
namespace OC\Files\ObjectStore;

use Aws\S3\Exception\S3MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\ObjectUploader;
use Aws\S3\S3Client;
use Icewind\Streams\CallbackWrapper;
use OC\Files\Stream\SeekableHttpStream;

trait S3ObjectTrait {
	/**
	 * Returns the connection.
	 *
	 * @return S3Client connected client
	 *
	 * @throws \Exception if connection could not be made
	 */
	abstract protected function getConnection();

	/* compute configured encryption headers for put operations */
	abstract protected function getSseKmsPutParameters();

	/* compute configured encryption headers for get operations */
	abstract protected function getSseKmsGetParameters();

	/**
	 * @param string $urn the unified resource name used to identify the object
	 *
	 * @return resource stream with the read data
	 *
	 * @throws \Exception when something goes wrong, message will be logged
	 *
	 * @since 7.0.0
	 */
	public function readObject($urn) {
		return SeekableHttpStream::open(function ($range) use ($urn) {
			$s3params = [
				'Bucket' => $this->bucket,
				'Key' => $urn,
				'Range' => 'bytes='.$range,
			] + $this->getSseKmsGetParameters();
			$command = $this->getConnection()->getCommand('GetObject', $s3params);
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
	 * @param string|null $mimetype the mimetype to set for the remove object @since 22.0.0
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	public function writeObject($urn, $stream, string $mimetype = null) {
		$count = 0;
		$countStream = CallbackWrapper::wrap($stream, function ($read) use (&$count) {
			$count += $read;
		});

		$s3params = [
			'bucket' => $this->bucket,
			'key' => $urn,
			'part_size' => $this->uploadPartSize,
			'params' => [
				'ContentType' => $mimetype
			] + $this->getSseKmsPutParameters(),
		];
		$uploader = new MultipartUploader($this->getConnection(), $countStream, $s3params);

		try {
			$uploader->upload();
		} catch (S3MultipartUploadException $e) {
			// This is an empty file so just touch it then
			if ($count === 0 && feof($countStream)) {
				$s3params = [
					'params' => $this->getSseKmsPutParameters(),
				];
				$uploader = new ObjectUploader($this->getConnection(), $this->bucket, $urn, '', 'private', $s3params);
				$uploader->upload();
			} else {
				throw $e;
			}
		} finally {
			// this handles [S3] fclose(): supplied resource is not a valid stream resource #23373
			// see https://stackoverflow.com/questions/11247507/fclose-18-is-not-a-valid-stream-resource/11247555
			// which also recommends the solution
			if (is_resource($countStream)) {
				fclose($countStream);
			}
		}
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 *
	 * @return void
	 *
	 * @throws \Exception when something goes wrong, message will be logged
	 *
	 * @since 7.0.0
	 */
	public function deleteObject($urn) {
		$this->getConnection()->deleteObject([
			'Bucket' => $this->bucket,
			'Key' => $urn,
		]);
	}

	public function objectExists($urn) {
		return $this->getConnection()->doesObjectExist($this->bucket, $urn);
	}
	
	/**
	 * S3 copy command with SSE KMS key handling.
	 */
	public function copyObject($from, $to) {
		$this->getConnection()->copy($this->getBucket(), $from, $this->getBucket(), $to, 'private', [
			'params' => $this->getSseKmsPutParameters(),
		]);
	}
}
