<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\UpdateNotification\Tests;

use OC\Updater\ChangesCheck;
use OC\Updater\VersionCheck;
use OCA\UpdateNotification\UpdateChecker;
use Test\TestCase;

class UpdateCheckerTest extends TestCase {
	/** @var ChangesCheck|\PHPUnit_Framework_MockObject_MockObject */
	protected $changesChecker;
	/** @var VersionCheck|\PHPUnit_Framework_MockObject_MockObject */
	private $updater;
	/** @var UpdateChecker */
	private $updateChecker;

	protected function setUp(): void {
		parent::setUp();

		$this->updater = $this->createMock(VersionCheck::class);
		$this->changesChecker = $this->createMock(ChangesCheck::class);
		$this->updateChecker = new UpdateChecker($this->updater, $this->changesChecker);
	}

	public function testGetUpdateStateWithUpdateAndInvalidLink() {
		$this->updater
			->expects($this->once())
			->method('check')
			->willReturn([
				'version' => '1.2.3',
				'versionstring' => 'Nextcloud 1.2.3',
				'web'=> 'javascript:alert(1)',
				'url'=> 'javascript:alert(2)',
				'changes' => 'javascript:alert(3)',
				'autoupdater'=> '0',
				'eol'=> '1',
			]);

		$expected = [
			'updateAvailable' => true,
			'updateVersion' => '1.2.3',
			'updateVersionString' => 'Nextcloud 1.2.3',
			'updaterEnabled' => false,
			'versionIsEol' => true,
		];
		$this->assertSame($expected, $this->updateChecker->getUpdateState());
	}

	public function testGetUpdateStateWithUpdateAndValidLink() {
		$changes = [
			'changelog' => 'https://nextcloud.com/changelog/#123-0-0',
			'whatsNew' => [
				'en' => [
					'regular' => [
						'Yardarm heave to brig spyglass smartly pillage',
						'Bounty gangway bilge skysail rope\'s end',
						'Maroon cutlass spirits nipperkin Plate Fleet',
					],
					'admin' => [
						'Scourge of the seven seas coffer doubloon',
						'Brig me splice the main brace',
					]
				]
			]
		];

		$this->updater
			->expects($this->once())
			->method('check')
			->willReturn([
				'version' => '1.2.3',
				'versionstring' => 'Nextcloud 1.2.3',
				'web'=> 'https://docs.nextcloud.com/myUrl',
				'url'=> 'https://downloads.nextcloud.org/server',
				'changes' => 'https://updates.nextcloud.com/changelog_server/?version=123.0.0',
				'autoupdater'=> '1',
				'eol'=> '0',
			]);

		$this->changesChecker->expects($this->once())
			->method('check')
			->willReturn($changes);

		$expected = [
			'updateAvailable' => true,
			'updateVersion' => '1.2.3',
			'updateVersionString' => 'Nextcloud 1.2.3',
			'updaterEnabled' => true,
			'versionIsEol' => false,
			'updateLink' => 'https://docs.nextcloud.com/myUrl',
			'downloadLink' => 'https://downloads.nextcloud.org/server',
			'changes' => $changes,
		];
		$this->assertSame($expected, $this->updateChecker->getUpdateState());
	}

	public function testGetUpdateStateWithoutUpdate() {
		$this->updater
			->expects($this->once())
			->method('check')
			->willReturn([]);

		$expected = [];
		$this->assertSame($expected, $this->updateChecker->getUpdateState());
	}
}
