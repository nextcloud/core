<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jan-Philipp Litza <jplitza@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Owen Winkler <a_github@midnightcircus.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Semih Serhat Karakaya <karakayasemi@itu.edu.tr>
 * @author Stefan Schneider <stefan.schneider@squareweave.com.au>
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
namespace OCA\DAV\Connector\Sabre;

use Icewind\Streams\CallbackWrapper;
use OC\AppFramework\Http\Request;
use OC\Files\Filesystem;
use OC\Files\Stream\HashWrapper;
use OC\Files\View;
use OCA\DAV\Connector\Sabre\Exception\EntityTooLarge;
use OCA\DAV\Connector\Sabre\Exception\FileLocked;
use OCA\DAV\Connector\Sabre\Exception\Forbidden as DAVForbiddenException;
use OCA\DAV\Connector\Sabre\Exception\UnsupportedMediaType;
use OCP\Encryption\Exceptions\GenericEncryptionException;
use OCP\Files\EntityTooLargeException;
use OCP\Files\FileInfo;
use OCP\Files\ForbiddenException;
use OCP\Files\GenericFileException;
use OCP\Files\InvalidContentException;
use OCP\Files\InvalidPathException;
use OCP\Files\LockNotAcquiredException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage;
use OCP\Files\StorageNotAvailableException;
use OCP\ILogger;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IManager;
use Sabre\DAV\Exception;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\Exception\ServiceUnavailable;
use Sabre\DAV\IFile;

class File extends Node implements IFile {
	protected $request;

	/**
	 * Sets up the node, expects a full path name
	 *
	 * @param \OC\Files\View $view
	 * @param \OCP\Files\FileInfo $info
	 * @param \OCP\Share\IManager $shareManager
	 * @param \OC\AppFramework\Http\Request $request
	 */
	public function __construct(View $view, FileInfo $info, IManager $shareManager = null, Request $request = null) {
		parent::__construct($view, $info, $shareManager);

		if (isset($request)) {
			$this->request = $request;
		} else {
			$this->request = \OC::$server->getRequest();
		}
	}

