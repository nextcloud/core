<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests\Settings\Admin;

use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OC\Encryption\Manager;
use OCA\Settings\Settings\Admin\Security;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SecurityTest extends TestCase {
	/** @var Security */
	private $admin;
	/** @var Manager */
	private $manager;
	/** @var IUserManager */
	private $userManager;
	/** @var MandatoryTwoFactor|MockObject */
	private $mandatoryTwoFactor;
	/** @var IInitialStateService|MockObject */
	private $initialState;

	protected function setUp(): void {
		parent::setUp();
		$this->manager = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()->getMock();
		$this->userManager = $this->getMockBuilder(IUserManager::class)->getMock();
		$this->mandatoryTwoFactor = $this->createMock(MandatoryTwoFactor::class);
		$this->initialState = $this->createMock(IInitialStateService::class);

		$this->admin = new Security(
			$this->manager,
			$this->userManager,
			$this->mandatoryTwoFactor,
			$this->initialState
		);
	}

	/**
	 * @return array
	 */
	public function encryptionSettingsProvider() {
		return [
			[true],
			[false],
		];
	}

	/**
	 * @dataProvider encryptionSettingsProvider
	 * @param bool $enabled
	 */
	public function testGetFormWithOnlyOneBackend($enabled) {
		$this->manager
			->expects($this->once())
			->method('isEnabled')
			->willReturn($enabled);
		$this->manager
			->expects($this->once())
			->method('isReady')
			->willReturn($enabled);
		$this->manager
			->expects($this->once())
			->method('getEncryptionModules')
			->willReturn([]);
		$this->userManager
			->expects($this->once())
			->method('getBackends')
			->willReturn(['entry']);
		$expected = new TemplateResponse(
			'settings',
			'settings/admin/security',
			[
				'encryptionEnabled' => $enabled,
				'encryptionReady' => $enabled,
				'externalBackendsEnabled' => false,
				'encryptionModules' => []
			],
			''
		);
		$this->assertEquals($expected, $this->admin->getForm());
	}

	/**
	 * @dataProvider encryptionSettingsProvider
	 * @param bool $enabled
	 */
	public function testGetFormWithMultipleBackends($enabled) {
		$this->manager
			->expects($this->once())
			->method('isEnabled')
			->willReturn($enabled);
		$this->manager
			->expects($this->once())
			->method('isReady')
			->willReturn($enabled);
		$this->manager
			->expects($this->once())
			->method('getEncryptionModules')
			->willReturn([]);
		$this->userManager
			->expects($this->once())
			->method('getBackends')
			->willReturn(['entry', 'entry']);
		$expected = new TemplateResponse(
			'settings',
			'settings/admin/security',
			[
				'encryptionEnabled' => $enabled,
				'encryptionReady' => $enabled,
				'externalBackendsEnabled' => true,
				'encryptionModules' => []
			],
			''
		);
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('security', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(10, $this->admin->getPriority());
	}
}
