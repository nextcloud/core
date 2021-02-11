<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Denis Mosolov <denismosolov@gmail.com>
 *
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
use OCP\IGroupManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Delete extends Base {
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
			->setName('group:delete')
			->setDescription('Remove a group')
			->addArgument(
				'groupid',
				InputArgument::REQUIRED,
				'Group name'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$gid = $input->getArgument('groupid');
		if ($gid === 'admin') {
			$output->writeln('<error>Group "' . $gid . '" could not be deleted.</error>');
			return 1;
		}
		if (! $this->groupManager->groupExists($gid)) {
			$output->writeln('<error>Group "' . $gid . '" does not exist.</error>');
			return 1;
		}
		$group = $this->groupManager->get($gid);
		if ($group->delete()) {
			$output->writeln('Group "' . $gid . '" was removed');
		} else {
			$output->writeln('<error>Group "' . $gid . '" could not be deleted. Please check the logs.</error>');
			return 1;
		}
		return 0;
	}
}
