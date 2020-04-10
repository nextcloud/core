<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\ShareByMail\Tests;

use OCA\ShareByMail\Settings;
use OCA\ShareByMail\Settings\SettingsManager;
use Test\TestCase;

class SettingsTest extends TestCase  {

	/** @var  Settings */
	private $instance;

	/** @var  SettingsManager | \PHPUnit_Framework_MockObject_MockObject */
	private $settingsManager;

	protected function setUp(): void {
		parent::setUp();

		$this->settingsManager = $this->getMockBuilder(SettingsManager::class)
			->disableOriginalConstructor()->getMock();
		$this->instance = new Settings($this->settingsManager);
	}

	public function testAnnounceShareProvider() {
		$before = [
			'oc_appconfig' =>
				json_encode([
					'key1' => 'value1',
					'key2' => 'value2'
				]),
			'oc_foo' => 'oc_bar'
		];

		$after = [
			'oc_appconfig' =>
				json_encode([
					'key1' => 'value1',
					'key2' => 'value2',
					'shareByMailEnabled' => true
				]),
			'oc_foo' => 'oc_bar'
		];

		$this->instance->announceShareProvider(['array' => &$before]);
		$this->assertSame($after, $before);
	}


	public function testAnnounceShareByMailSettings() {
		$this->settingsManager->expects($this->once())->method('enforcePasswordProtection')->willReturn(true);
		$before = [
			'oc_appconfig' =>
				json_encode([
					'key1' => 'value1',
					'key2' => 'value2'
				]),
			'oc_foo' => 'oc_bar'
		];

		$after = [
			'oc_appconfig' =>
				json_encode([
					'key1' => 'value1',
					'key2' => 'value2',
					'shareByMail' => ['enforcePasswordProtection' => true]
				]),
			'oc_foo' => 'oc_bar'
		];

		$this->instance->announceShareByMailSettings(['array' => &$before]);
		$this->assertSame($after, $before);
	}

}
