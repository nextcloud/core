<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Files_External\Tests\Settings;

use OCA\Files_External\Lib\Auth\Password\GlobalAuth;
use OCA\Files_External\Service\BackendService;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Encryption\IManager;
use Test\TestCase;

class AdminTest extends TestCase {
	/** @var Admin */
	private $admin;
	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $encryptionManager;
	/** @var GlobalStoragesService|\PHPUnit_Framework_MockObject_MockObject */
	private $globalStoragesService;
	/** @var BackendService|\PHPUnit_Framework_MockObject_MockObject */
	private $backendService;
	/** @var GlobalAuth|\PHPUnit_Framework_MockObject_MockObject */
	private $globalAuth;

	protected function setUp(): void {
		parent::setUp();
		$this->encryptionManager = $this->createMock(IManager::class);
		$this->globalStoragesService = $this->createMock(GlobalStoragesService::class);
		$this->backendService = $this->createMock(BackendService::class);
		$this->globalAuth = $this->createMock(GlobalAuth::class);

		$this->admin = new Admin(
			$this->encryptionManager,
			$this->globalStoragesService,
			$this->backendService,
			$this->globalAuth
		);
	}

	public function testGetForm() {
		$this->encryptionManager
			->expects($this->once())
			->method('isEnabled')
			->willReturn(false);
		$this->globalStoragesService
			->expects($this->once())
			->method('getStorages')
			->willReturn(['a', 'b', 'c']);
		$this->backendService
			->expects($this->once())
			->method('getAvailableBackends')
			->willReturn(['d', 'e', 'f']);
		$this->backendService
			->expects($this->once())
			->method('getAuthMechanisms')
			->willReturn(['g', 'h', 'i']);
		$this->backendService
			->expects($this->once())
			->method('isUserMountingAllowed')
			->willReturn(true);
		$this->backendService
			->expects($this->exactly(2))
			->method('getBackends')
			->willReturn([]);
		$this->globalAuth
			->expects($this->once())
			->method('getAuth')
			->with('')
			->willReturn('asdf:asdf');
		$params = [
			'encryptionEnabled'    => false,
			'visibilityType'       => BackendService::VISIBILITY_ADMIN,
			'storages'             => ['a', 'b', 'c'],
			'backends'             => ['d', 'e', 'f'],
			'authMechanisms'       => ['g', 'h', 'i'],
			'dependencies'         => \OC_Mount_Config::dependencyMessage($this->backendService->getBackends()),
			'allowUserMounting'    => true,
			'globalCredentials'    => 'asdf:asdf',
			'globalCredentialsUid' => '',
		];
		$expected = new TemplateResponse('files_external', 'settings', $params, '');
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection() {
		$this->assertSame('externalstorages', $this->admin->getSection());
	}

	public function testGetPriority() {
		$this->assertSame(40, $this->admin->getPriority());
	}
}
