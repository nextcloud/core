<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCP\Files\Storage;

/**
 * Interface that adds the ability to write a stream directly to file
 *
 * @since 15.0.0
 */
interface IWriteStreamStorage extends IStorage {
	/**
	 * Write the data from a stream to a file
	 *
	 * @param string $path
	 * @param resource $stream
	 * @param int|null $size the size of the stream if known in advance
	 * @return int the number of bytes written
	 * @since 15.0.0
	 */
	public function writeStream(string $path, $stream, int $size = null): int;
}
