<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Encryption\Tests\Controller;

use OCA\Encryption\Controller\RecoveryController;
use OCA\Encryption\Recovery;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use Test\TestCase;

class RecoveryControllerTest extends TestCase {
	/** @var RecoveryController */
	private $controller;
	/** @var \OCP\IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $requestMock;
	/** @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $configMock;
	/** @var \OCP\IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nMock;
	/** @var \OCA\Encryption\Recovery|\PHPUnit_Framework_MockObject_MockObject */
	private $recoveryMock;

	public function adminRecoveryProvider() {
		return [
			['test', 'test', '1', 'Recovery key successfully enabled', Http::STATUS_OK],
			['', 'test', '1', 'Missing recovery key password', Http::STATUS_BAD_REQUEST],
			['test', '', '1', 'Please repeat the recovery key password', Http::STATUS_BAD_REQUEST],
			['test', 'soimething that doesn\'t match', '1', 'Repeated recovery key password does not match the provided recovery key password', Http::STATUS_BAD_REQUEST],
			['test', 'test', '0', 'Recovery key successfully disabled', Http::STATUS_OK],
		];
	}

	/**
	 * @dataProvider adminRecoveryProvider
	 * @param $recoveryPassword
	 * @param $passConfirm
	 * @param $enableRecovery
	 * @param $expectedMessage
	 * @param $expectedStatus
	 */
	public function testAdminRecovery($recoveryPassword, $passConfirm, $enableRecovery, $expectedMessage, $expectedStatus) {


		$this->recoveryMock->expects($this->any())
			->method('enableAdminRecovery')
			->willReturn(true);

		$this->recoveryMock->expects($this->any())
			->method('disableAdminRecovery')
			->willReturn(true);

		$response = $this->controller->adminRecovery($recoveryPassword,
			$passConfirm,
			$enableRecovery);


		$this->assertEquals($expectedMessage, $response->getData()['data']['message']);
		$this->assertEquals($expectedStatus, $response->getStatus());


	}

	public function changeRecoveryPasswordProvider() {
		return [
			['test', 'test', 'oldtestFail', 'Could not change the password. Maybe the old password was not correct.', Http::STATUS_BAD_REQUEST],
			['test', 'test', 'oldtest', 'Password successfully changed.', Http::STATUS_OK],
			['test', 'notmatch', 'oldtest', 'Repeated recovery key password does not match the provided recovery key password', Http::STATUS_BAD_REQUEST],
			['', 'test', 'oldtest', 'Please provide a new recovery password', Http::STATUS_BAD_REQUEST],
			['test', 'test', '', 'Please provide the old recovery password', Http::STATUS_BAD_REQUEST]
		];
	}

	/**
	 * @dataProvider changeRecoveryPasswordProvider
	 * @param $password
	 * @param $confirmPassword
	 * @param $oldPassword
	 * @param $expectedMessage
	 * @param $expectedStatus
	 */
	public function testChangeRecoveryPassword($password, $confirmPassword, $oldPassword, $expectedMessage, $expectedStatus) {
		$this->recoveryMock->expects($this->any())
			->method('changeRecoveryKeyPassword')
			->with($password, $oldPassword)
			->willReturnMap([
				['test', 'oldTestFail', false],
				['test', 'oldtest', true]
			]);

		$response = $this->controller->changeRecoveryPassword($password,
			$oldPassword,
			$confirmPassword);

		$this->assertEquals($expectedMessage, $response->getData()['data']['message']);
		$this->assertEquals($expectedStatus, $response->getStatus());


	}

	public function userSetRecoveryProvider() {
		return [
			['1', 'Recovery Key enabled', Http::STATUS_OK],
			['0', 'Could not enable the recovery key, please try again or contact your administrator', Http::STATUS_BAD_REQUEST]
		];
	}

	/**
	 * @dataProvider userSetRecoveryProvider
	 * @param $enableRecovery
	 * @param $expectedMessage
	 * @param $expectedStatus
	 */
	public function testUserSetRecovery($enableRecovery, $expectedMessage, $expectedStatus) {
		$this->recoveryMock->expects($this->any())
			->method('setRecoveryForUser')
			->with($enableRecovery)
			->willReturnMap([
				['1', true],
				['0', false]
			]);


		$response = $this->controller->userSetRecovery($enableRecovery);

		$this->assertEquals($expectedMessage, $response->getData()['data']['message']);
		$this->assertEquals($expectedStatus, $response->getStatus());

	}

	protected function setUp(): void {
		parent::setUp();

		$this->requestMock = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()
			->getMock();

		$this->configMock = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();

		$this->l10nMock = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();

		// Make l10n work in our tests
		$this->l10nMock->expects($this->any())
			->method('t')
			->willReturnArgument(0);

		$this->recoveryMock = $this->getMockBuilder(Recovery::class)
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new RecoveryController('encryption',
			$this->requestMock,
			$this->configMock,
			$this->l10nMock,
			$this->recoveryMock);
	}

}
