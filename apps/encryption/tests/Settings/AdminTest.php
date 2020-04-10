<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Encryption\Tests\Settings;

use OCA\Encryption\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\ISession;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

class AdminTest extends TestCase {
	/** @var Admin */
	private $admin;
	/** @var IL10N */
	private $l;
	/** @var ILogger */
	private $logger;
	/** @var IUserSession */
	private $userSession;
	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var ISession */
	private $session;

	protected function setUp(): void {
		parent::setUp();

		$this->l = $this->getMockBuilder(IL10N::class)->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)->getMock();
		$this->session = $this->getMockBuilder(ISession::class)->getMock();

		$this->admin = new Admin(
			$this->l,
			$this->logger,
			$this->userSession,
			$this->config,
			$this->userManager,
			$this->session
		);
	}

	public function testGetForm() {
		$this->config
			->expects($this->at(0))
			->method('getAppValue')
			->with('encryption', 'recoveryAdminEnabled', '0')
			->willReturn(1);
		$this->config
			->expects($this->at(1))
			->method('getAppValue')
			->with('encryption', 'encryptHomeStorage', '1')
			->willReturn(1);
		$params = [
			'recoveryEnabled' => 1,
			'initStatus' => '0',
			'encryptHomeStorage' => false,
			'masterKeyEnabled' => false
		];
		$expected = new TemplateResponse('encryption', 'settings-admin', $params, '');
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('security', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(11, $this->admin->getPriority());
	}
}
