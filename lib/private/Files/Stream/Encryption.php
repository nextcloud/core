<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author jknockaert <jasper@knockaert.nl>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author martink-p <47943787+martink-p@users.noreply.github.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
use OC\Encryption\Exceptions\EncryptionHeaderKeyExistsException;
use function is_array;
use function stream_context_create;

class Encryption extends Wrapper {

	/** @var \OC\Encryption\Util */
	protected $util;

	/** @var \OC\Encryption\File */
	protected $file;

	/** @var \OCP\Encryption\IEncryptionModule */
	protected $encryptionModule;

	/** @var \OC\Files\Storage\Storage */
	protected $storage;

	/** @var \OC\Files\Storage\Wrapper\Encryption */
	protected $encryptionStorage;

	/** @var string */
	protected $internalPath;

	/** @var string */
	protected $cache;

	/** @var integer */
	protected $size;

	/** @var integer */
	protected $position;

	/** @var integer */
	protected $unencryptedSize;

	/** @var integer */
	protected $headerSize;

	/** @var integer */
	protected $unencryptedBlockSize;

	/** @var array */
	protected $header;

	/** @var string */
	protected $fullPath;

	/** @var  bool */
	protected $signed;

	/**
	 * header data returned by the encryption module, will be written to the file
	 * in case of a write operation
	 *
	 * @var array
	 */
	protected $newHeader;

	/**
	 * user who perform the read/write operation null for public access
	 *
	 * @var string
	 */
	protected $uid;

	/** @var bool */
	protected $readOnly;

	/** @var bool */
	protected $writeFlag;

	/** @var array */
	protected $expectedContextProperties;

	/** @var bool */
	protected $fileUpdated;

	public function __construct() {
		$this->expectedContextProperties = [
			'source',
			'storage',
			'internalPath',
			'fullPath',
			'encryptionModule',
			'header',
			'uid',
			'file',
			'util',
			'size',
			'unencryptedSize',
			'encryptionStorage',
			'headerSize',
			'signed'
		];
	}


	/**
	 * Wraps a stream with the provided callbacks
	 *
	 * @param resource $source
	 * @param string $internalPath relative to mount point
	 * @param string $fullPath relative to data/
	 * @param array $header
	 * @param string $uid
	 * @param \OCP\Encryption\IEncryptionModule $encryptionModule
	 * @param \OC\Files\Storage\Storage $storage
	 * @param \OC\Files\Storage\Wrapper\Encryption $encStorage
	 * @param \OC\Encryption\Util $util
	 * @param \OC\Encryption\File $file
	 * @param string $mode
	 * @param int $size
	 * @param int $unencryptedSize
	 * @param int $headerSize
	 * @param bool $signed
	 * @param string $wrapper stream wrapper class
	 * @return resource
	 *
	 * @throws \BadMethodCallException
	 */
	public static function wrap($source, $internalPath, $fullPath, array $header,
								$uid,
								\OCP\Encryption\IEncryptionModule $encryptionModule,
								\OC\Files\Storage\Storage $storage,
								\OC\Files\Storage\Wrapper\Encryption $encStorage,
								\OC\Encryption\Util $util,
								 \OC\Encryption\File $file,
								$mode,
								$size,
								$unencryptedSize,
								$headerSize,
								$signed,
								$wrapper = Encryption::class) {
		$context = stream_context_create([
			'ocencryption' => [
				'source' => $source,
				'storage' => $storage,
				'internalPath' => $internalPath,
				'fullPath' => $fullPath,
				'encryptionModule' => $encryptionModule,
				'header' => $header,
				'uid' => $uid,
				'util' => $util,
				'file' => $file,
				'size' => $size,
				'unencryptedSize' => $unencryptedSize,
				'encryptionStorage' => $encStorage,
				'headerSize' => $headerSize,
				'signed' => $signed
			]
		]);

		return self::wrapSource($source, $context, 'ocencryption', $wrapper, $mode);
	}