	/**
	 * Updates the data
	 *
	 * The data argument is a readable stream resource.
	 *
	 * After a successful put operation, you may choose to return an ETag. The
	 * etag must always be surrounded by double-quotes. These quotes must
	 * appear in the actual string you're returning.
	 *
	 * Clients may use the ETag from a PUT request to later on make sure that
	 * when they update the file, the contents haven't changed in the mean
	 * time.
	 *
	 * If you don't plan to store the file byte-by-byte, and you return a
	 * different object on a subsequent GET you are strongly recommended to not
	 * return an ETag, and just return null.
	 *
	 * @param resource $data
	 *
	 * @throws Forbidden
	 * @throws UnsupportedMediaType
	 * @throws BadRequest
	 * @throws Exception
	 * @throws EntityTooLarge
	 * @throws ServiceUnavailable
	 * @throws FileLocked
	 * @return string|null
	 */
	public function put($data) {
		try {
			$exists = $this->fileView->file_exists($this->path);
			if ($this->info && $exists && !$this->info->isUpdateable()) {
				throw new Forbidden();
			}
		} catch (StorageNotAvailableException $e) {
			throw new ServiceUnavailable("File is not updatable: " . $e->getMessage());
		}

		// verify path of the target
		$this->verifyPath();

		// chunked handling
		if (isset($_SERVER['HTTP_OC_CHUNKED'])) {
			try {
				return $this->createFileChunked($data);
			} catch (\Exception $e) {
				$this->convertToSabreException($e);
			}
		}

		/** @var Storage $partStorage */
		[$partStorage] = $this->fileView->resolvePath($this->path);
		$needsPartFile = $partStorage->needsPartFile() && (strlen($this->path) > 1);

		$view = \OC\Files\Filesystem::getView();

		if ($needsPartFile) {
			// mark file as partial while uploading (ignored by the scanner)
			$partFilePath = $this->getPartFileBasePath($this->path) . '.ocTransferId' . rand() . '.part';

			if (!$view->isCreatable($partFilePath) && $view->isUpdatable($this->path)) {
				$needsPartFile = false;
			}
		}
		if (!$needsPartFile) {
			// upload file directly as the final path
			$partFilePath = $this->path;

			if ($view && !$this->emitPreHooks($exists)) {
				throw new Exception('Could not write to final file, canceled by hook');
			}
		}

		// the part file and target file might be on a different storage in case of a single file storage (e.g. single file share)
		/** @var \OC\Files\Storage\Storage $partStorage */
		[$partStorage, $internalPartPath] = $this->fileView->resolvePath($partFilePath);
		/** @var \OC\Files\Storage\Storage $storage */
		[$storage, $internalPath] = $this->fileView->resolvePath($this->path);
		try {
			if (!$needsPartFile) {
				$this->changeLock(ILockingProvider::LOCK_EXCLUSIVE);
			}

			if (!is_resource($data)) {
				$tmpData = fopen('php://temp', 'r+');
				if ($data !== null) {
					fwrite($tmpData, $data);
					rewind($tmpData);
				}
				$data = $tmpData;
			}

			$data = HashWrapper::wrap($data, 'md5', function ($hash) {
				$this->header('X-Hash-MD5: ' . $hash);
			});
			$data = HashWrapper::wrap($data, 'sha1', function ($hash) {
				$this->header('X-Hash-SHA1: ' . $hash);
			});
			$data = HashWrapper::wrap($data, 'sha256', function ($hash) {
				$this->header('X-Hash-SHA256: ' . $hash);
			});

			if ($partStorage->instanceOfStorage(Storage\IWriteStreamStorage::class)) {
				$isEOF = false;
				$wrappedData = CallbackWrapper::wrap($data, null, null, null, null, function ($stream) use (&$isEOF) {
					$isEOF = feof($stream);
				});

				$result = true;
				$count = -1;
				try {
					$count = $partStorage->writeStream($internalPartPath, $wrappedData);
				} catch (GenericFileException $e) {
					$result = false;
				}


				if ($result === false) {
					$result = $isEOF;
					if (is_resource($wrappedData)) {
						$result = feof($wrappedData);
					}
				}
			} else {
				$target = $partStorage->fopen($internalPartPath, 'wb');
				if ($target === false) {
					\OC::$server->getLogger()->error('\OC\Files\Filesystem::fopen() failed', ['app' => 'webdav']);
					// because we have no clue about the cause we can only throw back a 500/Internal Server Error
					throw new Exception('Could not write file contents');
				}
				[$count, $result] = \OC_Helper::streamCopy($data, $target);
				fclose($target);
			}

			if ($result === false) {
				$expected = -1;
				if (isset($_SERVER['CONTENT_LENGTH'])) {
					$expected = $_SERVER['CONTENT_LENGTH'];
				}
				if ($expected !== "0") {
					throw new Exception('Error while copying file to target location (copied bytes: ' . $count . ', expected filesize: ' . $expected . ' )');
				}
			}

			// if content length is sent by client:
			// double check if the file was fully received
			// compare expected and actual size
			if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
				$expected = (int)$_SERVER['CONTENT_LENGTH'];
				if ($count !== $expected) {
					throw new BadRequest('Expected filesize of ' . $expected . ' bytes but read (from Nextcloud client) and wrote (to Nextcloud storage) ' . $count . ' bytes. Could either be a network problem on the sending side or a problem writing to the storage on the server side.');
				}
			}
		} catch (\Exception $e) {
			$context = [];

			if ($e instanceof LockedException) {
				$context['level'] = ILogger::DEBUG;
			}

			\OC::$server->getLogger()->logException($e, $context);
			if ($needsPartFile) {
				$partStorage->unlink($internalPartPath);
			}
			$this->convertToSabreException($e);
		}

