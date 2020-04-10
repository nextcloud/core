<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Liam Dennehy <liam@wiemax.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Trashbin\Command;

use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IUserBackend;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanUp extends Command {

	/** @var IUserManager */
	protected $userManager;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var \OCP\IDBConnection */
	protected $dbConnection;

	/**
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 * @param IDBConnection $dbConnection
	 */
	function __construct(IRootFolder $rootFolder, IUserManager $userManager, IDBConnection $dbConnection) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->dbConnection = $dbConnection;
	}

	protected function configure() {
		$this
			->setName('trashbin:cleanup')
			->setDescription('Remove deleted files')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'remove deleted files of the given user(s)'
			)
			->addOption(
				'all-users',
				null,
				InputOption::VALUE_NONE,
				'run action on all users'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$users = $input->getArgument('user_id');
		if ((!empty($users)) and ($input->getOption('all-users'))) {
			throw new InvalidOptionException('Either specify a user_id or --all-users');
		} elseif (!empty($users)) {
			foreach ($users as $user) {
				if ($this->userManager->userExists($user)) {
					$output->writeln("Remove deleted files of   <info>$user</info>");
					$this->removeDeletedFiles($user);
				} else {
					$output->writeln("<error>Unknown user $user</error>");
				}
			}
		} elseif ($input->getOption('all-users')) {
			$output->writeln('Remove deleted files for all users');
			foreach ($this->userManager->getBackends() as $backend) {
				$name = get_class($backend);
				if ($backend instanceof IUserBackend) {
					$name = $backend->getBackendName();
				}
				$output->writeln("Remove deleted files for users on backend <info>$name</info>");
				$limit = 500;
				$offset = 0;
				do {
					$users = $backend->getUsers('', $limit, $offset);
					foreach ($users as $user) {
						$output->writeln("   <info>$user</info>");
						$this->removeDeletedFiles($user);
					}
					$offset += $limit;
				} while (count($users) >= $limit);
			}
		} else {
			throw new InvalidOptionException('Either specify a user_id or --all-users');
		}
	}

	/**
	 * remove deleted files for the given user
	 *
	 * @param string $uid
	 */
	protected function removeDeletedFiles($uid) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($uid);
		if ($this->rootFolder->nodeExists('/' . $uid . '/files_trashbin')) {
			$this->rootFolder->get('/' . $uid . '/files_trashbin')->delete();
			$query = $this->dbConnection->getQueryBuilder();
			$query->delete('files_trash')
				->where($query->expr()->eq('user', $query->createParameter('uid')))
				->setParameter('uid', $uid);
			$query->execute();
		}
	}

}
