<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Juan Pablo Villafañez <jvillafanez@solidgear.es>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roland Tapken <roland@bitarbeiter.net>
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

use Icewind\SMB\ACL;
use Icewind\SMB\BasicAuth;
use Icewind\SMB\Exception\AlreadyExistsException;
use Icewind\SMB\Exception\ConnectException;
use Icewind\SMB\Exception\Exception;
use Icewind\SMB\Exception\ForbiddenException;
use Icewind\SMB\Exception\InvalidArgumentException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\Exception\OutOfSpaceException;
use Icewind\SMB\Exception\TimedOutException;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\Native\NativeServer;
use Icewind\SMB\Options;
use Icewind\SMB\ServerFactory;
use Icewind\SMB\System;
use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Cache\CappedMemoryCache;
use OC\Files\Filesystem;
use OC\Files\Storage\Common;
use OCA\Files_External\Lib\Notify\SMBNotifyHandler;
use OCP\Constants;
use OCP\Files\EntityTooLargeException;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\IRenameChange;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\INotifyStorage;
use OCP\Files\StorageAuthException;
use OCP\Files\StorageNotAvailableException;
use OCP\ILogger;

class SMB extends Common implements INotifyStorage {
	/**
	 * @var \Icewind\SMB\IServer
	 */
	protected $server;

	/**
	 * @var \Icewind\SMB\IShare
	 */
	protected $share;

	/**
	 * @var string
	 */
	protected $root;

	/**
	 * @var \Icewind\SMB\IFileInfo[]
	 */
	protected $statCache;

	/** @var ILogger */
	protected $logger;

	/** @var bool */
	protected $showHidden;

	/** @var bool */
	protected $checkAcl;

	public function __construct($params) {
		if (!isset($params['host'])) {
			throw new \Exception('Invalid configuration, no host provided');
		}

		if (isset($params['auth'])) {
			$auth = $params['auth'];
		} elseif (isset($params['user']) && isset($params['password']) && isset($params['share'])) {
			[$workgroup, $user] = $this->splitUser($params['user']);
			$auth = new BasicAuth($user, $workgroup, $params['password']);
		} else {
			throw new \Exception('Invalid configuration, no credentials provided');
		}

		if (isset($params['logger'])) {
			$this->logger = $params['logger'];
		} else {
			$this->logger = \OC::$server->getLogger();
		}

		$options = new Options();
		if (isset($params['timeout'])) {
			$timeout = (int)$params['timeout'];
			if ($timeout > 0) {
				$options->setTimeout($timeout);
			}
		}
		$serverFactory = new ServerFactory($options);
		$this->server = $serverFactory->createServer($params['host'], $auth);
		$this->share = $this->server->getShare(trim($params['share'], '/'));

		$this->root = $params['root'] ?? '/';
		$this->root = '/' . ltrim($this->root, '/');
		$this->root = rtrim($this->root, '/') . '/';

		$this->showHidden = isset($params['show_hidden']) && $params['show_hidden'];
		$this->checkAcl = isset($params['check_acl']) && $params['check_acl'];

		$this->statCache = new CappedMemoryCache();
		parent::__construct($params);
	}

	private function splitUser($user) {
		if (strpos($user, '/')) {
			return explode('/', $user, 2);
		} elseif (strpos($user, '\\')) {
			return explode('\\', $user);
		} else {
			return [null, $user];
		}
	}

