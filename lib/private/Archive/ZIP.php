<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
namespace OC\Archive;

use Icewind\Streams\CallbackWrapper;
use OCP\ILogger;

class ZIP extends Archive {
	/**
	 * @var \ZipArchive zip
	 */
	private $zip = null;
	private $path;

	/**
	 * @param string $source
	 */
	public function __construct($source) {
		$this->path = $source;
		$this->zip = new \ZipArchive();
		if ($this->zip->open($source, \ZipArchive::OVERWRITE)) {
		} else {
			\OCP\Util::writeLog('files_archive', 'Error while opening archive '.$source, ILogger::WARN);
		}
	}
	/**
	 * add an empty folder to the archive
	 * @param string $path
	 * @return bool
	 */
	public function addFolder($path) {
		return $this->zip->addEmptyDir($path);
	}
	/**
	 * add a file to the archive
	 * @param string $path
	 * @param string $source either a local file or string data
	 * @return bool
	 */
	public function addFile($path, $source = '') {
		if ($source and $source[0] == '/' and file_exists($source)) {
			$result = $this->zip->addFile($source, $path);
		} else {
			$result = $this->zip->addFromString($path, $source);
		}
		if ($result) {
			$this->zip->close();//close and reopen to save the zip
			$this->zip->open($this->path);
		}
		return $result;
	}
	/**
	 * rename a file or folder in the archive
	 * @param string $source
	 * @param string $dest
	 * @return boolean|null
	 */
	public function rename($source, $dest) {
		$source = $this->stripPath($source);
		$dest = $this->stripPath($dest);
		$this->zip->renameName($source, $dest);
	}
	/**
	 * get the uncompressed size of a file in the archive
	 * @param string $path
	 * @return int
	 */
	public function filesize($path) {
		$stat = $this->zip->statName($path);
		return $stat['size'];
	}
	/**
	 * get the last modified time of a file in the archive
	 * @param string $path
	 * @return int
	 */
	public function mtime($path) {
		return filemtime($this->path);
	}
	/**
	 * get the files in a folder
	 * @param string $path
	 * @return array
	 */
	public function getFolder($path) {
		$files = $this->getFiles();
		$folderContent = [];
		$pathLength = strlen($path);
		foreach ($files as $file) {
			if (substr($file, 0, $pathLength) == $path and $file != $path) {
				if (strrpos(substr($file, 0, -1), '/') <= $pathLength) {
					$folderContent[] = substr($file, $pathLength);
				}
			}
		}
		return $folderContent;
	}
	/**
	 * get all files in the archive
	 * @return array
	 */
	public function getFiles() {
		$fileCount = $this->zip->numFiles;
		$files = [];
		for ($i = 0;$i < $fileCount;$i++) {
			$files[] = $this->zip->getNameIndex($i);
		}
		return $files;
	}
	/**
	 * get the content of a file
	 * @param string $path
	 * @return string
	 */
	public function getFile($path) {
		return $this->zip->getFromName($path);
	}
	/**
	 * extract a single file from the archive
	 * @param string $path
	 * @param string $dest
	 * @return boolean|null
	 */
	public function extractFile($path, $dest) {
		$fp = $this->zip->getStream($path);
		file_put_contents($dest, $fp);
	}
	/**
	 * extract the archive
	 * @param string $dest
	 * @return bool
	 */
	public function extract($dest) {
		return $this->zip->extractTo($dest);
	}
	/**
	 * check if a file or folder exists in the archive
	 * @param string $path
	 * @return bool
	 */
	public function fileExists($path) {
		return ($this->zip->locateName($path) !== false) or ($this->zip->locateName($path.'/') !== false);
	}
	/**
	 * remove a file or folder from the archive
	 * @param string $path
	 * @return bool
	 */
	public function remove($path) {
		if ($this->fileExists($path.'/')) {
			return $this->zip->deleteName($path.'/');
		} else {
			return $this->zip->deleteName($path);
		}
	}
	/**
	 * get a file handler
	 * @param string $path
	 * @param string $mode
	 * @return bool|resource
	 */
	public function getStream($path, $mode) {
		if ($mode == 'r' or $mode == 'rb') {
			return $this->zip->getStream($path);
		} else {
			//since we can't directly get a writable stream,
			//make a temp copy of the file and put it back
			//in the archive when the stream is closed
			if (strrpos($path, '.') !== false) {
				$ext = substr($path, strrpos($path, '.'));
			} else {
				$ext = '';
			}
			$tmpFile = \OC::$server->getTempManager()->getTemporaryFile($ext);
			if ($this->fileExists($path)) {
				$this->extractFile($path, $tmpFile);
			}
			$handle = fopen($tmpFile, $mode);
			return CallbackWrapper::wrap($handle, null, null, function () use ($path, $tmpFile) {
				$this->writeBack($tmpFile, $path);
			});
		}
	}

	/**
	 * write back temporary files
	 */
	public function writeBack($tmpFile, $path) {
		$this->addFile($path, $tmpFile);
		unlink($tmpFile);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function stripPath($path) {
		if (!$path || $path[0] == '/') {
			return substr($path, 1);
		} else {
			return $path;
		}
	}
}
