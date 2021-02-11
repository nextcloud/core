<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Michael Weimann <mail@michael-weimann.eu>
 *
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\Files\SimpleFS;

use OCP\Files\NotPermittedException;

/**
 * This class represents a file that is only hold in memory.
 *
 * @since 16.0.0
 */
class InMemoryFile implements ISimpleFile {
	/**
	 * Holds the file name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Holds the file contents.
	 *
	 * @var string
	 */
	private $contents;

	/**
	 * InMemoryFile constructor.
	 *
	 * @param string $name The file name
	 * @param string $contents The file contents
	 * @since 16.0.0
	 */
	public function __construct(string $name, string $contents) {
		$this->name = $name;
		$this->contents = $contents;
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getSize() {
		return strlen($this->contents);
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getETag() {
		return '';
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getMTime() {
		return time();
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getContent() {
		return $this->contents;
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function putContent($data) {
		$this->contents = $data;
	}

	/**
	 * In memory files can't be deleted.
	 *
	 * @since 16.0.0
	 */
	public function delete() {
		// unimplemented for in memory files
	}

	/**
	 * @inheritdoc
	 * @since 16.0.0
	 */
	public function getMimeType() {
		$fileInfo = new \finfo(FILEINFO_MIME_TYPE);
		return $fileInfo->buffer($this->contents);
	}

	/**
	 * Stream reading is unsupported for in memory files.
	 *
	 * @throws NotPermittedException
	 * @since 16.0.0
	 */
	public function read() {
		throw new NotPermittedException(
			'Stream reading is unsupported for in memory files'
		);
	}

	/**
	 * Stream writing isn't available for in memory files.
	 *
	 * @throws NotPermittedException
	 * @since 16.0.0
	 */
	public function write() {
		throw new NotPermittedException(
			'Stream writing is unsupported for in memory files'
		);
	}
}
