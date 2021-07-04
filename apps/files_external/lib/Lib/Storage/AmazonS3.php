<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author André Gaul <gaul@web-yard.de>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christian Berendt <berendt@b1-systems.de>
 * @author Christopher T. Johnson <ctjctj@gmail.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author enoch <lanxenet@hotmail.com>
 * @author Johan Björk <johanimon@gmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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
namespace OCA\Files_External\Lib\Storage;

use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OC\Files\Cache\CacheEntry;
use OC\Files\ObjectStore\S3ConnectionTrait;
use OC\Files\ObjectStore\S3ObjectTrait;
use OCP\Constants;
use OCP\Files\IMimeTypeDetector;

class AmazonS3 extends \OC\Files\Storage\Common {
	use S3ConnectionTrait;
	use S3ObjectTrait;

	public function needsPartFile() {
		return false;
	}

	/** @var CappedMemoryCache|Result[] */
	private $objectCache;

	/** @var CappedMemoryCache|bool[] */
	private $directoryCache;

	/** @var CappedMemoryCache|array */
	private $filesCache;

	/** @var IMimeTypeDetector */
	private $mimeDetector;

	public function __construct($parameters) {
		parent::__construct($parameters);
		$this->parseParams($parameters);
		$this->objectCache = new CappedMemoryCache();
		$this->directoryCache = new CappedMemoryCache();
		$this->filesCache = new CappedMemoryCache();
		$this->mimeDetector = \OC::$server->get(IMimeTypeDetector::class);
	}

	/**
	 * @param string $path
	 * @return string correctly encoded path
	 */
	private function normalizePath($path) {
		$path = trim($path, '/');

		if (!$path) {
			$path = '.';
		}

		return $path;
	}

	private function isRoot($path) {
		return $path === '.';
	}

	private function cleanKey($path) {
		if ($this->isRoot($path)) {
			return '/';
		}
		return $path;
	}

	private function clearCache() {
		$this->objectCache = new CappedMemoryCache();
		$this->directoryCache = new CappedMemoryCache();
		$this->filesCache = new CappedMemoryCache();
	}

	private function invalidateCache($key) {
		unset($this->objectCache[$key]);
		$keys = array_keys($this->objectCache->getData());
		$keyLength = strlen($key);
		foreach ($keys as $existingKey) {
			if (substr($existingKey, 0, $keyLength) === $key) {
				unset($this->objectCache[$existingKey]);
			}
		}
		unset($this->directoryCache[$key], $this->filesCache[$key]);
	}

	/**
	 * @param $key
	 * @return Result|boolean
	 */
	private function headObject($key) {
		if (!isset($this->objectCache[$key])) {
			try {
				$this->objectCache[$key] = $this->getConnection()->headObject([
					'Bucket' => $this->bucket,
					'Key' => $key
				]);
			} catch (S3Exception $e) {
				if ($e->getStatusCode() >= 500) {
					throw $e;
				}
				$this->objectCache[$key] = false;
			}
		}

		return $this->objectCache[$key];
	}

	/**
	 * Return true if directory exists
	 *
	 * There are no folders in s3. A folder like structure could be archived
	 * by prefixing files with the folder name.
	 *
	 * Implementation from flysystem-aws-s3-v3:
	 * https://github.com/thephpleague/flysystem-aws-s3-v3/blob/8241e9cc5b28f981e0d24cdaf9867f14c7498ae4/src/AwsS3Adapter.php#L670-L694
	 *
	 * @param $path
	 * @return bool
	 * @throws \Exception
	 */
	private function doesDirectoryExist($path) {
		if (!isset($this->directoryCache[$path])) {
			// Maybe this isn't an actual key, but a prefix.
			// Do a prefix listing of objects to determine.
			try {
				$result = $this->getConnection()->listObjects([
					'Bucket' => $this->bucket,
					'Prefix' => rtrim($path, '/'),
					'MaxKeys' => 1,
					'Delimiter' => '/',
				]);

				if ((isset($result['Contents'][0]['Key']) && $result['Contents'][0]['Key'] === rtrim($path, '/') . '/')
					 || isset($result['CommonPrefixes'])) {
					$this->directoryCache[$path] = true;
				} else {
					$this->directoryCache[$path] = false;
				}
			} catch (S3Exception $e) {
				if ($e->getStatusCode() === 403) {
					$this->directoryCache[$path] = false;
				}
				throw $e;
			}
		}

		return $this->directoryCache[$path];
	}

