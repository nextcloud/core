<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author szaimen <szaimen@e.mail.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
namespace OC;

use OC\Files\Filesystem;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use ownCloud\TarStreamer\TarStreamer;
use ZipStreamer\ZipStreamer;

class Streamer {
	// array of regexp. Matching user agents will get tar instead of zip
	private $preferTarFor = [ '/macintosh|mac os x/i' ];

	// streamer instance
	private $streamerInstance;

	/**
	 * Streamer constructor.
	 *
	 * @param IRequest $request
	 * @param int $size The size of the files in bytes
	 * @param int $numberOfFiles The number of files (and directories) that will
	 *        be included in the streamed file
	 */
	public function __construct(IRequest $request, $size, int $numberOfFiles) {

		/**
		 * zip32 constraints for a basic (without compression, volumes nor
		 * encryption) zip file according to the Zip specification:
		 * - No file size is larger than 4 bytes (file size < 4294967296); see
		 *   4.4.9 uncompressed size
		 * - The size of all files plus their local headers is not larger than
		 *   4 bytes; see 4.4.16 relative offset of local header and 4.4.24
		 *   offset of start of central directory with respect to the starting
		 *   disk number
		 * - The total number of entries (files and directories) in the zip file
		 *   is not larger than 2 bytes (number of entries < 65536); see 4.4.22
		 *   total number of entries in the central dir
		 * - The size of the central directory is not larger than 4 bytes; see
		 *   4.4.23 size of the central directory
		 *
		 * Due to all that, zip32 is used if the size is below 4GB and there are
		 * less than 65536 files; the margin between 4*1000^3 and 4*1024^3
		 * should give enough room for the extra zip metadata. Technically, it
		 * would still be possible to create an invalid zip32 file (for example,
		 * a zip file from files smaller than 4GB with a central directory
		 * larger than 4GiB), but it should not happen in the real world.
		 *
		 * We also have to check for a size above 0. As negative sizes could be
		 * from not fully scanned external storage. And then things fall apart
		 * if somebody tries to package to much.
		 */
		if ($size > 0 && $size < 4 * 1000 * 1000 * 1000 && $numberOfFiles < 65536) {
			$this->streamerInstance = new ZipStreamer(['zip64' => false]);
		} elseif ($request->isUserAgent($this->preferTarFor)) {
			$this->streamerInstance = new TarStreamer();
		} else {
			$this->streamerInstance = new ZipStreamer(['zip64' => PHP_INT_SIZE !== 4]);
		}
	}

	/**
	 * Send HTTP headers
	 * @param string $name
	 */
	public function sendHeaders($name) {
		$extension = $this->streamerInstance instanceof ZipStreamer ? '.zip' : '.tar';
		$fullName = $name . $extension;
		$this->streamerInstance->sendHeaders($fullName);
	}

	/**
	 * Stream directory recursively
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws InvalidPathException
	 */
	public function addDirRecursive(string $dir, string $internalDir = ''): void {
		$dirname = basename($dir);
		$rootDir = $internalDir . $dirname;
		if (!empty($rootDir)) {
			$this->streamerInstance->addEmptyDir($rootDir);
		}
		$internalDir .= $dirname . '/';
		// prevent absolute dirs
		$internalDir = ltrim($internalDir, '/');

		$userFolder = \OC::$server->getRootFolder()->get(Filesystem::getRoot());
		/** @var Folder $dirNode */
		$dirNode = $userFolder->get($dir);
		$files = $dirNode->getDirectoryListing();

		foreach ($files as $file) {
			if ($file instanceof File) {
				try {
					$fh = $file->fopen('r');
				} catch (NotPermittedException $e) {
					continue;
				}
				$this->addFileFromStream(
					$fh,
					$internalDir . $file->getName(),
					$file->getSize(),
					$file->getMTime()
				);
				fclose($fh);
			} elseif ($file instanceof Folder) {
				if ($file->isReadable()) {
					$this->addDirRecursive($dir . '/' . $file->getName(), $internalDir);
				}
			}
		}
	}

	/**
	 * Add a file to the archive at the specified location and file name.
	 *
	 * @param string $stream Stream to read data from
	 * @param string $internalName Filepath and name to be used in the archive.
	 * @param int $size Filesize
	 * @param int|bool $time File mtime as int, or false
	 * @return bool $success
	 */
	public function addFileFromStream($stream, $internalName, $size, $time) {
		$options = [];
		if ($time) {
			$options = [
				'timestamp' => $time
			];
		}

		if ($this->streamerInstance instanceof ZipStreamer) {
			return $this->streamerInstance->addFileFromStream($stream, $internalName, $options);
		} else {
			return $this->streamerInstance->addFileFromStream($stream, $internalName, $size, $options);
		}
	}

	/**
	 * Add an empty directory entry to the archive.
	 *
	 * @param string $dirName Directory Path and name to be added to the archive.
	 * @return bool $success
	 */
	public function addEmptyDir($dirName) {
		return $this->streamerInstance->addEmptyDir($dirName);
	}

	/**
	 * Close the archive.
	 * A closed archive can no longer have new files added to it. After
	 * closing, the file is completely written to the output stream.
	 * @return bool $success
	 */
	public function finalize() {
		return $this->streamerInstance->finalize();
	}
}
