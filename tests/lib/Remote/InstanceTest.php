<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace Test\Remote;

use OC\Memcache\ArrayCache;
use OC\Remote\Instance;
use OCP\ICache;
use Test\TestCase;
use Test\Traits\ClientServiceTrait;

class InstanceTest extends TestCase {
	use ClientServiceTrait;

	/** @var ICache */
	private $cache;

	protected function setUp(): void {
		parent::setUp();

		$this->cache = new ArrayCache();
	}

	public function testBasicStatus() {
		$instance = new Instance('example.com', $this->cache, $this->getClientService());
		$this->expectGetRequest('https://example.com/status.php', '{"installed":true,"maintenance":false,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');

		$this->assertEquals(true, $instance->isActive());
		$this->assertEquals('13.0.0.5', $instance->getVersion());
		$this->assertEquals('https', $instance->getProtocol());
		$this->assertEquals('https://example.com', $instance->getFullUrl());
	}

	public function testHttpFallback() {
		$instance = new Instance('example.com', $this->cache, $this->getClientService());
		$this->expectGetRequest('https://example.com/status.php', new \Exception());
		$this->expectGetRequest('http://example.com/status.php', '{"installed":true,"maintenance":false,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');

		$this->assertEquals('http', $instance->getProtocol());
		$this->assertEquals('http://example.com', $instance->getFullUrl());
	}

	public function testRerequestHttps() {
		$instance = new Instance('example.com', $this->cache, $this->getClientService());
		$this->expectGetRequest('https://example.com/status.php', '{"installed":true,"maintenance":false,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');

		$this->assertEquals('https', $instance->getProtocol());
		$this->assertEquals(true, $instance->isActive());

		$this->cache->remove('remote/example.com/status');
		$this->expectGetRequest('https://example.com/status.php', '{"installed":true,"maintenance":true,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');
		$instance2 = new Instance('example.com', $this->cache, $this->getClientService());
		$this->assertEquals('https', $instance2->getProtocol());
		$this->assertEquals(false, $instance2->isActive());
	}

	
	public function testPreventDowngradeAttach() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('refusing to connect to remote instance(example.com) over http that was previously accessible over https');

		$instance = new Instance('example.com', $this->cache, $this->getClientService());
		$this->expectGetRequest('https://example.com/status.php', '{"installed":true,"maintenance":false,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');

		$this->assertEquals('https', $instance->getProtocol());

		$this->expectGetRequest('https://example.com/status.php', new \Exception());
		$this->cache->remove('remote/example.com/status');
		$instance2 = new Instance('example.com', $this->cache, $this->getClientService());
		$instance2->getProtocol();
	}
}
