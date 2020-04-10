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

namespace OCA\Files_External\Tests;

use OC\Files\Mount\Manager;
use OCA\Files_External\Lib\PersonalMount;
use Test\TestCase;

class PersonalMountTest extends TestCase {
	public function testFindByStorageId() {
		/** @var \OCA\Files_External\Service\UserStoragesService $storageService */
		$storageService = $this->getMockBuilder('\OCA\Files_External\Service\UserStoragesService')
			->disableOriginalConstructor()
			->getMock();

		$storage = $this->getMockBuilder('\OC\Files\Storage\Storage')
			->disableOriginalConstructor()
			->getMock();

		$storage->expects($this->any())
			->method('getId')
			->willReturn('dummy');

		$mount = new PersonalMount($storageService, 10, $storage, '/foo');

		$mountManager = new Manager();
		$mountManager->addMount($mount);

		$this->assertEquals([$mount], $mountManager->findByStorageId('dummy'));
	}
}
