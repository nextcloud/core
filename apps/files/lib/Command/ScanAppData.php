<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joel S <joel.devbox@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files\Command;

use Doctrine\DBAL\Connection;
use OC\Core\Command\Base;
use OC\Core\Command\InterruptedException;
use OC\ForbiddenException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;
use OCP\IConfig;
use OCP\IDBConnection;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanAppData extends Base {

	/** @var IRootFolder */
	protected $root;
	/** @var IConfig */
	protected $config;
	/** @var float */
	protected $execTime = 0;
	/** @var int */
	protected $foldersCounter = 0;
	/** @var int */
	protected $filesCounter = 0;

	public function __construct(IRootFolder $rootFolder, IConfig $config) {
		parent::__construct();

		$this->root = $rootFolder;
		$this->config = $config;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('files:scan-app-data')
			->setDescription('rescan the AppData folder');
	}

	public function checkScanWarning($fullPath, OutputInterface $output) {
		$normalizedPath = basename(\OC\Files\Filesystem::normalizePath($fullPath));
		$path = basename($fullPath);

		if ($normalizedPath !== $path) {
			$output->writeln("\t<error>Entry \"" . $fullPath . '" will not be accessible due to incompatible encoding</error>');
		}
	}

	protected function scanFiles(OutputInterface $output) {
		try {
			$appData = $this->getAppDataFolder();
		} catch (NotFoundException $e) {
			$output->writeln('NoAppData folder found');
			return;
		}

		$connection = $this->reconnectToDatabase($output);
		$scanner = new \OC\Files\Utils\Scanner(null, $connection, \OC::$server->query(IEventDispatcher::class), \OC::$server->getLogger());

		# check on each file/folder if there was a user interrupt (ctrl-c) and throw an exception
		$scanner->listen('\OC\Files\Utils\Scanner', 'scanFile', function ($path) use ($output) {
			$output->writeln("\tFile   <info>$path</info>", OutputInterface::VERBOSITY_VERBOSE);
			++$this->filesCounter;
			$this->abortIfInterrupted();
		});

		$scanner->listen('\OC\Files\Utils\Scanner', 'scanFolder', function ($path) use ($output) {
			$output->writeln("\tFolder <info>$path</info>", OutputInterface::VERBOSITY_VERBOSE);
			++$this->foldersCounter;
			$this->abortIfInterrupted();
		});

		$scanner->listen('\OC\Files\Utils\Scanner', 'StorageNotAvailable', function (StorageNotAvailableException $e) use ($output) {
			$output->writeln('Error while scanning, storage not available (' . $e->getMessage() . ')', OutputInterface::VERBOSITY_VERBOSE);
		});

		$scanner->listen('\OC\Files\Utils\Scanner', 'scanFile', function ($path) use ($output) {
			$this->checkScanWarning($path, $output);
		});

		$scanner->listen('\OC\Files\Utils\Scanner', 'scanFolder', function ($path) use ($output) {
			$this->checkScanWarning($path, $output);
		});

		try {
			$scanner->scan($appData->getPath());
		} catch (ForbiddenException $e) {
			$output->writeln('<error>Storage not writable</error>');
			$output->writeln('Make sure you\'re running the scan command only as the user the web server runs as');
		} catch (InterruptedException $e) {
			# exit the function if ctrl-c has been pressed
			$output->writeln('Interrupted by user');
		} catch (NotFoundException $e) {
			$output->writeln('<error>Path not found: ' . $e->getMessage() . '</error>');
		} catch (\Exception $e) {
			$output->writeln('<error>Exception during scan: ' . $e->getMessage() . '</error>');
			$output->writeln('<error>' . $e->getTraceAsString() . '</error>');
		}
	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		# restrict the verbosity level to VERBOSITY_VERBOSE
		if ($output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
			$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
		}

		$output->writeln("\nScanning AppData for files");

		$this->initTools();

		$this->scanFiles($output);

		$this->presentStats($output);
	}

	/**
	 * Initialises some useful tools for the Command
	 */
	protected function initTools() {
		// Start the timer
		$this->execTime = -microtime(true);
		// Convert PHP errors to exceptions
		set_error_handler([$this, 'exceptionErrorHandler'], E_ALL);
	}

	/**
	 * Processes PHP errors as exceptions in order to be able to keep track of problems
	 *
	 * @see https://secure.php.net/manual/en/function.set-error-handler.php
	 *
	 * @param int $severity the level of the error raised
	 * @param string $message
	 * @param string $file the filename that the error was raised in
	 * @param int $line the line number the error was raised
	 *
	 * @throws \ErrorException
	 */
	public function exceptionErrorHandler($severity, $message, $file, $line) {
		if (!(error_reporting() & $severity)) {
			// This error code is not included in error_reporting
			return;
		}
		throw new \ErrorException($message, 0, $severity, $file, $line);
	}

	/**
	 * @param OutputInterface $output
	 */
	protected function presentStats(OutputInterface $output) {
		// Stop the timer
		$this->execTime += microtime(true);
		$output->writeln("");

		$headers = [
			'Folders', 'Files', 'Elapsed time'
		];

		$this->showSummary($headers, null, $output);
	}

	/**
	 * Shows a summary of operations
	 *
	 * @param string[] $headers
	 * @param string[] $rows
	 * @param OutputInterface $output
	 */
	protected function showSummary($headers, $rows, OutputInterface $output) {
		$niceDate = $this->formatExecTime();
		if (!$rows) {
			$rows = [
				$this->foldersCounter,
				$this->filesCounter,
				$niceDate,
			];
		}
		$table = new Table($output);
		$table
			->setHeaders($headers)
			->setRows([$rows]);
		$table->render();
	}


	/**
	 * Formats microtime into a human readable format
	 *
	 * @return string
	 */
	protected function formatExecTime() {
		$secs = round($this->execTime);
		# convert seconds into HH:MM:SS form
		return sprintf('%02d:%02d:%02d', ($secs/3600), ($secs/60%60), $secs%60);
	}

	/**
	 * @return \OCP\IDBConnection
	 */
	protected function reconnectToDatabase(OutputInterface $output) {
		/** @var Connection | IDBConnection $connection*/
		$connection = \OC::$server->getDatabaseConnection();
		try {
			$connection->close();
		} catch (\Exception $ex) {
			$output->writeln("<info>Error while disconnecting from database: {$ex->getMessage()}</info>");
		}
		while (!$connection->isConnected()) {
			try {
				$connection->connect();
			} catch (\Exception $ex) {
				$output->writeln("<info>Error while re-connecting to database: {$ex->getMessage()}</info>");
				sleep(60);
			}
		}
		return $connection;
	}

	/**
	 * @return \OCP\Files\Folder
	 * @throws NotFoundException
	 */
	private function getAppDataFolder() {
		$instanceId = $this->config->getSystemValue('instanceid', null);

		if ($instanceId === null) {
			throw new NotFoundException();
		}

		return $this->root->get('appdata_'.$instanceId);
	}
}
