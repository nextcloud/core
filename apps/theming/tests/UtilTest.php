<?php
/**
 * @copyright Copyright (c) 2016 Julius Härtl <jus@bitgrid.net>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Michael Weimann <mail@michael-weimann.eu>
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

use OCA\Theming\Util;
use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IConfig;
use Test\TestCase;

class UtilTest extends TestCase {

	/** @var Util */
	protected $util;
	/** @var IConfig */
	protected $config;
	/** @var IAppData */
	protected $appData;
	/** @var IAppManager */
	protected $appManager;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appData = $this->createMock(IAppData::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->util = new Util($this->config, $this->appManager, $this->appData);
	}

	public function dataInvertTextColor() {
		return [
			['#ffffff', true],
			['#000000', false],
			['#0082C9', false],
			['#ffff00', true],
		];
	}
	/**
	 * @dataProvider dataInvertTextColor
	 */
	public function testInvertTextColor($color, $expected) {
		$invert = $this->util->invertTextColor($color);
		$this->assertEquals($expected, $invert);
	}

	public function testCalculateLuminanceLight() {
		$luminance = $this->util->calculateLuminance('#ffffff');
		$this->assertEquals(1, $luminance);
	}

	public function testCalculateLuminanceDark() {
		$luminance = $this->util->calculateLuminance('#000000');
		$this->assertEquals(0, $luminance);
	}

	public function testCalculateLuminanceLightShorthand() {
		$luminance = $this->util->calculateLuminance('#fff');
		$this->assertEquals(1, $luminance);
	}

	public function testCalculateLuminanceDarkShorthand() {
		$luminance = $this->util->calculateLuminance('#000');
		$this->assertEquals(0, $luminance);
	}
	public function testInvertTextColorInvalid() {
		$invert = $this->util->invertTextColor('aaabbbcccddd123');
		$this->assertEquals(false, $invert);
	}

	public function testInvertTextColorEmpty() {
		$invert = $this->util->invertTextColor('');
		$this->assertEquals(false, $invert);
	}

	public function testElementColorDefault() {
		$elementColor = $this->util->elementColor("#000000");
		$this->assertEquals('#000000', $elementColor);
	}

	public function testElementColorOnDarkBackground() {
		$elementColor = $this->util->elementColor("#000000", false);
		$this->assertEquals('#555555', $elementColor);
	}

	public function testElementColorOnBrightBackground() {
		$elementColor = $this->util->elementColor('#ffffff');
		$this->assertEquals('#aaaaaa', $elementColor);
	}

	public function testGenerateRadioButtonWhite() {
		$button = $this->util->generateRadioButton('#ffffff');
		$expected = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiI+PHBhdGggZD0iTTggMWE3IDcgMCAwIDAtNyA3IDcgNyAwIDAgMCA3IDcgNyA3IDAgMCAwIDctNyA3IDcgMCAwIDAtNy03em0wIDFhNiA2IDAgMCAxIDYgNiA2IDYgMCAwIDEtNiA2IDYgNiAwIDAgMS02LTYgNiA2IDAgMCAxIDYtNnptMCAyYTQgNCAwIDEgMCAwIDggNCA0IDAgMCAwIDAtOHoiIGZpbGw9IiNmZmZmZmYiLz48L3N2Zz4=';
		$this->assertEquals($expected, $button);
	}

	public function testGenerateRadioButtonBlack() {
		$button = $this->util->generateRadioButton('#000000');
		$expected = 'PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGhlaWdodD0iMTYiIHdpZHRoPSIxNiI+PHBhdGggZD0iTTggMWE3IDcgMCAwIDAtNyA3IDcgNyAwIDAgMCA3IDcgNyA3IDAgMCAwIDctNyA3IDcgMCAwIDAtNy03em0wIDFhNiA2IDAgMCAxIDYgNiA2IDYgMCAwIDEtNiA2IDYgNiAwIDAgMS02LTYgNiA2IDAgMCAxIDYtNnptMCAyYTQgNCAwIDEgMCAwIDggNCA0IDAgMCAwIDAtOHoiIGZpbGw9IiMwMDAwMDAiLz48L3N2Zz4=';
		$this->assertEquals($expected, $button);
	}

	/**
	 * @dataProvider dataGetAppIcon
	 */
	public function testGetAppIcon($app, $expected) {
		$this->appData->expects($this->any())
			->method('getFolder')
			->with('images')
			->willThrowException(new NotFoundException());
		$this->appManager->expects($this->once())
			->method('getAppPath')
			->with($app)
			->willReturn(\OC_App::getAppPath($app));
		$icon = $this->util->getAppIcon($app);
		$this->assertEquals($expected, $icon);
	}

	public function dataGetAppIcon() {
		return [
			['user_ldap', \OC_App::getAppPath('user_ldap') . '/img/app.svg'],
			['noapplikethis', \OC::$SERVERROOT . '/core/img/logo/logo.svg'],
			['comments', \OC_App::getAppPath('comments') . '/img/comments.svg'],
		];
	}

	public function testGetAppIconThemed() {
		$file = $this->createMock(ISimpleFile::class);
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->expects($this->once())
			->method('getFile')
			->with('logo')
			->willReturn($file);
		$this->appData->expects($this->once())
			->method('getFolder')
			->with('images')
			->willReturn($folder);
		$icon = $this->util->getAppIcon('noapplikethis');
		$this->assertEquals($file, $icon);
	}

	/**
	 * @dataProvider dataGetAppImage
	 */
	public function testGetAppImage($app, $image, $expected) {
		if ($app !== 'core') {
			$this->appManager->expects($this->once())
				->method('getAppPath')
				->with($app)
				->willReturn(\OC_App::getAppPath($app));
		}
		$this->assertEquals($expected, $this->util->getAppImage($app, $image));
	}

	public function dataGetAppImage() {
		return [
			['core', 'logo/logo.svg', \OC::$SERVERROOT . '/core/img/logo/logo.svg'],
			['files', 'external', \OC::$SERVERROOT . '/apps/files/img/external.svg'],
			['files', 'external.svg', \OC::$SERVERROOT . '/apps/files/img/external.svg'],
			['noapplikethis', 'foobar.svg', false],
		];
	}

	public function testColorizeSvg() {
		$input = "#0082c9 #0082C9 #000000 #FFFFFF";
		$expected = "#AAAAAA #AAAAAA #000000 #FFFFFF";
		$result = $this->util->colorizeSvg($input, '#AAAAAA');
		$this->assertEquals($expected, $result);
	}

	public function testIsAlreadyThemedFalse() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('theme', '')
			->willReturn('');
		$actual = $this->util->isAlreadyThemed();
		$this->assertFalse($actual);
	}

	public function testIsAlreadyThemedTrue() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('theme', '')
			->willReturn('example');
		$actual = $this->util->isAlreadyThemed();
		$this->assertTrue($actual);
	}

	public function dataIsBackgroundThemed() {
		return [
			['', false],
			['png', true],
			['backgroundColor', false],
		];
	}
	/**
	 * @dataProvider dataIsBackgroundThemed
	 */
	public function testIsBackgroundThemed($backgroundMime, $expected) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('theming', 'backgroundMime', '')
			->willReturn($backgroundMime);
		$this->assertEquals($expected, $this->util->isBackgroundThemed());
	}
}
