<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_External\Lib\Storage;

/**
 * Low level wrapper around the ftp functions that smooths over some difference between servers
 */
class FtpConnection {
	/** @var resource */
	private $connection;

	public function __construct(bool $secure, string $hostname, int $port, string $username, string $password) {
		if ($secure) {
			$connection = ftp_ssl_connect($hostname, $port);
		} else {
			$connection = ftp_connect($hostname, $port);
		}

		if ($connection === false) {
			throw new \Exception("Failed to connect to ftp");
		}

		if (ftp_login($connection, $username, $password) === false) {
			throw new \Exception("Failed to connect to login to ftp");
		}

		ftp_pasv($connection, true);
		$this->connection = $connection;
	}

	public function __destruct() {
		if ($this->connection) {
			ftp_close($this->connection);
		}
		$this->connection = null;
	}

	public function setUtf8Mode(): bool {
		$response = ftp_raw($this->connection, "OPTS UTF8 ON");
		return substr($response[0], 0, 3) === '200';
	}

	public function fput(string $path, $handle) {
		return @ftp_fput($this->connection, $path, $handle, FTP_BINARY);
	}

	public function fget($handle, string $path) {
		return @ftp_fget($this->connection, $handle, $path, FTP_BINARY);
	}

	public function mkdir(string $path) {
		return @ftp_mkdir($this->connection, $path);
	}

	public function chdir(string $path) {
		return @ftp_chdir($this->connection, $path);
	}

	public function delete(string $path) {
		return @ftp_delete($this->connection, $path);
	}

	public function rmdir(string $path) {
		return @ftp_rmdir($this->connection, $path);
	}

	public function rename(string $path1, string $path2) {
		return @ftp_rename($this->connection, $path1, $path2);
	}

	public function mdtm(string $path) {
		return @ftp_mdtm($this->connection, $path);
	}

	public function size(string $path) {
		return @ftp_size($this->connection, $path);
	}

	public function systype() {
		return @ftp_systype($this->connection);
	}

	public function nlist(string $path) {
		$files = @ftp_nlist($this->connection, $path);
		return array_map(function ($name) {
			if (strpos($name, '/') !== false) {
				$name = basename($name);
			}
			return $name;
		}, $files);
	}

	public function mlsd(string $path) {
		$files = @ftp_mlsd($this->connection, $path);

		if ($files !== false) {
			return array_map(function ($file) {
				if (strpos($file['name'], '/') !== false) {
					$file['name'] = basename($file['name']);
				}
				return $file;
			}, $files);
		} else {
			// not all servers support mlsd, in those cases we parse the raw list ourselves
			$rawList = @ftp_rawlist($this->connection, '-aln ' . $path);
			if ($rawList === false) {
				return false;
			}
			return $this->parseRawList($rawList, $path);
		}
	}

	// rawlist parsing logic is based on the ftp implementation from https://github.com/thephpleague/flysystem
	private function parseRawList(array $rawList, string $directory): array {
		return array_map(function ($item) use ($directory) {
			return $this->parseRawListItem($item, $directory);
		}, $rawList);
	}

	private function parseRawListItem(string $item, string $directory): array {
		$isWindows = preg_match('/^[0-9]{2,4}-[0-9]{2}-[0-9]{2}/', $item);

		return $isWindows ? $this->parseWindowsItem($item, $directory) : $this->parseUnixItem($item, $directory);
	}

	private function parseUnixItem(string $item, string $directory): array {
		$item = preg_replace('#\s+#', ' ', $item, 7);

		if (count(explode(' ', $item, 9)) !== 9) {
			throw new \RuntimeException("Metadata can't be parsed from item '$item' , not enough parts.");
		}

		[$permissions, /* $number */, /* $owner */, /* $group */, $size, $month, $day, $time, $name] = explode(' ', $item, 9);
		if ($name === '.') {
			$type = 'cdir';
		} elseif ($name === '..') {
			$type = 'pdir';
		} else {
			$type = substr($permissions, 0, 1) === 'd' ? 'dir' : 'file';
		}

		$parsedDate = (new \DateTime())
			->setTimestamp(strtotime("$month $day $time"));
		$tomorrow = (new \DateTime())->add(new \DateInterval("P1D"));

		// since the provided date doesn't include the year, we either set it to the correct year
		// or when the date would otherwise be in the future (by more then 1 day to account for timezone errors)
		// we use last year
		if ($parsedDate > $tomorrow) {
			$parsedDate = $parsedDate->sub(new \DateInterval("P1Y"));
		}

		$formattedDate = $parsedDate
			->format('YmdHis');

		return [
			'type' => $type,
			'name' => $name,
			'modify' => $formattedDate,
			'perm' => $this->normalizePermissions($permissions),
			'size' => (int)$size,
		];
	}

	private function normalizePermissions(string $permissions) {
		$isDir = substr($permissions, 0, 1) === 'd';
		// remove the type identifier and only use owner permissions
		$permissions = substr($permissions, 1, 4);

		// map the string rights to the ftp counterparts
		$filePermissionsMap = ['r' => 'r', 'w' => 'fadfw'];
		$dirPermissionsMap = ['r' => 'e', 'w' => 'flcdmp'];

		$map = $isDir ? $dirPermissionsMap : $filePermissionsMap;

		return array_reduce(str_split($permissions), function ($ftpPermissions, $permission) use ($map) {
			if (isset($map[$permission])) {
				$ftpPermissions .= $map[$permission];
			}
			return $ftpPermissions;
		}, '');
	}

	private function parseWindowsItem(string $item, string $directory): array {
		$item = preg_replace('#\s+#', ' ', trim($item), 3);

		if (count(explode(' ', $item, 4)) !== 4) {
			throw new \RuntimeException("Metadata can't be parsed from item '$item' , not enough parts.");
		}

		[$date, $time, $size, $name] = explode(' ', $item, 4);

		// Check for the correct date/time format
		$format = strlen($date) === 8 ? 'm-d-yH:iA' : 'Y-m-dH:i';
		$formattedDate = \DateTime::createFromFormat($format, $date . $time)->format('YmdGis');

		if ($name === '.') {
			$type = 'cdir';
		} elseif ($name === '..') {
			$type = 'pdir';
		} else {
			$type = ($size === '<DIR>') ? 'dir' : 'file';
		}

		return [
			'type' => $type,
			'name' => $name,
			'modify' => $formattedDate,
			'perm' => ($type === 'file') ? 'adfrw' : 'flcdmpe',
			'size' => (int)$size,
		];
	}
}