	/**
	 * Updates old storage ids (v0.2.1 and older) that are based on key and secret to new ones based on the bucket name.
	 * TODO Do this in a repair step. requires iterating over all users and loading the mount.json from their home
	 *
	 * @param array $params
	 */
	public function updateLegacyId(array $params) {
		$oldId = 'amazon::' . $params['key'] . md5($params['secret']);

		// find by old id or bucket
		$stmt = \OC::$server->getDatabaseConnection()->prepare(
			'SELECT `numeric_id`, `id` FROM `*PREFIX*storages` WHERE `id` IN (?, ?)'
		);
		$stmt->execute([$oldId, $this->id]);
		while ($row = $stmt->fetch()) {
			$storages[$row['id']] = $row['numeric_id'];
		}

		if (isset($storages[$this->id]) && isset($storages[$oldId])) {
			// if both ids exist, delete the old storage and corresponding filecache entries
			\OC\Files\Cache\Storage::remove($oldId);
		} elseif (isset($storages[$oldId])) {
			// if only the old id exists do an update
			$stmt = \OC::$server->getDatabaseConnection()->prepare(
				'UPDATE `*PREFIX*storages` SET `id` = ? WHERE `id` = ?'
			);
			$stmt->execute([$this->id, $oldId]);
		}
		// only the bucket based id may exist, do nothing
	}

