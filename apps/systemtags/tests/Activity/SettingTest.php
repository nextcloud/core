<?php
/**
 *
 *
 * @author Rinat Gumirov <rinat.gumirov@mail.ru>
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

namespace OCA\SystemTags\Tests\Activity;

use OCA\SystemTags\Activity\Setting;
use OCP\IL10N;
use Test\TestCase;

class SettingTest extends TestCase {
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l;
	/** @var Setting */
	private $setting;

	protected function setUp(): void {
		parent::setUp();
		$this->l = $this->createMock(IL10N::class);

		$this->setting = new Setting($this->l);
	}

	public function testGetIdentifier() {
		$this->assertSame('systemtags', $this->setting->getIdentifier());
	}

	public function testGetName() {
		$this->l
			->expects($this->once())
			->method('t')
			->with('<strong>System tags</strong> for a file have been modified')
			->willReturn('<strong>System tags</strong> for a file have been modified');

		$this->assertSame('<strong>System tags</strong> for a file have been modified', $this->setting->getName());
	}

	public function testGetPriority() {
		$this->assertSame(50, $this->setting->getPriority());
	}

	public function testCanChangeStream() {
		$this->assertSame(true, $this->setting->canChangeStream());
	}
	
	public function testIsDefaultEnabledStream() {
		$this->assertSame(true, $this->setting->isDefaultEnabledStream());
	}
	
	public function testCanChangeMail() {
		$this->assertSame(true, $this->setting->canChangeMail());
	}
	
	public function testIsDefaultEnabledMail() {
		$this->assertSame(false, $this->setting->isDefaultEnabledMail());
	}
}
