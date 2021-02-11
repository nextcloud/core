<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Federation\Tests;

use OC\OCS\DiscoveryService;
use OCA\Federation\DbHandler;
use OCA\Federation\SyncFederationAddressBooks;

class SyncFederationAddressbooksTest extends \Test\TestCase {

	/** @var array */
	private $callBacks = [];

	/** @var  \PHPUnit\Framework\MockObject\MockObject | DiscoveryService */
	private $discoveryService;

	protected function setUp(): void {
		parent::setUp();

		$this->discoveryService = $this->getMockBuilder(DiscoveryService::class)
			->disableOriginalConstructor()->getMock();
		$this->discoveryService->expects($this->any())->method('discover')->willReturn([]);
	}

	public function testSync() {
		/** @var DbHandler | \PHPUnit\Framework\MockObject\MockObject $dbHandler */
		$dbHandler = $this->getMockBuilder('OCA\Federation\DbHandler')->
			disableOriginalConstructor()->
			getMock();
		$dbHandler->method('getAllServer')->
			willReturn([
				[
					'url' => 'https://cloud.drop.box',
					'url_hash' => 'sha1',
					'shared_secret' => 'iloveowncloud',
					'sync_token' => '0'
				]
			]);
		$dbHandler->expects($this->once())->method('setServerStatus')->
			with('https://cloud.drop.box', 1, '1');
		$syncService = $this->getMockBuilder('OCA\DAV\CardDAV\SyncService')
			->disableOriginalConstructor()
			->getMock();
		$syncService->expects($this->once())->method('syncRemoteAddressBook')
			->willReturn(1);

		/** @var \OCA\DAV\CardDAV\SyncService $syncService */
		$s = new SyncFederationAddressBooks($dbHandler, $syncService, $this->discoveryService);
		$s->syncThemAll(function ($url, $ex) {
			$this->callBacks[] = [$url, $ex];
		});
		$this->assertEquals(1, count($this->callBacks));
	}

	public function testException() {
		/** @var DbHandler | \PHPUnit\Framework\MockObject\MockObject $dbHandler */
		$dbHandler = $this->getMockBuilder('OCA\Federation\DbHandler')->
		disableOriginalConstructor()->
		getMock();
		$dbHandler->method('getAllServer')->
		willReturn([
			[
				'url' => 'https://cloud.drop.box',
				'url_hash' => 'sha1',
				'shared_secret' => 'iloveowncloud',
				'sync_token' => '0'
			]
		]);
		$syncService = $this->getMockBuilder('OCA\DAV\CardDAV\SyncService')
			->disableOriginalConstructor()
			->getMock();
		$syncService->expects($this->once())->method('syncRemoteAddressBook')
			->willThrowException(new \Exception('something did not work out'));

		/** @var \OCA\DAV\CardDAV\SyncService $syncService */
		$s = new SyncFederationAddressBooks($dbHandler, $syncService, $this->discoveryService);
		$s->syncThemAll(function ($url, $ex) {
			$this->callBacks[] = [$url, $ex];
		});
		$this->assertEquals(2, count($this->callBacks));
	}
}
