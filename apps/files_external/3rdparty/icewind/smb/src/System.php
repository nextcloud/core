<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB;

use Icewind\SMB\Exception\Exception;

class System implements ISystem {
	/** @var (string|bool)[] */
	private $paths = [];

	/**
	 * Get the path to a file descriptor of the current process
	 *
	 * @param int $num the file descriptor id
	 * @return string
	 * @throws Exception
	 */
	public function getFD($num) {
		$folders = [
			'/proc/self/fd',
			'/dev/fd'
		];
		foreach ($folders as $folder) {
			if (file_exists($folder)) {
				return $folder . '/' . $num;
			}
		}
		throw new Exception('Cant find file descriptor path');
	}

	public function getSmbclientPath() {
		return $this->getBinaryPath('smbclient');
	}

	public function getNetPath() {
		return $this->getBinaryPath('net');
	}

	public function getSmbcAclsPath() {
		return $this->getBinaryPath('smbcacls');
	}

	public function getStdBufPath() {
		return $this->getBinaryPath('stdbuf');
	}

	public function getDatePath() {
		return $this->getBinaryPath('date');
	}

	public function libSmbclientAvailable() {
		return function_exists('smbclient_state_new');
	}

	protected function getBinaryPath($binary) {
		if (!isset($this->paths[$binary])) {
			$result = null;
			$output = [];
			exec("which $binary 2>&1", $output, $result);
			$this->paths[$binary] = $result === 0 ? trim(implode('', $output)) : false;
		}
		return $this->paths[$binary];
	}
}
