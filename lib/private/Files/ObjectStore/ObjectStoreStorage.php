<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Marcel Klehr <mklehr@gmx.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tigran Mkrtchyan <tigran.mkrtchyan@desy.de>
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

use Aws\S3\Exception\S3Exception;
use Aws\S3\Exception\S3MultipartUploadException;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Files\Cache\Cache;
use OC\Files\Cache\CacheEntry;
use OC\Files\Storage\PolyFill\CopyDirectory;
use OC\Memcache\ArrayCache;
use OC\Memcache\NullCache;
use OCP\Files\Cache\ICacheEntry;
use OCP\Files\FileInfo;
use OCP\Files\GenericFileException;
use OCP\Files\NotFoundException;
use OCP\Files\ObjectStore\IObjectStore;
use OCP\Files\ObjectStore\IObjectStoreMultiPartUpload;
use OCP\Files\Storage\IChunkedFileWrite;
use OCP\Files\Storage\IStorage;
use OCP\ICache;

class ObjectStoreStorage extends \OC\Files\Storage\Common implements IChunkedFileWrite {
	use CopyDirectory;

	/**
	 * @var \OCP\Files\ObjectStore\IObjectStore $objectStore
	 */
	protected $objectStore;
	/**
	 * @var string $id
	 */
	protected $id;
	/**
	 * @var \OC\User\User $user
	 */
	protected $user;

	private $objectPrefix = 'urn:oid:';

	private $logger;

	/** @var ICache */
	private $uploadCache;

	public function __construct($params) {
		if (isset($params['objectstore']) && $params['objectstore'] instanceof IObjectStore) {
			$this->objectStore = $params['objectstore'];
		} else {
			throw new \Exception('missing IObjectStore instance');
		}
		if (isset($params['storageid'])) {
			$this->id = 'object::store:' . $params['storageid'];
		} else {
			$this->id = 'object::store:' . $this->objectStore->getStorageId();
		}
		if (isset($params['objectPrefix'])) {
			$this->objectPrefix = $params['objectPrefix'];
		}
		//initialize cache with root directory in cache
		if (!$this->is_dir('/')) {
			$this->mkdir('/');
		}

		$this->logger = \OC::$server->getLogger();
		$this->uploadCache = \OC::$server->getMemCacheFactory()->createDistributed('objectstore');
	}

	public function mkdir($path) {
		$path = $this->normalizePath($path);
		if ($this->file_exists($path)) {
			return false;
		}

		$mTime = time();
		$data = [
			'mimetype' => 'httpd/unix-directory',
			'size' => 0,
			'mtime' => $mTime,
			'storage_mtime' => $mTime,
			'permissions' => \OCP\Constants::PERMISSION_ALL,
		];
		if ($path === '') {
			//create root on the fly
			$data['etag'] = $this->getETag('');
			$this->getCache()->put('', $data);
			return true;
		} else {
			// if parent does not exist, create it
			$parent = $this->normalizePath(dirname($path));
			$parentType = $this->filetype($parent);
			if ($parentType === false) {
				if (!$this->mkdir($parent)) {
					// something went wrong
					return false;
				}
			} elseif ($parentType === 'file') {
				// parent is a file
				return false;
			}
			// finally create the new dir
			$mTime = time(); // update mtime
			$data['mtime'] = $mTime;
			$data['storage_mtime'] = $mTime;
			$data['etag'] = $this->getETag($path);
			$this->getCache()->put($path, $data);
			return true;
		}
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function normalizePath($path) {
		$path = trim($path, '/');
		//FIXME why do we sometimes get a path like 'files//username'?
		$path = str_replace('//', '/', $path);

		// dirname('/folder') returns '.' but internally (in the cache) we store the root as ''
		if (!$path || $path === '.') {
			$path = '';
		}

		return $path;
	}

	/**
	 * Object Stores use a NoopScanner because metadata is directly stored in
	 * the file cache and cannot really scan the filesystem. The storage passed in is not used anywhere.
	 *
	 * @param string $path
	 * @param \OC\Files\Storage\Storage (optional) the storage to pass to the scanner
	 * @return \OC\Files\ObjectStore\NoopScanner
	 */
	public function getScanner($path = '', $storage = null) {
		if (!$storage) {
			$storage = $this;
		}
		if (!isset($this->scanner)) {
			$this->scanner = new NoopScanner($storage);
		}
		return $this->scanner;
	}

	public function getId() {
		return $this->id;
	}

	public function rmdir($path) {
		$path = $this->normalizePath($path);

		if (!$this->is_dir($path)) {
			return false;
		}

		if (!$this->rmObjects($path)) {
			return false;
		}

		$this->getCache()->remove($path);

		return true;
	}

	private function rmObjects($path) {
		$children = $this->getCache()->getFolderContents($path);
		foreach ($children as $child) {
			if ($child['mimetype'] === 'httpd/unix-directory') {
				if (!$this->rmObjects($child['path'])) {
					return false;
				}
			} else {
				if (!$this->unlink($child['path'])) {
					return false;
				}
			}
		}

		return true;
	}

	public function unlink($path) {
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);

		if ($stat && isset($stat['fileid'])) {
			if ($stat['mimetype'] === 'httpd/unix-directory') {
				return $this->rmdir($path);
			}
			try {
				$this->objectStore->deleteObject($this->getURN($stat['fileid']));
			} catch (\Exception $ex) {
				if ($ex->getCode() !== 404) {
					$this->logger->logException($ex, [
						'app' => 'objectstore',
						'message' => 'Could not delete object ' . $this->getURN($stat['fileid']) . ' for ' . $path,
					]);
					return false;
				}
				//removing from cache is ok as it does not exist in the objectstore anyway
			}
			$this->getCache()->remove($path);
			return true;
		}
		return false;
	}

