<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Files_External\Tests\Command;

use OCA\Files_External\Command\Applicable;
use OCP\IGroupManager;
use OCP\IUserManager;

class ApplicableTest extends CommandTest {
	private function getInstance($storageService) {
		/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject $userManager */
		$userManager = $this->createMock(IUserManager::class);
		/** @var \OCP\IGroupManager|\PHPUnit_Framework_MockObject_MockObject $groupManager */
		$groupManager = $this->createMock(IGroupManager::class);

		$userManager->expects($this->any())
			->method('userExists')
			->willReturn(true);

		$groupManager->expects($this->any())
			->method('groupExists')
			->willReturn(true);

		return new Applicable($storageService, $userManager, $groupManager);
	}

	public function testListEmpty() {
		$mount = $this->getMount(1, '', '');

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json'
		]);

		$result = json_decode($this->executeCommand($command, $input), true);

		$this->assertEquals(['users' => [], 'groups' => []], $result);
	}

	public function testList() {
		$mount = $this->getMount(1, '', '', '', [], [], ['test', 'asd']);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json'
		]);

		$result = json_decode($this->executeCommand($command, $input), true);

		$this->assertEquals(['users' => ['test', 'asd'], 'groups' => []], $result);
	}

	public function testAddSingle() {
		$mount = $this->getMount(1, '', '', '', [], [], []);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json',
			'add-user' => ['foo']
		]);

		$this->executeCommand($command, $input);

		$this->assertEquals(['foo'], $mount->getApplicableUsers());
	}

	public function testAddDuplicate() {
		$mount = $this->getMount(1, '', '', '', [], [], ['foo']);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json',
			'add-user' => ['foo', 'bar']
		]);

		$this->executeCommand($command, $input);

		$this->assertEquals(['foo', 'bar'], $mount->getApplicableUsers());
	}

	public function testRemoveSingle() {
		$mount = $this->getMount(1, '', '', '', [], [], ['foo', 'bar']);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json',
			'remove-user' => ['bar']
		]);

		$this->executeCommand($command, $input);

		$this->assertEquals(['foo'], $mount->getApplicableUsers());
	}

	public function testRemoveNonExisting() {
		$mount = $this->getMount(1, '', '', '', [], [], ['foo', 'bar']);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json',
			'remove-user' => ['bar', 'asd']
		]);

		$this->executeCommand($command, $input);

		$this->assertEquals(['foo'], $mount->getApplicableUsers());
	}

	public function testRemoveAddRemove() {
		$mount = $this->getMount(1, '', '', '', [], [], ['foo', 'bar']);

		$storageService = $this->getGlobalStorageService([$mount]);
		$command = $this->getInstance($storageService);

		$input = $this->getInput($command, [
			'mount_id' => 1
		], [
			'output' => 'json',
			'remove-user' => ['bar', 'asd'],
			'add-user' => ['test']
		]);

		$this->executeCommand($command, $input);

		$this->assertEquals(['foo', 'test'], $mount->getApplicableUsers());
	}
}
