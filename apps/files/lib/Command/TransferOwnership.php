<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Carla Schroder <carla@owncloud.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sujith Haridasan <sujith.h@gmail.com>
 * @author Sujith H <sharidasan@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tobia De Koninck <LEDfan@users.noreply.github.com>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files\Command;

use OCA\Files\Exception\TransferOwnershipException;
use OCA\Files\Service\OwnershipTransferService;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TransferOwnership extends Command {

	/** @var IUserManager */
	private $userManager;

	/** @var OwnershipTransferService */
	private $transferService;

	public function __construct(IUserManager $userManager,
								OwnershipTransferService $transferService) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->transferService = $transferService;
	}

	protected function configure() {
		$this
			->setName('files:transfer-ownership')
			->setDescription('All files and folders are moved to another user - shares are moved as well.')
			->addArgument(
				'source-user',
				InputArgument::REQUIRED,
				'owner of files which shall be moved'
			)
			->addArgument(
				'destination-user',
				InputArgument::REQUIRED,
				'user who will be the new owner of the files'
			)
			->addOption(
				'path',
				null,
				InputOption::VALUE_REQUIRED,
				'selectively provide the path to transfer. For example --path="folder_name"',
				''
			)->addOption(
				'move',
				null,
				InputOption::VALUE_NONE,
				'move data from source user to root directory of destination user, which must be empty'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {

		/**
		 * Check if source and destination users are same. If they are same then just ignore the transfer.
		 */

		if ($input->getArgument(('source-user')) === $input->getArgument('destination-user')) {
			$output->writeln("<error>Ownership can't be transferred when Source and Destination users are the same user. Please check your input.</error>");
			return 1;
		}

		$sourceUserObject = $this->userManager->get($input->getArgument('source-user'));
		$destinationUserObject = $this->userManager->get($input->getArgument('destination-user'));

		if (!$sourceUserObject instanceof IUser) {
			$output->writeln("<error>Unknown source user " . $input->getArgument('source-user') . "</error>");
			return 1;
		}

		if (!$destinationUserObject instanceof IUser) {
			$output->writeln("<error>Unknown destination user " . $input->getArgument('destination-user') . "</error>");
			return 1;
		}

		try {
			$this->transferService->transfer(
				$sourceUserObject,
				$destinationUserObject,
				ltrim($input->getOption('path'), '/'),
				$output,
				$input->getOption('move') === true
			);
		} catch (TransferOwnershipException $e) {
			$output->writeln("<error>" . $e->getMessage() . "</error>");
			return $e->getCode() !== 0 ? $e->getCode() : 1;
		}

		return 0;
	}
}
