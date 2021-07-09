<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCP\Files\Storage;

use OCP\Files\GenericFileException;

/**
 * @since 22.0.0
 */
interface IChunkedFileWrite extends IStorage {

	/**
	 * @param string $targetPath Relative target path in the storage
	 * @return string writeToken to be used with the other methods to uniquely identify the file write operation
	 * @throws GenericFileException
	 * @since 22.0.0
	 */
	public function beginChunkedFile(string $targetPath): string;

	/**
	 * @param string $targetPath
	 * @param string $writeToken
	 * @param string $chunkId
	 * @param resource $data
	 * @param int|null $size
	 * @throws GenericFileException
	 * @since 22.0.0
	 */
	public function putChunkedFilePart(string $targetPath, string $writeToken, string $chunkId, $data, int $size = null): void;

	/**
	 * @param string $targetPath
	 * @param string $writeToken
	 * @return int
	 * @throws GenericFileException
	 * @since 22.0.0
	 */
	public function writeChunkedFile(string $targetPath, string $writeToken): int;

	/**
	 * @param string $targetPath
	 * @param string $writeToken
	 * @throws GenericFileException
	 * @since 22.0.0
	 */
	public function cancelChunkedFile(string $targetPath, string $writeToken): void;
}
