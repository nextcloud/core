<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Wrapped;

use Icewind\SMB\AbstractShare;
use Icewind\SMB\ACL;
use Icewind\SMB\Exception\ConnectionException;
use Icewind\SMB\Exception\DependencyException;
use Icewind\SMB\Exception\FileInUseException;
use Icewind\SMB\Exception\InvalidTypeException;
use Icewind\SMB\Exception\NotFoundException;
use Icewind\SMB\Exception\InvalidRequestException;
use Icewind\SMB\IFileInfo;
use Icewind\SMB\INotifyHandler;
use Icewind\SMB\IServer;
use Icewind\SMB\ISystem;
use Icewind\Streams\CallbackWrapper;
use Icewind\SMB\Native\NativeShare;
use Icewind\SMB\Native\NativeServer;

class Share extends AbstractShare {
	/**
	 * @var IServer $server
	 */
	private $server;

	/**
	 * @var string $name
	 */
	private $name;

	/**
	 * @var Connection $connection
	 */
	public $connection;

	/**
	 * @var Parser
	 */
	protected $parser;

	/**
	 * @var ISystem
	 */
	private $system;

	const MODE_MAP = [
		FileInfo::MODE_READONLY => 'r',
		FileInfo::MODE_HIDDEN   => 'h',
		FileInfo::MODE_ARCHIVE  => 'a',
		FileInfo::MODE_SYSTEM   => 's'
	];

	const EXEC_CMD = 'exec';

	/**
	 * @param IServer $server
	 * @param string $name
	 * @param ISystem $system
	 */
	public function __construct(IServer $server, $name, ISystem $system) {
		parent::__construct();
		$this->server = $server;
		$this->name = $name;
		$this->system = $system;
		$this->parser = new Parser($server->getTimeZone());
	}

	private function getAuthFileArgument() {
		if ($this->server->getAuth()->getUsername()) {
			return '--authentication-file=' . $this->system->getFD(3);
		} else {
			return '';
		}
	}

	protected function getConnection() {
		$command = sprintf(
			'%s %s%s -t %s %s %s %s',
			self::EXEC_CMD,
			$this->system->getStdBufPath() ? $this->system->getStdBufPath() . ' -o0 ' : '',
			$this->system->getSmbclientPath(),
			$this->server->getOptions()->getTimeout(),
			$this->getAuthFileArgument(),
			$this->server->getAuth()->getExtraCommandLineArguments(),
			escapeshellarg('//' . $this->server->getHost() . '/' . $this->name)
		);
		$connection = new Connection($command, $this->parser);
		$connection->writeAuthentication($this->server->getAuth()->getUsername(), $this->server->getAuth()->getPassword());
		$connection->connect();
		if (!$connection->isValid()) {
			throw new ConnectionException($connection->readLine());
		}
		// some versions of smbclient add a help message in first of the first prompt
		$connection->clearTillPrompt();
		return $connection;
	}

	/**
	 * @throws \Icewind\SMB\Exception\ConnectionException
	 * @throws \Icewind\SMB\Exception\AuthenticationException
	 * @throws \Icewind\SMB\Exception\InvalidHostException
	 */
	protected function connect() {
		if ($this->connection and $this->connection->isValid()) {
			return;
		}
		$this->connection = $this->getConnection();
	}

	protected function reconnect() {
		$this->connection->reconnect();
		if (!$this->connection->isValid()) {
			throw new ConnectionException();
		}
	}

	/**
	 * Get the name of the share
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	protected function simpleCommand($command, $path) {
		$escapedPath = $this->escapePath($path);
		$cmd = $command . ' ' . $escapedPath;
		$output = $this->execute($cmd);
		return $this->parseOutput($output, $path);
	}

	/**
	 * List the content of a remote folder
	 *
	 * @param $path
	 * @return \Icewind\SMB\IFileInfo[]
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function dir($path) {
		$escapedPath = $this->escapePath($path);
		$output = $this->execute('cd ' . $escapedPath);
		//check output for errors
		$this->parseOutput($output, $path);
		$output = $this->execute('dir');

		$this->execute('cd /');

		return $this->parser->parseDir($output, $path, function ($path) {
			return $this->getAcls($path);
		});
	}

	/**
	 * @param string $path
	 * @return \Icewind\SMB\IFileInfo
	 */
	public function stat($path) {
		// some windows server setups don't seem to like the allinfo command
		// use the dir command instead to get the file info where possible
		if ($path !== "" && $path !== "/") {
			$parent = dirname($path);
			$dir = $this->dir($parent);
			$file = array_values(array_filter($dir, function (IFileInfo $info) use ($path) {
				return $info->getPath() === $path;
			}));
			if ($file) {
				return $file[0];
			}
		}

		$escapedPath = $this->escapePath($path);
		$output = $this->execute('allinfo ' . $escapedPath);
		// Windows and non Windows Fileserver may respond different
		// to the allinfo command for directories. If the result is a single
		// line = error line, redo it with a different allinfo parameter
		if ($escapedPath == '""' && count($output) < 2) {
			$output = $this->execute('allinfo ' . '"."');
		}
		if (count($output) < 3) {
			$this->parseOutput($output, $path);
		}
		$stat = $this->parser->parseStat($output);
		return new FileInfo($path, basename($path), $stat['size'], $stat['mtime'], $stat['mode'], function () use ($path) {
			return $this->getAcls($path);
		});
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
		return $this->simpleCommand('mkdir', $path);
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
		return $this->simpleCommand('rmdir', $path);
	}