	/**
	 * Remove a file or folder
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function remove($path) {
		// remember fileType to reduce http calls
		$fileType = $this->filetype($path);
		if ($fileType === 'dir') {
			return $this->rmdir($path);
		} elseif ($fileType === 'file') {
			return $this->unlink($path);
		} else {
			return false;
		}
	}

	public function mkdir($path) {
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return false;
		}

		try {
			$this->getConnection()->putObject([
				'Bucket' => $this->bucket,
				'Key' => $path . '/',
				'Body' => '',
				'ContentType' => 'httpd/unix-directory'
			]);
			$this->testTimeout();
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}

		$this->invalidateCache($path);

		return true;
	}

	public function file_exists($path) {
		return $this->filetype($path) !== false;
	}


	public function rmdir($path) {
		$path = $this->normalizePath($path);

		if ($this->isRoot($path)) {
			return $this->clearBucket();
		}

		if (!$this->file_exists($path)) {
			return false;
		}

		$this->invalidateCache($path);
		return $this->batchDelete($path);
	}

	protected function clearBucket() {
		$this->clearCache();
		try {
			$this->getConnection()->clearBucket($this->bucket);
			return true;
			// clearBucket() is not working with Ceph, so if it fails we try the slower approach
		} catch (\Exception $e) {
			return $this->batchDelete();
		}
	}

	private function batchDelete($path = null) {
		$params = [
			'Bucket' => $this->bucket
		];
		if ($path !== null) {
			$params['Prefix'] = $path . '/';
		}
		try {
			$connection = $this->getConnection();
			// Since there are no real directories on S3, we need
			// to delete all objects prefixed with the path.
			do {
				// instead of the iterator, manually loop over the list ...
				$objects = $connection->listObjects($params);
				// ... so we can delete the files in batches
				if (isset($objects['Contents'])) {
					$connection->deleteObjects([
						'Bucket' => $this->bucket,
						'Delete' => [
							'Objects' => $objects['Contents']
						]
					]);
					$this->testTimeout();
				}
				// we reached the end when the list is no longer truncated
			} while ($objects['IsTruncated']);
			$this->deleteObject($path);
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}
		return true;
	}

	public function opendir($path) {
		$path = $this->normalizePath($path);

		if ($this->isRoot($path)) {
			$path = '';
		} else {
			$path .= '/';
		}

		try {
			$files = [];
			$results = $this->getConnection()->getPaginator('ListObjects', [
				'Bucket' => $this->bucket,
				'Delimiter' => '/',
				'Prefix' => $path,
			]);

			foreach ($results as $result) {
				// sub folders
				if (is_array($result['CommonPrefixes'])) {
					foreach ($result['CommonPrefixes'] as $prefix) {
						$directoryName = trim($prefix['Prefix'], '/');
						$files[] = substr($directoryName, strlen($path));
						$this->directoryCache[$directoryName] = true;
					}
				}
				if (is_array($result['Contents'])) {
					foreach ($result['Contents'] as $object) {
						if (isset($object['Key']) && $object['Key'] === $path) {
							// it's the directory itself, skip
							continue;
						}
						$file = basename(
							isset($object['Key']) ? $object['Key'] : $object['Prefix']
						);
						$files[] = $file;

						// store this information for later usage
						$this->filesCache[$path . $file] = [
							'ContentLength' => $object['Size'],
							'LastModified' => (string)$object['LastModified'],
						];
					}
				}
			}

			return IteratorDirectory::wrap($files);
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}
	}

	public function stat($path) {
		$path = $this->normalizePath($path);

		try {
			$stat = [];
			if ($this->is_dir($path)) {
				//folders don't really exist
				$stat['size'] = -1; //unknown
				$stat['mtime'] = time();
				$cacheEntry = $this->getCache()->get($path);
				if ($cacheEntry instanceof CacheEntry && $this->getMountOption('filesystem_check_changes', 1) !== 1) {
					$stat['size'] = $cacheEntry->getSize();
					$stat['mtime'] = $cacheEntry->getMTime();
				}
			} else {
				$stat['size'] = $this->getContentLength($path);
				$stat['mtime'] = strtotime($this->getLastModified($path));
			}
			$stat['atime'] = time();

			return $stat;
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}
	}

	/**
	 * Return content length for object
	 *
	 * When the information is already present (e.g. opendir has been called before)
	 * this value is return. Otherwise a headObject is emitted.
	 *
	 * @param $path
	 * @return int|mixed
	 */
	private function getContentLength($path) {
		if (isset($this->filesCache[$path])) {
			return (int)$this->filesCache[$path]['ContentLength'];
		}

		$result = $this->headObject($path);
		if (isset($result['ContentLength'])) {
			return (int)$result['ContentLength'];
		}

		return 0;
	}

	/**
	 * Return last modified for object
	 *
	 * When the information is already present (e.g. opendir has been called before)
	 * this value is return. Otherwise a headObject is emitted.
	 *
	 * @param $path
	 * @return mixed|string
	 */
	private function getLastModified($path) {
		if (isset($this->filesCache[$path])) {
			return $this->filesCache[$path]['LastModified'];
		}

		$result = $this->headObject($path);
		if (isset($result['LastModified'])) {
			return $result['LastModified'];
		}

		return 'now';
	}

	public function is_dir($path) {
		$path = $this->normalizePath($path);

		if (isset($this->filesCache[$path])) {
			return false;
		}

		try {
			return $this->isRoot($path) || $this->doesDirectoryExist($path);
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}
	}

	public function filetype($path) {
		$path = $this->normalizePath($path);

		if ($this->isRoot($path)) {
			return 'dir';
		}

		try {
			if (isset($this->filesCache[$path]) || $this->headObject($path)) {
				return 'file';
			}
			if ($this->doesDirectoryExist($path)) {
				return 'dir';
			}
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}

		return false;
	}

	public function getPermissions($path) {
		$type = $this->filetype($path);
		if (!$type) {
			return 0;
		}
		return $type === 'dir' ? Constants::PERMISSION_ALL : Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE;
	}

	public function unlink($path) {
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return $this->rmdir($path);
		}

		try {
			$this->deleteObject($path);
			$this->invalidateCache($path);
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}

