<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\Settings\Admin;

use OC\Settings\Admin\TipsTricks;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use Test\TestCase;

class TipsTrickTest extends TestCase {
	/** @var TipsTricks */
	private $admin;
	/** @var IConfig */
	private $config;

	public function setUp() {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();

		$this->admin = new TipsTricks(
			$this->config
		);
	}

	public function testGetFormWithExcludedGroupsWithSQLite() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('dbtype')
			->willReturn('sqlite');
		if(extension_loaded('gnupg')) {
			$expected = new TemplateResponse(
				'settings',
				'settings/admin/tipstricks',
				[
					'databaseOverload' => true,
					'noGpg' => false,
				],
				''
			);
		} else {
			$expected = new TemplateResponse(
				'settings',
				'settings/admin/tipstricks',
				[
					'databaseOverload' => true,
					'noGpg' => true,
				],
				''
			);
		}


		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetFormWithExcludedGroupsWithoutSQLite() {
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('dbtype')
			->willReturn('mysql');

		if(extension_loaded('gnupg')) {
			$expected = new TemplateResponse(
				'settings',
				'settings/admin/tipstricks',
				[
					'databaseOverload' => false,
					'noGpg' => false,
				],
				''
			);
		} else {
			$expected = new TemplateResponse(
				'settings',
				'settings/admin/tipstricks',
				[
					'databaseOverload' => false,
					'noGpg' => true,
				],
				''
			);
		}

		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('tips-tricks', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(0, $this->admin->getPriority());
	}
}
