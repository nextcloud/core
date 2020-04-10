<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\Authentication\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\ProviderSet;
use OCA\TwoFactorBackupCodes\Provider\BackupCodesProvider;
use OCP\Authentication\TwoFactorAuth\IProvider;
use Test\TestCase;

class ProviderSetTest extends TestCase {

	/** @var ProviderSet */
	private $providerSet;

	public function testIndexesProviders() {
		$p1 = $this->createMock(IProvider::class);
		$p1->method('getId')->willReturn('p1');
		$p2 = $this->createMock(IProvider::class);
		$p2->method('getId')->willReturn('p2');
		$expected = [
			'p1' => $p1,
			'p2' => $p2,
		];

		$set = new ProviderSet([$p2, $p1], false);

		$this->assertEquals($expected, $set->getProviders());
	}

	public function testGet3rdPartyProviders() {
		$p1 = $this->createMock(IProvider::class);
		$p1->method('getId')->willReturn('p1');
		$p2 = $this->createMock(IProvider::class);
		$p2->method('getId')->willReturn('p2');
		$p3 = $this->createMock(BackupCodesProvider::class);
		$p3->method('getId')->willReturn('p3');
		$expected = [
			'p1' => $p1,
			'p2' => $p2,
		];

		$set = new ProviderSet([$p2, $p1], false);

		$this->assertEquals($expected, $set->getPrimaryProviders());
	}

	public function testGetProvider() {
		$p1 = $this->createMock(IProvider::class);
		$p1->method('getId')->willReturn('p1');

		$set = new ProviderSet([$p1], false);
		$provider = $set->getProvider('p1');

		$this->assertEquals($p1, $provider);
	}

	public function testGetProviderNotFound() {
		$set = new ProviderSet([], false);
		$provider = $set->getProvider('p1');

		$this->assertNull($provider);
	}

	public function testIsProviderMissing() {
		$set = new ProviderSet([], true);

		$this->assertTrue($set->isProviderMissing());
	}

}
