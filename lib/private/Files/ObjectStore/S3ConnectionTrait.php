<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author S. Cat <33800996+sparrowjack63@users.noreply.github.com>
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

use Aws\ClientResolver;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use OCP\ILogger;

trait S3ConnectionTrait {
	/** @var array */
	protected $params;

	/** @var S3Client */
	protected $connection;

	/** @var string */
	protected $id;

	/** @var string */
	protected $bucket;

	/** @var int */
	protected $timeout;

	protected $test;

	protected function parseParams($params) {
		if (empty($params['key']) || empty($params['secret']) || empty($params['bucket'])) {
			throw new \Exception("Access Key, Secret and Bucket have to be configured.");
		}

		$this->id = 'amazon::' . $params['bucket'];

		$this->test = isset($params['test']);
		$this->bucket = $params['bucket'];
		$this->timeout = !isset($params['timeout']) ? 15 : $params['timeout'];
		$params['region'] = empty($params['region']) ? 'eu-west-1' : $params['region'];
		$params['hostname'] = empty($params['hostname']) ? 's3.' . $params['region'] . '.amazonaws.com' : $params['hostname'];
		if (!isset($params['port']) || $params['port'] === '') {
			$params['port'] = (isset($params['use_ssl']) && $params['use_ssl'] === false) ? 80 : 443;
		}
		$this->params = $params;
	}

	public function getBucket() {
		return $this->bucket;
	}

	/**
	 * Returns the connection
	 *
	 * @return S3Client connected client
	 * @throws \Exception if connection could not be made
	 */
	public function getConnection() {
		if (!is_null($this->connection)) {
			return $this->connection;
		}

		$scheme = (isset($this->params['use_ssl']) && $this->params['use_ssl'] === false) ? 'http' : 'https';
		$base_url = $scheme . '://' . $this->params['hostname'] . ':' . $this->params['port'] . '/';

		$options = [
			'version' => isset($this->params['version']) ? $this->params['version'] : 'latest',
			'credentials' => [
				'key' => $this->params['key'],
				'secret' => $this->params['secret'],
			],
			'endpoint' => $base_url,
			'region' => $this->params['region'],
			'use_path_style_endpoint' => isset($this->params['use_path_style']) ? $this->params['use_path_style'] : false,
			'signature_provider' => \Aws\or_chain([self::class, 'legacySignatureProvider'], ClientResolver::_default_signature_provider())
		];
		if (isset($this->params['proxy'])) {
			$options['request.options'] = ['proxy' => $this->params['proxy']];
		}
		if (isset($this->params['legacy_auth']) && $this->params['legacy_auth']) {
			$options['signature_version'] = 'v2';
		}
		$this->connection = new S3Client($options);

		if (!$this->connection->isBucketDnsCompatible($this->bucket)) {
			$logger = \OC::$server->getLogger();
			$logger->debug('Bucket "' . $this->bucket . '" This bucket name is not dns compatible, it may contain invalid characters.',
					 ['app' => 'objectstore']);
		}

		if (!$this->connection->doesBucketExist($this->bucket)) {
			$logger = \OC::$server->getLogger();
			try {
				$logger->info('Bucket "' . $this->bucket . '" does not exist - creating it.', ['app' => 'objectstore']);
				if (!$this->connection->isBucketDnsCompatible($this->bucket)) {
					throw new \Exception("The bucket will not be created because the name is not dns compatible, please correct it: " . $this->bucket);
				}
				$this->connection->createBucket(['Bucket' => $this->bucket]);
				$this->testTimeout();
			} catch (S3Exception $e) {
				$logger->logException($e, [
					'message' => 'Invalid remote storage.',
					'level' => ILogger::DEBUG,
					'app' => 'objectstore',
				]);
				throw new \Exception('Creation of bucket "' . $this->bucket . '" failed. ' . $e->getMessage());
			}
		}

		// google cloud's s3 compatibility doesn't like the EncodingType parameter
		if (strpos($base_url, 'storage.googleapis.com')) {
			$this->connection->getHandlerList()->remove('s3.auto_encode');
		}

		return $this->connection;
	}

	/**
	 * when running the tests wait to let the buckets catch up
	 */
	private function testTimeout() {
		if ($this->test) {
			sleep($this->timeout);
		}
	}

	public static function legacySignatureProvider($version, $service, $region) {
		switch ($version) {
			case 'v2':
			case 's3':
				return new S3Signature();
			default:
				return null;
		}
	}
}