	/**
	 * Delete a file on the share
	 *
	 * @param string $path
	 * @param bool $secondTry
	 * @return bool
	 * @throws InvalidTypeException
	 * @throws NotFoundException
	 * @throws \Exception
	 */
	public function del($path, $secondTry = false) {
		//del return a file not found error when trying to delete a folder
		//we catch it so we can check if $path doesn't exist or is of invalid type
		try {
			return $this->simpleCommand('del', $path);
		} catch (NotFoundException $e) {
			//no need to do anything with the result, we just check if this throws the not found error
			try {
				$this->simpleCommand('ls', $path);
			} catch (NotFoundException $e2) {
				throw $e;
			} catch (\Exception $e2) {
				throw new InvalidTypeException($path);
			}
			throw $e;
		} catch (FileInUseException $e) {
			if ($secondTry) {
				throw $e;
			}
			$this->reconnect();
			return $this->del($path, true);
		}
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
		$path1 = $this->escapePath($from);
		$path2 = $this->escapePath($to);
		$output = $this->execute('rename ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output, $to);
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
		$path1 = $this->escapeLocalPath($source); //first path is local, needs different escaping
		$path2 = $this->escapePath($target);
		$output = $this->execute('put ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output, $target);
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
	 */
	public function get($source, $target) {
		$path1 = $this->escapePath($source);
		$path2 = $this->escapeLocalPath($target); //second path is local, needs different escaping
		$output = $this->execute('get ' . $path1 . ' ' . $path2);
		return $this->parseOutput($output, $source);
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
		$source = $this->escapePath($source);
		// since returned stream is closed by the caller we need to create a new instance
		// since we can't re-use the same file descriptor over multiple calls
		$connection = $this->getConnection();

		$connection->write('get ' . $source . ' ' . $this->system->getFD(5));
		$connection->write('exit');
		$fh = $connection->getFileOutputStream();
		stream_context_set_option($fh, 'file', 'connection', $connection);
		return $fh;
	}

	/**
	 * Open a writable stream to a remote file
	 *
	 * @param string $target
	 * @return resource a write only stream to upload a remote file
	 *
	 * @throws \Icewind\SMB\Exception\NotFoundException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 */
	public function write($target) {
		$target = $this->escapePath($target);
		// since returned stream is closed by the caller we need to create a new instance
		// since we can't re-use the same file descriptor over multiple calls
		$connection = $this->getConnection();

		$fh = $connection->getFileInputStream();
		$connection->write('put ' . $this->system->getFD(4) . ' ' . $target);
		$connection->write('exit');

		// use a close callback to ensure the upload is finished before continuing
		// this also serves as a way to keep the connection in scope
		return CallbackWrapper::wrap($fh, null, null, function () use ($connection, $target) {
			$connection->close(false); // dont terminate, give the upload some time
		});
	}

	/**
	 * Append to stream
	 * Note: smbclient does not support this (Use php-libsmbclient)
	 *
	 * @param string $target
	 *
	 * @throws \Icewind\SMB\Exception\DependencyException
	 */
	public function append($target) {
		throw new DependencyException('php-libsmbclient is required for append');
	}

	/**
	 * @param string $path
	 * @param int $mode a combination of FileInfo::MODE_READONLY, FileInfo::MODE_ARCHIVE, FileInfo::MODE_SYSTEM and FileInfo::MODE_HIDDEN, FileInfo::NORMAL
	 * @return mixed
	 */
	public function setMode($path, $mode) {
		$modeString = '';
		foreach (self::MODE_MAP as $modeByte => $string) {
			if ($mode & $modeByte) {
				$modeString .= $string;
			}
		}
		$path = $this->escapePath($path);

		// first reset the mode to normal
		$cmd = 'setmode ' . $path . ' -rsha';
		$output = $this->execute($cmd);
		$this->parseOutput($output, $path);

		if ($mode !== FileInfo::MODE_NORMAL) {
			// then set the modes we want
			$cmd = 'setmode ' . $path . ' ' . $modeString;
			$output = $this->execute($cmd);
			return $this->parseOutput($output, $path);
		} else {
			return true;
		}
	}

	/**
	 * @param string $path
	 * @return INotifyHandler
	 * @throws ConnectionException
	 * @throws DependencyException
	 */
	public function notify($path) {
		if (!$this->system->getStdBufPath()) { //stdbuf is required to disable smbclient's output buffering
			throw new DependencyException('stdbuf is required for usage of the notify command');
		}
		$connection = $this->getConnection(); // use a fresh connection since the notify command blocks the process
		$command = 'notify ' . $this->escapePath($path);
		$connection->write($command . PHP_EOL);
		return new NotifyHandler($connection, $path);
	}

	/**
	 * @param string $command
	 * @return array
	 */
	protected function execute($command) {
		$this->connect();
		$this->connection->write($command . PHP_EOL);
		return $this->connection->read();
	}

	/**
	 * check output for errors
	 *
	 * @param string[] $lines
	 * @param string $path
	 *
	 * @return bool
	 * @throws \Icewind\SMB\Exception\AlreadyExistsException
	 * @throws \Icewind\SMB\Exception\AccessDeniedException
	 * @throws \Icewind\SMB\Exception\NotEmptyException
	 * @throws \Icewind\SMB\Exception\InvalidTypeException
	 * @throws \Icewind\SMB\Exception\Exception
	 * @throws NotFoundException
	 */
	protected function parseOutput($lines, $path = '') {
		if (count($lines) === 0) {
			return true;
		} else {
			$this->parser->checkForError($lines, $path);
			return false;
		}
	}

	/**
	 * @param string $string
	 * @return string
	 */
	protected function escape($string) {
		return escapeshellarg($string);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function escapePath($path) {
		$this->verifyPath($path);
		if ($path === '/') {
			$path = '';
		}
		$path = str_replace('/', '\\', $path);
		$path = str_replace('"', '^"', $path);
		$path = ltrim($path, '\\');
		return '"' . $path . '"';
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function escapeLocalPath($path) {
		$path = str_replace('"', '\"', $path);
		return '"' . $path . '"';
	}

	protected function getAcls($path) {
		$commandPath = $this->system->getSmbcAclsPath();
		if (!$commandPath) {
			return [];
		}

		$command = sprintf(
			'%s %s %s %s/%s %s',
			$commandPath,
			$this->getAuthFileArgument(),
			$this->server->getAuth()->getExtraCommandLineArguments(),
			escapeshellarg('//' . $this->server->getHost()),
			escapeshellarg($this->name),
			escapeshellarg($path)
		);
		$connection = new RawConnection($command);
		$connection->writeAuthentication($this->server->getAuth()->getUsername(), $this->server->getAuth()->getPassword());
		$connection->connect();
		if (!$connection->isValid()) {
			throw new ConnectionException($connection->readLine());
		}

		$rawAcls = $connection->readAll();

		$acls = [];
		foreach ($rawAcls as $acl) {
			[$type, $acl] = explode(':', $acl, 2);
			if ($type !== 'ACL') {
				continue;
			}
			[$user, $permissions] = explode(':', $acl, 2);
			[$type, $flags, $mask] = explode('/', $permissions);

			$type = $type === 'ALLOWED' ? ACL::TYPE_ALLOW : ACL::TYPE_DENY;

			$flagsInt = 0;
			foreach (explode('|', $flags) as $flagString) {
				if ($flagString === 'OI') {
					$flagsInt += ACL::FLAG_OBJECT_INHERIT;
				} elseif ($flagString === 'CI') {
					$flagsInt += ACL::FLAG_CONTAINER_INHERIT;
				}
			}

			if (substr($mask, 0, 2) === '0x') {
				$maskInt = hexdec($mask);
			} else {
				$maskInt = 0;
				foreach (explode('|', $mask) as $maskString) {
					if ($maskString === 'R') {
						$maskInt += ACL::MASK_READ;
					} elseif ($maskString === 'W') {
						$maskInt += ACL::MASK_WRITE;
					} elseif ($maskString === 'X') {
						$maskInt += ACL::MASK_EXECUTE;
					} elseif ($maskString === 'D') {
						$maskInt += ACL::MASK_DELETE;
					} elseif ($maskString === 'READ') {
						$maskInt += ACL::MASK_READ + ACL::MASK_EXECUTE;
					} elseif ($maskString === 'CHANGE') {
						$maskInt += ACL::MASK_READ + ACL::MASK_EXECUTE + ACL::MASK_WRITE + ACL::MASK_DELETE;
					} elseif ($maskString === 'FULL') {
						$maskInt += ACL::MASK_READ + ACL::MASK_EXECUTE + ACL::MASK_WRITE + ACL::MASK_DELETE;
					}
				}
			}

			if (isset($acls[$user])) {
				$existing = $acls[$user];
				$maskInt += $existing->getMask();
			}
			$acls[$user] = new ACL($type, $flagsInt, $maskInt);
		}

		return $acls;
	}

	public function __destruct() {
		unset($this->connection);
	}
}