	/**
	 * add stream wrapper
	 *
	 * @param resource|int $source
	 * @param resource|array $context
	 * @param string|null $protocol
	 * @param string|null $class
	 * @param string $mode
	 * @return resource
	 * @throws \BadMethodCallException
	 */
	protected static function wrapSource($source, $context = [], $protocol = null, $class = null, $mode = 'r+') {
		try {
			if ($protocol === null) {
				$protocol = self::getProtocol($class);
			}

			stream_wrapper_register($protocol, $class);
			$context = self::buildContext($protocol, $context, $source);
			if (self::isDirectoryHandle($source)) {
				$wrapped = opendir($protocol . '://', $context);
			} else {
				$wrapped = fopen($protocol . '://', $mode, false, $context);
			}
		} catch (\BadMethodCallException $e) {
			stream_wrapper_unregister($protocol);
			throw $e;
		}
		stream_wrapper_unregister($protocol);
		return $wrapped;
	}

	/**
	 * @todo this is a copy of \Icewind\Streams\WrapperHandler::buildContext -> combine to one shared method?
	 */
	private static function buildContext($protocol, $context, $source) {
		if (is_array($context)) {
			$context['source'] = $source;
			return stream_context_create([$protocol => $context]);
		}

		return $context;
	}

	/**
	 * Load the source from the stream context and return the context options
	 *
	 * @param string|null $name
	 * @return array
	 * @throws \BadMethodCallException
	 */
	protected function loadContext($name = null) {
		$context = parent::loadContext($name);

		foreach ($this->expectedContextProperties as $property) {
			if (array_key_exists($property, $context)) {
				$this->{$property} = $context[$property];
			} else {
				throw new \BadMethodCallException('Invalid context, "' . $property . '" options not set');
			}
		}
		return $context;
	}

	public function stream_open($path, $mode, $options, &$opened_path) {
		$this->loadContext('ocencryption');

		$this->position = 0;
		$this->cache = '';
		$this->writeFlag = false;
		$this->fileUpdated = false;
		$this->unencryptedBlockSize = $this->encryptionModule->getUnencryptedBlockSize($this->signed);

		if (
			$mode === 'w'
			|| $mode === 'w+'
			|| $mode === 'wb'
			|| $mode === 'wb+'
			|| $mode === 'r+'
			|| $mode === 'rb+'
		) {
			$this->readOnly = false;
		} else {
			$this->readOnly = true;
		}

		$sharePath = $this->fullPath;
		if (!$this->storage->file_exists($this->internalPath)) {
			$sharePath = dirname($sharePath);
		}

		$accessList = [];
		if ($this->encryptionModule->needDetailedAccessList()) {
			$accessList = $this->file->getAccessList($sharePath);
		}
		$this->newHeader = $this->encryptionModule->begin($this->fullPath, $this->uid, $mode, $this->header, $accessList);

		if (
			$mode === 'w'
			|| $mode === 'w+'
			|| $mode === 'wb'
			|| $mode === 'wb+'
		) {
			// We're writing a new file so start write counter with 0 bytes
			$this->unencryptedSize = 0;
			$this->writeHeader();
			$this->headerSize = $this->util->getHeaderSize();
			$this->size = $this->headerSize;
		} else {
			$this->skipHeader();
		}

		return true;
	}

	public function stream_eof() {
		return $this->position >= $this->unencryptedSize;
	}

	public function stream_read($count) {
		$result = '';

		$count = min($count, $this->unencryptedSize - $this->position);
		while ($count > 0) {
			$remainingLength = $count;
			// update the cache of the current block
			$this->readCache();
			// determine the relative position in the current block
			$blockPosition = ($this->position % $this->unencryptedBlockSize);
			// if entire read inside current block then only position needs to be updated
			if ($remainingLength < ($this->unencryptedBlockSize - $blockPosition)) {
				$result .= substr($this->cache, $blockPosition, $remainingLength);
				$this->position += $remainingLength;
				$count = 0;
			// otherwise remainder of current block is fetched, the block is flushed and the position updated
			} else {
				$result .= substr($this->cache, $blockPosition);
				$this->flush();
				$this->position += ($this->unencryptedBlockSize - $blockPosition);
				$count -= ($this->unencryptedBlockSize - $blockPosition);
			}
		}
		return $result;
	}

