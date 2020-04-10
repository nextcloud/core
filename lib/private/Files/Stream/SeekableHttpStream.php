<?php
/**
 *
 * @copyright Copyright (c) 2020, Lukas Stabe (lukas@stabe.de)
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

namespace OC\Files\Stream;

use Icewind\Streams\File;

/**
 * A stream wrapper that uses http range requests to provide a seekable stream for http reading
 */
class SeekableHttpStream implements File {
	private const PROTOCOL = 'httpseek';

	private static $registered = false;

	/**
	 * Registers the stream wrapper using the `httpseek://` url scheme
	 * $return void
	 */
	private static function registerIfNeeded() {
		if (!self::$registered) {
			stream_wrapper_register(
				self::PROTOCOL,
				self::class
			);
			self::$registered = true;
		}
	}

	/**
	 * Open a readonly-seekable http stream
	 *
	 * The provided callback will be called with byte range and should return an http stream for the requested range
	 *
	 * @param callable $callback
	 * @return false|resource
	 */
	public static function open(callable $callback) {
		$context = stream_context_create([
			SeekableHttpStream::PROTOCOL => [
				'callback' => $callback
			],
		]);

		SeekableHttpStream::registerIfNeeded();
		return fopen(SeekableHttpStream::PROTOCOL . '://', 'r', false, $context);
	}

	/** @var resource */
	public $context;

	/** @var callable */
	private $openCallback;

	/** @var resource */
	private $current;
	/** @var int */
	private $offset = 0;

	private function reconnect(int $start) {
		$range = $start . '-';
		if ($this->current != null) {
			fclose($this->current);
		}

		$this->current = ($this->openCallback)($range);

		if ($this->current === false) {
			return false;
		}

		$responseHead = stream_get_meta_data($this->current)['wrapper_data'];
		$rangeHeaders = array_values(array_filter($responseHead, function ($v) {
			return preg_match('#^content-range:#i', $v) === 1;
		}));
		if (!$rangeHeaders) {
			return false;
		}
		$contentRange = $rangeHeaders[0];

		$content = trim(explode(':', $contentRange)[1]);
		$range = trim(explode(' ', $content)[1]);
		$begin = intval(explode('-', $range)[0]);

		if ($begin !== $start) {
			return false;
		}

		$this->offset = $begin;

		return true;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$options = stream_context_get_options($this->context)[self::PROTOCOL];
		$this->openCallback = $options['callback'];

		return $this->reconnect(0);
	}

	public function stream_read($count) {
		if (!$this->current) {
			return false;
		}
		$ret = fread($this->current, $count);
		$this->offset += strlen($ret);
		return $ret;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		switch ($whence) {
			case SEEK_SET:
				if ($offset === $this->offset) {
					return true;
				}
				return $this->reconnect($offset);
			case SEEK_CUR:
				if ($offset === 0) {
					return true;
				}
				return $this->reconnect($this->offset + $offset);
			case SEEK_END:
				return false;
		}
		return false;
	}

	public function stream_tell() {
		return $this->offset;
	}

	public function stream_stat() {
		if (is_resource($this->current)) {
			return fstat($this->current);
		} else {
			return false;
		}
	}

	public function stream_eof() {
		if (is_resource($this->current)) {
			return feof($this->current);
		} else {
			return true;
		}
	}

	public function stream_close() {
		if (is_resource($this->current)) {
			fclose($this->current);
		}
	}

	public function stream_write($data) {
		return false;
	}

	public function stream_set_option($option, $arg1, $arg2) {
		return false;
	}

	public function stream_truncate($size) {
		return false;
	}

	public function stream_lock($operation) {
		return false;
	}

	public function stream_flush() {
		return; //noop because readonly stream
	}
}
