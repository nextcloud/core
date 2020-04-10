<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author hkjolhede <hkjolhede@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Files_External\Tests\Storage;

use OCA\Files_External\Lib\Storage\SFTP;

/**
 * Class SftpTest
 *
 * @group DB
 *
 * @package OCA\Files_External\Tests\Storage
 */
class SftpTest extends \Test\Files\Storage\Storage {
	/**
	 * @var SFTP instance
	 */
	protected $instance;

	private $config;

	protected function setUp(): void {
		parent::setUp();

		$id = $this->getUniqueID();
		$this->config = include('files_external/tests/config.sftp.php');
		if (!is_array($this->config) or !$this->config['run']) {
			$this->markTestSkipped('SFTP backend not configured');
		}
		$this->config['root'] .= '/' . $id; //make sure we have an new empty folder to work in
		$this->instance = new SFTP($this->config);
		$this->instance->mkdir('/');
	}

	protected function tearDown(): void {
		if ($this->instance) {
			$this->instance->rmdir('/');
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider configProvider
	 */
	public function testStorageId($config, $expectedStorageId) {
		$instance = new SFTP($config);
		$this->assertEquals($expectedStorageId, $instance->getId());
	}

	public function configProvider() {
		return [
			[
				// no root path
				[
					'run' => true,
					'host' => 'somehost',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => '',
				],
				'sftp::someuser@somehost//',
			],
			[
				// without leading nor trailing slash
				[
					'run' => true,
					'host' => 'somehost',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => 'remotedir/subdir',
				],
				'sftp::someuser@somehost//remotedir/subdir/',
			],
			[
				// regular path
				[
					'run' => true,
					'host' => 'somehost',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => '/remotedir/subdir/',
				],
				'sftp::someuser@somehost//remotedir/subdir/',
			],
			[
				// different port
				[
					'run' => true,
					'host' => 'somehost:8822',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => 'remotedir/subdir/',
				],
				'sftp::someuser@somehost:8822//remotedir/subdir/',
			],
			[
				// ipv6 with port
				[
					'run' => true,
					'host' => 'FE80:0000:0000:0000:0202:B3FF:FE1E:8329',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => 'remotedir/subdir/',
				],
				'sftp::someuser@FE80:0000:0000:0000:0202:B3FF:FE1E:8329//remotedir/subdir/',
			],
			[
				// ipv6 without port
				[
					'run' => true,
					'host' => 'FE80:0000:0000:0000:0202:B3FF:FE1E:8329:8822',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => 'remotedir/subdir/',
				],
				'sftp::someuser@FE80:0000:0000:0000:0202:B3FF:FE1E:8329:8822//remotedir/subdir/',
			],
			[
				// collapsed ipv6 with port
				[
					'run' => true,
					'host' => 'FE80::0202:B3FF:FE1E:8329:8822',
					'user' => 'someuser',
					'password' => 'somepassword',
					'root' => 'remotedir/subdir/',
				],
				'sftp::someuser@FE80::0202:B3FF:FE1E:8329:8822//remotedir/subdir/',
			],
		];
	}
}
