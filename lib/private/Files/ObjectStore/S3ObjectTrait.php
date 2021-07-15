<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Florent <florent@coppint.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Files\ObjectStore;

use Aws\S3\Exception\S3MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Utils;
use OC\Files\Stream\SeekableHttpStream;
use GuzzleHttp\Psr7;
use Psr\Http\Message\StreamInterface;

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
	public function readObject($urn) {
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

			if ($this->getProxy()) {
				$opts['http']['proxy'] = $this->getProxy();
				$opts['http']['request_fulluri'] = true;
			}

			$context = stream_context_create($opts);
			return fopen($request->getUri(), 'r', false, $context);
		});
	}

	/**
	 * Single object put helper
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource|StreamInterface $stream stream with the data to write
	 * @param string|null $mimetype the mimetype to set for the remove object @since 22.0.0
	 * @throws \Exception when something goes wrong, message will be logged
	 */
	protected function writeSingle(string $urn, $stream, string $mimetype = null) {
		$this->getConnection()->putObject([
			'Bucket' => $this->bucket,
			'Key' => $urn,
			'Body' => $stream,
			'ACL' => 'private',
			'ContentType' => $mimetype,
		]);
	}


	/**
	 * Multipart upload helper that tries to avoid orphaned fragments in S3
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource|StreamInterface $stream stream with the data to write
	 * @param string|null $mimetype the mimetype to set for the remove object
	 * @throws \Exception when something goes wrong, message will be logged
	 */
	protected function writeMultiPart(string $urn, $stream, string $mimetype = null) {
		$uploader = new MultipartUploader($this->getConnection(), $stream, [
			'bucket' => $this->bucket,
			'key' => $urn,
			'part_size' => $this->uploadPartSize,
			'params' => [
				'ContentType' => $mimetype
			],
		]);

		try {
			$uploader->upload();
		} catch (S3MultipartUploadException $e) {
			// if anything goes wrong with multipart, make sure that you don´t poison and
			// slow down s3 bucket with orphaned fragments
			$uploadInfo = $e->getState()->getId();
			if ($e->getState()->isInitiated() && (array_key_exists('UploadId', $uploadInfo))) {
				$this->getConnection()->abortMultipartUpload($uploadInfo);
			}
			throw $e;
		}
	}


	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @param resource $stream stream with the data to write
	 * @param string|null $mimetype the mimetype to set for the remove object @since 22.0.0
	 * @throws \Exception when something goes wrong, message will be logged
	 * @since 7.0.0
	 */
	public function writeObject($urn, $stream, string $mimetype = null) {
		$psrStream = Utils::streamFor($stream);

		// ($psrStream->isSeekable() && $psrStream->getSize() !== null) evaluates to true for a On-Seekable stream
		// so the optimisation does not apply
		$buffer = new Psr7\Stream(fopen("php://memory", 'rwb+'));
		Utils::copyToStream($psrStream, $buffer, MultipartUploader::PART_MIN_SIZE);
		$buffer->seek(0);
		if ($buffer->getSize() < MultipartUploader::PART_MIN_SIZE) {
			// buffer is fully seekable, so use it directly for the small upload
			$this->writeSingle($urn, $buffer, $mimetype);
		} else {
			$loadStream = new Psr7\AppendStream([$buffer, $psrStream]);
			$this->writeMultiPart($urn, $loadStream, $mimetype);
		}
	}

	/**
	 * @param string $urn the unified resource name used to identify the object
	 * @return void
	 * @throws \Exception when something goes wrong, message will be logged
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

	public function copyObject($from, $to) {
		$this->getConnection()->copy($this->getBucket(), $from, $this->getBucket(), $to);
	}
}
