<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christian Jürges <christian@eqipe.ch>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author sammo2828 <sammo2828@gmail.com>
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

namespace OC\Encryption;

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Files\View;
use OCP\Encryption\IEncryptionModule;
use OCP\IUserManager;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DecryptAll {

	/** @var  OutputInterface */
	protected $output;

	/** @var  InputInterface */
	protected $input;

	/** @var  Manager */
	protected $encryptionManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var View */
	protected $rootView;

	/** @var  array files which couldn't be decrypted */
	protected $failed;

	/**
	 * @param Manager $encryptionManager
	 * @param IUserManager $userManager
	 * @param View $rootView
	 */
	public function __construct(
		Manager $encryptionManager,
		IUserManager $userManager,
		View $rootView
	) {
		$this->encryptionManager = $encryptionManager;
		$this->userManager = $userManager;
		$this->rootView = $rootView;
		$this->failed = [];
	}

	/**
	 * start to decrypt all files
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param string $user which users data folder should be decrypted, default = all users
	 * @return bool
	 * @throws \Exception
	 */
	public function decryptAll(InputInterface $input, OutputInterface $output, $user = '') {
		$this->input = $input;
		$this->output = $output;

		if ($user !== '' && $this->userManager->userExists($user) === false) {
			$this->output->writeln('User "' . $user . '" does not exist. Please check the username and try again');
			return false;
		}

		$this->output->writeln('prepare encryption modules...');
		if ($this->prepareEncryptionModules($user) === false) {
			return false;
		}
		$this->output->writeln(' done.');

		$this->decryptAllUsersFiles($user);

		if (empty($this->failed)) {
			$this->output->writeln('all files could be decrypted successfully!');
		} else {
			$this->output->writeln('Files for following users couldn\'t be decrypted, ');
			$this->output->writeln('maybe the user is not set up in a way that supports this operation: ');
			foreach ($this->failed as $uid => $paths) {
				$this->output->writeln('    ' . $uid);
				foreach ($paths as $path) {
					$this->output->writeln('        ' . $path);
				}
			}
			$this->output->writeln('');
		}

		return true;
	}

	/**
	 * prepare encryption modules to perform the decrypt all function
	 *
	 * @param $user
	 * @return bool
	 */
	protected function prepareEncryptionModules($user) {
		// prepare all encryption modules for decrypt all
		$encryptionModules = $this->encryptionManager->getEncryptionModules();
		foreach ($encryptionModules as $moduleDesc) {
			/** @var IEncryptionModule $module */
			$module = call_user_func($moduleDesc['callback']);
			$this->output->writeln('');
			$this->output->writeln('Prepare "' . $module->getDisplayName() . '"');
			$this->output->writeln('');
			if ($module->prepareDecryptAll($this->input, $this->output, $user) === false) {
				$this->output->writeln('Module "' . $moduleDesc['displayName'] . '" does not support the functionality to decrypt all files again or the initialization of the module failed!');
				return false;
			}
		}

		return true;
	}

	/**
	 * iterate over all user and encrypt their files
	 *
	 * @param string $user which users files should be decrypted, default = all users
	 */
	protected function decryptAllUsersFiles($user = '') {
		$this->output->writeln("\n");

		$userList = [];
		if ($user === '') {
			$fetchUsersProgress = new ProgressBar($this->output);
			$fetchUsersProgress->setFormat(" %message% \n [%bar%]");
			$fetchUsersProgress->start();
			$fetchUsersProgress->setMessage("Fetch list of users...");
			$fetchUsersProgress->advance();

			foreach ($this->userManager->getBackends() as $backend) {
				$limit = 500;
				$offset = 0;
				do {
					$users = $backend->getUsers('', $limit, $offset);
					foreach ($users as $user) {
						$userList[] = $user;
					}
					$offset += $limit;
					$fetchUsersProgress->advance();
				} while (count($users) >= $limit);
				$fetchUsersProgress->setMessage("Fetch list of users... finished");
				$fetchUsersProgress->finish();
			}
		} else {
			$userList[] = $user;
		}

		$this->output->writeln("\n\n");

		$progress = new ProgressBar($this->output);
		$progress->setFormat(" %message% \n [%bar%]");
		$progress->start();
		$progress->setMessage("starting to decrypt files...");
		$progress->advance();

		$numberOfUsers = count($userList);
		$userNo = 1;
		foreach ($userList as $uid) {
			$userCount = "$uid ($userNo of $numberOfUsers)";
			$this->decryptUsersFiles($uid, $progress, $userCount);
			$userNo++;
		}

		$progress->setMessage("starting to decrypt files... finished");
		$progress->finish();

		$this->output->writeln("\n\n");
	}

	/**
	 * encrypt files from the given user
	 *
	 * @param string $uid
	 * @param ProgressBar $progress
	 * @param string $userCount
	 */
	protected function decryptUsersFiles($uid, ProgressBar $progress, $userCount) {
		$this->setupUserFS($uid);
		$directories = [];
		$directories[] = '/' . $uid . '/files';

		while ($root = array_pop($directories)) {
			$content = $this->rootView->getDirectoryContent($root);
			foreach ($content as $file) {
				// only decrypt files owned by the user
				if ($file->getStorage()->instanceOfStorage('OCA\Files_Sharing\SharedStorage')) {
					continue;
				}
				$path = $root . '/' . $file['name'];
				if ($this->rootView->is_dir($path)) {
					$directories[] = $path;
					continue;
				} else {
					try {
						$progress->setMessage("decrypt files for user $userCount: $path");
						$progress->advance();
						if ($file->isEncrypted() === false) {
							$progress->setMessage("decrypt files for user $userCount: $path (already decrypted)");
							$progress->advance();
						} else {
							if ($this->decryptFile($path) === false) {
								$progress->setMessage("decrypt files for user $userCount: $path (already decrypted)");
								$progress->advance();
							}
						}
					} catch (\Exception $e) {
						if (isset($this->failed[$uid])) {
							$this->failed[$uid][] = $path;
						} else {
							$this->failed[$uid] = [$path];
						}
					}
				}
			}
		}
	}

	/**
	 * encrypt file
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function decryptFile($path) {

		// skip already decrypted files
		$fileInfo = $this->rootView->getFileInfo($path);
		if ($fileInfo !== false && !$fileInfo->isEncrypted()) {
			return true;
		}

		$source = $path;
		$target = $path . '.decrypted.' . $this->getTimestamp();

		try {
			$this->rootView->copy($source, $target);
			$this->rootView->touch($target, $fileInfo->getMTime());
			$this->rootView->rename($target, $source);
		} catch (DecryptionFailedException $e) {
			if ($this->rootView->file_exists($target)) {
				$this->rootView->unlink($target);
			}
			return false;
		}

		return true;
	}

	/**
	 * get current timestamp
	 *
	 * @return int
	 */
	protected function getTimestamp() {
		return time();
	}


	/**
	 * setup user file system
	 *
	 * @param string $uid
	 */
	protected function setupUserFS($uid) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($uid);
	}
}
