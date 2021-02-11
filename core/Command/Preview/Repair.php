<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Morris Jobke <hey@morrisjobke.de>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Command\Preview;

use bantu\IniGetWrapper\IniGetWrapper;
use OC\Preview\Storage\Root;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Repair extends Command {
	/** @var IConfig */
	protected $config;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var ILogger */
	private $logger;

	/** @var bool */
	private $stopSignalReceived = false;
	/** @var int */
	private $memoryLimit;
	/** @var int */
	private $memoryTreshold;
	/** @var ILockingProvider */
	private $lockingProvider;

	public function __construct(IConfig $config, IRootFolder $rootFolder, ILogger $logger, IniGetWrapper $phpIni, ILockingProvider $lockingProvider) {
		$this->config = $config;
		$this->rootFolder = $rootFolder;
		$this->logger = $logger;
		$this->lockingProvider = $lockingProvider;

		$this->memoryLimit = $phpIni->getBytes('memory_limit');
		$this->memoryTreshold = $this->memoryLimit - 25 * 1024 * 1024;

		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('preview:repair')
			->setDescription('distributes the existing previews into subfolders')
			->addOption('batch', 'b', InputOption::VALUE_NONE, 'Batch mode - will not ask to start the migration and start it right away.')
			->addOption('dry', 'd', InputOption::VALUE_NONE, 'Dry mode - will not create, move or delete any files - in combination with the verbose mode one could check the operations.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($this->memoryLimit !== -1) {
			$limitInMiB = round($this->memoryLimit / 1024 / 1024, 1);
			$thresholdInMiB = round($this->memoryTreshold / 1024 / 1024, 1);
			$output->writeln("Memory limit is $limitInMiB MiB");
			$output->writeln("Memory threshold is $thresholdInMiB MiB");
			$output->writeln("");
			$memoryCheckEnabled = true;
		} else {
			$output->writeln("No memory limit in place - disabled memory check. Set a PHP memory limit to automatically stop the execution of this migration script once memory consumption is close to this limit.");
			$output->writeln("");
			$memoryCheckEnabled = false;
		}

		$dryMode = $input->getOption('dry');

		if ($dryMode) {
			$output->writeln("INFO: The migration is run in dry mode and will not modify anything.");
			$output->writeln("");
		}

		$instanceId = $this->config->getSystemValueString('instanceid');

		$output->writeln("This will migrate all previews from the old preview location to the new one.");
		$output->writeln('');

		$output->writeln('Fetching previews that need to be migrated …');
		/** @var \OCP\Files\Folder $currentPreviewFolder */
		$currentPreviewFolder = $this->rootFolder->get("appdata_$instanceId/preview");

		$directoryListing = $currentPreviewFolder->getDirectoryListing();

		$total = count($directoryListing);
		/**
		 * by default there could be 0-9 a-f and the old-multibucket folder which are all fine
		 */
		if ($total < 18) {
			$directoryListing = array_filter($directoryListing, function ($dir) {
				if ($dir->getName() === 'old-multibucket') {
					return false;
				}

				// a-f can't be a file ID -> removing from migration
				if (preg_match('!^[a-f]$!', $dir->getName())) {
					return false;
				}

				if (preg_match('!^[0-9]$!', $dir->getName())) {
					// ignore folders that only has folders in them
					if ($dir instanceof Folder) {
						foreach ($dir->getDirectoryListing() as $entry) {
							if (!$entry instanceof Folder) {
								return true;
							}
						}
						return false;
					}
				}
				return true;
			});
			$total = count($directoryListing);
		}

		if ($total === 0) {
			$output->writeln("All previews are already migrated.");
			return 0;
		}

		$output->writeln("A total of $total preview files need to be migrated.");
		$output->writeln("");
		$output->writeln("The migration will always migrate all previews of a single file in a batch. After each batch the process can be canceled by pressing CTRL-C. This fill finish the current batch and then stop the migration. This migration can then just be started and it will continue.");

		if ($input->getOption('batch')) {
			$output->writeln('Batch mode active: migration is started right away.');
		} else {
			$helper = $this->getHelper('question');
			$question = new ConfirmationQuestion('<info>Should the migration be started? (y/[n]) </info>', false);

			if (!$helper->ask($input, $output, $question)) {
				return 0;
			}
		}

		// register the SIGINT listener late in here to be able to exit in the early process of this command
		pcntl_signal(SIGINT, [$this, 'sigIntHandler']);

		$output->writeln("");
		$output->writeln("");
		$section1 = $output->section();
		$section2 = $output->section();
		$progressBar = new ProgressBar($section2, $total);
		$progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% Used Memory: %memory:6s%");
		$time = (new \DateTime())->format('H:i:s');
		$progressBar->setMessage("$time Starting …");
		$progressBar->maxSecondsBetweenRedraws(0.2);
		$progressBar->start();

		foreach ($directoryListing as $oldPreviewFolder) {
			pcntl_signal_dispatch();
			$name = $oldPreviewFolder->getName();
			$time = (new \DateTime())->format('H:i:s');
			$section1->writeln("$time Migrating previews of file with fileId $name …");
			$progressBar->display();

			if ($this->stopSignalReceived) {
				$section1->writeln("$time Stopping migration …");
				return 0;
			}
			if (!$oldPreviewFolder instanceof Folder) {
				$section1->writeln("         Skipping non-folder $name …");
				$progressBar->advance();
				continue;
			}
			if ($name === 'old-multibucket') {
				$section1->writeln("         Skipping fallback mount point $name …");
				$progressBar->advance();
				continue;
			}
			if (in_array($name, ['a', 'b', 'c', 'd', 'e', 'f'])) {
				$section1->writeln("         Skipping hex-digit folder $name …");
				$progressBar->advance();
				continue;
			}
			if (!preg_match('!^\d+$!', $name)) {
				$section1->writeln("         Skipping non-numeric folder $name …");
				$progressBar->advance();
				continue;
			}

			$newFoldername = Root::getInternalFolder($name);

			$memoryUsage = memory_get_usage();
			if ($memoryCheckEnabled && $memoryUsage > $this->memoryTreshold) {
				$section1->writeln("");
				$section1->writeln("");
				$section1->writeln("");
				$section1->writeln("         Stopped process 25 MB before reaching the memory limit to avoid a hard crash.");
				$time = (new \DateTime())->format('H:i:s');
				$section1->writeln("$time Reached memory limit and stopped to avoid hard crash.");
				return 1;
			}

			$lockName = 'occ preview:repair lock ' . $oldPreviewFolder->getId();
			try {
				$section1->writeln("         Locking \"$lockName\" …", OutputInterface::VERBOSITY_VERBOSE);
				$this->lockingProvider->acquireLock($lockName, ILockingProvider::LOCK_EXCLUSIVE);
			} catch (LockedException $e) {
				$section1->writeln("         Skipping because it is locked - another process seems to work on this …");
				continue;
			}

			$previews = $oldPreviewFolder->getDirectoryListing();
			if ($previews !== []) {
				try {
					$this->rootFolder->get("appdata_$instanceId/preview/$newFoldername");
				} catch (NotFoundException $e) {
					$section1->writeln("         Create folder preview/$newFoldername", OutputInterface::VERBOSITY_VERBOSE);
					if (!$dryMode) {
						$this->rootFolder->newFolder("appdata_$instanceId/preview/$newFoldername");
					}
				}

				foreach ($previews as $preview) {
					pcntl_signal_dispatch();
					$previewName = $preview->getName();

					if ($preview instanceof Folder) {
						$section1->writeln("         Skipping folder $name/$previewName …");
						$progressBar->advance();
						continue;
					}
					$section1->writeln("         Move preview/$name/$previewName to preview/$newFoldername", OutputInterface::VERBOSITY_VERBOSE);
					if (!$dryMode) {
						try {
							$preview->move("appdata_$instanceId/preview/$newFoldername/$previewName");
						} catch (\Exception $e) {
							$this->logger->logException($e, ['app' => 'core', 'message' => "Failed to move preview from preview/$name/$previewName to preview/$newFoldername"]);
						}
					}
				}
			}
			if ($oldPreviewFolder->getDirectoryListing() === []) {
				$section1->writeln("         Delete empty folder preview/$name", OutputInterface::VERBOSITY_VERBOSE);
				if (!$dryMode) {
					try {
						$oldPreviewFolder->delete();
					} catch (\Exception $e) {
						$this->logger->logException($e, ['app' => 'core', 'message' => "Failed to delete empty folder preview/$name"]);
					}
				}
			}

			$this->lockingProvider->releaseLock($lockName, ILockingProvider::LOCK_EXCLUSIVE);
			$section1->writeln("         Unlocked", OutputInterface::VERBOSITY_VERBOSE);

			$section1->writeln("         Finished migrating previews of file with fileId $name …");
			$progressBar->advance();
		}

		$progressBar->finish();
		$output->writeln("");
		return 0;
	}

	protected function sigIntHandler() {
		echo "\nSignal received - will finish the step and then stop the migration.\n\n\n";
		$this->stopSignalReceived = true;
	}
}
