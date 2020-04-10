<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Guillaume COMPAGNON <gcompagnon@outlook.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Theming\Tests;

use OCA\Theming\Capabilities;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\IConfig;
use OCP\IURLGenerator;
use Test\TestCase;

/**
 * Class CapabilitiesTest
 *
 * @package OCA\Theming\Tests
 */
class CapabilitiesTest extends TestCase  {
	/** @var ThemingDefaults|\PHPUnit_Framework_MockObject_MockObject */
	protected $theming;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $url;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var Util|\PHPUnit_Framework_MockObject_MockObject */
	protected $util;

	/** @var Capabilities */
	protected $capabilities;

	protected function setUp(): void {
		parent::setUp();

		$this->theming = $this->createMock(ThemingDefaults::class);
		$this->url = $this->getMockBuilder(IURLGenerator::class)->getMock();
		$this->config = $this->createMock(IConfig::class);
		$this->util = $this->createMock(Util::class);
		$this->capabilities = new Capabilities($this->theming, $this->util, $this->url, $this->config);
	}

	public function dataGetCapabilities() {
		return [
			['name', 'url', 'slogan', '#FFFFFF', '#000000', 'logo', 'background', 'http://absolute/', true, [
				'name' => 'name',
				'url' => 'url',
				'slogan' => 'slogan',
				'color' => '#FFFFFF',
				'color-text' => '#000000',
				'color-element' => '#aaaaaa',
				'logo' => 'http://absolute/logo',
				'background' => 'http://absolute/background',
				'background-plain' => false,
				'background-default' => false,
				'logoheader' => 'http://absolute/logo',
				'favicon' => 'http://absolute/logo',
			]],
			['name1', 'url2', 'slogan3', '#01e4a0', '#ffffff', 'logo5', 'background6', 'http://localhost/', false, [
				'name' => 'name1',
				'url' => 'url2',
				'slogan' => 'slogan3',
				'color' => '#01e4a0',
				'color-text' => '#ffffff',
				'color-element' => '#01e4a0',
				'logo' => 'http://localhost/logo5',
				'background' => 'http://localhost/background6',
				'background-plain' => false,
				'background-default' => true,
				'logoheader' => 'http://localhost/logo5',
				'favicon' => 'http://localhost/logo5',
			]],
			['name1', 'url2', 'slogan3', '#000000', '#ffffff', 'logo5', 'backgroundColor', 'http://localhost/', true, [
				'name' => 'name1',
				'url' => 'url2',
				'slogan' => 'slogan3',
				'color' => '#000000',
				'color-text' => '#ffffff',
				'color-element' => '#000000',
				'logo' => 'http://localhost/logo5',
				'background' => '#000000',
				'background-plain' => true,
				'background-default' => false,
				'logoheader' => 'http://localhost/logo5',
				'favicon' => 'http://localhost/logo5',
			]],
			['name1', 'url2', 'slogan3', '#000000', '#ffffff', 'logo5', 'backgroundColor', 'http://localhost/', false, [
				'name' => 'name1',
				'url' => 'url2',
				'slogan' => 'slogan3',
				'color' => '#000000',
				'color-text' => '#ffffff',
				'color-element' => '#000000',
				'logo' => 'http://localhost/logo5',
				'background' => '#000000',
				'background-plain' => true,
				'background-default' => true,
				'logoheader' => 'http://localhost/logo5',
				'favicon' => 'http://localhost/logo5',
			]],
		];
	}

	/**
	 * @dataProvider dataGetCapabilities
	 * @param string $name
	 * @param string $url
	 * @param string $slogan
	 * @param string $color
	 * @param string $textColor
	 * @param string $logo
	 * @param string $background
	 * @param string $baseUrl
	 * @param bool $backgroundThemed
	 * @param string[] $expected
	 */
	public function testGetCapabilities($name, $url, $slogan, $color, $textColor, $logo, $background, $baseUrl, $backgroundThemed, array $expected) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->willReturn($background);
		$this->theming->expects($this->once())
			->method('getName')
			->willReturn($name);
		$this->theming->expects($this->once())
			->method('getBaseUrl')
			->willReturn($url);
		$this->theming->expects($this->once())
			->method('getSlogan')
			->willReturn($slogan);
		$this->theming->expects($this->atLeast(1))
			->method('getColorPrimary')
			->willReturn($color);
		$this->theming->expects($this->exactly(3))
			->method('getLogo')
			->willReturn($logo);
		$this->theming->expects($this->once())
			->method('getTextColorPrimary')
			->willReturn($textColor);

		$util = new Util($this->config, $this->createMock(IAppManager::class), $this->createMock(IAppData::class));
		$this->util->expects($this->once())
			->method('elementColor')
			->with($color)
			->willReturn($util->elementColor($color));

		$this->util->expects($this->once())
			->method('isBackgroundThemed')
			->willReturn($backgroundThemed);

		if($background !== 'backgroundColor') {
			$this->theming->expects($this->once())
				->method('getBackground')
				->willReturn($background);
			$this->url->expects($this->exactly(4))
				->method('getAbsoluteURL')
				->willReturnCallback(function ($url) use ($baseUrl) {
					return $baseUrl . $url;
				});
		} else {
			$this->url->expects($this->exactly(3))
				->method('getAbsoluteURL')
				->willReturnCallback(function ($url) use ($baseUrl) {
					return $baseUrl . $url;
				});
		}

		$this->assertEquals(['theming' => $expected], $this->capabilities->getCapabilities());
	}
}
