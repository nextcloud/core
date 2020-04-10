<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Files_External\Tests;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\DefinitionParameter;
use OCA\Files_External\Lib\StorageConfig;

class StorageConfigTest extends \Test\TestCase {

	public function testJsonSerialization() {
		$backend = $this->getMockBuilder(Backend::class)
			->disableOriginalConstructor()
			->getMock();
		$parameter = $this->getMockBuilder(DefinitionParameter::class)
			->disableOriginalConstructor()
			->getMock();
		$parameter
			->expects($this->once())
			->method('getType')
			->willReturn(1);
		$backend
			->expects($this->once())
			->method('getParameters')
			->willReturn(['secure' => $parameter]);
		$backend->method('getIdentifier')
			->willReturn('storage::identifier');

		$authMech = $this->getMockBuilder(AuthMechanism::class)
			->disableOriginalConstructor()
			->getMock();
		$authMech->method('getIdentifier')
			->willReturn('auth::identifier');

		$storageConfig = new StorageConfig(1);
		$storageConfig->setMountPoint('test');
		$storageConfig->setBackend($backend);
		$storageConfig->setAuthMechanism($authMech);
		$storageConfig->setBackendOptions(['user' => 'test', 'password' => 'password123', 'secure' => '1']);
		$storageConfig->setPriority(128);
		$storageConfig->setApplicableUsers(['user1', 'user2']);
		$storageConfig->setApplicableGroups(['group1', 'group2']);
		$storageConfig->setMountOptions(['preview' => false]);

		$json = $storageConfig->jsonSerialize();

		$this->assertSame(1, $json['id']);
		$this->assertSame('/test', $json['mountPoint']);
		$this->assertSame('storage::identifier', $json['backend']);
		$this->assertSame('auth::identifier', $json['authMechanism']);
		$this->assertSame('test', $json['backendOptions']['user']);
		$this->assertSame('password123', $json['backendOptions']['password']);
		$this->assertSame(true, $json['backendOptions']['secure']);
		$this->assertSame(128, $json['priority']);
		$this->assertSame(['user1', 'user2'], $json['applicableUsers']);
		$this->assertSame(['group1', 'group2'], $json['applicableGroups']);
		$this->assertSame(['preview' => false], $json['mountOptions']);
	}

}
