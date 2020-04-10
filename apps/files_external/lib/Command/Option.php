<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

use OCA\Files_External\Lib\StorageConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class Option extends Config {
	protected function configure() {
		$this
			->setName('files_external:option')
			->setDescription('Manage mount options for a mount')
			->addArgument(
				'mount_id',
				InputArgument::REQUIRED,
				'The id of the mount to edit'
			)->addArgument(
				'key',
				InputArgument::REQUIRED,
				'key of the mount option to set/get'
			)->addArgument(
				'value',
				InputArgument::OPTIONAL,
				'value to set the mount option to, when no value is provided the existing value will be printed'
			);
	}

	/**
	 * @param StorageConfig $mount
	 * @param string $key
	 * @param OutputInterface $output
	 */
	protected function getOption(StorageConfig $mount, $key, OutputInterface $output) {
		$value = $mount->getMountOption($key);
		if (!is_string($value)) { // show bools and objects correctly
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
		if (!is_null($decoded)) {
			$value = $decoded;
		}
		$mount->setMountOption($key, $value);
		$this->globalService->updateStorage($mount);
	}
}
