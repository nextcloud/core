<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\Files\Stream;

use Icewind\Streams\Wrapper;

/**
 * stream wrapper limits the amount of data that can be written to a stream
 *
 * usage: resource \OC\Files\Stream\Quota::wrap($stream, $limit)
 */
class Quota extends Wrapper {
	/**
	 * @var int $limit
	 */
	private $limit;

	/**
	 * @param resource $stream
	 * @param int $limit
	 * @return bool|resource
	 */
	public static function wrap($stream, $limit) {
		$context = stream_context_create([
			'quota' => [
				'source' => $stream,
				'limit' => $limit
			]
		]);
		return Wrapper::wrapSource($stream, $context, 'quota', self::class);
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$context = $this->loadContext('quota');
		$this->source = $context['source'];
		$this->limit = $context['limit'];

		return true;
	}

	public function dir_opendir($path, $options) {
		return false;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		if ($whence === SEEK_END) {
			// go to the end to find out last position's offset
			$oldOffset = $this->stream_tell();
			if (fseek($this->source, 0, $whence) !== 0) {
				return false;
			}
			$whence = SEEK_SET;
			$offset = $this->stream_tell() + $offset;
			$this->limit += $oldOffset - $offset;
		} elseif ($whence === SEEK_SET) {
			$this->limit += $this->stream_tell() - $offset;
		} else {
			$this->limit -= $offset;
		}
		// this wrapper needs to return "true" for success.
		// the fseek call itself returns 0 on succeess
		return fseek($this->source, $offset, $whence) === 0;
	}

	public function stream_read($count) {
		$this->limit -= $count;
		return fread($this->source, $count);
	}

	public function stream_write($data) {
		$size = strlen($data);
		if ($size > $this->limit) {
			$data = substr($data, 0, $this->limit);
			$size = $this->limit;
		}
		$this->limit -= $size;
		return fwrite($this->source, $data);
	}
}
