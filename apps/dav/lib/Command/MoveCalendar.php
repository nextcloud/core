<?php
/**
 *
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

	const URI_USERS = 'principals/users/';

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param IShareManager $shareManager
	 * @param IConfig $config
	 * @param IL10N $l10n
	 * @param CalDavBackend $calDav
	 */
	function __construct(
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
			->addOption('force', 'f', InputOption::VALUE_NONE, "Force the migration by removing existing shares");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
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

		$calendar = $this->calDav->getCalendarByUri(self::URI_USERS . $userOrigin, $name);

		if (null === $calendar) {
			throw new \InvalidArgumentException("User <$userOrigin> has no calendar named <$name>. You can run occ dav:list-calendars to list calendars URIs for this user.");
		}

		if (null !== $this->calDav->getCalendarByUri(self::URI_USERS . $userDestination, $name)) {
			throw new \InvalidArgumentException("User <$userDestination> already has a calendar named <$name>.");
		}

		$this->checkShares($calendar, $userOrigin, $userDestination, $input->getOption('force'));

		$this->calDav->moveCalendar($name, self::URI_USERS . $userOrigin, self::URI_USERS . $userDestination);

		$this->io->success("Calendar <$name> was moved from user <$userOrigin> to <$userDestination>");
	}

	/**
	 * Check that moving the calendar won't break shares
	 *
	 * @param array $calendar
	 * @param string $userOrigin
	 * @param string $userDestination
	 * @param bool $force
	 */
	private function checkShares(array $calendar, string $userOrigin, string $userDestination, bool $force = false)
	{
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
		/**
		 * Warn that share links have changed if there are shares
		 */
		if (count($shares) > 0) {
			$this->io->note([
				"Please note that moving calendar " . $calendar['uri'] . " from user <$userOrigin> to <$userDestination> has caused share links to change.",
				"Sharees will need to change \"example.com/remote.php/dav/calendars/uid/" . $calendar['uri'] . "_shared_by_$userOrigin\" to \"example.com/remote.php/dav/calendars/uid/" . $calendar['uri'] . "_shared_by_$userDestination\""
			]);
		}
	}
}
