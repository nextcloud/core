<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Denis Mosolov <denismosolov@gmail.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Denis Mosolov <denismosolov@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\Group;

use OC\Core\Command\Base;
use OCP\IGroup;
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {
	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IGroupManager $groupManager) {
		$this->groupManager = $groupManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('group:add')
			->setDescription('Add a group')
			->addArgument(
				'groupid',
				InputArgument::REQUIRED,
				'Group id'
			)
			->addOption(
				'display-name',
				null,
				InputOption::VALUE_REQUIRED,
				'Group name used in the web UI (can contain any characters)'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$gid = $input->getArgument('groupid');
		$group = $this->groupManager->get($gid);
		if ($group) {
			$output->writeln('<error>Group "' . $gid . '" already exists.</error>');
			return 1;
		} else {
			$group = $this->groupManager->createGroup($gid);
			if (!$group instanceof IGroup) {
				$output->writeln('<error>Could not create group</error>');
				return 2;
			}
			$output->writeln('Created group "' . $group->getGID() . '"');

			$displayName = trim((string)$input->getOption('display-name'));
			if ($displayName !== '') {
				$group->setDisplayName($displayName);
			}
		}
		return 0;
	}
}