	/**
	 * stream_read_block
	 *
	 * This function is a wrapper for function stream_read.
	 * It calls stream read until the requested $blockSize was received or no remaining data is present.
	 * This is required as stream_read only returns smaller chunks of data when the stream fetches from a
	 * remote storage over the internet and it does not care about the given $blockSize.
	 *
	 * @param int $blockSize Length of requested data block in bytes
	 * @return string Data fetched from stream.
	 */
	private function stream_read_block(int $blockSize): string {
		$remaining = $blockSize;
		$data = '';

		do {
			$chunk = parent::stream_read($remaining);
			$chunk_len = strlen($chunk);
			$data .= $chunk;
			$remaining -= $chunk_len;
		} while (($remaining > 0) && ($chunk_len > 0));

		return $data;
	}

	public function stream_write($data) {
		$length = 0;
		// loop over $data to fit it in 6126 sized unencrypted blocks
		while (isset($data[0])) {
			$remainingLength = strlen($data);

			// set the cache to the current 6126 block
			$this->readCache();

			// for seekable streams the pointer is moved back to the beginning of the encrypted block
			// flush will start writing there when the position moves to another block
			$positionInFile = (int)floor($this->position / $this->unencryptedBlockSize) *
				$this->util->getBlockSize() + $this->headerSize;
			$resultFseek = $this->parentStreamSeek($positionInFile);

			// only allow writes on seekable streams, or at the end of the encrypted stream
			if (!$this->readOnly && ($resultFseek || $positionInFile === $this->size)) {

				// switch the writeFlag so flush() will write the block
				$this->writeFlag = true;
				$this->fileUpdated = true;

				// determine the relative position in the current block
				$blockPosition = ($this->position % $this->unencryptedBlockSize);
				// check if $data fits in current block
				// if so, overwrite existing data (if any)
				// update position and liberate $data
				if ($remainingLength < ($this->unencryptedBlockSize - $blockPosition)) {
					$this->cache = substr($this->cache, 0, $blockPosition)
						. $data . substr($this->cache, $blockPosition + $remainingLength);
					$this->position += $remainingLength;
					$length += $remainingLength;
					$data = '';
				// if $data doesn't fit the current block, the fill the current block and reiterate
					// after the block is filled, it is flushed and $data is updatedxxx
				} else {
					$this->cache = substr($this->cache, 0, $blockPosition) .
						substr($data, 0, $this->unencryptedBlockSize - $blockPosition);
					$this->flush();
					$this->position += ($this->unencryptedBlockSize - $blockPosition);
					$length += ($this->unencryptedBlockSize - $blockPosition);
					$data = substr($data, $this->unencryptedBlockSize - $blockPosition);
				}
			} else {
				$data = '';
			}
			$this->unencryptedSize = max($this->unencryptedSize, $this->position);
		}
		return $length;
	}

	public function stream_tell() {
		return $this->position;
	}

	public function stream_seek($offset, $whence = SEEK_SET) {
		$return = false;

		switch ($whence) {
			case SEEK_SET:
				$newPosition = $offset;
				break;
			case SEEK_CUR:
				$newPosition = $this->position + $offset;
				break;
			case SEEK_END:
				$newPosition = $this->unencryptedSize + $offset;
				break;
			default:
				return $return;
		}

		if ($newPosition > $this->unencryptedSize || $newPosition < 0) {
			return $return;
		}

		$newFilePosition = (int)floor($newPosition / $this->unencryptedBlockSize)
			* $this->util->getBlockSize() + $this->headerSize;

		$oldFilePosition = parent::stream_tell();
		if ($this->parentStreamSeek($newFilePosition)) {
			$this->parentStreamSeek($oldFilePosition);
			$this->flush();
			$this->parentStreamSeek($newFilePosition);
			$this->position = $newPosition;
			$return = true;
		}
		return $return;
	}

