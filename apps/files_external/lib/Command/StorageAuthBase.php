<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Files_External\Command;


use OC\Core\Command\Base;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Service\GlobalStoragesService;
use OCP\Files\Storage\IStorage;
use OCP\Files\StorageNotAvailableException;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StorageAuthBase extends Base {
	/** @var GlobalStoragesService */
	protected $globalService;
	/** @var IUserManager */
	protected $userManager;

	private function getUserOption(InputInterface $input): ?string {
		if ($input->getOption('user')) {
			return (string)$input->getOption('user');
		} elseif (isset($_ENV['NOTIFY_USER'])) {
			return (string)$_ENV['NOTIFY_USER'];
		} elseif (isset($_SERVER['NOTIFY_USER'])) {
			return (string)$_SERVER['NOTIFY_USER'];
		} else {
			return null;
		}
	}

	private function getPasswordOption(InputInterface $input): ?string {
		if ($input->getOption('password')) {
			return (string)$input->getOption('password');
		} elseif (isset($_ENV['NOTIFY_PASSWORD'])) {
			return (string)$_ENV['NOTIFY_PASSWORD'];
		} elseif (isset($_SERVER['NOTIFY_PASSWORD'])) {
			return (string)$_SERVER['NOTIFY_PASSWORD'];
		} else {
			return null;
		}
	}

	protected function createStorage(InputInterface $input, OutputInterface $output): ?array {
		$mount = $this->globalService->getStorage($input->getArgument('mount_id'));
		if (is_null($mount)) {
			$output->writeln('<error>Mount not found</error>');
			return null;
		}
		$noAuth = false;

		$userOption = $this->getUserOption($input);
		$passwordOption = $this->getPasswordOption($input);

		// if only the user is provided, we get the user object to pass along to the auth backend
		// this allows using saved user credentials
		$user = ($userOption && !$passwordOption) ? $this->userManager->get($userOption) : null;

		try {
			$authBackend = $mount->getAuthMechanism();
			$authBackend->manipulateStorageConfig($mount, $user);
		} catch (InsufficientDataForMeaningfulAnswerException $e) {
			$noAuth = true;
		} catch (StorageNotAvailableException $e) {
			$noAuth = true;
		}

		if ($userOption) {
			$mount->setBackendOption('user', $userOption);
		}
		if ($passwordOption) {
			$mount->setBackendOption('password', $passwordOption);
		}

		try {
			$backend = $mount->getBackend();
			$backend->manipulateStorageConfig($mount, $user);
		} catch (InsufficientDataForMeaningfulAnswerException $e) {
			$noAuth = true;
		} catch (StorageNotAvailableException $e) {
			$noAuth = true;
		}

		try {
			$class = $mount->getBackend()->getStorageClass();
			/** @var IStorage $storage */
			$storage = new $class($mount->getBackendOptions());
			if (!$storage->test()) {
				throw new \Exception();
			}
			return [$mount, $storage];
		} catch (\Exception $e) {
			$output->writeln('<error>Error while trying to create storage</error>');
			if ($noAuth) {
				$output->writeln('<error>Username and/or password required</error>');
			}
			return [$mount, null];
		}
	}
}
