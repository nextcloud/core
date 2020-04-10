<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Native;

/**
 * Stream optimized for write only usage
 */
class NativeWriteStream extends NativeStream {
	const CHUNK_SIZE = 1048576; // 1MB chunks
	/**
	 * @var resource
	 */
	private $writeBuffer = null;

	private $bufferSize = 0;

	private $pos = 0;

	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->writeBuffer = fopen('php://memory', 'r+');

		return parent::stream_open($path, $mode, $options, $opened_path);
	}

	/**
	 * Wrap a stream from libsmbclient-php into a regular php stream
	 *
	 * @param \Icewind\SMB\NativeState $state
	 * @param resource $smbStream
	 * @param string $mode
	 * @param string $url
	 * @return resource
	 */
	public static function wrap($state, $smbStream, $mode, $url) {
		stream_wrapper_register('nativesmb', NativeWriteStream::class);
		$context = stream_context_create([
			'nativesmb' => [
				'state'  => $state,
				'handle' => $smbStream,
				'url'    => $url
			]
		]);
		$fh = fopen('nativesmb://', $mode, false, $context);
		stream_wrapper_unregister('nativesmb');
		return $fh;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		$this->flushWrite();
		$result = parent::stream_seek($offset, $whence);
		if ($result) {
			$this->pos = parent::stream_tell();
		}
		return $result;
	}

	private function flushWrite() {
		rewind($this->writeBuffer);
		$this->state->write($this->handle, stream_get_contents($this->writeBuffer), $this->url);
		$this->writeBuffer = fopen('php://memory', 'r+');
		$this->bufferSize = 0;
	}

	public function stream_write($data) {
		$written = fwrite($this->writeBuffer, $data);
		$this->bufferSize += $written;
		$this->pos += $written;

		if ($this->bufferSize >= self::CHUNK_SIZE) {
			$this->flushWrite();
		}

		return $written;
	}

	public function stream_close() {
		try {
			$this->flushWrite();
			$flushResult = true;
		} catch (\Exception $e) {
			$flushResult = false;
		}
		return parent::stream_close() && $flushResult;
	}

	public function stream_tell() {
		return $this->pos;
	}

	public function stream_read($count) {
		return false;
	}

	public function stream_truncate($size) {
		$this->flushWrite();
		$this->pos = $size;
		return parent::stream_truncate($size);
	}
}
