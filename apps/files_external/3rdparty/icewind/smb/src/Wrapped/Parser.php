<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Wrapped;

use Icewind\SMB\Exception\AccessDeniedException;
use Icewind\SMB\Exception\AlreadyExistsException;
use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\Exception;
use Icewind\SMB\Exception\FileInUseException;
use Icewind\SMB\Exception\InvalidHostException;
use Icewind\SMB\Exception\InvalidParameterException;
use Icewind\SMB\Exception\InvalidResourceException;
use Icewind\SMB\Exception\InvalidTypeException;
use Icewind\SMB\Exception\NoLoginServerException;
use Icewind\SMB\Exception\NotEmptyException;
use Icewind\SMB\Exception\NotFoundException;

class Parser {
	const MSG_NOT_FOUND = 'Error opening local file ';

	/**
	 * @var string
	 */
	protected $timeZone;

	/**
	 * @var string
	 */
	private $host;

	// see error.h
	const EXCEPTION_MAP = [
		ErrorCodes::LogonFailure      => AuthenticationException::class,
		ErrorCodes::PathNotFound      => NotFoundException::class,
		ErrorCodes::ObjectNotFound    => NotFoundException::class,
		ErrorCodes::NoSuchFile        => NotFoundException::class,
		ErrorCodes::NameCollision     => AlreadyExistsException::class,
		ErrorCodes::AccessDenied      => AccessDeniedException::class,
		ErrorCodes::DirectoryNotEmpty => NotEmptyException::class,
		ErrorCodes::FileIsADirectory  => InvalidTypeException::class,
		ErrorCodes::NotADirectory     => InvalidTypeException::class,
		ErrorCodes::SharingViolation  => FileInUseException::class,
		ErrorCodes::InvalidParameter  => InvalidParameterException::class
	];

	const MODE_STRINGS = [
		'R' => FileInfo::MODE_READONLY,
		'H' => FileInfo::MODE_HIDDEN,
		'S' => FileInfo::MODE_SYSTEM,
		'D' => FileInfo::MODE_DIRECTORY,
		'A' => FileInfo::MODE_ARCHIVE,
		'N' => FileInfo::MODE_NORMAL
	];

	/**
	 * @param string $timeZone
	 */
	public function __construct($timeZone) {
		$this->timeZone = $timeZone;
	}

	private function getErrorCode($line) {
		$parts = explode(' ', $line);
		foreach ($parts as $part) {
			if (substr($part, 0, 9) === 'NT_STATUS') {
				return $part;
			}
		}
		return false;
	}

	public function checkForError($output, $path) {
		if (strpos($output[0], 'does not exist')) {
			throw new NotFoundException($path);
		}
		$error = $this->getErrorCode($output[0]);

		if (substr($output[0], 0, strlen(self::MSG_NOT_FOUND)) === self::MSG_NOT_FOUND) {
			$localPath = substr($output[0], strlen(self::MSG_NOT_FOUND));
			throw new InvalidResourceException('Failed opening local file "' . $localPath . '" for writing');
		}

		throw Exception::fromMap(self::EXCEPTION_MAP, $error, $path);
	}

	/**
	 * check if the first line holds a connection failure
	 *
	 * @param $line
	 * @throws AuthenticationException
	 * @throws InvalidHostException
	 * @throws NoLoginServerException
	 * @throws AccessDeniedException
	 */
	public function checkConnectionError($line) {
		$line = rtrim($line, ')');
		if (substr($line, -23) === ErrorCodes::LogonFailure) {
			throw new AuthenticationException('Invalid login');
		}
		if (substr($line, -26) === ErrorCodes::BadHostName) {
			throw new InvalidHostException('Invalid hostname');
		}
		if (substr($line, -22) === ErrorCodes::Unsuccessful) {
			throw new InvalidHostException('Connection unsuccessful');
		}
		if (substr($line, -28) === ErrorCodes::ConnectionRefused) {
			throw new InvalidHostException('Connection refused');
		}
		if (substr($line, -26) === ErrorCodes::NoLogonServers) {
			throw new NoLoginServerException('No login server');
		}
		if (substr($line, -23) === ErrorCodes::AccessDenied) {
			throw new AccessDeniedException('Access denied');
		}
	}

	public function parseMode($mode) {
		$result = 0;
		foreach (self::MODE_STRINGS as $char => $val) {
			if (strpos($mode, $char) !== false) {
				$result |= $val;
			}
		}
		return $result;
	}

	public function parseStat($output) {
		$data = [];
		foreach ($output as $line) {
			// A line = explode statement may not fill all array elements
			// properly. May happen when accessing non Windows Fileservers
			$words = explode(':', $line, 2);
			$name = isset($words[0]) ? $words[0] : '';
			$value = isset($words[1]) ? $words[1] : '';
			$value = trim($value);

			if (!isset($data[$name])) {
				$data[$name] = $value;
			}
		}
		return [
			'mtime' => strtotime($data['write_time']),
			'mode'  => hexdec(substr($data['attributes'], strpos($data['attributes'], '(') + 1, -1)),
			'size'  => isset($data['stream']) ? (int)(explode(' ', $data['stream'])[1]) : 0
		];
	}

	public function parseDir($output, $basePath, callable $aclCallback) {
		//last line is used space
		array_pop($output);
		$regex = '/^\s*(.*?)\s\s\s\s+(?:([NDHARS]*)\s+)?([0-9]+)\s+(.*)$/';
		//2 spaces, filename, optional type, size, date
		$content = [];
		foreach ($output as $line) {
			if (preg_match($regex, $line, $matches)) {
				list(, $name, $mode, $size, $time) = $matches;
				if ($name !== '.' and $name !== '..') {
					$mode = $this->parseMode($mode);
					$time = strtotime($time . ' ' . $this->timeZone);
					$path = $basePath . '/' . $name;
					$content[] = new FileInfo($path, $name, $size, $time, $mode, function () use ($aclCallback, $path) {
						return $aclCallback($path);
					});
				}
			}
		}
		return $content;
	}

	public function parseListShares($output) {
		$shareNames = [];
		foreach ($output as $line) {
			if (strpos($line, '|')) {
				list($type, $name, $description) = explode('|', $line);
				if (strtolower($type) === 'disk') {
					$shareNames[$name] = $description;
				}
			} elseif (strpos($line, 'Disk')) {
				// new output format
				list($name, $description) = explode('Disk', $line);
				$shareNames[trim($name)] = trim($description);
			}
		}
		return $shareNames;
	}
}
