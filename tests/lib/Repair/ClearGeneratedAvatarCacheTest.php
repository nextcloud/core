<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Repair;

use OC\Avatar\AvatarManager;
use OC\Repair\ClearGeneratedAvatarCache;
use OCP\IConfig;
use OCP\Migration\IOutput;

class ClearGeneratedAvatarCacheTest extends \Test\TestCase {

	/** @var AvatarManager */
	private $avatarManager;

	/** @var IOutput */
	private $outputMock;

	/** @var IConfig */
	private $config;

	/** @var ClearGeneratedAvatarCache */
	protected $repair;

	protected function setUp(): void {
		parent::setUp();

		$this->outputMock    = $this->createMock(IOutput::class);
		$this->avatarManager = $this->createMock(AvatarManager::class);
		$this->config        = $this->createMock(IConfig::class);

		$this->repair = new ClearGeneratedAvatarCache($this->config, $this->avatarManager);
	}

	public function shouldRunDataProvider() {
		return [
			['11.0.0.0', true],
			['15.0.0.3', true],
			['13.0.5.2', true],
			['12.0.0.0', true],
			['16.0.0.1', false],
			['15.0.0.2', true],
			['13.0.0.0', true],
			['15.0.0.5', false]
		];
	}

	/**
	 * @dataProvider shouldRunDataProvider
	 *
	 * @param string $from
	 * @param boolean $expected
	 */
	public function testShouldRun($from, $expected) {
		$this->config->expects($this->any())
			   ->method('getSystemValue')
			   ->with('version', '0.0.0.0')
			   ->willReturn($from);

		$this->assertEquals($expected, $this->invokePrivate($this->repair, 'shouldRun'));
	}
}
