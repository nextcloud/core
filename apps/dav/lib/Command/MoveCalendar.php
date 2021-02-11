<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\Command;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\Calendar;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\IManager as IShareManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MoveCalendar extends Command {

	/** @var IUserManager */
	private $userManager;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IShareManager */
	private $shareManager;

	/** @var IConfig $config */
	private $config;

	/** @var IL10N */
	private $l10n;

	/** @var SymfonyStyle */
	private $io;

	/** @var CalDavBackend */
	private $calDav;

	public const URI_USERS = 'principals/users/';

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IShareManager $shareManager
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param CalDavBackend $calDav
	 */
	public function __construct(
		IUserManager $userManager,
		IGroupManager $groupManager,
		IShareManager $shareManager,
		IConfig $config,
		IL10N $l10n,
		CalDavBackend $calDav
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		$this->shareManager = $shareManager;
		$this->config = $config;
		$this->l10n = $l10n;
		$this->calDav = $calDav;
	}

	protected function configure() {
		$this
			->setName('dav:move-calendar')
			->setDescription('Move a calendar from an user to another')
			->addArgument('name',
				InputArgument::REQUIRED,
				'Name of the calendar to move')
			->addArgument('sourceuid',
				InputArgument::REQUIRED,
				'User who currently owns the calendar')
			->addArgument('destinationuid',
				InputArgument::REQUIRED,
				'User who will receive the calendar')
			->addOption('force', 'f', InputOption::VALUE_NONE, "Force the migration by removing existing shares and renaming calendars in case of conflicts");
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userOrigin = $input->getArgument('sourceuid');
		$userDestination = $input->getArgument('destinationuid');

		$this->io = new SymfonyStyle($input, $output);

		if (!$this->userManager->userExists($userOrigin)) {
			throw new \InvalidArgumentException("User <$userOrigin> is unknown.");
		}

		if (!$this->userManager->userExists($userDestination)) {
			throw new \InvalidArgumentException("User <$userDestination> is unknown.");
		}

		$name = $input->getArgument('name');
		$newName = null;

		$calendar = $this->calDav->getCalendarByUri(self::URI_USERS . $userOrigin, $name);

		if (null === $calendar) {
			throw new \InvalidArgumentException("User <$userOrigin> has no calendar named <$name>. You can run occ dav:list-calendars to list calendars URIs for this user.");
		}

		// Calendar already exists
		if ($this->calendarExists($userDestination, $name)) {
			if ($input->getOption('force')) {
				// Try to find a suitable name
				$newName = $this->getNewCalendarName($userDestination, $name);

				// If we didn't find a suitable value after all the iterations, give up
				if ($this->calendarExists($userDestination, $newName)) {
					throw new \InvalidArgumentException("Unable to find a suitable calendar name for <$userDestination> with initial name <$name>.");
				}
			} else {
				throw new \InvalidArgumentException("User <$userDestination> already has a calendar named <$name>.");
			}
		}

		$hadShares = $this->checkShares($calendar, $userOrigin, $userDestination, $input->getOption('force'));
		if ($hadShares) {
			/**
			 * Warn that share links have changed if there are shares
			 */
			$this->io->note([
				"Please note that moving calendar " . $calendar['uri'] . " from user <$userOrigin> to <$userDestination> has caused share links to change.",
				"Sharees will need to change \"example.com/remote.php/dav/calendars/uid/" . $calendar['uri'] . "_shared_by_$userOrigin\" to \"example.com/remote.php/dav/calendars/uid/" . $newName ?: $calendar['uri'] . "_shared_by_$userDestination\""
			]);
		}

		$this->calDav->moveCalendar($name, self::URI_USERS . $userOrigin, self::URI_USERS . $userDestination, $newName);

		$this->io->success("Calendar <$name> was moved from user <$userOrigin> to <$userDestination>" . ($newName ? " as <$newName>" : ''));
		return 0;
	}

	/**
	 * Check if the calendar exists for user
	 *
	 * @param string $userDestination
	 * @param string $name
	 * @return bool
	 */
	protected function calendarExists(string $userDestination, string $name): bool {
		return null !== $this->calDav->getCalendarByUri(self::URI_USERS . $userDestination, $name);
	}

	/**
	 * Try to find a suitable new calendar name that
	 * doesn't exists for the provided user
	 *
	 * @param string $userDestination
	 * @param string $name
	 * @return string
	 */
	protected function getNewCalendarName(string $userDestination, string $name): string {
		$increment = 1;
		$newName = $name . '-' . $increment;
		while ($increment <= 10) {
			$this->io->writeln("Trying calendar name <$newName>", OutputInterface::VERBOSITY_VERBOSE);
			if (!$this->calendarExists($userDestination, $newName)) {
				// New name is good to go
				$this->io->writeln("Found proper new calendar name <$newName>", OutputInterface::VERBOSITY_VERBOSE);
				break;
			}
			$newName = $name . '-' . $increment;
			$increment++;
		}

		return $newName;
	}

	/**
	 * Check that moving the calendar won't break shares
	 *
	 * @param array $calendar
	 * @param string $userOrigin
	 * @param string $userDestination
	 * @param bool $force
	 * @return bool had any shares or not
	 * @throws \InvalidArgumentException
	 */
	private function checkShares(array $calendar, string $userOrigin, string $userDestination, bool $force = false): bool {
		$shares = $this->calDav->getShares($calendar['id']);
		foreach ($shares as $share) {
			list(, $prefix, $userOrGroup) = explode('/', $share['href'], 3);

			/**
			 * Check that user destination is member of the groups which whom the calendar was shared
			 * If we ask to force the migration, the share with the group is dropped
			 */
			if ($this->shareManager->shareWithGroupMembersOnly() === true && 'groups' === $prefix && !$this->groupManager->isInGroup($userDestination, $userOrGroup)) {
				if ($force) {
					$this->calDav->updateShares(new Calendar($this->calDav, $calendar, $this->l10n, $this->config), [], ['href' => 'principal:principals/groups/' . $userOrGroup]);
				} else {
					throw new \InvalidArgumentException("User <$userDestination> is not part of the group <$userOrGroup> with whom the calendar <" . $calendar['uri'] . "> was shared. You may use -f to move the calendar while deleting this share.");
				}
			}

			/**
			 * Check that calendar isn't already shared with user destination
			 */
			if ($userOrGroup === $userDestination) {
				if ($force) {
					$this->calDav->updateShares(new Calendar($this->calDav, $calendar, $this->l10n, $this->config), [], ['href' => 'principal:principals/users/' . $userOrGroup]);
				} else {
					throw new \InvalidArgumentException("The calendar <" . $calendar['uri'] . "> is already shared to user <$userDestination>.You may use -f to move the calendar while deleting this share.");
				}
			}
		}

		return count($shares) > 0;
	}
}