	/**
	 * @return string
	 */
	public function getId() {
		// FIXME: double slash to keep compatible with the old storage ids,
		// failure to do so will lead to creation of a new storage id and
		// loss of shares from the storage
		return 'smb::' . $this->server->getAuth()->getUsername() . '@' . $this->server->getHost() . '//' . $this->share->getName() . '/' . $this->root;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function buildPath($path) {
		return Filesystem::normalizePath($this->root . '/' . $path, true, false, true);
	}

	protected function relativePath($fullPath) {
		if ($fullPath === $this->root) {
			return '';
		} elseif (substr($fullPath, 0, strlen($this->root)) === $this->root) {
			return substr($fullPath, strlen($this->root));
		} else {
			return null;
		}
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo
	 * @throws StorageAuthException
	 */
	protected function getFileInfo($path) {
		try {
			$path = $this->buildPath($path);
			if (!isset($this->statCache[$path])) {
				$this->statCache[$path] = $this->share->stat($path);
			}
			return $this->statCache[$path];
		} catch (ConnectException $e) {
			$this->throwUnavailable($e);
		} catch (ForbiddenException $e) {
			// with php-smbclient, this exceptions is thrown when the provided password is invalid.
			// Possible is also ForbiddenException with a different error code, so we check it.
			if ($e->getCode() === 1) {
				$this->throwUnavailable($e);
			}
			throw $e;
		}
	}

	/**
	 * @param \Exception $e
	 * @throws StorageAuthException
	 */
	protected function throwUnavailable(\Exception $e) {
		$this->logger->logException($e, ['message' => 'Error while getting file info']);
		throw new StorageAuthException($e->getMessage(), $e);
	}

	/**
	 * get the acl from fileinfo that is relevant for the configured user
	 *
	 * @param IFileInfo $file
	 * @return ACL|null
	 */
	private function getACL(IFileInfo $file): ?ACL {
		$acls = $file->getAcls();
		foreach ($acls as $user => $acl) {
			[, $user] = explode('\\', $user); // strip domain
			if ($user === $this->server->getAuth()->getUsername()) {
				return $acl;
			}
		}

		return null;
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo[]
	 * @throws StorageNotAvailableException
	 */
	protected function getFolderContents($path): iterable {
		try {
			$path = ltrim($this->buildPath($path), '/');
			try {
				$files = $this->share->dir($path);
			} catch (ForbiddenException $e) {
				throw new NotPermittedException();
			}
			foreach ($files as $file) {
				$this->statCache[$path . '/' . $file->getName()] = $file;
			}

			foreach ($files as $file) {
				try {
					// the isHidden check is done before checking the config boolean to ensure that the metadata is always fetch
					// so we trigger the below exceptions where applicable
					$hide = $file->isHidden() && !$this->showHidden;

					if ($this->checkAcl && $acl = $this->getACL($file)) {
						// if there is no explicit deny, we assume it's allowed
						// this doesn't take inheritance fully into account but if read permissions is denied for a parent we wouldn't be in this folder
						// additionally, it's better to have false negatives here then false positives
						if ($acl->denies(ACL::MASK_READ) || $acl->denies(ACL::MASK_EXECUTE)) {
							$this->logger->debug('Hiding non readable entry ' . $file->getName());
							return false;
						}
					}

					if ($hide) {
						$this->logger->debug('hiding hidden file ' . $file->getName());
					}
					if (!$hide) {
						yield $file;
					}
				} catch (ForbiddenException $e) {
					$this->logger->logException($e, ['level' => ILogger::DEBUG, 'message' => 'Hiding forbidden entry ' . $file->getName()]);
				} catch (NotFoundException $e) {
					$this->logger->logException($e, ['level' => ILogger::DEBUG, 'message' => 'Hiding not found entry ' . $file->getName()]);
				}
			}
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while getting folder content']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * @param \Icewind\SMB\IFileInfo $info
	 * @return array
	 */
	protected function formatInfo($info) {
		$result = [
			'size' => $info->getSize(),
			'mtime' => $info->getMTime(),
		];
		if ($info->isDirectory()) {
			$result['type'] = 'dir';
		} else {
			$result['type'] = 'file';
		}
		return $result;
	}

	/**
	 * Rename the files. If the source or the target is the root, the rename won't happen.
	 *
	 * @param string $source the old name of the path
	 * @param string $target the new name of the path
	 * @return bool true if the rename is successful, false otherwise
	 */
	public function rename($source, $target, $retry = true) {
		if ($this->isRootDir($source) || $this->isRootDir($target)) {
			return false;
		}

		$absoluteSource = $this->buildPath($source);
		$absoluteTarget = $this->buildPath($target);
		try {
			$result = $this->share->rename($absoluteSource, $absoluteTarget);
		} catch (AlreadyExistsException $e) {
			if ($retry) {
				$this->remove($target);
				$result = $this->share->rename($absoluteSource, $absoluteTarget, false);
			} else {
				$this->logger->logException($e, ['level' => ILogger::WARN]);
				return false;
			}
		} catch (InvalidArgumentException $e) {
			if ($retry) {
				$this->remove($target);
				$result = $this->share->rename($absoluteSource, $absoluteTarget, false);
			} else {
				$this->logger->logException($e, ['level' => ILogger::WARN]);
				return false;
			}
		} catch (\Exception $e) {
			$this->logger->logException($e, ['level' => ILogger::WARN]);
			return false;
		}
		unset($this->statCache[$absoluteSource], $this->statCache[$absoluteTarget]);
		return $result;
	}

	public function stat($path, $retry = true) {
		try {
			$result = $this->formatInfo($this->getFileInfo($path));
		} catch (ForbiddenException $e) {
			return false;
		} catch (NotFoundException $e) {
			return false;
		} catch (TimedOutException $e) {
			if ($retry) {
				return $this->stat($path, false);
			} else {
				throw $e;
			}
		}
		if ($this->remoteIsShare() && $this->isRootDir($path)) {
			$result['mtime'] = $this->shareMTime();
		}
		return $result;
	}

	/**
	 * get the best guess for the modification time of the share
	 *
	 * @return int
	 */
	private function shareMTime() {
		$highestMTime = 0;
		$files = $this->share->dir($this->root);
		foreach ($files as $fileInfo) {
			try {
				if ($fileInfo->getMTime() > $highestMTime) {
					$highestMTime = $fileInfo->getMTime();
				}
			} catch (NotFoundException $e) {
				// Ignore this, can happen on unavailable DFS shares
			} catch (ForbiddenException $e) {
				// Ignore this too - it's a symlink
			}
		}
		return $highestMTime;
	}

	/**
	 * Check if the path is our root dir (not the smb one)
	 *
	 * @param string $path the path
	 * @return bool
	 */
	private function isRootDir($path) {
		return $path === '' || $path === '/' || $path === '.';
	}

	/**
	 * Check if our root points to a smb share
	 *
	 * @return bool true if our root points to a share false otherwise
	 */
	private function remoteIsShare() {
		return $this->share->getName() && (!$this->root || $this->root === '/');
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function unlink($path) {
		if ($this->isRootDir($path)) {
			return false;
		}

		try {
			if ($this->is_dir($path)) {
				return $this->rmdir($path);
			} else {
				$path = $this->buildPath($path);
				unset($this->statCache[$path]);
				$this->share->del($path);
				return true;
			}
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while deleting file']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * check if a file or folder has been updated since $time
	 *
	 * @param string $path
	 * @param int $time
	 * @return bool
	 */
	public function hasUpdated($path, $time) {
		if (!$path and $this->root === '/') {
			// mtime doesn't work for shares, but giving the nature of the backend,
			// doing a full update is still just fast enough
			return true;
		} else {
			$actualTime = $this->filemtime($path);
			return $actualTime > $time;
		}
	}

	/**
	 * @param string $path
	 * @param string $mode
	 * @return resource|bool
	 */
	public function fopen($path, $mode) {
		$fullPath = $this->buildPath($path);
		try {
			switch ($mode) {
				case 'r':
				case 'rb':
					if (!$this->file_exists($path)) {
						return false;
					}
					return $this->share->read($fullPath);
				case 'w':
				case 'wb':
					$source = $this->share->write($fullPath);
					return CallBackWrapper::wrap($source, null, null, function () use ($fullPath) {
						unset($this->statCache[$fullPath]);
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
					//emulate these
					if (strrpos($path, '.') !== false) {
						$ext = substr($path, strrpos($path, '.'));
					} else {
						$ext = '';
					}
					if ($this->file_exists($path)) {
						if (!$this->isUpdatable($path)) {
							return false;
						}
						$tmpFile = $this->getCachedFile($path);
					} else {
						if (!$this->isCreatable(dirname($path))) {
							return false;
						}
						$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
					}
					$source = fopen($tmpFile, $mode);
					$share = $this->share;
					return CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $fullPath, $share) {
						unset($this->statCache[$fullPath]);
						$share->put($tmpFile, $fullPath);
						unlink($tmpFile);
					});
			}
			return false;
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		} catch (OutOfSpaceException $e) {
			throw new EntityTooLargeException("not enough available space to create file", 0, $e);
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while opening file']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function rmdir($path) {
		if ($this->isRootDir($path)) {
			return false;
		}

		try {
			$this->statCache = [];
			$content = $this->share->dir($this->buildPath($path));
			foreach ($content as $file) {
				if ($file->isDirectory()) {
					$this->rmdir($path . '/' . $file->getName());
				} else {
					$this->share->del($file->getPath());
				}
			}
			$this->share->rmdir($this->buildPath($path));
			return true;
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while removing folder']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function touch($path, $mtime = null) {
		try {
			if (!$this->file_exists($path)) {
				$fh = $this->share->write($this->buildPath($path));
				fclose($fh);
				return true;
			}
			return false;
		} catch (OutOfSpaceException $e) {
			throw new EntityTooLargeException("not enough available space to create file", 0, $e);
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while creating file']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function getMetaData($path) {
		$fileInfo = $this->getFileInfo($path);
		if (!$fileInfo) {
			return null;
		}

		return $this->getMetaDataFromFileInfo($fileInfo);
	}

	private function getMetaDataFromFileInfo(IFileInfo $fileInfo) {
		$permissions = Constants::PERMISSION_READ + Constants::PERMISSION_SHARE;

		if (!$fileInfo->isReadOnly()) {
			$permissions += Constants::PERMISSION_DELETE;
			$permissions += Constants::PERMISSION_UPDATE;
			if ($fileInfo->isDirectory()) {
				$permissions += Constants::PERMISSION_CREATE;
			}
		}

		$data = [];
		if ($fileInfo->isDirectory()) {
			$data['mimetype'] = 'httpd/unix-directory';
		} else {
			$data['mimetype'] = \OC::$server->getMimeTypeDetector()->detectPath($fileInfo->getPath());
		}
		$data['mtime'] = $fileInfo->getMTime();
		if ($fileInfo->isDirectory()) {
			$data['size'] = -1; //unknown
		} else {
			$data['size'] = $fileInfo->getSize();
		}
		$data['etag'] = $this->getETag($fileInfo->getPath());
		$data['storage_mtime'] = $data['mtime'];
		$data['permissions'] = $permissions;
		$data['name'] = $fileInfo->getName();

		return $data;
	}

	public function opendir($path) {
		try {
			$files = $this->getFolderContents($path);
		} catch (NotFoundException $e) {
			return false;
		} catch (NotPermittedException $e) {
			return false;
		}
		$names = array_map(function ($info) {
			/** @var \Icewind\SMB\IFileInfo $info */
			return $info->getName();
		}, iterator_to_array($files));
		return IteratorDirectory::wrap($names);
	}

	public function getDirectoryContent($directory): \Traversable {
		$files = $this->getFolderContents($directory);
		foreach ($files as $file) {
			yield $this->getMetaDataFromFileInfo($file);
		}
	}

	public function filetype($path) {
		try {
			return $this->getFileInfo($path)->isDirectory() ? 'dir' : 'file';
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		}
	}

	public function mkdir($path) {
		$path = $this->buildPath($path);
		try {
			$this->share->mkdir($path);
			return true;
		} catch (ConnectException $e) {
			$this->logger->logException($e, ['message' => 'Error while creating folder']);
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		} catch (Exception $e) {
			return false;
		}
	}

	public function file_exists($path) {
		try {
			$this->getFileInfo($path);
			return true;
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		} catch (ConnectException $e) {
			throw new StorageNotAvailableException($e->getMessage(), $e->getCode(), $e);
		}
	}

	public function isReadable($path) {
		try {
			$info = $this->getFileInfo($path);
			return $this->showHidden || !$info->isHidden();
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		}
	}

	public function isUpdatable($path) {
		try {
			$info = $this->getFileInfo($path);
			// following windows behaviour for read-only folders: they can be written into
			// (https://support.microsoft.com/en-us/kb/326549 - "cause" section)
			return ($this->showHidden || !$info->isHidden()) && (!$info->isReadOnly() || $this->is_dir($path));
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		}
	}

	public function isDeletable($path) {
		try {
			$info = $this->getFileInfo($path);
			return ($this->showHidden || !$info->isHidden()) && !$info->isReadOnly();
		} catch (NotFoundException $e) {
			return false;
		} catch (ForbiddenException $e) {
			return false;
		}
	}

	/**
	 * check if smbclient is installed
	 */
	public static function checkDependencies() {
		return (
			(bool)\OC_Helper::findBinaryPath('smbclient')
			|| NativeServer::available(new System())
		) ? true : ['smbclient'];
	}

	/**
	 * Test a storage for availability
	 *
	 * @return bool
	 */
	public function test() {
		try {
			return parent::test();
		} catch (Exception $e) {
			$this->logger->logException($e);
			return false;
		}
	}

	public function listen($path, callable $callback) {
		$this->notify($path)->listen(function (IChange $change) use ($callback) {
			if ($change instanceof IRenameChange) {
				return $callback($change->getType(), $change->getPath(), $change->getTargetPath());
			} else {
				return $callback($change->getType(), $change->getPath());
			}
		});
	}

	public function notify($path) {
		$path = '/' . ltrim($path, '/');
		$shareNotifyHandler = $this->share->notify($this->buildPath($path));
		return new SMBNotifyHandler($shareNotifyHandler, $this->root);
	}
}
