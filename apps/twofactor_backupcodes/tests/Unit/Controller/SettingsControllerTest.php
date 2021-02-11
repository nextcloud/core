<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\TwoFactorBackupCodes\Tests\Unit\Controller;

use OCA\TwoFactorBackupCodes\Controller\SettingsController;
use OCA\TwoFactorBackupCodes\Service\BackupCodeStorage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Test\TestCase;

class SettingsControllerTest extends TestCase {

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var BackupCodeStorage|\PHPUnit\Framework\MockObject\MockObject */
	private $storage;

	/** @var IUserSession|\PHPUnit\Framework\MockObject\MockObject */
	private $userSession;

	/** @var SettingsController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->storage = $this->getMockBuilder(BackupCodeStorage::class)
			->disableOriginalConstructor()
			->getMock();
		$this->userSession = $this->getMockBuilder(IUserSession::class)->getMock();

		$this->controller = new SettingsController('twofactor_backupcodes', $this->request, $this->storage, $this->userSession);
	}

	public function testCreateCodes() {
		$user = $this->getMockBuilder(IUser::class)->getMock();

		$codes = ['a', 'b'];
		$this->userSession->expects($this->once())
			->method('getUser')
			->willReturn($user);
		$this->storage->expects($this->once())
			->method('createCodes')
			->with($user)
			->willReturn($codes);
		$this->storage->expects($this->once())
			->method('getBackupCodesState')
			->with($user)
			->willReturn('state');

		$expected = [
			'codes' => $codes,
			'state' => 'state',
		];
		$response = $this->controller->createCodes();
		$this->assertInstanceOf(JSONResponse::class, $response);
		$this->assertEquals($expected, $response->getData());
	}
}
