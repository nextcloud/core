<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Wrapped;

use Icewind\SMB\Exception\AuthenticationException;
use Icewind\SMB\Exception\ConnectException;
use Icewind\SMB\Exception\ConnectionException;
use Icewind\SMB\Exception\InvalidHostException;
use Icewind\SMB\Exception\NoLoginServerException;

class Connection extends RawConnection {
	const DELIMITER = 'smb:';
	const DELIMITER_LENGTH = 4;

	/** @var Parser */
	private $parser;

	public function __construct($command, Parser $parser, $env = []) {
		parent::__construct($command, $env);
		$this->parser = $parser;
	}

	/**
	 * send input to smbclient
	 *
	 * @param string $input
	 */
	public function write($input) {
		parent::write($input . PHP_EOL);
	}

	/**
	 * @throws ConnectException
	 */
	public function clearTillPrompt() {
		$this->write('');
		do {
			$promptLine = $this->readLine();
			$this->parser->checkConnectionError($promptLine);
		} while (!$this->isPrompt($promptLine));
		$this->write('');
		$this->readLine();
	}

	/**
	 * get all unprocessed output from smbclient until the next prompt
	 *
	 * @param callable $callback (optional) callback to call for every line read
	 * @return string[]
	 * @throws AuthenticationException
	 * @throws ConnectException
	 * @throws ConnectionException
	 * @throws InvalidHostException
	 * @throws NoLoginServerException
	 */
	public function read(callable $callback = null) {
		if (!$this->isValid()) {
			throw new ConnectionException('Connection not valid');
		}
		$promptLine = $this->readLine(); //first line is prompt
		$this->parser->checkConnectionError($promptLine);

		$output = [];
		if (!$this->isPrompt($promptLine)) {
			$line = $promptLine;
		} else {
			$line = $this->readLine();
		}
		if ($line === false) {
			$this->unknownError($promptLine);
		}
		while (!$this->isPrompt($line)) { //next prompt functions as delimiter
			if (is_callable($callback)) {
				$result = $callback($line);
				if ($result === false) { // allow the callback to close the connection for infinite running commands
					$this->close(true);
					break;
				}
			} else {
				$output[] .= $line;
			}
			$line = $this->readLine();
		}
		return $output;
	}

	/**
	 * Check
	 *
	 * @param $line
	 * @return bool
	 */
	private function isPrompt($line) {
		return mb_substr($line, 0, self::DELIMITER_LENGTH) === self::DELIMITER || $line === false;
	}

	/**
	 * @param string $promptLine (optional) prompt line that might contain some info about the error
	 * @throws ConnectException
	 */
	private function unknownError($promptLine = '') {
		if ($promptLine) { //maybe we have some error we missed on the previous line
			throw new ConnectException('Unknown error (' . $promptLine . ')');
		} else {
			$error = $this->readError(); // maybe something on stderr
			if ($error) {
				throw new ConnectException('Unknown error (' . $error . ')');
			} else {
				throw new ConnectException('Unknown error');
			}
		}
	}

	public function close($terminate = true) {
		if (get_resource_type($this->getInputStream()) === 'stream') {
			// ignore any errors while trying to send the close command, the process might already be dead
			@$this->write('close' . PHP_EOL);
		}
		parent::close($terminate);
	}
}