		return true;
	}

	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);

		switch ($mode) {
			case 'r':
			case 'rb':
				// Don't try to fetch empty files
				$stat = $this->stat($path);
				if (is_array($stat) && isset($stat['size']) && $stat['size'] === 0) {
					return fopen('php://memory', $mode);
				}

				try {
					return $this->readObject($path);
				} catch (S3Exception $e) {
					\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
					return false;
				}
			case 'w':
			case 'wb':
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile();

				$handle = fopen($tmpFile, 'w');
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
					$this->writeBack($tmpFile, $path);
				});
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				if (strrpos($path, '.') !== false) {
					$ext = substr($path, strrpos($path, '.'));
				} else {
					$ext = '';
				}
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
				if ($this->file_exists($path)) {
					$source = $this->readObject($path);
					file_put_contents($tmpFile, $source);
				}

				$handle = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
					$this->writeBack($tmpFile, $path);
				});
		}
		return false;
	}

	public function touch($path, $mtime = null) {
		if (is_null($mtime)) {
			$mtime = time();
		}
		$metadata = [
			'lastmodified' => gmdate(\DateTime::RFC1123, $mtime)
		];

		try {
			if (!$this->file_exists($path)) {
				$mimeType = $this->mimeDetector->detectPath($path);
				$this->getConnection()->putObject([
					'Bucket' => $this->bucket,
					'Key' => $this->cleanKey($path),
					'Metadata' => $metadata,
					'Body' => '',
					'ContentType' => $mimeType,
					'MetadataDirective' => 'REPLACE',
				]);
				$this->testTimeout();
			}
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}

		$this->invalidateCache($path);
		return true;
	}

	public function copy($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		if ($this->is_file($path1)) {
			try {
				$this->getConnection()->copyObject([
					'Bucket' => $this->bucket,
					'Key' => $this->cleanKey($path2),
					'CopySource' => S3Client::encodeKey($this->bucket . '/' . $path1)
				]);
				$this->testTimeout();
			} catch (S3Exception $e) {
				\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
				return false;
			}
		} else {
			$this->remove($path2);

			try {
				$this->getConnection()->copyObject([
					'Bucket' => $this->bucket,
					'Key' => $path2 . '/',
					'CopySource' => S3Client::encodeKey($this->bucket . '/' . $path1 . '/')
				]);
				$this->testTimeout();
			} catch (S3Exception $e) {
				\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
				return false;
			}

			$dh = $this->opendir($path1);
			if (is_resource($dh)) {
				while (($file = readdir($dh)) !== false) {
					if (\OC\Files\Filesystem::isIgnoredDir($file)) {
						continue;
					}

					$source = $path1 . '/' . $file;
					$target = $path2 . '/' . $file;
					$this->copy($source, $target);
				}
			}
		}

		$this->invalidateCache($path2);

		return true;
	}

	public function rename($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		if ($this->is_file($path1)) {
			if ($this->copy($path1, $path2) === false) {
				return false;
			}

			if ($this->unlink($path1) === false) {
				$this->unlink($path2);
				return false;
			}
		} else {
			if ($this->copy($path1, $path2) === false) {
				return false;
			}

			if ($this->rmdir($path1) === false) {
				$this->rmdir($path2);
				return false;
			}
		}

		return true;
	}

	public function test() {
		$this->getConnection()->headBucket([
			'Bucket' => $this->bucket
		]);
		return true;
	}

	public function getId() {
		return $this->id;
	}

	public function writeBack($tmpFile, $path) {
		try {
			$source = fopen($tmpFile, 'r');
			$this->writeObject($path, $source, $this->mimeDetector->detectPath($path));
			$this->invalidateCache($path);

			unlink($tmpFile);
			return true;
		} catch (S3Exception $e) {
			\OC::$server->getLogger()->logException($e, ['app' => 'files_external']);
			return false;
		}
	}

	/**
	 * check if curl is installed
	 */
	public static function checkDependencies() {
		return true;
	}
}
