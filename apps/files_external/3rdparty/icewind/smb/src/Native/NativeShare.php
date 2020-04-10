<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Native;

use Icewind\SMB\AbstractShare;
use Icewind\SMB\Exception\DependencyException;
use Icewind\SMB\Exception\InvalidPathException;
use Icewind\SMB\Exception\InvalidResourceException;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IServer;
use Icewind\SMB\Wrapped\Server;
use Icewind\SMB\Wrapped\Share;

class NativeShare extends AbstractShare {
	/**
	 * @var IServer $server
	 */
	private $server;

	/**
	 * @var string $name
	 */
	private $name;

	/**
	 * @var NativeState $state
	 */
	private $state;

	/**
	 * @param IServer $server
	 * @param string $name
	 */
	public function __construct($server, $name) {
		parent::__construct();
		$this->server = $server;
		$this->name = $name;
	}

	/**
	 * @throws \Icewind\SMB\Exception\ConnectionException
	 * @throws \Icewind\SMB\Exception\AuthenticationException
	 * @throws \Icewind\SMB\Exception\InvalidHostException
	 */
	protected function getState() {
		if ($this->state and $this->state instanceof NativeState) {
			return $this->state;
		}

		$this->state = new NativeState();
		$this->state->init($this->server->getAuth(), $this->server->getOptions());
		return $this->state;
	}

	/**
	 * Get the name of the share
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	private function buildUrl($path) {
		$this->verifyPath($path);
		$url = sprintf('smb://%s/%s', $this->server->getHost(), $this->name);
		if ($path) {
			$path = trim($path, '/');
			$url .= '/';
			$url .= implode('/', array_map('rawurlencode', explode('/', $path)));
		}
		return $url;
	}

	/**
	 * List the content of a remote folder
	 *
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo[]
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function dir($path) {
		$files = [];

		$dh = $this->getState()->opendir($this->buildUrl($path));
		while ($file = $this->getState()->readdir($dh)) {
			$name = $file['name'];
			if ($name !== '.' and $name !== '..') {
				$fullPath = $path . '/' . $name;
				$files [] = new NativeFileInfo($this, $fullPath, $name);
			}
		}

		$this->getState()->closedir($dh);
		return $files;
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo
	 */
	public function stat($path) {
		$info = new NativeFileInfo($this, $path, self::mb_basename($path));

		// trigger attribute loading
		$info->getSize();

		return $info;
	}

