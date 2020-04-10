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

namespace Test\Remote\Api;

use OC\Memcache\ArrayCache;
use OC\Remote\Api\OCS;
use OC\Remote\Credentials;
use OC\Remote\InstanceFactory;
use OCP\Remote\IInstanceFactory;
use Test\TestCase;
use Test\Traits\ClientServiceTrait;

class OCSTest extends TestCase {
	use ClientServiceTrait;

	/** @var IInstanceFactory */
	private $instanceFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->instanceFactory = new InstanceFactory(new ArrayCache(), $this->getClientService());
		$this->expectGetRequest('https://example.com/status.php',
			'{"installed":true,"maintenance":false,"needsDbUpgrade":false,"version":"13.0.0.5","versionstring":"13.0.0 alpha","edition":"","productname":"Nextcloud"}');
	}

	protected function getOCSClient() {
		return new OCS(
			$this->instanceFactory->getInstance('example.com'),
			new Credentials('user', 'pass'),
			$this->getClientService()
		);
	}

	protected function getOCSUrl($url) {
		return 'https://example.com/ocs/v2.php/' . $url;
	}

	public function testGetUser() {
		$client = $this->getOCSClient();

		$this->expectGetRequest($this->getOCSUrl('cloud/users/user'),
			'{"ocs":{"meta":{"status":"ok","statuscode":200,"message":"OK"},
			"data":{"id":"user","quota":{"free":5366379387,"used":2329733,"total":5368709120,"relative":0.040000000000000001,"quota":5368709120},
			"email":null,"displayname":"test","phone":"","address":"","website":"","twitter":"","groups":["Test","Test1"],"language":"en"}}}');

		$user = $client->getUser('user');
		$this->assertEquals('user', $user->getUserId());
	}

	
	public function testGetUserInvalidResponse() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Invalid user response, expected field email not found');

		$client = $this->getOCSClient();

		$this->expectGetRequest($this->getOCSUrl('cloud/users/user'),
			'{"ocs":{"meta":{"status":"ok","statuscode":200,"message":"OK"},
			"data":{"id":"user"}}}');

		$client->getUser('user');
	}

	
	public function testInvalidPassword() {
		$this->expectException(\OC\ForbiddenException::class);

		$client = $this->getOCSClient();

		$this->expectGetRequest($this->getOCSUrl('cloud/users/user'),
			'{"ocs":{"meta":{"status":"failure","statuscode":997,"message":"Current user is not logged in"},"data":[]}}');

		$client->getUser('user');
	}
}