	public function stream_close() {
		$this->flush('end');
		$position = (int)floor($this->position / $this->unencryptedBlockSize);
		$remainingData = $this->encryptionModule->end($this->fullPath, $position . 'end');
		if ($this->readOnly === false) {
			if (!empty($remainingData)) {
				parent::stream_write($remainingData);
			}
			$this->encryptionStorage->updateUnencryptedSize($this->fullPath, $this->unencryptedSize);
		}
		$result = parent::stream_close();

		if ($this->fileUpdated) {
			$cache = $this->storage->getCache();
			$cacheEntry = $cache->get($this->internalPath);
			if ($cacheEntry) {
				$version = $cacheEntry['encryptedVersion'] + 1;
				$cache->update($cacheEntry->getId(), ['encrypted' => $version, 'encryptedVersion' => $version]);
			}
		}

		return $result;
	}

	/**
	 * write block to file
	 * @param string $positionPrefix
	 */
	protected function flush($positionPrefix = '') {
		// write to disk only when writeFlag was set to 1
		if ($this->writeFlag) {
			// Disable the file proxies so that encryption is not
			// automatically attempted when the file is written to disk -
			// we are handling that separately here and we don't want to
			// get into an infinite loop
			$position = (int)floor($this->position / $this->unencryptedBlockSize);
			$encrypted = $this->encryptionModule->encrypt($this->cache, $position . $positionPrefix);
			$bytesWritten = parent::stream_write($encrypted);
			$this->writeFlag = false;
			// Check whether the write concerns the last block
			// If so then update the encrypted filesize
			// Note that the unencrypted pointer and filesize are NOT yet updated when flush() is called
			// We recalculate the encrypted filesize as we do not know the context of calling flush()
			$completeBlocksInFile = (int)floor($this->unencryptedSize / $this->unencryptedBlockSize);
			if ($completeBlocksInFile === (int)floor($this->position / $this->unencryptedBlockSize)) {
				$this->size = $this->util->getBlockSize() * $completeBlocksInFile;
				$this->size += $bytesWritten;
				$this->size += $this->headerSize;
			}
		}
		// always empty the cache (otherwise readCache() will not fill it with the new block)
		$this->cache = '';
	}

	/**
	 * read block to file
	 */
	protected function readCache() {
		// cache should always be empty string when this function is called
		// don't try to fill the cache when trying to write at the end of the unencrypted file when it coincides with new block
		if ($this->cache === '' && !($this->position === $this->unencryptedSize && ($this->position % $this->unencryptedBlockSize) === 0)) {
			// Get the data from the file handle
			$data = $this->stream_read_block($this->util->getBlockSize());
			$position = (int)floor($this->position / $this->unencryptedBlockSize);
			$numberOfChunks = (int)($this->unencryptedSize / $this->unencryptedBlockSize);
			if ($numberOfChunks === $position) {
				$position .= 'end';
			}
			$this->cache = $this->encryptionModule->decrypt($data, $position);
		}
	}

	/**
	 * write header at beginning of encrypted file
	 *
	 * @return integer
	 * @throws EncryptionHeaderKeyExistsException if header key is already in use
	 */
	protected function writeHeader() {
		$header = $this->util->createHeader($this->newHeader, $this->encryptionModule);
		return parent::stream_write($header);
	}

	/**
	 * read first block to skip the header
	 */
	protected function skipHeader() {
		$this->stream_read_block($this->headerSize);
	}

	/**
	 * call stream_seek() from parent class
	 *
	 * @param integer $position
	 * @return bool
	 */
	protected function parentStreamSeek($position) {
		return parent::stream_seek($position);
	}

	/**
	 * @param string $path
	 * @param array $options
	 * @return bool
	 */
	public function dir_opendir($path, $options) {
		return false;
	}
}
