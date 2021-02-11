<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Encryption\Crypto;

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Files\Cache\Scanner;
use OC\Files\View;
use OCA\Encryption\Exceptions\PublicKeyMissingException;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\Encryption\IEncryptionModule;
use OCP\IL10N;
use OCP\ILogger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Encryption implements IEncryptionModule {
	public const ID = 'OC_DEFAULT_MODULE';
	public const DISPLAY_NAME = 'Default encryption module';

	/**
	 * @var Crypt
	 */
	private $crypt;

	/** @var string */
	private $cipher;

	/** @var string */
	private $path;

	/** @var string */
	private $user;

	/** @var  array */
	private $owner;

	/** @var string */
	private $fileKey;

	/** @var string */
	private $writeCache;

	/** @var KeyManager */
	private $keyManager;

	/** @var array */
	private $accessList;

	/** @var boolean */
	private $isWriteOperation;

	/** @var Util */
	private $util;

	/** @var  Session */
	private $session;

	/** @var  ILogger */
	private $logger;

	/** @var IL10N */
	private $l;

	/** @var EncryptAll */
	private $encryptAll;

	/** @var  bool */
	private $useMasterPassword;

	/** @var DecryptAll  */
	private $decryptAll;

	/** @var int unencrypted block size if block contains signature */
	private $unencryptedBlockSizeSigned = 6072;

	/** @var int unencrypted block size */
	private $unencryptedBlockSize = 6126;

	/** @var int Current version of the file */
	private $version = 0;

	/** @var array remember encryption signature version */
	private static $rememberVersion = [];


	/**
	 *
	 * @param Crypt $crypt
	 * @param KeyManager $keyManager
	 * @param Util $util
	 * @param Session $session
	 * @param EncryptAll $encryptAll
	 * @param DecryptAll $decryptAll
	 * @param ILogger $logger
	 * @param IL10N $il10n
	 */
	public function __construct(Crypt $crypt,
								KeyManager $keyManager,
								Util $util,
								Session $session,
								EncryptAll $encryptAll,
								DecryptAll $decryptAll,
								ILogger $logger,
								IL10N $il10n) {
		$this->crypt = $crypt;
		$this->keyManager = $keyManager;
		$this->util = $util;
		$this->session = $session;
		$this->encryptAll = $encryptAll;
		$this->decryptAll = $decryptAll;
		$this->logger = $logger;
		$this->l = $il10n;
		$this->owner = [];
		$this->useMasterPassword = $util->isMasterKeyEnabled();
	}

	/**
	 * @return string defining the technical unique id
	 */
	public function getId() {
		return self::ID;
	}

	/**
	 * In comparison to getKey() this function returns a human readable (maybe translated) name
	 *
	 * @return string
	 */
	public function getDisplayName() {
		return self::DISPLAY_NAME;
	}

	/**
	 * start receiving chunks from a file. This is the place where you can
	 * perform some initial step before starting encrypting/decrypting the
	 * chunks
	 *
	 * @param string $path to the file
	 * @param string $user who read/write the file
	 * @param string $mode php stream open mode
	 * @param array $header contains the header data read from the file
	 * @param array $accessList who has access to the file contains the key 'users' and 'public'
	 *
	 * @return array $header contain data as key-value pairs which should be
	 *                       written to the header, in case of a write operation
	 *                       or if no additional data is needed return a empty array
	 */
	public function begin($path, $user, $mode, array $header, array $accessList) {
		$this->path = $this->getPathToRealFile($path);
		$this->accessList = $accessList;
		$this->user = $user;
		$this->isWriteOperation = false;
		$this->writeCache = '';

		if ($this->session->isReady() === false) {
			// if the master key is enabled we can initialize encryption
			// with a empty password and user name
			if ($this->util->isMasterKeyEnabled()) {
				$this->keyManager->init('', '');
			}
		}

		if ($this->session->decryptAllModeActivated()) {
			$encryptedFileKey = $this->keyManager->getEncryptedFileKey($this->path);
			$shareKey = $this->keyManager->getShareKey($this->path, $this->session->getDecryptAllUid());
			$this->fileKey = $this->crypt->multiKeyDecrypt($encryptedFileKey,
				$shareKey,
				$this->session->getDecryptAllKey());
		} else {
			$this->fileKey = $this->keyManager->getFileKey($this->path, $this->user);
		}

		// always use the version from the original file, also part files
		// need to have a correct version number if they get moved over to the
		// final location
		$this->version = (int)$this->keyManager->getVersion($this->stripPartFileExtension($path), new View());

		if (
			$mode === 'w'
			|| $mode === 'w+'
			|| $mode === 'wb'
			|| $mode === 'wb+'
		) {
			$this->isWriteOperation = true;
			if (empty($this->fileKey)) {
				$this->fileKey = $this->crypt->generateFileKey();
			}
		} else {
			// if we read a part file we need to increase the version by 1
			// because the version number was also increased by writing
			// the part file
			if (Scanner::isPartialFile($path)) {
				$this->version = $this->version + 1;
			}
		}

		if ($this->isWriteOperation) {
			$this->cipher = $this->crypt->getCipher();
		} elseif (isset($header['cipher'])) {
			$this->cipher = $header['cipher'];
		} else {
			// if we read a file without a header we fall-back to the legacy cipher
			// which was used in <=oC6
			$this->cipher = $this->crypt->getLegacyCipher();
		}

		return ['cipher' => $this->cipher, 'signed' => 'true'];
	}

	/**
	 * last chunk received. This is the place where you can perform some final
	 * operation and return some remaining data if something is left in your
	 * buffer.
	 *
	 * @param string $path to the file
	 * @param int $position
	 * @return string remained data which should be written to the file in case
	 *                of a write operation
	 * @throws PublicKeyMissingException
	 * @throws \Exception
	 * @throws \OCA\Encryption\Exceptions\MultiKeyEncryptException
	 */
	public function end($path, $position = 0) {
		$result = '';
		if ($this->isWriteOperation) {
			// in case of a part file we remember the new signature versions
			// the version will be set later on update.
			// This way we make sure that other apps listening to the pre-hooks
			// still get the old version which should be the correct value for them
			if (Scanner::isPartialFile($path)) {
				self::$rememberVersion[$this->stripPartFileExtension($path)] = $this->version + 1;
			}
			if (!empty($this->writeCache)) {
				$result = $this->crypt->symmetricEncryptFileContent($this->writeCache, $this->fileKey, $this->version + 1, $position);
				$this->writeCache = '';
			}
			$publicKeys = [];
			if ($this->useMasterPassword === true) {
				$publicKeys[$this->keyManager->getMasterKeyId()] = $this->keyManager->getPublicMasterKey();
			} else {
				foreach ($this->accessList['users'] as $uid) {
					try {
						$publicKeys[$uid] = $this->keyManager->getPublicKey($uid);
					} catch (PublicKeyMissingException $e) {
						$this->logger->warning(
							'no public key found for user "{uid}", user will not be able to read the file',
							['app' => 'encryption', 'uid' => $uid]
						);
						// if the public key of the owner is missing we should fail
						if ($uid === $this->user) {
							throw $e;
						}
					}
				}
			}

			$publicKeys = $this->keyManager->addSystemKeys($this->accessList, $publicKeys, $this->getOwner($path));
			$encryptedKeyfiles = $this->crypt->multiKeyEncrypt($this->fileKey, $publicKeys);
			$this->keyManager->setAllFileKeys($this->path, $encryptedKeyfiles);
		}
		return $result;
	}



	/**
	 * encrypt data
	 *
	 * @param string $data you want to encrypt
	 * @param int $position
	 * @return string encrypted data
	 */
	public function encrypt($data, $position = 0) {
		// If extra data is left over from the last round, make sure it
		// is integrated into the next block
		if ($this->writeCache) {

			// Concat writeCache to start of $data
			$data = $this->writeCache . $data;

			// Clear the write cache, ready for reuse - it has been
			// flushed and its old contents processed
			$this->writeCache = '';
		}

		$encrypted = '';
		// While there still remains some data to be processed & written
		while (strlen($data) > 0) {

			// Remaining length for this iteration, not of the
			// entire file (may be greater than 8192 bytes)
			$remainingLength = strlen($data);

			// If data remaining to be written is less than the
			// size of 1 6126 byte block
			if ($remainingLength < $this->unencryptedBlockSizeSigned) {

				// Set writeCache to contents of $data
				// The writeCache will be carried over to the
				// next write round, and added to the start of
				// $data to ensure that written blocks are
				// always the correct length. If there is still
				// data in writeCache after the writing round
				// has finished, then the data will be written
				// to disk by $this->flush().
				$this->writeCache = $data;

				// Clear $data ready for next round
				$data = '';
			} else {

				// Read the chunk from the start of $data
				$chunk = substr($data, 0, $this->unencryptedBlockSizeSigned);

				$encrypted .= $this->crypt->symmetricEncryptFileContent($chunk, $this->fileKey, $this->version + 1, $position);

				// Remove the chunk we just processed from
				// $data, leaving only unprocessed data in $data
				// var, for handling on the next round
				$data = substr($data, $this->unencryptedBlockSizeSigned);
			}
		}

		return $encrypted;
	}

	/**
	 * decrypt data
	 *
	 * @param string $data you want to decrypt
	 * @param int|string $position
	 * @return string decrypted data
	 * @throws DecryptionFailedException
	 */
	public function decrypt($data, $position = 0) {
		if (empty($this->fileKey)) {
			$msg = 'Can not decrypt this file, probably this is a shared file. Please ask the file owner to reshare the file with you.';
			$hint = $this->l->t('Can not decrypt this file, probably this is a shared file. Please ask the file owner to reshare the file with you.');
			$this->logger->error($msg);

			throw new DecryptionFailedException($msg, $hint);
		}

		return $this->crypt->symmetricDecryptFileContent($data, $this->fileKey, $this->cipher, $this->version, $position);
	}

	/**
	 * update encrypted file, e.g. give additional users access to the file
	 *
	 * @param string $path path to the file which should be updated
	 * @param string $uid of the user who performs the operation
	 * @param array $accessList who has access to the file contains the key 'users' and 'public'
	 * @return boolean
	 */
	public function update($path, $uid, array $accessList) {
		if (empty($accessList)) {
			if (isset(self::$rememberVersion[$path])) {
				$this->keyManager->setVersion($path, self::$rememberVersion[$path], new View());
				unset(self::$rememberVersion[$path]);
			}
			return;
		}

		$fileKey = $this->keyManager->getFileKey($path, $uid);

		if (!empty($fileKey)) {
			$publicKeys = [];
			if ($this->useMasterPassword === true) {
				$publicKeys[$this->keyManager->getMasterKeyId()] = $this->keyManager->getPublicMasterKey();
			} else {
				foreach ($accessList['users'] as $user) {
					try {
						$publicKeys[$user] = $this->keyManager->getPublicKey($user);
					} catch (PublicKeyMissingException $e) {
						$this->logger->warning('Could not encrypt file for ' . $user . ': ' . $e->getMessage());
					}
				}
			}

			$publicKeys = $this->keyManager->addSystemKeys($accessList, $publicKeys, $this->getOwner($path));

			$encryptedFileKey = $this->crypt->multiKeyEncrypt($fileKey, $publicKeys);

			$this->keyManager->deleteAllFileKeys($path);

			$this->keyManager->setAllFileKeys($path, $encryptedFileKey);
		} else {
			$this->logger->debug('no file key found, we assume that the file "{file}" is not encrypted',
				['file' => $path, 'app' => 'encryption']);

			return false;
		}

		return true;
	}

	/**
	 * should the file be encrypted or not
	 *
	 * @param string $path
	 * @return boolean
	 */
	public function shouldEncrypt($path) {
		if ($this->util->shouldEncryptHomeStorage() === false) {
			$storage = $this->util->getStorage($path);
			if ($storage->instanceOfStorage('\OCP\Files\IHomeStorage')) {
				return false;
			}
		}
		$parts = explode('/', $path);
		if (count($parts) < 4) {
			return false;
		}

		if ($parts[2] === 'files') {
			return true;
		}
		if ($parts[2] === 'files_versions') {
			return true;
		}
		if ($parts[2] === 'files_trashbin') {
			return true;
		}

		return false;
	}

	/**
	 * get size of the unencrypted payload per block.
	 * Nextcloud read/write files with a block size of 8192 byte
	 *
	 * @param bool $signed
	 * @return int
	 */
	public function getUnencryptedBlockSize($signed = false) {
		if ($signed === false) {
			return $this->unencryptedBlockSize;
		}

		return $this->unencryptedBlockSizeSigned;
	}

	/**
	 * check if the encryption module is able to read the file,
	 * e.g. if all encryption keys exists
	 *
	 * @param string $path
	 * @param string $uid user for whom we want to check if he can read the file
	 * @return bool
	 * @throws DecryptionFailedException
	 */
	public function isReadable($path, $uid) {
		$fileKey = $this->keyManager->getFileKey($path, $uid);
		if (empty($fileKey)) {
			$owner = $this->util->getOwner($path);
			if ($owner !== $uid) {
				// if it is a shared file we throw a exception with a useful
				// error message because in this case it means that the file was
				// shared with the user at a point where the user didn't had a
				// valid private/public key
				$msg = 'Encryption module "' . $this->getDisplayName() .
					'" is not able to read ' . $path;
				$hint = $this->l->t('Can not read this file, probably this is a shared file. Please ask the file owner to reshare the file with you.');
				$this->logger->warning($msg);
				throw new DecryptionFailedException($msg, $hint);
			}
			return false;
		}

		return true;
	}

	/**
	 * Initial encryption of all files
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output write some status information to the terminal during encryption
	 */
	public function encryptAll(InputInterface $input, OutputInterface $output) {
		$this->encryptAll->encryptAll($input, $output);
	}

	/**
	 * prepare module to perform decrypt all operation
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $user
	 * @return bool
	 */
	public function prepareDecryptAll(InputInterface $input, OutputInterface $output, $user = '') {
		return $this->decryptAll->prepare($input, $output, $user);
	}


	/**
	 * @param string $path
	 * @return string
	 */
	protected function getPathToRealFile($path) {
		$realPath = $path;
		$parts = explode('/', $path);
		if ($parts[2] === 'files_versions') {
			$realPath = '/' . $parts[1] . '/files/' . implode('/', array_slice($parts, 3));
			$length = strrpos($realPath, '.');
			$realPath = substr($realPath, 0, $length);
		}

		return $realPath;
	}

	/**
	 * remove .part file extension and the ocTransferId from the file to get the
	 * original file name
	 *
	 * @param string $path
	 * @return string
	 */
	protected function stripPartFileExtension($path) {
		if (pathinfo($path, PATHINFO_EXTENSION) === 'part') {
			$pos = strrpos($path, '.', -6);
			$path = substr($path, 0, $pos);
		}

		return $path;
	}

	/**
	 * get owner of a file
	 *
	 * @param string $path
	 * @return string
	 */
	protected function getOwner($path) {
		if (!isset($this->owner[$path])) {
			$this->owner[$path] = $this->util->getOwner($path);
		}
		return $this->owner[$path];
	}

	/**
	 * Check if the module is ready to be used by that specific user.
	 * In case a module is not ready - because e.g. key pairs have not been generated
	 * upon login this method can return false before any operation starts and might
	 * cause issues during operations.
	 *
	 * @param string $user
	 * @return boolean
	 * @since 9.1.0
	 */
	public function isReadyForUser($user) {
		if ($this->util->isMasterKeyEnabled()) {
			return true;
		}
		return $this->keyManager->userHasKeys($user);
	}

	/**
	 * We only need a detailed access list if the master key is not enabled
	 *
	 * @return bool
	 */
	public function needDetailedAccessList() {
		return !$this->util->isMasterKeyEnabled();
	}
}
