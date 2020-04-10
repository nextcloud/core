<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Command;

use OCA\DAV\CalDAV\BirthdayService;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncBirthdayCalendar extends Command {

	/** @var BirthdayService */
	private $birthdayService;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/**
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param BirthdayService $birthdayService
	 */
	function __construct(IUserManager $userManager, IConfig $config,
						 BirthdayService $birthdayService) {
		parent::__construct();
		$this->birthdayService = $birthdayService;
		$this->config = $config;
		$this->userManager = $userManager;
	}

	protected function configure() {
		$this
			->setName('dav:sync-birthday-calendar')
			->setDescription('Synchronizes the birthday calendar')
			->addArgument('user',
				InputArgument::OPTIONAL,
				'User for whom the birthday calendar will be synchronized');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->verifyEnabled();

		$user = $input->getArgument('user');
		if (!is_null($user)) {
			if (!$this->userManager->userExists($user)) {
				throw new \InvalidArgumentException("User <$user> in unknown.");
			}

			// re-enable the birthday calendar in case it's called directly with a user name
			$isEnabled = $this->config->getUserValue($user, 'dav', 'generateBirthdayCalendar', 'yes');
			if ($isEnabled !== 'yes') {
				$this->config->setUserValue($user, 'dav', 'generateBirthdayCalendar', 'yes');
				$output->writeln("Re-enabling birthday calendar for $user");
			}

			$output->writeln("Start birthday calendar sync for $user");
			$this->birthdayService->syncUser($user);
			return;
		}
		$output->writeln("Start birthday calendar sync for all users ...");
		$p = new ProgressBar($output);
		$p->start();
		$this->userManager->callForSeenUsers(function ($user) use ($p) {
			$p->advance();

			$userId = $user->getUID();
			$isEnabled = $this->config->getUserValue($userId, 'dav', 'generateBirthdayCalendar', 'yes');
			if ($isEnabled !== 'yes') {
				return;
			}

			/** @var IUser $user */
			$this->birthdayService->syncUser($user->getUID());
		});

		$p->finish();
		$output->writeln('');
	}

	protected function verifyEnabled() {
		$isEnabled = $this->config->getAppValue('dav', 'generateBirthdayCalendar', 'yes');

		if ($isEnabled !== 'yes') {
			throw new \InvalidArgumentException('Birthday calendars are disabled');
		}
	}
}
