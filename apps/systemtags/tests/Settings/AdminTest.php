<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\SystemTags\Tests\Settings;

use OCA\SystemTags\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use Test\TestCase;

class AdminTest extends TestCase {
	/** @var Admin */
	private $admin;

	protected function setUp(): void {
		parent::setUp();

		$this->admin = new Admin();
	}

	public function testGetForm() {
		$expected = new TemplateResponse('systemtags', 'admin', [], '');
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('server', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(70, $this->admin->getPriority());
	}
}
