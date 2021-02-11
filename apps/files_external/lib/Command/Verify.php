<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OC\Core\Command\Base;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\Files\StorageNotAvailableException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Verify extends Base {
	/**
	 * @var GlobalStoragesService
	 */
	protected $globalService;

	public function __construct(GlobalStoragesService $globalService) {
		parent::__construct();
		$this->globalService = $globalService;
	}

	protected function configure() {
		$this
			->setName('files_external:verify')
			->setDescription('Verify mount configuration')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'The id of the mount to check'
			)->addOption(
				'config',
				'c',
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'Additional config option to set before checking in key=value pairs, required for certain auth backends such as login credentails'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$mountId = $input->getArgument('mount_id');
		$configInput = $input->getOption('config');

		try {
			$mount = $this->globalService->getStorage($mountId);
		} catch (NotFoundException $e) {
			$output->writeln('<error>Mount with id "' . $mountId . ' not found, check "occ files_external:list" to get available mounts"</error>');
			return 404;
		}

		$this->updateStorageStatus($mount, $configInput, $output);

		$this->writeArrayInOutputFormat($input, $output, [
			'status' => StorageNotAvailableException::getStateCodeName($mount->getStatus()),
			'code' => $mount->getStatus(),
			'message' => $mount->getStatusMessage()
		]);
		return 0;
	}

	private function manipulateStorageConfig(StorageConfig $storage) {
		/** @var AuthMechanism */
		$authMechanism = $storage->getAuthMechanism();
		$authMechanism->manipulateStorageConfig($storage);
		/** @var Backend */
		$backend = $storage->getBackend();
		$backend->manipulateStorageConfig($storage);
	}

	private function updateStorageStatus(StorageConfig &$storage, $configInput, OutputInterface $output) {
		try {
			try {
				$this->manipulateStorageConfig($storage);
			} catch (InsufficientDataForMeaningfulAnswerException $e) {
				if (count($configInput) === 0) { // extra config options might solve the error
					throw $e;
				}
			}

			foreach ($configInput as $configOption) {
				if (!strpos($configOption, '=')) {
					$output->writeln('<error>Invalid mount configuration option "' . $configOption . '"</error>');
					return;
				}
				list($key, $value) = explode('=', $configOption, 2);
				$storage->setBackendOption($key, $value);
			}

			/** @var Backend */
			$backend = $storage->getBackend();
			// update status (can be time-consuming)
			$storage->setStatus(
				\OCA\Files_External\MountConfig::getBackendStatus(
					$backend->getStorageClass(),
					$storage->getBackendOptions(),
					false
				)
			);
		} catch (InsufficientDataForMeaningfulAnswerException $e) {
			$status = $e->getCode() ? $e->getCode() : StorageNotAvailableException::STATUS_INDETERMINATE;
			$storage->setStatus(
				$status,
				$e->getMessage()
			);
		} catch (StorageNotAvailableException $e) {
			$storage->setStatus(
				$e->getCode(),
				$e->getMessage()
			);
		} catch (\Exception $e) {
			// FIXME: convert storage exceptions to StorageNotAvailableException
			$storage->setStatus(
				StorageNotAvailableException::STATUS_ERROR,
				get_class($e) . ': ' . $e->getMessage()
			);
		}
	}
}
