<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_Trashbin\Command;

use OC\Core\Command\Base;
use OCP\Command\IBus;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Size extends Base {
	private $config;
	private $userManager;
	private $commandBus;

	public function __construct(
		IConfig $config,
		IUserManager $userManager,
		IBus $commandBus
	) {
		parent::__construct();

		$this->config = $config;
		$this->userManager = $userManager;
		$this->commandBus = $commandBus;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('trashbin:size')
			->setDescription('Configure the target trashbin size')
			->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'configure the target size for the provided user, if no user is given the default size is configured')
			->addArgument(
				'size',
				InputArgument::OPTIONAL,
				'the target size for the trashbin, if not provided the current trashbin size will be returned'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getOption('user');
		$size = $input->getArgument('size');

		if ($size) {
			$parsedSize = \OC_Helper::computerFileSize($size);
			if ($parsedSize === false) {
				$output->writeln("<error>Failed to parse input size</error>");
				return -1;
			}
			if ($user) {
				$this->config->setUserValue($user, 'files_trashbin', 'trashbin_size', (string)$parsedSize);
				$this->commandBus->push(new Expire($user));
			} else {
				$this->config->setAppValue('files_trashbin', 'trashbin_size', (string)$parsedSize);
				$output->writeln("<info>Warning: changing the default trashbin size will automatically trigger cleanup of existing trashbins,</info>");
				$output->writeln("<info>a users trashbin can exceed the configured size until they move a new file to the trashbin.</info>");
			}
		} else {
			$this->printTrashbinSize($input, $output, $user);
		}

		return 0;
	}

	private function printTrashbinSize(InputInterface $input, OutputInterface $output, ?string $user) {
		$globalSize = (int)$this->config->getAppValue('files_trashbin', 'trashbin_size', '-1');
		if ($globalSize < 0) {
			$globalHumanSize = "default (50% of available space)";
		} else {
			$globalHumanSize = \OC_Helper::humanFileSize($globalSize);
		}

		if ($user) {
			$userSize = (int)$this->config->getUserValue($user, 'files_trashbin', 'trashbin_size', '-1');

			if ($userSize < 0) {
				$userHumanSize = ($globalSize < 0) ? $globalHumanSize : "default($globalHumanSize)";
			} else {
				$userHumanSize = \OC_Helper::humanFileSize($userSize);
			}

			if ($input->getOption('output') == self::OUTPUT_FORMAT_PLAIN) {
				$output->writeln($userHumanSize);
			} else {
				$userValue = ($userSize < 0) ? 'default' : $userSize;
				$globalValue = ($globalSize < 0) ? 'default' : $globalSize;
				$this->writeArrayInOutputFormat($input, $output, [
					'user_size' => $userValue,
					'global_size' => $globalValue,
					'effective_size' => ($userSize < 0) ? $globalValue : $userValue,
				]);
			}
		} else {
			$users = [];
			$this->userManager->callForSeenUsers(function (IUser $user) use (&$users) {
				$users[] = $user->getUID();
			});
			$userValues = $this->config->getUserValueForUsers('files_trashbin', 'trashbin_size', $users);

			if ($input->getOption('output') == self::OUTPUT_FORMAT_PLAIN) {
				$output->writeln("Default size: $globalHumanSize");
				$output->writeln("");
				if (count($userValues)) {
					$output->writeln("Per-user sizes:");
					$this->writeArrayInOutputFormat($input, $output, array_map(function ($size) {
						return \OC_Helper::humanFileSize($size);
					}, $userValues));
				} else {
					$output->writeln("No per-user sizes configured");
				}
			} else {
				$globalValue = ($globalSize < 0) ? 'default' : $globalSize;
				$this->writeArrayInOutputFormat($input, $output, [
					'global_size' => $globalValue,
					'user_sizes' => $userValues,
				]);
			}
		}
	}
}
