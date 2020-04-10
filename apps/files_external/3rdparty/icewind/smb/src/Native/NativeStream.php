<?php
/**
 * Copyright (c) 2014 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Licensed under the MIT license:
 * http://opensource.org/licenses/MIT
 */

namespace Icewind\SMB\Native;

use Icewind\SMB\Exception\Exception;
use Icewind\SMB\Exception\InvalidRequestException;
use Icewind\Streams\File;

class NativeStream implements File {
	/**
	 * @var resource
	 */
	public $context;

	/**
	 * @var NativeState
	 */
	protected $state;

	/**
	 * @var resource
	 */
	protected $handle;

	/**
	 * @var bool
	 */
	protected $eof = false;

	/**
	 * @var string
	 */
	protected $url;

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
		stream_wrapper_register('nativesmb', NativeStream::class);
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

	public function stream_close() {
		try {
			return $this->state->close($this->handle, $this->url);
		} catch (\Exception $e) {
			return false;
		}
	}

	public function stream_eof() {
		return $this->eof;
	}

	public function stream_flush() {
	}


	public function stream_open($path, $mode, $options, &$opened_path) {
		$context = stream_context_get_options($this->context);
		$this->state = $context['nativesmb']['state'];
		$this->handle = $context['nativesmb']['handle'];
		$this->url = $context['nativesmb']['url'];
		return true;
	}

	public function stream_read($count) {
		$result = $this->state->read($this->handle, $count);
		if (strlen($result) < $count) {
			$this->eof = true;
		}
		return $result;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		$this->eof = false;
		try {
			return $this->state->lseek($this->handle, $offset, $whence) !== false;
		} catch (InvalidRequestException $e) {
			return false;
		}
	}

	public function stream_stat() {
		try {
			return $this->state->stat($this->url);
		} catch (Exception $e) {
			return false;
		}
	}

	public function stream_tell() {
		return $this->state->lseek($this->handle, 0, SEEK_CUR);
	}

	public function stream_write($data) {
		return $this->state->write($this->handle, $data, $this->url);
	}

	public function stream_truncate($size) {
		return $this->state->ftruncate($this->handle, $size);
	}

	public function stream_set_option($option, $arg1, $arg2) {
		return false;
	}

	public function stream_lock($operation) {
		return false;
	}
}
