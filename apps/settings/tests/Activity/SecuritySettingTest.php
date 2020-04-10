<?php
/**
 * @copyright Copyright (c) 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests;

use OCA\Settings\Activity\SecuritySetting;
use OCP\IL10N;
use Test\TestCase;

class SecuritySettingTest extends TestCase {

	private $l10n;

	/** @var SecuritySetting */
	private $setting;

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);

		$this->setting = new SecuritySetting($this->l10n);
	}

	public function testCanChangeMail() {
		$this->assertFalse($this->setting->canChangeMail());
	}

	public function testCanChangeStream() {
		$this->assertFalse($this->setting->canChangeStream());
	}

	public function testGetIdentifier() {
		$this->assertEquals('security', $this->setting->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Security')
			->willReturn('Sicherheit');
		$this->assertEquals('Sicherheit', $this->setting->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(30, $this->setting->getPriority());
	}

	public function testIsDefaultEnabled() {
		$this->assertTrue($this->setting->isDefaultEnabledMail());
		$this->assertTrue($this->setting->isDefaultEnabledStream());
	}

}
