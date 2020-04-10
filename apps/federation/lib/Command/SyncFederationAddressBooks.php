<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Federation\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncFederationAddressBooks extends Command {

	/** @var \OCA\Federation\SyncFederationAddressBooks */
	private $syncService;

	/**
	 * @param \OCA\Federation\SyncFederationAddressBooks $syncService
	 */
	public function __construct(\OCA\Federation\SyncFederationAddressBooks $syncService) {
		parent::__construct();

		$this->syncService = $syncService;
	}

	protected function configure() {
		$this
			->setName('federation:sync-addressbooks')
			->setDescription('Synchronizes addressbooks of all federated clouds');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {

		$progress = new ProgressBar($output);
		$progress->start();
		$this->syncService->syncThemAll(function ($url, $ex) use ($progress, $output) {
			if ($ex instanceof \Exception) {
				$output->writeln("Error while syncing $url : " . $ex->getMessage());

			} else {
				$progress->advance();
			}
		});

		$progress->finish();
		$output->writeln('');

		return 0;
	}
}