		try {
			if ($needsPartFile) {
				if ($view && !$this->emitPreHooks($exists)) {
					$partStorage->unlink($internalPartPath);
					throw new Exception('Could not rename part file to final file, canceled by hook');
				}
				try {
					$this->changeLock(ILockingProvider::LOCK_EXCLUSIVE);
				} catch (LockedException $e) {
					// during very large uploads, the shared lock we got at the start might have been expired
					// meaning that the above lock can fail not just only because somebody else got a shared lock
					// or because there is no existing shared lock to make exclusive
					//
					// Thus we try to get a new exclusive lock, if the original lock failed because of a different shared
					// lock this will still fail, if our original shared lock expired the new lock will be successful and
					// the entire operation will be safe

					try {
						$this->acquireLock(ILockingProvider::LOCK_EXCLUSIVE);
					} catch (LockedException $ex) {
						if ($needsPartFile) {
							$partStorage->unlink($internalPartPath);
						}
						throw new FileLocked($e->getMessage(), $e->getCode(), $e);
					}
				}

				// rename to correct path
				try {
					$renameOkay = $storage->moveFromStorage($partStorage, $internalPartPath, $internalPath);
					$fileExists = $storage->file_exists($internalPath);
					if ($renameOkay === false || $fileExists === false) {
						\OC::$server->getLogger()->error('renaming part file to final file failed $renameOkay: ' . ($renameOkay ? 'true' : 'false') . ', $fileExists: ' . ($fileExists ? 'true' : 'false') . ')', ['app' => 'webdav']);
						throw new Exception('Could not rename part file to final file');
					}
				} catch (ForbiddenException $ex) {
					if (!$ex->getRetry()) {
						$partStorage->unlink($internalPartPath);
					}
					throw new DAVForbiddenException($ex->getMessage(), $ex->getRetry());
				} catch (\Exception $e) {
					$partStorage->unlink($internalPartPath);
					$this->convertToSabreException($e);
				}
			}

			// since we skipped the view we need to scan and emit the hooks ourselves
			$storage->getUpdater()->update($internalPath);

			try {
				$this->changeLock(ILockingProvider::LOCK_SHARED);
			} catch (LockedException $e) {
				throw new FileLocked($e->getMessage(), $e->getCode(), $e);
			}

			// allow sync clients to send the mtime along in a header
			if (isset($this->request->server['HTTP_X_OC_MTIME'])) {
				$mtime = $this->sanitizeMtime($this->request->server['HTTP_X_OC_MTIME']);
				if ($this->fileView->touch($this->path, $mtime)) {
					$this->header('X-OC-MTime: accepted');
				}
			}

			$fileInfoUpdate = [
				'upload_time' => time()
			];

			// allow sync clients to send the creation time along in a header
			if (isset($this->request->server['HTTP_X_OC_CTIME'])) {
				$ctime = $this->sanitizeMtime($this->request->server['HTTP_X_OC_CTIME']);
				$fileInfoUpdate['creation_time'] = $ctime;
				$this->header('X-OC-CTime: accepted');
			}

			$this->fileView->putFileInfo($this->path, $fileInfoUpdate);

			if ($view) {
				$this->emitPostHooks($exists);
			}

			$this->refreshInfo();

			if (isset($this->request->server['HTTP_OC_CHECKSUM'])) {
				$checksum = trim($this->request->server['HTTP_OC_CHECKSUM']);
				$this->setChecksum($checksum);
			} elseif ($this->getChecksum() !== null && $this->getChecksum() !== '') {
				$this->setChecksum('');
			}
		} catch (StorageNotAvailableException $e) {
			throw new ServiceUnavailable("Failed to check file size: " . $e->getMessage(), 0, $e);
		}

