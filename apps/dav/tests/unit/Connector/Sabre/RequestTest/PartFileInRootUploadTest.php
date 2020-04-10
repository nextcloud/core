<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre\RequestTest;

use OCP\IConfig;

/**
 * Class PartFileInRootUploadTest
 *
 * @group DB
 *
 * @package OCA\DAV\Tests\unit\Connector\Sabre\RequestTest
 */
class PartFileInRootUploadTest extends UploadTest {
	protected function setUp(): void {
		$config = \OC::$server->getConfig();
		$mockConfig = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$mockConfig->expects($this->any())
			->method('getSystemValue')
			->willReturnCallback(function ($key, $default) use ($config) {
				if ($key === 'part_file_in_storage') {
					return false;
				} else {
					return $config->getSystemValue($key, $default);
				}
			});
		$this->overwriteService('AllConfig', $mockConfig);
		parent::setUp();
	}

	protected function tearDown(): void {
		$this->restoreService('AllConfig');
		parent::tearDown();
	}
}
