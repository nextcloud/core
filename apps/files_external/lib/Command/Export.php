<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends ListCommand {
	protected function configure() {
		$this
			->setName('files_external:export')
			->setDescription('Export mount configurations')
			->addArgument(
				'user_id',
				InputArgument::OPTIONAL,
				'user id to export the personal mounts for, if no user is provided admin mounts will be exported'
			)->addOption(
				'all',
				'a',
				InputOption::VALUE_NONE,
				'show both system wide mounts and all personal mounts'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$listCommand = new ListCommand($this->globalService, $this->userService, $this->userSession, $this->userManager);
		$listInput = new ArrayInput([], $listCommand->getDefinition());
		$listInput->setArgument('user_id', $input->getArgument('user_id'));
		$listInput->setOption('all', $input->getOption('all'));
		$listInput->setOption('output', 'json_pretty');
		$listInput->setOption('show-password', true);
		$listInput->setOption('full', true);
		$listCommand->execute($listInput, $output);
		return 0;
	}
}
