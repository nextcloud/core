<?php
/**
 * @author Robin Appelman <robin@icewind.nl>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Files_External\Lib\Storage;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;
use OC\Files\Storage\Common;
use OC\Files\Storage\PolyFill\CopyDirectory;
use OCP\Constants;
use OCP\Files\FileInfo;
use OCP\Files\StorageNotAvailableException;

class FTP extends Common {
	use CopyDirectory;

	private $root;
	private $host;
	private $password;
	private $username;
	private $secure;
	private $port;
	private $utf8Mode;

	/** @var FtpConnection|null */
	private $connection;

	public function __construct($params) {
		if (isset($params['host']) && isset($params['user']) && isset($params['password'])) {
			$this->host = $params['host'];
			$this->username = $params['user'];
			$this->password = $params['password'];
			if (isset($params['secure'])) {
				if (is_string($params['secure'])) {
					$this->secure = ($params['secure'] === 'true');
				} else {
					$this->secure = (bool)$params['secure'];
				}
			} else {
				$this->secure = false;
			}
			$this->root = isset($params['root']) ? '/' . ltrim($params['root']) : '/';
			$this->port = $params['port'] ?? 21;
			$this->utf8Mode = isset($params['utf8']) && $params['utf8'];
		} else {
			throw new \Exception('Creating ' . self::class . ' storage failed, required parameters not set');
		}
	}

	public function __destruct() {
		$this->connection = null;
	}

	protected function getConnection(): FtpConnection {
		if (!$this->connection) {
			try {
				$this->connection = new FtpConnection(
					$this->secure,
					$this->host,
					$this->port,
					$this->username,
					$this->password
				);
			} catch (\Exception $e) {
				throw new StorageNotAvailableException("Failed to create ftp connection", 0, $e);
			}
			if ($this->utf8Mode) {
				if (!$this->connection->setUtf8Mode()) {
					throw new StorageNotAvailableException("Could not set UTF-8 mode");
				}
			}
		}

		return $this->connection;
	}

	public function getId() {
		return 'ftp::' . $this->username . '@' . $this->host . '/' . $this->root;
	}

	protected function buildPath($path) {
		return rtrim($this->root . '/' . $path, '/');
	}

	public static function checkDependencies() {
		if (function_exists('ftp_login')) {
			return (true);
		} else {
			return ['ftp'];
		}
	}

	public function filemtime($path) {
		$result = $this->getConnection()->mdtm($this->buildPath($path));

		if ($result === -1) {
			if ($this->is_dir($path)) {
				$list = $this->getConnection()->mlsd($this->buildPath($path));
				if (!$list) {
					\OC::$server->getLogger()->warning("Unable to get last modified date for ftp folder ($path), failed to list folder contents");
					return time();
				}
				$currentDir = current(array_filter($list, function ($item) {
					return $item['type'] === 'cdir';
				}));
				if ($currentDir) {
					$time = \DateTime::createFromFormat('YmdHis', $currentDir['modify']);
					if ($time === false) {
						throw new \Exception("Invalid date format for directory: $currentDir");
					}
					return $time->getTimestamp();
				} else {
					\OC::$server->getLogger()->warning("Unable to get last modified date for ftp folder ($path), folder contents doesn't include current folder");
					return time();
				}
			} else {
				return false;
			}
		} else {
			return $result;
		}
	}

	public function filesize($path) {
		$result = $this->getConnection()->size($this->buildPath($path));
		if ($result === -1) {
			return false;
		} else {
			return $result;
		}
	}

	public function rmdir($path) {
		if ($this->is_dir($path)) {
			$result = $this->getConnection()->rmdir($this->buildPath($path));
			// recursive rmdir support depends on the ftp server
			if ($result) {
				return $result;
			} else {
				return $this->recursiveRmDir($path);
			}
		} elseif ($this->is_file($path)) {
			return $this->unlink($path);
		} else {
			return false;
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	private function recursiveRmDir($path): bool {
		$contents = $this->getDirectoryContent($path);
		$result = true;
		foreach ($contents as $content) {
			if ($content['mimetype'] === FileInfo::MIMETYPE_FOLDER) {
				$result = $result && $this->recursiveRmDir($path . '/' . $content['name']);
			} else {
				$result = $result && $this->getConnection()->delete($this->buildPath($path . '/' . $content['name']));
			}
		}
		$result = $result && $this->getConnection()->rmdir($this->buildPath($path));

		return $result;
	}

	public function test() {
		try {
			return $this->getConnection()->systype() !== false;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function stat($path) {
		if (!$this->file_exists($path)) {
			return false;
		}
		return [
			'mtime' => $this->filemtime($path),
			'size' => $this->filesize($path),
		];
	}

	public function file_exists($path) {
		if ($path === '' || $path === '.' || $path === '/') {
			return true;
		}
		return $this->filetype($path) !== false;
	}

	public function unlink($path) {
		switch ($this->filetype($path)) {
			case 'dir':
				return $this->rmdir($path);
			case 'file':
				return $this->getConnection()->delete($this->buildPath($path));
			default:
				return false;
		}
	}

	public function opendir($path) {
		$files = $this->getConnection()->nlist($this->buildPath($path));
		return IteratorDirectory::wrap($files);
	}

	public function mkdir($path) {
		if ($this->is_dir($path)) {
			return false;
		}
		return $this->getConnection()->mkdir($this->buildPath($path)) !== false;
	}

	public function is_dir($path) {
		if ($path === "") {
			return true;
		}
		if ($this->getConnection()->chdir($this->buildPath($path)) === true) {
			$this->getConnection()->chdir('/');
			return true;
		} else {
			return false;
		}
	}

	public function is_file($path) {
		return $this->filesize($path) !== false;
	}

	public function filetype($path) {
		if ($this->is_dir($path)) {
			return 'dir';
		} elseif ($this->is_file($path)) {
			return 'file';
		} else {
			return false;
		}
	}

	public function fopen($path, $mode) {
		$useExisting = true;
		switch ($mode) {
			case 'r':
			case 'rb':
				return $this->readStream($path);
			case 'w':
			case 'w+':
			case 'wb':
			case 'wb+':
				$useExisting = false;
			// no break
			case 'a':
			case 'ab':
			case 'r+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':
				//emulate these
				if ($useExisting and $this->file_exists($path)) {
					if (!$this->isUpdatable($path)) {
						return false;
					}
					$tmpFile = $this->getCachedFile($path);
				} else {
					if (!$this->isCreatable(dirname($path))) {
						return false;
					}
					$tmpFile = \OC::$server->getTempManager()->getTemporaryFile();
				}
				$source = fopen($tmpFile, $mode);
				return CallbackWrapper::wrap($source, null, null, function () use ($tmpFile, $path) {
					$this->writeStream($path, fopen($tmpFile, 'r'));
					unlink($tmpFile);
				});
		}
		return false;
	}

	public function writeStream(string $path, $stream, int $size = null): int {
		if ($size === null) {
			$stream = CountWrapper::wrap($stream, function ($writtenSize) use (&$size) {
				$size = $writtenSize;
			});
		}

		$this->getConnection()->fput($this->buildPath($path), $stream);
		fclose($stream);

		return $size;
	}

	public function readStream(string $path) {
		$stream = fopen('php://temp', 'w+');
		$result = $this->getConnection()->fget($stream, $this->buildPath($path));
		rewind($stream);

		if (!$result) {
			fclose($stream);
			return false;
		}
		return $stream;
	}

	public function touch($path, $mtime = null) {
		if ($this->file_exists($path)) {
			return false;
		} else {
			$this->file_put_contents($path, '');
			return true;
		}
	}

	public function rename($path1, $path2) {
		$this->unlink($path2);
		return $this->getConnection()->rename($this->buildPath($path1), $this->buildPath($path2));
	}

	public function getDirectoryContent($directory): \Traversable {
		$files = $this->getConnection()->mlsd($this->buildPath($directory));
		$mimeTypeDetector = \OC::$server->getMimeTypeDetector();

		foreach ($files as $file) {
			$name = $file['name'];
			if ($file['type'] === 'cdir' || $file['type'] === 'pdir') {
				continue;
			}
			$permissions = Constants::PERMISSION_ALL - Constants::PERMISSION_CREATE;
			$isDir = $file['type'] === 'dir';
			if ($isDir) {
				$permissions += Constants::PERMISSION_CREATE;
			}

			$data = [];
			$data['mimetype'] = $isDir ? FileInfo::MIMETYPE_FOLDER : $mimeTypeDetector->detectPath($name);
			$data['mtime'] = \DateTime::createFromFormat('YmdGis', $file['modify'])->getTimestamp();
			if ($data['mtime'] === false) {
				$data['mtime'] = time();
			}
			if ($isDir) {
				$data['size'] = -1; //unknown
			} elseif (isset($file['size'])) {
				$data['size'] = $file['size'];
			} else {
				$data['size'] = $this->filesize($directory . '/' . $name);
			}
			$data['etag'] = uniqid();
			$data['storage_mtime'] = $data['mtime'];
			$data['permissions'] = $permissions;
			$data['name'] = $name;

			yield $data;
		}
	}
}