	public function stat($path) {
		$path = $this->normalizePath($path);
		$cacheEntry = $this->getCache()->get($path);
		if ($cacheEntry instanceof CacheEntry) {
			return $cacheEntry->getData();
		} else {
			return false;
		}
	}

	public function getPermissions($path) {
		$stat = $this->stat($path);

		if (is_array($stat) && isset($stat['permissions'])) {
			return $stat['permissions'];
		}

		return parent::getPermissions($path);
	}

	/**
	 * Override this method if you need a different unique resource identifier for your object storage implementation.
	 * The default implementations just appends the fileId to 'urn:oid:'. Make sure the URN is unique over all users.
	 * You may need a mapping table to store your URN if it cannot be generated from the fileid.
	 *
	 * @param int $fileId the fileid
	 * @return null|string the unified resource name used to identify the object
	 */
	public function getURN($fileId) {
		if (is_numeric($fileId)) {
			return $this->objectPrefix . $fileId;
		}
		return null;
	}

	public function opendir($path) {
		$path = $this->normalizePath($path);

		try {
			$files = [];
			$folderContents = $this->getCache()->getFolderContents($path);
			foreach ($folderContents as $file) {
				$files[] = $file['name'];
			}

			return IteratorDirectory::wrap($files);
		} catch (\Exception $e) {
			$this->logger->logException($e);
			return false;
		}
	}