		return '"' . $this->info->getEtag() . '"';
	}

	private function getPartFileBasePath($path) {
		$partFileInStorage = \OC::$server->getConfig()->getSystemValue('part_file_in_storage', true);
		if ($partFileInStorage) {
			return $path;
		} else {
			return md5($path); // will place it in the root of the view with a unique name
		}
	}

	/**
	 * @param string $path
	 */
	private function emitPreHooks($exists, $path = null) {
		if (is_null($path)) {
			$path = $this->path;
		}
		$hookPath = Filesystem::getView()->getRelativePath($this->fileView->getAbsolutePath($path));
		$run = true;

		if (!$exists) {
			\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_create, [
				\OC\Files\Filesystem::signal_param_path => $hookPath,
				\OC\Files\Filesystem::signal_param_run => &$run,
			]);
		} else {
			\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_update, [
				\OC\Files\Filesystem::signal_param_path => $hookPath,
				\OC\Files\Filesystem::signal_param_run => &$run,
			]);
		}
		\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_write, [
			\OC\Files\Filesystem::signal_param_path => $hookPath,
			\OC\Files\Filesystem::signal_param_run => &$run,
		]);
		return $run;
	}

	/**
	 * @param string $path
	 */
	private function emitPostHooks($exists, $path = null) {
		if (is_null($path)) {
			$path = $this->path;
		}
		$hookPath = Filesystem::getView()->getRelativePath($this->fileView->getAbsolutePath($path));
		if (!$exists) {
			\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_post_create, [
				\OC\Files\Filesystem::signal_param_path => $hookPath
			]);
		} else {
			\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_post_update, [
				\OC\Files\Filesystem::signal_param_path => $hookPath
			]);
		}
		\OC_Hook::emit(\OC\Files\Filesystem::CLASSNAME, \OC\Files\Filesystem::signal_post_write, [
			\OC\Files\Filesystem::signal_param_path => $hookPath
		]);
	}

	/**
	 * Returns the data
	 *
	 * @return resource
	 * @throws Forbidden
	 * @throws ServiceUnavailable
	 */
	public function get() {
		//throw exception if encryption is disabled but files are still encrypted
		try {
			if (!$this->info->isReadable()) {
				// do a if the file did not exist
				throw new NotFound();
			}
			try {
				$res = $this->fileView->fopen(ltrim($this->path, '/'), 'rb');
			} catch (\Exception $e) {
				$this->convertToSabreException($e);
			}
			if ($res === false) {
				throw new ServiceUnavailable("Could not open file");
			}
			return $res;
		} catch (GenericEncryptionException $e) {
			// returning 503 will allow retry of the operation at a later point in time
			throw new ServiceUnavailable("Encryption not ready: " . $e->getMessage());
		} catch (StorageNotAvailableException $e) {
			throw new ServiceUnavailable("Failed to open file: " . $e->getMessage());
		} catch (ForbiddenException $ex) {
			throw new DAVForbiddenException($ex->getMessage(), $ex->getRetry());
		} catch (LockedException $e) {
			throw new FileLocked($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Delete the current file
	 *
	 * @throws Forbidden
	 * @throws ServiceUnavailable
	 */
	public function delete() {
		if (!$this->info->isDeletable()) {
			throw new Forbidden();
		}

		try {
			if (!$this->fileView->unlink($this->path)) {
				// assume it wasn't possible to delete due to permissions
				throw new Forbidden();
			}
		} catch (StorageNotAvailableException $e) {
			throw new ServiceUnavailable("Failed to unlink: " . $e->getMessage());
		} catch (ForbiddenException $ex) {
			throw new DAVForbiddenException($ex->getMessage(), $ex->getRetry());
		} catch (LockedException $e) {
			throw new FileLocked($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Returns the mime-type for a file
	 *
	 * If null is returned, we'll assume application/octet-stream
	 *
	 * @return string
	 */
	public function getContentType() {
		$mimeType = $this->info->getMimetype();

		// PROPFIND needs to return the correct mime type, for consistency with the web UI
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'PROPFIND') {
			return $mimeType;
		}
		return \OC::$server->getMimeTypeDetector()->getSecureMimeType($mimeType);
	}

	/**
	 * @return array|bool
	 */
	public function getDirectDownload() {
		if (\OCP\App::isEnabled('encryption')) {
			return [];
		}
		/** @var \OCP\Files\Storage $storage */
		[$storage, $internalPath] = $this->fileView->resolvePath($this->path);
		if (is_null($storage)) {
			return [];
		}

		return $storage->getDirectDownload($internalPath);
	}

	/**
	 * @param resource $data
	 * @return null|string
	 * @throws Exception
	 * @throws BadRequest
	 * @throws NotImplemented
	 * @throws ServiceUnavailable
	 */
	private function createFileChunked($data) {
		[$path, $name] = \Sabre\Uri\split($this->path);

		$info = \OC_FileChunking::decodeName($name);
		if (empty($info)) {
			throw new NotImplemented('Invalid chunk name');
		}

		$chunk_handler = new \OC_FileChunking($info);
		$bytesWritten = $chunk_handler->store($info['index'], $data);

		//detect aborted upload
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
			if (isset($_SERVER['CONTENT_LENGTH'])) {
				$expected = (int)$_SERVER['CONTENT_LENGTH'];
				if ($bytesWritten !== $expected) {
					$chunk_handler->remove($info['index']);
					throw new BadRequest('Expected filesize of ' . $expected . ' bytes but read (from Nextcloud client) and wrote (to Nextcloud storage) ' . $bytesWritten . ' bytes. Could either be a network problem on the sending side or a problem writing to the storage on the server side.');
				}
			}
		}

		if ($chunk_handler->isComplete()) {
			/** @var Storage $storage */
			[$storage,] = $this->fileView->resolvePath($path);
			$needsPartFile = $storage->needsPartFile();
			$partFile = null;

			$targetPath = $path . '/' . $info['name'];
			/** @var \OC\Files\Storage\Storage $targetStorage */
			[$targetStorage, $targetInternalPath] = $this->fileView->resolvePath($targetPath);

			$exists = $this->fileView->file_exists($targetPath);

			try {
				$this->fileView->lockFile($targetPath, ILockingProvider::LOCK_SHARED);

				$this->emitPreHooks($exists, $targetPath);
				$this->fileView->changeLock($targetPath, ILockingProvider::LOCK_EXCLUSIVE);
				/** @var \OC\Files\Storage\Storage $targetStorage */
				[$targetStorage, $targetInternalPath] = $this->fileView->resolvePath($targetPath);

				if ($needsPartFile) {
					// we first assembly the target file as a part file
					$partFile = $this->getPartFileBasePath($path . '/' . $info['name']) . '.ocTransferId' . $info['transferid'] . '.part';
					/** @var \OC\Files\Storage\Storage $targetStorage */
					[$partStorage, $partInternalPath] = $this->fileView->resolvePath($partFile);


					$chunk_handler->file_assemble($partStorage, $partInternalPath);

					// here is the final atomic rename
					$renameOkay = $targetStorage->moveFromStorage($partStorage, $partInternalPath, $targetInternalPath);
					$fileExists = $targetStorage->file_exists($targetInternalPath);
					if ($renameOkay === false || $fileExists === false) {
						\OC::$server->getLogger()->error('\OC\Files\Filesystem::rename() failed', ['app' => 'webdav']);
						// only delete if an error occurred and the target file was already created
						if ($fileExists) {
							// set to null to avoid double-deletion when handling exception
							// stray part file
							$partFile = null;
							$targetStorage->unlink($targetInternalPath);
						}
						$this->fileView->changeLock($targetPath, ILockingProvider::LOCK_SHARED);
						throw new Exception('Could not rename part file assembled from chunks');
					}
				} else {
					// assemble directly into the final file
					$chunk_handler->file_assemble($targetStorage, $targetInternalPath);
				}

				// allow sync clients to send the mtime along in a header
				if (isset($this->request->server['HTTP_X_OC_MTIME'])) {
					$mtime = $this->sanitizeMtime($this->request->server['HTTP_X_OC_MTIME']);
					if ($targetStorage->touch($targetInternalPath, $mtime)) {
						$this->header('X-OC-MTime: accepted');
					}
				}

				// since we skipped the view we need to scan and emit the hooks ourselves
				$targetStorage->getUpdater()->update($targetInternalPath);

				$this->fileView->changeLock($targetPath, ILockingProvider::LOCK_SHARED);

				$this->emitPostHooks($exists, $targetPath);

				// FIXME: should call refreshInfo but can't because $this->path is not the of the final file
				$info = $this->fileView->getFileInfo($targetPath);

				if (isset($this->request->server['HTTP_OC_CHECKSUM'])) {
					$checksum = trim($this->request->server['HTTP_OC_CHECKSUM']);
					$this->fileView->putFileInfo($targetPath, ['checksum' => $checksum]);
				} elseif ($info->getChecksum() !== null && $info->getChecksum() !== '') {
					$this->fileView->putFileInfo($this->path, ['checksum' => '']);
				}

				$this->fileView->unlockFile($targetPath, ILockingProvider::LOCK_SHARED);

				return $info->getEtag();
			} catch (\Exception $e) {
				if ($partFile !== null) {
					$targetStorage->unlink($targetInternalPath);
				}
				$this->convertToSabreException($e);
			}
		}

		return null;
	}

	/**
	 * Convert the given exception to a SabreException instance
	 *
	 * @param \Exception $e
	 *
	 * @throws \Sabre\DAV\Exception
	 */
	private function convertToSabreException(\Exception $e) {
		if ($e instanceof \Sabre\DAV\Exception) {
			throw $e;
		}
		if ($e instanceof NotPermittedException) {
			// a more general case - due to whatever reason the content could not be written
			throw new Forbidden($e->getMessage(), 0, $e);
		}
		if ($e instanceof ForbiddenException) {
			// the path for the file was forbidden
			throw new DAVForbiddenException($e->getMessage(), $e->getRetry(), $e);
		}
		if ($e instanceof EntityTooLargeException) {
			// the file is too big to be stored
			throw new EntityTooLarge($e->getMessage(), 0, $e);
		}
		if ($e instanceof InvalidContentException) {
			// the file content is not permitted
			throw new UnsupportedMediaType($e->getMessage(), 0, $e);
		}
		if ($e instanceof InvalidPathException) {
			// the path for the file was not valid
			// TODO: find proper http status code for this case
			throw new Forbidden($e->getMessage(), 0, $e);
		}
		if ($e instanceof LockedException || $e instanceof LockNotAcquiredException) {
			// the file is currently being written to by another process
			throw new FileLocked($e->getMessage(), $e->getCode(), $e);
		}
		if ($e instanceof GenericEncryptionException) {
			// returning 503 will allow retry of the operation at a later point in time
			throw new ServiceUnavailable('Encryption not ready: ' . $e->getMessage(), 0, $e);
		}
		if ($e instanceof StorageNotAvailableException) {
			throw new ServiceUnavailable('Failed to write file contents: ' . $e->getMessage(), 0, $e);
		}
		if ($e instanceof NotFoundException) {
			throw new NotFound('File not found: ' . $e->getMessage(), 0, $e);
		}

		throw new \Sabre\DAV\Exception($e->getMessage(), 0, $e);
	}

	/**
	 * Get the checksum for this file
	 *
	 * @return string|null
	 */
	public function getChecksum() {
		if (!$this->info) {
			return null;
		}
		return $this->info->getChecksum();
	}

	public function setChecksum(string $checksum) {
		$this->fileView->putFileInfo($this->path, ['checksum' => $checksum]);
		$this->refreshInfo();
	}

	protected function header($string) {
		if (!\OC::$CLI) {
			\header($string);
		}
	}

	public function hash(string $type) {
		return $this->fileView->hash($type, $this->path);
	}
}