	/**
	 * Multibyte unicode safe version of basename()
	 *
	 * @param string $path
	 * @link http://php.net/manual/en/function.basename.php#121405
	 * @return string
	 */
	protected static function mb_basename($path) {
		if (preg_match('@^.*[\\\\/]([^\\\\/]+)$@s', $path, $matches)) {
			return $matches[1];
		} elseif (preg_match('@^([^\\\\/]+)$@s', $path, $matches)) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Create a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\AlreadyExistsException
	 */
	public function mkdir($path) {
		return $this->getState()->mkdir($this->buildUrl($path));
	}

	/**
	 * Remove a folder on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function rmdir($path) {
		return $this->getState()->rmdir($this->buildUrl($path));
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function del($path) {
		return $this->getState()->unlink($this->buildUrl($path));
	}

	/**
	 * Rename a remote file
	 *
	 * @param string $from
	 * @param string $to
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\AlreadyExistsException
	 */
	public function rename($from, $to) {
		return $this->getState()->rename($this->buildUrl($from), $this->buildUrl($to));
	}

	/**
	 * Upload a local file
	 *
	 * @param string $source local file
	 * @param string $target remove file
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function put($source, $target) {
		$sourceHandle = fopen($source, 'rb');
		$targetUrl = $this->buildUrl($target);

		$targetHandle = $this->getState()->create($targetUrl);

		while ($data = fread($sourceHandle, NativeReadStream::CHUNK_SIZE)) {
			$this->getState()->write($targetHandle, $data, $targetUrl);
		}
		$this->getState()->close($targetHandle, $targetUrl);
		return true;
	}

	/**
	 * Download a remote file
	 *
	 * @param string $source remove file
	 * @param string $target local file
	 * @return bool
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 * @throws \Icewind\SMB\Exception\InvalidPathException
	 * @throws \Icewind\SMB\Exception\InvalidResourceException
	 */
	public function get($source, $target) {
		if (!$target) {
			throw new InvalidPathException('Invalid target path: Filename cannot be empty');
		}

		$sourceHandle = $this->getState()->open($this->buildUrl($source), 'r');
		if (!$sourceHandle) {
			throw new InvalidResourceException('Failed opening remote file "' . $source . '" for reading');
		}

		$targetHandle = @fopen($target, 'wb');
		if (!$targetHandle) {
			$error = error_get_last();
			if (is_array($error)) {
				$reason = $error['message'];
			} else {
				$reason = 'Unknown error';
			}
			$this->getState()->close($sourceHandle, $this->buildUrl($source));
			throw new InvalidResourceException('Failed opening local file "' . $target . '" for writing: ' . $reason);
		}

		while ($data = $this->getState()->read($sourceHandle, NativeReadStream::CHUNK_SIZE)) {
			fwrite($targetHandle, $data);
		}
		$this->getState()->close($sourceHandle, $this->buildUrl($source));
		return true;
	}

	/**
	 * Open a readable stream to a remote file
	 *
	 * @param string $source
	 * @return resource a read only stream with the contents of the remote file
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function read($source) {
		$url = $this->buildUrl($source);
		$handle = $this->getState()->open($url, 'r');
		return NativeReadStream::wrap($this->getState(), $handle, 'r', $url);
	}

	/**
	 * Open a writeable stream to a remote file
	 * Note: This method will truncate the file to 0bytes first
	 *
	 * @param string $source
	 * @return resource a writeable stream
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function write($source) {
		$url = $this->buildUrl($source);
		$handle = $this->getState()->create($url);
		return NativeWriteStream::wrap($this->getState(), $handle, 'w', $url);
	}

	/**
	 * Open a writeable stream and set the cursor to the end of the stream
	 *
	 * @param string $source
	 * @return resource a writeable stream
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function append($source) {
		$url = $this->buildUrl($source);
		$handle = $this->getState()->open($url, "a+");
		return NativeWriteStream::wrap($this->getState(), $handle, "a", $url);
	}

	/**
	 * Get extended attributes for the path
	 *
	 * @param string $path
	 * @param string $attribute attribute to get the info
	 * @return string the attribute value
	 */
	public function getAttribute($path, $attribute) {
		return $this->getState()->getxattr($this->buildUrl($path), $attribute);
	}

	/**
	 * Set extended attributes for the given path
	 *
	 * @param string $path
	 * @param string $attribute attribute to get the info
	 * @param string|int $value
	 * @return mixed the attribute value
	 */
	public function setAttribute($path, $attribute, $value) {
		if ($attribute === 'system.dos_attr.mode' and is_int($value)) {
			$value = '0x' . dechex($value);
		}

		return $this->getState()->setxattr($this->buildUrl($path), $attribute, $value);
	}

	/**
	 * Set DOS comaptible node mode
	 *
	 * @param string $path
	 * @param int $mode a combination of FileInfo::MODE_READONLY, FileInfo::MODE_ARCHIVE, FileInfo::MODE_SYSTEM and FileInfo::MODE_HIDDEN, FileInfo::NORMAL
	 * @return mixed
	 */
	public function setMode($path, $mode) {
		return $this->setAttribute($path, 'system.dos_attr.mode', $mode);
	}

	/**
	 * Start smb notify listener
	 * Note: This is a blocking call
	 *
	 * @param string $path
	 * @return INotifyHandler
	 */
	public function notify($path) {
		// php-smbclient does not support notify (https://github.com/eduardok/libsmbclient-php/issues/29)
		// so we use the smbclient based backend for this
		if (!Server::available($this->server->getSystem())) {
			throw new DependencyException('smbclient not found in path for notify command');
		}
		$share = new Share($this->server, $this->getName(), $this->server->getSystem());
		return $share->notify($path);
	}

	public function __destruct() {
		unset($this->state);
	}
}
