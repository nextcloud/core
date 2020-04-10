<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Ardinis <Ardinis@users.noreply.github.com>
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

use OC\Core\Command\Base;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;
use OCA\Files_External\Service\GlobalStoragesService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Config extends Base {
	/**
	 * @var GlobalStoragesService
	 */
	protected $globalService;

	function __construct(GlobalStoragesService $globalService) {
		parent::__construct();
		$this->globalService = $globalService;
	}

	protected function configure() {
		$this
			->setName('files_external:config')
			->setDescription('Manage backend configuration for a mount')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'The id of the mount to edit'
			)->addArgument(
				'key',
				InputArgument::REQUIRED,
				'key of the config option to set/get'
			)->addArgument(
				'value',
				InputArgument::OPTIONAL,
				'value to set the config option to, when no value is provided the existing value will be printed'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$mountId = $input->getArgument('mount_id');
		$key = $input->getArgument('key');
		try {
			$mount = $this->globalService->getStorage($mountId);
		} catch (NotFoundException $e) {
			$output->writeln('<error>Mount with id "' . $mountId . ' not found, check "occ files_external:list" to get available mounts"</error>');
			return 404;
		}

		$value = $input->getArgument('value');
		if ($value !== null) {
			$this->setOption($mount, $key, $value, $output);
		} else {
			$this->getOption($mount, $key, $output);
		}
	}

	/**
	 * @param StorageConfig $mount
	 * @param string $key
	 * @param OutputInterface $output
	 */
	protected function getOption(StorageConfig $mount, $key, OutputInterface $output) {
		if ($key === 'mountpoint' || $key === 'mount_point') {
			$value = $mount->getMountPoint();
		} else {
			$value = $mount->getBackendOption($key);
		}
		if (!is_string($value) && json_decode(json_encode($value)) === $value) { // show bools and objects correctly
			$value = json_encode($value);
		}
		$output->writeln($value);
	}

	/**
	 * @param StorageConfig $mount
	 * @param string $key
	 * @param string $value
	 * @param OutputInterface $output
	 */
	protected function setOption(StorageConfig $mount, $key, $value, OutputInterface $output) {
		$decoded = json_decode($value, true);
		if (!is_null($decoded) && json_encode($decoded) === $value) {
			$value = $decoded;
		}
		if ($key === 'mountpoint' || $key === 'mount_point') {
			$mount->setMountPoint($value);
		} else {
			$mount->setBackendOption($key, $value);
		}
		$this->globalService->updateStorage($mount);
	}
}
