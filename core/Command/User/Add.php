<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Laurens Post <lkpost@scept.re>
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

namespace OC\Core\Command\User;

use OC\Files\Filesystem;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class Add extends Command {
	/** @var \OCP\IUserManager */
	protected $userManager;

	/** @var \OCP\IGroupManager */
	protected $groupManager;

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IUserManager $userManager, IGroupManager $groupManager) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
	}

	protected function configure() {
		$this
			->setName('user:add')
			->setDescription('adds a user')
			->addArgument(
				'uid',
				InputArgument::REQUIRED,
				'User ID used to login (must only contain a-z, A-Z, 0-9, -, _ and @)'
			)
			->addOption(
				'password-from-env',
				null,
				InputOption::VALUE_NONE,
				'read password from environment variable OC_PASS'
			)
			->addOption(
				'display-name',
				null,
				InputOption::VALUE_OPTIONAL,
				'User name used in the web UI (can contain any characters)'
			)
			->addOption(
				'group',
				'g',
				InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
				'groups the user should be added to (The group will be created if it does not exist)'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$uid = $input->getArgument('uid');
		if ($this->userManager->userExists($uid)) {
			$output->writeln('<error>The user "' . $uid . '" already exists.</error>');
			return 1;
		}

		if ($input->getOption('password-from-env')) {
			$password = getenv('OC_PASS');
			if (!$password) {
				$output->writeln('<error>--password-from-env given, but OC_PASS is empty!</error>');
				return 1;
			}
		} elseif ($input->isInteractive()) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');

			$question = new Question('Enter password: ');
			$question->setHidden(true);
			$password = $helper->ask($input, $output, $question);

			$question = new Question('Confirm password: ');
			$question->setHidden(true);
			$confirm = $helper->ask($input, $output,$question);

			if ($password !== $confirm) {
				$output->writeln("<error>Passwords did not match!</error>");
				return 1;
			}
		} else {
			$output->writeln("<error>Interactive input or --password-from-env is needed for entering a password!</error>");
			return 1;
		}

		try {
			$user = $this->userManager->createUser(
				$input->getArgument('uid'),
				$password
			);
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}


		if ($user instanceof IUser) {
			$output->writeln('<info>The user "' . $user->getUID() . '" was created successfully</info>');
		} else {
			$output->writeln('<error>An error occurred while creating the user</error>');
			return 1;
		}

		if ($input->getOption('display-name')) {
			$user->setDisplayName($input->getOption('display-name'));
			$output->writeln('Display name set to "' . $user->getDisplayName() . '"');
		}

		$groups = $input->getOption('group');

		if (!empty($groups)) {
			// Make sure we init the Filesystem for the user, in case we need to
			// init some group shares.
			Filesystem::init($user->getUID(), '');
		}

		foreach ($groups as $groupName) {
			$group = $this->groupManager->get($groupName);
			if (!$group) {
				$this->groupManager->createGroup($groupName);
				$group = $this->groupManager->get($groupName);
				if($group instanceof IGroup) {
					$output->writeln('Created group "' . $group->getGID() . '"');
				}
			}
			if($group instanceof IGroup) {
				$group->addUser($user);
				$output->writeln('User "' . $user->getUID() . '" added to group "' . $group->getGID() . '"');
			}
		}
	}
}
