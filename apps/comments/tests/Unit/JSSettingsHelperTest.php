<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Comments\Tests\Unit;

use OCA\Comments\JSSettingsHelper;
use OCP\IConfig;
use OCP\IServerContainer;
use Test\TestCase;

class JSSettingsHelperTest extends TestCase {
	/** @var  IServerContainer|\PHPUnit_Framework_MockObject_MockObject */
	protected $c;
	/** @var  JSSettingsHelper */
	protected $helper;

	protected function setUp(): void {
		parent::setUp();

		$this->c = $this->createMock(IServerContainer::class);

		$this->helper = new JSSettingsHelper($this->c);
	}

	public function testExtend() {
		$config = $this->createMock(IConfig::class);
		$config->expects($this->once())
			->method('getAppValue')
			->with('comments', 'maxAutoCompleteResults')
			->willReturn(13);

		$this->c->expects($this->once())
			->method('getConfig')
			->willReturn($config);

		$config = [
			'oc_appconfig' => json_encode([
				'anotherapp' => [
					'foo' => 'bar',
					'foobar' => true
				]
			])
		];

		$this->helper->extend(['array' => &$config]);

		$appConfig = json_decode($config['oc_appconfig'], true);
		$this->assertTrue(isset($appConfig['comments']));
		$this->assertTrue(isset($appConfig['anotherapp']));
		$this->assertSame(2, count($appConfig['anotherapp']));
		$this->assertSame(13, $appConfig['comments']['maxAutoCompleteResults']);
	}
}
