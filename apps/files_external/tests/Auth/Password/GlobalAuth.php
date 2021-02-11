<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files_External\Tests\Auth\Password;

use OCA\Files_External\Lib\Auth\Password\GlobalAuth;
use OCA\Files_external\Lib\StorageConfig;
use OCP\IL10N;
use OCP\Security\ICredentialsManager;
use Test\TestCase;

class GlobalAuthTest extends TestCase {
	/**
	 * @var \OCP\IL10N|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $l10n;

	/**
	 * @var \OCP\Security\ICredentialsManager|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $credentialsManager;

	/**
	 * @var GlobalAuth
	 */
	private $instance;

	protected function setUp(): void {
		parent::setUp();
		$this->l10n = $this->createMock(IL10N::class);
		$this->credentialsManager = $this->createMock(ICredentialsManager::class);
		$this->instance = new GlobalAuth($this->l10n, $this->credentialsManager);
	}

	private function getStorageConfig($type, $config = []) {
		/** @var \OCA\Files_External\Lib\StorageConfig|\PHPUnit\Framework\MockObject\MockObject $storageConfig */
		$storageConfig = $this->createMock(StorageConfig::class);
		$storageConfig->expects($this->any())
			->method('getType')
			->willReturn($type);
		$storageConfig->expects($this->any())
			->method('getBackendOptions')
			->willReturnCallback(function () use (&$config) {
				return $config;
			});
		$storageConfig->expects($this->any())
			->method('getBackendOption')
			->willReturnCallback(function ($key) use (&$config) {
				return $config[$key];
			});
		$storageConfig->expects($this->any())
			->method('setBackendOption')
			->willReturnCallback(function ($key, $value) use (&$config) {
				$config[$key] = $value;
			});

		return $storageConfig;
	}

	public function testNoCredentials() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn(null);

		$storage = $this->getStorageConfig(StorageConfig::MOUNT_TYPE_ADMIN);

		$this->instance->manipulateStorageConfig($storage);
		$this->assertEquals([], $storage->getBackendOptions());
	}

	public function testSavedCredentials() {
		$this->credentialsManager->expects($this->once())
			->method('retrieve')
			->willReturn([
				'user' => 'a',
				'password' => 'b'
			]);

		$storage = $this->getStorageConfig(StorageConfig::MOUNT_TYPE_ADMIN);

		$this->instance->manipulateStorageConfig($storage);
		$this->assertEquals([
			'user' => 'a',
			'password' => 'b'
		], $storage->getBackendOptions());
	}


	public function testNoCredentialsPersonal() {
		$this->expectException(\OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException::class);

		$this->credentialsManager->expects($this->never())
			->method('retrieve');

		$storage = $this->getStorageConfig(StorageConfig::MOUNT_TYPE_PERSONAl);

		$this->instance->manipulateStorageConfig($storage);
		$this->assertEquals([], $storage->getBackendOptions());
	}
}
