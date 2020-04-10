<?php

declare (strict_types = 1);
/**
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv@protonmail.com)
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace Test\Template;

use OC\Files\AppData\AppData;
use OC\Files\AppData\Factory;
use OC\Template\IconsCacher;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ILogger;
use OCP\IURLGenerator;

class IconsCacherTest extends \Test\TestCase {
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	protected $logger;
	/** @var IAppData|\PHPUnit_Framework_MockObject_MockObject */
	protected $appData;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	protected $urlGenerator;
	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	protected function setUp(): void {
		$this->logger = $this->createMock(ILogger::class);
		$this->appData = $this->createMock(AppData::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);

		/** @var Factory|\PHPUnit_Framework_MockObject_MockObject $factory */
		$factory = $this->createMock(Factory::class);
		$factory->method('get')->with('css')->willReturn($this->appData);

		$this->folder = $this->createMock(ISimpleFolder::class);
		$this->appData->method('getFolder')->willReturn($this->folder);

		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->iconsCacher = new IconsCacher(
			$this->logger,
			$factory,
			$this->urlGenerator,
			$this->timeFactory
		);
	}

	public function testGetIconsFromEmptyCss() {
		$css = "
			icon.test {
				color: #aaa;
			}
		";
		$icons = self::invokePrivate($this->iconsCacher, 'getIconsFromCss', [$css]);
		$this->assertTrue(empty($icons));
	}

	public function testGetIconsFromValidCss() {
		$css = "
			icon.test {
				--icon-test: url('/svg/core/actions/add/000?v=1');
				background-image: var(--icon-test);
			}
		";
		$actual = self::invokePrivate($this->iconsCacher, 'getIconsFromCss', [$css]);
		$expected = [
			'icon-test' => '/svg/core/actions/add/000?v=1'
		];
		$this->assertEquals($expected, $actual);
	}

	public function testSetIconsFromEmptyCss() {
		$expected = "
			icon.test {
				color: #aaa;
			}
		";
		$actual = $this->iconsCacher->setIconsCss($expected);
		$this->assertEquals($expected, $actual);
	}

	public function testSetIconsFromValidCss() {
		$css = "
			icon.test {
				--icon-test: url('/index.php/svg/core/actions/add?color=000&v=1');
				background-image: var(--icon-test);
			}
		";
		$expected = "
			icon.test {
				
				background-image: var(--icon-test);
			}
		";

		$iconsFile = $this->createMock(ISimpleFile::class);
		$this->folder->expects($this->exactly(2))
			->method('getFile')
			->willReturn($iconsFile);

		$actual = $this->iconsCacher->setIconsCss($css);
		$this->assertEquals($expected, $actual);
	}

	public function testSetIconsFromValidCssMultipleTimes() {
		$css = "
			icon.test {
				--icon-test: url('/index.php/svg/core/actions/add?color=000&v=1');
				background-image: var(--icon-test);
			}
		";
		$expected = "
			icon.test {
				
				background-image: var(--icon-test);
			}
		";

		$iconsFile = $this->createMock(ISimpleFile::class);
		$this->folder->expects($this->exactly(4))
			->method('getFile')
			->willReturn($iconsFile);

		$actual = $this->iconsCacher->setIconsCss($css);
		$actual = $this->iconsCacher->setIconsCss($actual);
		$actual = $this->iconsCacher->setIconsCss($actual);
		$this->assertEquals($expected, $actual);
	}

}
