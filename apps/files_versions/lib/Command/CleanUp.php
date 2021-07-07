<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Daniel Rudolf <nextcloud.com@daniel-rudolf.de>
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
namespace OCA\Files_Versions\Command;

use OCP\Files\IRootFolder;
use OCP\IUserBackend;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanUp extends Command {

	/** @var IUserManager */
	protected $userManager;

	/** @var IRootFolder */
	protected $rootFolder;

	/**
	 * @param IRootFolder $rootFolder
	 * @param IUserManager $userManager
	 */
	public function __construct(IRootFolder $rootFolder, IUserManager $userManager) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
	}

	protected function configure() {
		$this
			->setName('versions:cleanup')
			->setDescription('Delete versions')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
				'delete versions of the given user(s), if no user is given all versions will be deleted'
			)
			->addOption(
				'path',
				'p',
				InputOption::VALUE_REQUIRED,
				'only delete versions of this path, e.g. --path="/alice/files_versions/Music"'
			);
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		$users = $input->getArgument('user_id');

		$path = $input->getOption('path');
		if ($path) {
			if (!preg_match('#^/([^/]+)/files_versions(/.*)?$#', $path, $pathMatches)) {
				$output->writeln("<error>Invalid path given</error>");
				return 1;
			}

			$users = [ $pathMatches[1] ];
			$path = trim($pathMatches[2], '/');
		}

		if (!empty($users)) {
			foreach ($users as $user) {
				if ($this->userManager->userExists($user)) {
					$output->writeln("Delete versions of   <info>$user</info>");
					$this->deleteVersions($user, $path);
				} else {
					$output->writeln("<error>Unknown user $user</error>");
					return 1;
				}
			}
		} else {
			$output->writeln('Delete all versions');
			foreach ($this->userManager->getBackends() as $backend) {
				$name = get_class($backend);

				if ($backend instanceof IUserBackend) {
					$name = $backend->getBackendName();
				}

				$output->writeln("Delete versions for users on backend <info>$name</info>");

				$limit = 500;
				$offset = 0;
				do {
					$users = $backend->getUsers('', $limit, $offset);
					foreach ($users as $user) {
						$output->writeln("   <info>$user</info>");
						$this->deleteVersions($user);
					}
					$offset += $limit;
				} while (count($users) >= $limit);
			}
		}
		return 0;
	}


	/**
	 * delete versions for the given user
	 *
	 * @param string      $user
	 * @param string|null $path
	 */
	protected function deleteVersions(string $user, string $path = null): void {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($user);

		$fullPath = '/' . $user . '/files_versions' . ($path ? '/' . $path : '');
		if ($this->rootFolder->nodeExists($fullPath)) {
			$this->rootFolder->get($fullPath)->delete();
		}
	}
}
