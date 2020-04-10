<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use OC\User\NoUserException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\ImportLegacyStoragesService;
use OCA\Files_External\Service\UserStoragesService;
use OCP\IUserManager;
use OCP\IUserSession;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Base {
	/**
	 * @var GlobalStoragesService
	 */
	private $globalService;

	/**
	 * @var UserStoragesService
	 */
	private $userService;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/** @var ImportLegacyStoragesService */
	private $importLegacyStorageService;

	/** @var BackendService */
	private $backendService;

	function __construct(GlobalStoragesService $globalService,
						 UserStoragesService $userService,
						 IUserSession $userSession,
						 IUserManager $userManager,
						 ImportLegacyStoragesService $importLegacyStorageService,
						 BackendService $backendService
	) {
		parent::__construct();
		$this->globalService = $globalService;
		$this->userService = $userService;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->importLegacyStorageService = $importLegacyStorageService;
		$this->backendService = $backendService;
	}

	protected function configure() {
		$this
			->setName('files_external:import')
			->setDescription('Import mount configurations')
			->addOption(
				'user',
				'',
				InputOption::VALUE_OPTIONAL,
				'user to add the mount configurations for, if not set the mount will be added as system mount'
			)
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'path to a json file containing the mounts to import, use "-" to read from stdin'
			)
			->addOption(
				'dry',
				'',
				InputOption::VALUE_NONE,
				'Don\'t save the imported mounts, only list the new mounts'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$user = $input->getOption('user');
		$path = $input->getArgument('path');
		if ($path === '-') {
			$json = file_get_contents('php://stdin');
		} else {
			if (!file_exists($path)) {
				$output->writeln('<error>File not found: ' . $path . '</error>');
				return 1;
			}
			$json = file_get_contents($path);
		}
		if (!is_string($json) || strlen($json) < 2) {
			$output->writeln('<error>Error while reading json</error>');
			return 1;
		}
		$data = json_decode($json, true);
		if (!is_array($data)) {
			$output->writeln('<error>Error while parsing json</error>');
			return 1;
		}

		$isLegacy = isset($data['user']) || isset($data['group']);
		if ($isLegacy) {
			$this->importLegacyStorageService->setData($data);
			$mounts = $this->importLegacyStorageService->getAllStorages();
			foreach ($mounts as $mount) {
				if ($mount->getBackendOption('password') === false) {
					$output->writeln('<error>Failed to decrypt password</error>');
					return 1;
				}
			}
		} else {
			if (!isset($data[0])) { //normalize to an array of mounts
				$data = [$data];
			}
			$mounts = array_map([$this, 'parseData'], $data);
		}

		if ($user) {
			// ensure applicables are correct for personal mounts
			foreach ($mounts as $mount) {
				$mount->setApplicableGroups([]);
				$mount->setApplicableUsers([$user]);
			}
		}

		$storageService = $this->getStorageService($user);

		$existingMounts = $storageService->getAllStorages();

		foreach ($mounts as $mount) {
			foreach ($existingMounts as $existingMount) {
				if (
					$existingMount->getMountPoint() === $mount->getMountPoint() &&
					$existingMount->getApplicableGroups() === $mount->getApplicableGroups() &&
					$existingMount->getApplicableUsers() === $mount->getApplicableUsers() &&
					$existingMount->getBackendOptions() === $mount->getBackendOptions()
				) {
					$output->writeln("<error>Duplicate mount (" . $mount->getMountPoint() . ")</error>");
					return 1;
				}
			}
		}

		if ($input->getOption('dry')) {
			if (count($mounts) === 0) {
				$output->writeln('<error>No mounts to be imported</error>');
				return 1;
			}
			$listCommand = new ListCommand($this->globalService, $this->userService, $this->userSession, $this->userManager);
			$listInput = new ArrayInput([], $listCommand->getDefinition());
			$listInput->setOption('output', $input->getOption('output'));
			$listInput->setOption('show-password', true);
			$listCommand->listMounts($user, $mounts, $listInput, $output);
		} else {
			foreach ($mounts as $mount) {
				$storageService->addStorage($mount);
			}
		}
		return 0;
	}

	private function parseData(array $data) {
		$mount = new StorageConfig($data['mount_id']);
		$mount->setMountPoint($data['mount_point']);
		$mount->setBackend($this->getBackendByClass($data['storage']));
		$authBackend = $this->backendService->getAuthMechanism($data['authentication_type']);
		$mount->setAuthMechanism($authBackend);
		$mount->setBackendOptions($data['configuration']);
		$mount->setMountOptions($data['options']);
		$mount->setApplicableUsers(isset($data['applicable_users']) ? $data['applicable_users'] : []);
		$mount->setApplicableGroups(isset($data['applicable_groups']) ? $data['applicable_groups'] : []);
		return $mount;
	}

	private function getBackendByClass($className) {
		$backends = $this->backendService->getBackends();
		foreach ($backends as $backend) {
			if ($backend->getStorageClass() === $className) {
				return $backend;
			}
		}
	}

	protected function getStorageService($userId) {
		if (!empty($userId)) {
			$user = $this->userManager->get($userId);
			if (is_null($user)) {
				throw new NoUserException("user $userId not found");
			}
			$this->userSession->setUser($user);
			return $this->userService;
		} else {
			return $this->globalService;
		}
	}
}
