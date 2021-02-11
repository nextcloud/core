<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author scolebrook <scolebrook@mac.com>
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

namespace OCA\User_LDAP\Command;

use OCA\User_LDAP\User\DeletedUsersIndex;
use OCP\IDateTimeFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowRemnants extends Command {
	/** @var \OCA\User_LDAP\User\DeletedUsersIndex */
	protected $dui;

	/** @var \OCP\IDateTimeFormatter */
	protected $dateFormatter;

	/**
	 * @param DeletedUsersIndex $dui
	 * @param IDateTimeFormatter $dateFormatter
	 */
	public function __construct(DeletedUsersIndex $dui, IDateTimeFormatter $dateFormatter) {
		$this->dui = $dui;
		$this->dateFormatter = $dateFormatter;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('ldap:show-remnants')
			->setDescription('shows which users are not available on LDAP anymore, but have remnants in Nextcloud.')
			->addOption('json', null, InputOption::VALUE_NONE, 'return JSON array instead of pretty table.')
			->addOption('short-date', null, InputOption::VALUE_NONE, 'show dates in Y-m-d format');
	}

	protected function formatDate(int $timestamp, string $default, bool $showShortDate) {
		if (!($timestamp > 0)) {
			return $default;
		}
		if ($showShortDate) {
			return date('Y-m-d', $timestamp);
		}
		return $this->dateFormatter->formatDate($timestamp);
	}

	/**
	 * executes the command, i.e. creates and outputs a table of LDAP users marked as deleted
	 *
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		/** @var \Symfony\Component\Console\Helper\Table $table */
		$table = new Table($output);
		$table->setHeaders([
			'Nextcloud name', 'Display Name', 'LDAP UID', 'LDAP DN', 'Last Login',
			'Detected on', 'Dir', 'Sharer'
		]);
		$rows = [];
		$resultSet = $this->dui->getUsers();
		foreach ($resultSet as $user) {
			$rows[] = [
				'ocName' => $user->getOCName(),
				'displayName' => $user->getDisplayName(),
				'uid' => $user->getUID(),
				'dn' => $user->getDN(),
				'lastLogin' => $this->formatDate($user->getLastLogin(), '-', (bool)$input->getOption('short-date')),
				'detectedOn' => $this->formatDate($user->getDetectedOn(), 'unknown', (bool)$input->getOption('short-date')),
				'homePath' => $user->getHomePath(),
				'sharer' => $user->getHasActiveShares() ? 'Y' : 'N',
			];
		}

		if ($input->getOption('json')) {
			$output->writeln(json_encode($rows));
		} else {
			$table->setRows($rows);
			$table->render();
		}
		return 0;
	}
}
