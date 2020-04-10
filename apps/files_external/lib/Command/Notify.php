<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Ari Selseng <ari@selseng.net>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Files_External\Command;

use Doctrine\DBAL\Exception\DriverException;
use OC\Core\Command\Base;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Notify\IChange;
use OCP\Files\Notify\INotifyHandler;
use OCP\Files\Notify\IRenameChange;
use OCP\Files\Storage\INotifyStorage;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IDBConnection;
use OCP\ILogger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Notify extends Base {
	/** @var GlobalStoragesService */
	private $globalService;
	/** @var IDBConnection */
	private $connection;
	/** @var ILogger */
	private $logger;

	function __construct(GlobalStoragesService $globalService, IDBConnection $connection, ILogger $logger) {
		parent::__construct();
		$this->globalService = $globalService;
		$this->connection = $connection;
		$this->logger = $logger;
	}

	protected function configure() {
		$this
			->setName('files_external:notify')
			->setDescription('Listen for active update notifications for a configured external mount')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'the mount id of the mount to listen to'
			)->addOption(
				'user',
				'u',
				InputOption::VALUE_REQUIRED,
				'The username for the remote mount (required only for some mount configuration that don\'t store credentials)'
			)->addOption(
				'password',
				'p',
				InputOption::VALUE_REQUIRED,
				'The password for the remote mount (required only for some mount configuration that don\'t store credentials)'
			)->addOption(
				'path',
				'',
				InputOption::VALUE_REQUIRED,
				'The directory in the storage to listen for updates in',
				'/'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$mount = $this->globalService->getStorage($input->getArgument('mount_id'));
		if (is_null($mount)) {
			$output->writeln('<error>Mount not found</error>');
			return 1;
		}
		$noAuth = false;
		try {
			$authBackend = $mount->getAuthMechanism();
			$authBackend->manipulateStorageConfig($mount);
		} catch (InsufficientDataForMeaningfulAnswerException $e) {
			$noAuth = true;
		} catch (StorageNotAvailableException $e) {
			$noAuth = true;
		}

		if ($input->getOption('user')) {
			$mount->setBackendOption('user', $input->getOption('user'));
		} else if (isset($_ENV['NOTIFY_USER'])) {
			$mount->setBackendOption('user', $_ENV['NOTIFY_USER']);
		} else if (isset($_SERVER['NOTIFY_USER'])) {
			$mount->setBackendOption('user', $_SERVER['NOTIFY_USER']);
		}
		if ($input->getOption('password')) {
			$mount->setBackendOption('password', $input->getOption('password'));
		} else if (isset($_ENV['NOTIFY_PASSWORD'])) {
			$mount->setBackendOption('password', $_ENV['NOTIFY_PASSWORD']);
		} else if (isset($_SERVER['NOTIFY_PASSWORD'])) {
			$mount->setBackendOption('password', $_SERVER['NOTIFY_PASSWORD']);
		}

		try {
			$storage = $this->createStorage($mount);
		} catch (\Exception $e) {
			$output->writeln('<error>Error while trying to create storage</error>');
			if ($noAuth) {
				$output->writeln('<error>Username and/or password required</error>');
			}
			return 1;
		}
		if (!$storage instanceof INotifyStorage) {
			$output->writeln('<error>Mount of type "' . $mount->getBackend()->getText() . '" does not support active update notifications</error>');
			return 1;
		}

		$verbose = $input->getOption('verbose');

		$path = trim($input->getOption('path'), '/');
		$notifyHandler = $storage->notify($path);
		$this->selfTest($storage, $notifyHandler, $verbose, $output);
		$notifyHandler->listen(function (IChange $change) use ($mount, $verbose, $output) {
			if ($verbose) {
				$this->logUpdate($change, $output);
			}
			if ($change instanceof IRenameChange) {
				$this->markParentAsOutdated($mount->getId(), $change->getTargetPath(), $output);
			}
			$this->markParentAsOutdated($mount->getId(), $change->getPath(), $output);
		});
	}

	private function createStorage(StorageConfig $mount) {
		$class = $mount->getBackend()->getStorageClass();
		return new $class($mount->getBackendOptions());
	}

	private function markParentAsOutdated($mountId, $path, OutputInterface $output) {
		$parent = ltrim(dirname($path), '/');
		if ($parent === '.') {
			$parent = '';
		}

		try {
			$storageIds = $this->getStorageIds($mountId);
		} catch (DriverException $ex) {
			$this->logger->logException($ex, ['message' => 'Error while trying to find correct storage ids.', 'level' => ILogger::WARN]);
			$this->connection = $this->reconnectToDatabase($this->connection, $output);
			$output->writeln('<info>Needed to reconnect to the database</info>');
			$storageIds = $this->getStorageIds($mountId);
		}
		if (count($storageIds) === 0) {
			throw new StorageNotAvailableException('No storages found by mount ID ' . $mountId);
		}
		$storageIds = array_map('intval', $storageIds);

		$result = $this->updateParent($storageIds, $parent);
		if ($result === 0) {
			//TODO: Find existing parent further up the tree in the database and register that folder instead.
			$this->logger->info('Failed updating parent for "' . $path . '" while trying to register change. It may not exist in the filecache.');
		}
	}

	private function logUpdate(IChange $change, OutputInterface $output) {
		switch ($change->getType()) {
			case INotifyStorage::NOTIFY_ADDED:
				$text = 'added';
				break;
			case INotifyStorage::NOTIFY_MODIFIED:
				$text = 'modified';
				break;
			case INotifyStorage::NOTIFY_REMOVED:
				$text = 'removed';
				break;
			case INotifyStorage::NOTIFY_RENAMED:
				$text = 'renamed';
				break;
			default:
				return;
		}

		$text .= ' ' . $change->getPath();
		if ($change instanceof IRenameChange) {
			$text .= ' to ' . $change->getTargetPath();
		}

		$output->writeln($text);
	}

	/**
	 * @param int $mountId
	 * @return array
	 */
	private function getStorageIds($mountId) {
		$qb = $this->connection->getQueryBuilder();
		return $qb
			->select('storage_id')
			->from('mounts')
			->where($qb->expr()->eq('mount_id', $qb->createNamedParameter($mountId, IQueryBuilder::PARAM_INT)))
			->execute()
			->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * @param array $storageIds
	 * @param string $parent
	 * @return int
	 */
	private function updateParent($storageIds, $parent) {
		$pathHash = md5(trim(\OC_Util::normalizeUnicode($parent), '/'));
		$qb = $this->connection->getQueryBuilder();
		return $qb
			->update('filecache')
			->set('size', $qb->createNamedParameter(-1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->in('storage', $qb->createNamedParameter($storageIds, IQueryBuilder::PARAM_INT_ARRAY, ':storage_ids')))
			->andWhere($qb->expr()->eq('path_hash', $qb->createNamedParameter($pathHash, IQueryBuilder::PARAM_STR)))
			->execute();
	}

	/**
	 * @return \OCP\IDBConnection
	 */
	private function reconnectToDatabase(IDBConnection $connection, OutputInterface $output) {
		try {
			$connection->close();
		} catch (\Exception $ex) {
			$this->logger->logException($ex, ['app' => 'files_external', 'message' => 'Error while disconnecting from DB', 'level' => ILogger::WARN]);
			$output->writeln("<info>Error while disconnecting from database: {$ex->getMessage()}</info>");
		}
		while (!$connection->isConnected()) {
			try {
				$connection->connect();
			} catch (\Exception $ex) {
				$this->logger->logException($ex, ['app' => 'files_external', 'message' => 'Error while re-connecting to database', 'level' => ILogger::WARN]);
				$output->writeln("<info>Error while re-connecting to database: {$ex->getMessage()}</info>");
				sleep(60);
			}
		}
		return $connection;
	}


	private function selfTest(IStorage $storage, INotifyHandler $notifyHandler, $verbose, OutputInterface $output) {
		usleep(100 * 1000); //give time for the notify to start
		$storage->file_put_contents('/.nc_test_file.txt', 'test content');
		$storage->mkdir('/.nc_test_folder');
		$storage->file_put_contents('/.nc_test_folder/subfile.txt', 'test content');

		usleep(100 * 1000); //time for all changes to be processed
		$changes = $notifyHandler->getChanges();

		$storage->unlink('/.nc_test_file.txt');
		$storage->unlink('/.nc_test_folder/subfile.txt');
		$storage->rmdir('/.nc_test_folder');

		usleep(100 * 1000); //time for all changes to be processed
		$notifyHandler->getChanges(); // flush

		$foundRootChange = false;
		$foundSubfolderChange = false;

		foreach ($changes as $change) {
			if ($change->getPath() === '/.nc_test_file.txt' || $change->getPath() === '.nc_test_file.txt') {
				$foundRootChange = true;
			} else if ($change->getPath() === '/.nc_test_folder/subfile.txt' || $change->getPath() === '.nc_test_folder/subfile.txt') {
				$foundSubfolderChange = true;
			}
		}

		if ($foundRootChange && $foundSubfolderChange && $verbose) {
			$output->writeln('<info>Self-test successful</info>');
		} else if ($foundRootChange && !$foundSubfolderChange) {
			$output->writeln('<error>Error while running self-test, change is subfolder not detected</error>');
		} else if (!$foundRootChange) {
			$output->writeln('<error>Error while running self-test, no changes detected</error>');
		}
	}
}
