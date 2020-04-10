<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Files_External\Tests\Command;

use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\BufferedOutput;
use Test\TestCase;

abstract class CommandTest extends TestCase {
	/**
	 * @param StorageConfig[] $mounts
	 * @return \OCA\Files_External\Service\GlobalStoragesService|\PHPUnit_Framework_MockObject_MockObject
	 */
	protected function getGlobalStorageService(array $mounts = []) {
		$mock = $this->getMockBuilder('OCA\Files_External\Service\GlobalStoragesService')
			->disableOriginalConstructor()
			->getMock();

		$this->bindMounts($mock, $mounts);

		return $mock;
	}

	/**
	 * @param \PHPUnit_Framework_MockObject_MockObject $mock
	 * @param StorageConfig[] $mounts
	 */
	protected function bindMounts(\PHPUnit_Framework_MockObject_MockObject $mock, array $mounts) {
		$mock->expects($this->any())
			->method('getStorage')
			->willReturnCallback(function ($id) use ($mounts) {
				foreach ($mounts as $mount) {
					if ($mount->getId() === $id) {
						return $mount;
					}
				}
				throw new NotFoundException();
			});
	}

	/**
	 * @param $id
	 * @param $mountPoint
	 * @param $backendClass
	 * @param string $applicableIdentifier
	 * @param array $config
	 * @param array $options
	 * @param array $users
	 * @param array $groups
	 * @return StorageConfig
	 */
	protected function getMount($id, $mountPoint, $backendClass, $applicableIdentifier = 'password::password', $config = [], $options = [], $users = [], $groups = []) {
		$mount = new StorageConfig($id);

		$mount->setMountPoint($mountPoint);
		$mount->setBackendOptions($config);
		$mount->setMountOptions($options);
		$mount->setApplicableUsers($users);
		$mount->setApplicableGroups($groups);

		return $mount;
	}

	protected function getInput(Command $command, array $arguments = [], array $options = []) {
		$input = new ArrayInput([]);
		$input->bind($command->getDefinition());
		foreach ($arguments as $key => $value) {
			$input->setArgument($key, $value);
		}
		foreach ($options as $key => $value) {
			$input->setOption($key, $value);
		}
		return $input;
	}

	protected function executeCommand(Command $command, Input $input) {
		$output = new BufferedOutput();
		$this->invokePrivate($command, 'execute', [$input, $output]);
		return $output->fetch();
	}
}