	public function filetype($path) {
		$path = $this->normalizePath($path);
		$stat = $this->stat($path);
		if ($stat) {
			if ($stat['mimetype'] === 'httpd/unix-directory') {
				return 'dir';
			}
			return 'file';
		} else {
			return false;
		}
	}

	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);

		if (strrpos($path, '.') !== false) {
			$ext = substr($path, strrpos($path, '.'));
		} else {
			$ext = '';
		}

		switch ($mode) {
			case 'r':
			case 'rb':
				$stat = $this->stat($path);
				if (is_array($stat)) {
					// Reading 0 sized files is a waste of time
					if (isset($stat['size']) && $stat['size'] === 0) {
						return fopen('php://memory', $mode);
					}

					try {
						return $this->objectStore->readObject($this->getURN($stat['fileid']));
					} catch (NotFoundException $e) {
						$this->logger->logException($e, [
							'app' => 'objectstore',
							'message' => 'Could not get object ' . $this->getURN($stat['fileid']) . ' for file ' . $path,
						]);
						throw $e;
					} catch (\Exception $ex) {
						$this->logger->logException($ex, [
							'app' => 'objectstore',
							'message' => 'Could not get object ' . $this->getURN($stat['fileid']) . ' for file ' . $path,
						]);
						return false;
					}
				} else {
					return false;
				}
			// no break
			case 'w':
			case 'wb':
			case 'w+':
			case 'wb+':
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
				$handle = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
					$this->writeBack($tmpFile, $path);
				});
			case 'a':
			case 'ab':
			case 'r+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
				if ($this->file_exists($path)) {
					$source = $this->fopen($path, 'r');
					file_put_contents($tmpFile, $source);
				}
				$handle = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
					$this->writeBack($tmpFile, $path);
				});
		}
		return false;
	}

	public function file_exists($path) {
		$path = $this->normalizePath($path);
		return (bool)$this->stat($path);
	}

	public function rename($source, $target) {
		$source = $this->normalizePath($source);
		$target = $this->normalizePath($target);
		$this->remove($target);
		$this->getCache()->move($source, $target);
		$this->touch(dirname($target));
		return true;
	}

	public function getMimeType($path) {
		$path = $this->normalizePath($path);
		return parent::getMimeType($path);
	}

	public function touch($path, $mtime = null) {
		if (is_null($mtime)) {
			$mtime = time();
		}

		$path = $this->normalizePath($path);
		$dirName = dirname($path);
		$parentExists = $this->is_dir($dirName);
		if (!$parentExists) {
			return false;
		}

		$stat = $this->stat($path);
		if (is_array($stat)) {
			// update existing mtime in db
			$stat['mtime'] = $mtime;
			$this->getCache()->update($stat['fileid'], $stat);
		} else {
			try {
				//create a empty file, need to have at least on char to make it
				// work with all object storage implementations
				$this->file_put_contents($path, ' ');
				$mimeType = \OC::$server->getMimeTypeDetector()->detectPath($path);
				$stat = [
					'etag' => $this->getETag($path),
					'mimetype' => $mimeType,
					'size' => 0,
					'mtime' => $mtime,
					'storage_mtime' => $mtime,
					'permissions' => \OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE,
				];
				$this->getCache()->put($path, $stat);
			} catch (\Exception $ex) {
				$this->logger->logException($ex, [
					'app' => 'objectstore',
					'message' => 'Could not create object for ' . $path,
				]);
				throw $ex;
			}
		}
		return true;
	}

	public function writeBack($tmpFile, $path) {
		$size = filesize($tmpFile);
		$this->writeStream($path, fopen($tmpFile, 'r'), $size);
	}

	/**
	 * external changes are not supported, exclusive access to the object storage is assumed
	 *
	 * @param string $path
	 * @param int $time
	 * @return false
	 */
	public function hasUpdated($path, $time) {
		return false;
	}

	public function needsPartFile() {
		return false;
	}

	public function file_put_contents($path, $data) {
		$handle = $this->fopen($path, 'w+');
		$result = fwrite($handle, $data);
		fclose($handle);
		return $result;
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		$stat = $this->stat($path);
		if (empty($stat)) {
			// create new file
			$stat = [
				'permissions' => \OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE,
			];
		}
		// update stat with new data
		$mTime = time();
		$stat['size'] = (int)$size;
		$stat['mtime'] = $mTime;
		$stat['storage_mtime'] = $mTime;

		$mimetypeDetector = \OC::$server->getMimeTypeDetector();
		$mimetype = $mimetypeDetector->detectPath($path);

		$stat['mimetype'] = $mimetype;
		$stat['etag'] = $this->getETag($path);
		$stat['checksum'] = '';

		$exists = $this->getCache()->inCache($path);
		$uploadPath = $exists ? $path : $path . '.part';

		if ($exists) {
			$fileId = $stat['fileid'];
		} else {
			$fileId = $this->getCache()->put($uploadPath, $stat);
		}

		$urn = $this->getURN($fileId);
		try {
			//upload to object storage
			if ($size === null) {
				$countStream = CountWrapper::wrap($stream, function ($writtenSize) use ($fileId, &$size) {
					$this->getCache()->update($fileId, [
						'size' => $writtenSize,
					]);
					$size = $writtenSize;
				});
				$this->objectStore->writeObject($urn, $countStream, $mimetype);
				if (is_resource($countStream)) {
					fclose($countStream);
				}
				$stat['size'] = $size;
			} else {
				$this->objectStore->writeObject($urn, $stream, $mimetype);
				if (is_resource($stream)) {
					fclose($stream);
				}
			}
		} catch (\Exception $ex) {
			if (!$exists) {
				/*
				 * Only remove the entry if we are dealing with a new file.
				 * Else people lose access to existing files
				 */
				$this->getCache()->remove($uploadPath);
				$this->logger->logException($ex, [
					'app' => 'objectstore',
					'message' => 'Could not create object ' . $urn . ' for ' . $path,
				]);
			} else {
				$this->logger->logException($ex, [
					'app' => 'objectstore',
					'message' => 'Could not update object ' . $urn . ' for ' . $path,
				]);
			}
			throw $ex; // make this bubble up
		}

		if ($exists) {
			$this->getCache()->update($fileId, $stat);
		} else {
			if ($this->objectStore->objectExists($urn)) {
				$this->getCache()->move($uploadPath, $path);
			} else {
				$this->getCache()->remove($uploadPath);
				throw new \Exception("Object not found after writing (urn: $urn, path: $path)", 404);
			}
		}

		return $size;
	}

	public function getObjectStore(): IObjectStore {
		return $this->objectStore;
	}

	public function copyFromStorage(IStorage $sourceStorage, $sourceInternalPath, $targetInternalPath, $preserveMtime = false) {
		if ($sourceStorage->instanceOfStorage(ObjectStoreStorage::class)) {
			/** @var ObjectStoreStorage $sourceStorage */
			if ($sourceStorage->getObjectStore()->getStorageId() === $this->getObjectStore()->getStorageId()) {
				$sourceEntry = $sourceStorage->getCache()->get($sourceInternalPath);
				$this->copyInner($sourceEntry, $targetInternalPath);
				return true;
			}
		}

		return parent::copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}

	public function copy($path1, $path2) {
		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		$cache = $this->getCache();
		$sourceEntry = $cache->get($path1);
		if (!$sourceEntry) {
			throw new NotFoundException('Source object not found');
		}

		$this->copyInner($sourceEntry, $path2);

		return true;
	}

	private function copyInner(ICacheEntry $sourceEntry, string $to) {
		$cache = $this->getCache();

		if ($sourceEntry->getMimeType() === FileInfo::MIMETYPE_FOLDER) {
			if ($cache->inCache($to)) {
				$cache->remove($to);
			}
			$this->mkdir($to);

			foreach ($cache->getFolderContentsById($sourceEntry->getId()) as $child) {
				$this->copyInner($child, $to . '/' . $child->getName());
			}
		} else {
			$this->copyFile($sourceEntry, $to);
		}
	}

	private function copyFile(ICacheEntry $sourceEntry, string $to) {
		$cache = $this->getCache();

		$sourceUrn = $this->getURN($sourceEntry->getId());

		if (!$cache instanceof Cache) {
			throw new \Exception("Invalid source cache for object store copy");
		}

		$targetId = $cache->copyFromCache($cache, $sourceEntry, $to);

		$targetUrn = $this->getURN($targetId);

		try {
			$this->objectStore->copyObject($sourceUrn, $targetUrn);
		} catch (\Exception $e) {
			$cache->remove($to);

			throw $e;
		}
	}

	public function beginChunkedFile(string $targetPath): string {
		$this->validateUploadCache();
		if (!$this->objectStore instanceof IObjectStoreMultiPartUpload) {
			throw new GenericFileException('Object store does not support multipart upload');
		}
		$cacheEntry = $this->getCache()->get($targetPath);
		$urn = $this->getURN($cacheEntry->getId());
		$uploadId = $this->objectStore->initiateMultipartUpload($urn);
		$this->uploadCache->set($this->getUploadCacheKey($urn, $uploadId, 'uploadId'), $uploadId);
		return $uploadId;
	}

	/**
	 *
	 * @throws GenericFileException
	 */
	public function putChunkedFilePart(string $targetPath, string $writeToken, string $chunkId, $data, $size = null): void {
		$this->validateUploadCache();
		if (!$this->objectStore instanceof IObjectStoreMultiPartUpload) {
			throw new GenericFileException('Object store does not support multipart upload');
		}
		$cacheEntry = $this->getCache()->get($targetPath);
		$urn = $this->getURN($cacheEntry->getId());
		$uploadId = $this->uploadCache->get($this->getUploadCacheKey($urn, $writeToken, 'uploadId'));

		$result = $this->objectStore->uploadMultipartPart($urn, $uploadId, (int)$chunkId, $data, $size);

		$parts = $this->uploadCache->get($this->getUploadCacheKey($urn, $uploadId, 'parts'));
		if (!$parts) {
			$parts = [];
		}
		$parts[$chunkId] = [
			'PartNumber' => $chunkId,
			'ETag' => trim($result->get('ETag'), '"')
		];
		$this->uploadCache->set($this->getUploadCacheKey($urn, $uploadId, 'parts'), $parts);
	}

	public function writeChunkedFile(string $targetPath, string $writeToken): int {
		$this->validateUploadCache();
		if (!$this->objectStore instanceof IObjectStoreMultiPartUpload) {
			throw new GenericFileException('Object store does not support multipart upload');
		}
		$cacheEntry = $this->getCache()->get($targetPath);
		$urn = $this->getURN($cacheEntry->getId());
		$uploadId = $this->uploadCache->get($this->getUploadCacheKey($urn, $writeToken, 'uploadId'));
		$parts = $this->uploadCache->get($this->getUploadCacheKey($urn, $uploadId, 'parts'));
		try {
			$size = $this->objectStore->completeMultipartUpload($urn, $uploadId, array_values($parts));
			$stat = $this->stat($targetPath);
			$mtime = time();
			if (is_array($stat)) {
				$stat['size'] = $size;
				$stat['mtime'] = $mtime;
				$stat['mimetype'] = $this->getMimeType($targetPath);
				$this->getCache()->update($stat['fileid'], $stat);
			}
		} catch (S3MultipartUploadException | S3Exception $e) {
			$this->objectStore->abortMultipartUpload($urn, $uploadId);
			$this->logger->logException($e, [
				'app' => 'objectstore',
				'message' => 'Could not compete multipart upload ' . $urn. ' with uploadId ' . $uploadId
			]);
			throw new GenericFileException('Could not write chunked file');
		} finally {
			$this->clearCache($urn, $uploadId);
		}
		return $size;
	}

	public function cancelChunkedFile(string $targetPath, string $writeToken): void {
		$this->validateUploadCache();
		if (!$this->objectStore instanceof IObjectStoreMultiPartUpload) {
			throw new GenericFileException('Object store does not support multipart upload');
		}
		$cacheEntry = $this->getCache()->get($targetPath);
		$urn = $this->getURN($cacheEntry->getId());
		$uploadId = $this->uploadCache->get($this->getUploadCacheKey($urn, $writeToken, 'uploadId'));
		$this->objectStore->abortMultipartUpload($urn, $uploadId);
		$this->clearCache($urn, $uploadId);
	}

	/**
	 * @throws GenericFileException
	 */
	private function validateUploadCache(): void {
		if ($this->uploadCache instanceof NullCache || $this->uploadCache instanceof ArrayCache) {
			throw new GenericFileException('ChunkedFileWrite not available: A cross-request persistent cache is required');
		}
	}

	private function getUploadCacheKey($urn, $uploadId, $chunkId = null): string {
		return $urn . '-' . $uploadId . '-' . ($chunkId ? $chunkId . '-' : '');
	}

	private function clearCache($urn, $uploadId): void {
		$this->uploadCache->clear($this->getUploadCacheKey($urn, $uploadId));
	}
}
