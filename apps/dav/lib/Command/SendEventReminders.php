<?php
/**
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OCA\DAV\CalDAV\Reminder\ReminderService;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SendEventReminders
 *
 * @package OCA\DAV\Command
 */
class SendEventReminders extends Command {

	/** @var ReminderService */
	protected $reminderService;

	/** @var IConfig */
	protected $config;

	/**
	 * @param ReminderService $reminderService
	 * @param IConfig $config
	 */
	public function __construct(ReminderService $reminderService,
								IConfig $config) {
		parent::__construct();
		$this->reminderService = $reminderService;
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	protected function configure():void {
		$this
			->setName('dav:send-event-reminders')
			->setDescription('Sends event reminders');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output):void {
		if ($this->config->getAppValue('dav', 'sendEventReminders', 'yes') !== 'yes') {
			$output->writeln('<error>Sending event reminders disabled!</error>');
			$output->writeln('<info>Please run "php occ config:app:set dav sendEventReminders --value yes"');
			return;
		}

		if ($this->config->getAppValue('dav', 'sendEventRemindersMode', 'backgroundjob') !== 'occ') {
			$output->writeln('<error>Sending event reminders mode set to background-job!</error>');
			$output->writeln('<info>Please run "php occ config:app:set dav sendEventRemindersMode --value occ"');
			return;
		}

		$this->reminderService->processReminders();
	}
}
