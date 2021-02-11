<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Command\Encryption;

use OC\Encryption\Keys\Storage;
use OC\Encryption\Util;
use OC\Files\Filesystem;
use OC\Files\View;
use OCP\IConfig;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ChangeKeyStorageRoot extends Command {

	/** @var View  */
	protected $rootView;

	/** @var IUserManager */
	protected $userManager;

	/** @var IConfig  */
	protected $config;

	/** @var Util  */
	protected $util;

	/** @var QuestionHelper */
	protected $questionHelper;

	/**
	 * @param View $view
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param Util $util
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(View $view, IUserManager $userManager, IConfig $config, Util $util, QuestionHelper $questionHelper) {
		parent::__construct();
		$this->rootView = $view;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->util = $util;
		$this->questionHelper = $questionHelper;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('encryption:change-key-storage-root')
			->setDescription('Change key storage root')
			->addArgument(
				'newRoot',
				InputArgument::OPTIONAL,
				'new root of the key storage relative to the data folder'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$oldRoot = $this->util->getKeyStorageRoot();
		$newRoot = $input->getArgument('newRoot');

		if ($newRoot === null) {
			$question = new ConfirmationQuestion('No storage root given, do you want to reset the key storage root to the default location? (y/n) ', false);
			if (!$this->questionHelper->ask($input, $output, $question)) {
				return 1;
			}
			$newRoot = '';
		}

		$oldRootDescription = $oldRoot !== '' ? $oldRoot : 'default storage location';
		$newRootDescription = $newRoot !== '' ? $newRoot : 'default storage location';
		$output->writeln("Change key storage root from <info>$oldRootDescription</info> to <info>$newRootDescription</info>");
		$success = $this->moveAllKeys($oldRoot, $newRoot, $output);
		if ($success) {
			$this->util->setKeyStorageRoot($newRoot);
			$output->writeln('');
			$output->writeln("Key storage root successfully changed to <info>$newRootDescription</info>");
			return 0;
		}
		return 1;
	}

	/**
	 * move keys to new key storage root
	 *
	 * @param string $oldRoot
	 * @param string $newRoot
	 * @param OutputInterface $output
	 * @return bool
	 * @throws \Exception
	 */
	protected function moveAllKeys($oldRoot, $newRoot, OutputInterface $output) {
		$output->writeln("Start to move keys:");

		if ($this->rootView->is_dir($oldRoot) === false) {
			$output->writeln("No old keys found: Nothing needs to be moved");
			return false;
		}

		$this->prepareNewRoot($newRoot);
		$this->moveSystemKeys($oldRoot, $newRoot);
		$this->moveUserKeys($oldRoot, $newRoot, $output);

		return true;
	}

	/**
	 * prepare new key storage
	 *
	 * @param string $newRoot
	 * @throws \Exception
	 */
	protected function prepareNewRoot($newRoot) {
		if ($this->rootView->is_dir($newRoot) === false) {
			throw new \Exception("New root folder doesn't exist. Please create the folder or check the permissions and try again.");
		}

		$result = $this->rootView->file_put_contents(
			$newRoot . '/' . Storage::KEY_STORAGE_MARKER,
			'Nextcloud will detect this folder as key storage root only if this file exists'
		);

		if (!$result) {
			throw new \Exception("Can't access the new root folder. Please check the permissions and make sure that the folder is in your data folder");
		}
	}


	/**
	 * move system key folder
	 *
	 * @param string $oldRoot
	 * @param string $newRoot
	 */
	protected function moveSystemKeys($oldRoot, $newRoot) {
		if (
			$this->rootView->is_dir($oldRoot . '/files_encryption') &&
			$this->targetExists($newRoot . '/files_encryption') === false
		) {
			$this->rootView->rename($oldRoot . '/files_encryption', $newRoot . '/files_encryption');
		}
	}


	/**
	 * setup file system for the given user
	 *
	 * @param string $uid
	 */
	protected function setupUserFS($uid) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($uid);
	}


	/**
	 * iterate over each user and move the keys to the new storage
	 *
	 * @param string $oldRoot
	 * @param string $newRoot
	 * @param OutputInterface $output
	 */
	protected function moveUserKeys($oldRoot, $newRoot, OutputInterface $output) {
		$progress = new ProgressBar($output);
		$progress->start();


		foreach ($this->userManager->getBackends() as $backend) {
			$limit = 500;
			$offset = 0;
			do {
				$users = $backend->getUsers('', $limit, $offset);
				foreach ($users as $user) {
					$progress->advance();
					$this->setupUserFS($user);
					$this->moveUserEncryptionFolder($user, $oldRoot, $newRoot);
				}
				$offset += $limit;
			} while (count($users) >= $limit);
		}
		$progress->finish();
	}

	/**
	 * move user encryption folder to new root folder
	 *
	 * @param string $user
	 * @param string $oldRoot
	 * @param string $newRoot
	 * @throws \Exception
	 */
	protected function moveUserEncryptionFolder($user, $oldRoot, $newRoot) {
		if ($this->userManager->userExists($user)) {
			$source = $oldRoot . '/' . $user . '/files_encryption';
			$target = $newRoot . '/' . $user . '/files_encryption';
			if (
				$this->rootView->is_dir($source) &&
				$this->targetExists($target) === false
			) {
				$this->prepareParentFolder($newRoot . '/' . $user);
				$this->rootView->rename($source, $target);
			}
		}
	}

	/**
	 * Make preparations to filesystem for saving a key file
	 *
	 * @param string $path relative to data/
	 */
	protected function prepareParentFolder($path) {
		$path = Filesystem::normalizePath($path);
		// If the file resides within a subdirectory, create it
		if ($this->rootView->file_exists($path) === false) {
			$sub_dirs = explode('/', ltrim($path, '/'));
			$dir = '';
			foreach ($sub_dirs as $sub_dir) {
				$dir .= '/' . $sub_dir;
				if ($this->rootView->file_exists($dir) === false) {
					$this->rootView->mkdir($dir);
				}
			}
		}
	}

	/**
	 * check if target already exists
	 *
	 * @param $path
	 * @return bool
	 * @throws \Exception
	 */
	protected function targetExists($path) {
		if ($this->rootView->file_exists($path)) {
			throw new \Exception("new folder '$path' already exists");
		}

		return false;
	}
}
