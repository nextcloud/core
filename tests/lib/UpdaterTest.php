<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Test;

use OC\Installer;
use OC\IntegrityCheck\Checker;
use OC\Updater;
use OCP\IConfig;
use OCP\ILogger;

class UpdaterTest extends TestCase {
	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var ILogger | \PHPUnit_Framework_MockObject_MockObject */
	private $logger;
	/** @var Updater */
	private $updater;
	/** @var Checker | \PHPUnit_Framework_MockObject_MockObject */
	private $checker;
	/** @var Installer|\PHPUnit_Framework_MockObject_MockObject */
	private $installer;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)
			->disableOriginalConstructor()
			->getMock();
		$this->checker = $this->getMockBuilder(Checker::class)
			->disableOriginalConstructor()
			->getMock();
		$this->installer = $this->getMockBuilder(Installer::class)
			->disableOriginalConstructor()
			->getMock();

		$this->updater = new Updater(
			$this->config,
			$this->checker,
			$this->logger,
			$this->installer
		);
	}

	/**
	 * @return array
	 */
	public function versionCompatibilityTestData() {
		return [
			// Upgrade with invalid version
			['9.1.1.13', '11.0.2.25', ['nextcloud' => ['11.0' => true]], false],
			['10.0.1.13', '11.0.2.25', ['nextcloud' => ['11.0' => true]], false],
			// Upgrad with valid version
			['11.0.1.13', '11.0.2.25', ['nextcloud' => ['11.0' => true]], true],
			// Downgrade with valid version
			['11.0.2.25', '11.0.1.13', ['nextcloud' => ['11.0' => true]], false],
			['11.0.2.25', '11.0.1.13', ['nextcloud' => ['11.0' => true]], true, true],
			// Downgrade with invalid version
			['11.0.2.25', '10.0.1.13', ['nextcloud' => ['10.0' => true]], false],
			['11.0.2.25', '10.0.1.13', ['nextcloud' => ['10.0' => true]], false, true],

			// Migration with unknown vendor
			['9.1.1.13', '11.0.2.25', ['nextcloud' => ['9.1' => true]], false, false, 'owncloud'],
			['9.1.1.13', '11.0.2.25', ['nextcloud' => ['9.1' => true]], false, true, 'owncloud'],
			// Migration with unsupported vendor version
			['9.1.1.13', '11.0.2.25', ['owncloud' => ['10.0' => true]], false, false, 'owncloud'],
			['9.1.1.13', '11.0.2.25', ['owncloud' => ['10.0' => true]], false, true, 'owncloud'],
			// Migration with valid vendor version
			['9.1.1.13', '11.0.2.25', ['owncloud' => ['9.1' => true]], true, false, 'owncloud'],
			['9.1.1.13', '11.0.2.25', ['owncloud' => ['9.1' => true]], true, true, 'owncloud'],
		];
	}

	/**
	 * @dataProvider versionCompatibilityTestData
	 *
	 * @param string $oldVersion
	 * @param string $newVersion
	 * @param array $allowedVersions
	 * @param bool $result
	 * @param bool $debug
	 * @param string $vendor
	 */
	public function testIsUpgradePossible($oldVersion, $newVersion, $allowedVersions, $result, $debug = false, $vendor = 'nextcloud') {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->with('debug', false)
			->willReturn($debug);
		$this->config->expects($this->any())
			->method('getAppValue')
			->with('core', 'vendor', '')
			->willReturn($vendor);

		$this->assertSame($result, $this->updater->isUpgradePossible($oldVersion, $newVersion, $allowedVersions));
	}

}
