<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
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

namespace OCA\OAuth2\Tests\Settings;

use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AdminTest extends TestCase {

	/** @var Admin|MockObject */
	private $admin;

	/** @var IInitialStateService|MockObject */
	private $initialStateService;

	/** @var ClientMapper|MockObject */
	private $clientMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->initialStateService = $this->createMock(IInitialStateService::class);
		$this->clientMapper = $this->createMock(ClientMapper::class);

		$this->admin = new Admin($this->initialStateService, $this->clientMapper);
	}

	public function testGetForm() {
		$expected = new TemplateResponse(
			'oauth2',
			'admin',
			[],
			''
		);
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('security', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(100, $this->admin->getPriority());
	}
}
