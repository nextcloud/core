<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\IntegrityCheck\Helpers;

/**
 * Class FileAccessHelper provides a helper around file_get_contents and
 * file_put_contents
 *
 * @package OC\IntegrityCheck\Helpers
 */
class FileAccessHelper {
	/**
	 * Wrapper around file_get_contents($filename, $data)
	 *
	 * @param string $filename
	 * @return string|false
	 */
	public function file_get_contents(string $filename) {
		return file_get_contents($filename);
	}

	/**
	 * Wrapper around file_exists($filename)
	 *
	 * @param string $filename
	 * @return bool
	 */
	public function file_exists(string $filename): bool {
		return file_exists($filename);
	}

	/**
	 * Wrapper around file_put_contents($filename, $data)
	 *
	 * @param string $filename
	 * @param string $data
	 * @return int
	 * @throws \Exception
	 */
	public function file_put_contents(string $filename, string $data): int {
		$bytesWritten = @file_put_contents($filename, $data);
		if ($bytesWritten === false || $bytesWritten !== \strlen($data)){
			throw new \Exception('Failed to write into ' . $filename);
		}
		return $bytesWritten;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function is_writable(string $path): bool {
		return is_writable($path);
	}

	/**
	 * @param string $path
	 * @throws \Exception
	 */
	public function assertDirectoryExists(string $path) {
		if (!is_dir($path)) {
			throw new \Exception('Directory ' . $path . ' does not exist.');
		}
	}
}
