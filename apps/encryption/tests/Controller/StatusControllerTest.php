<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OCA\Encryption\Controller\StatusController;
use OCA\Encryption\Session;
use OCP\Encryption\IManager;
use OCP\IL10N;
use OCP\IRequest;
use Test\TestCase;

class StatusControllerTest extends TestCase {

	/** @var \OCP\IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $requestMock;

	/** @var \OCP\IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nMock;

	/** @var  \OCA\Encryption\Session | \PHPUnit_Framework_MockObject_MockObject */
	protected $sessionMock;

	/** @var  IManager | \PHPUnit_Framework_MockObject_MockObject */
	protected $encryptionManagerMock;

	/** @var StatusController */
	protected $controller;

	protected function setUp(): void {

		parent::setUp();

		$this->sessionMock = $this->getMockBuilder(Session::class)
			->disableOriginalConstructor()->getMock();
		$this->requestMock = $this->createMock(IRequest::class);

		$this->l10nMock = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()->getMock();
		$this->l10nMock->expects($this->any())
			->method('t')
			->willReturnCallback(function ($message) {
				return $message;
			});
		$this->encryptionManagerMock = $this->createMock(IManager::class);

		$this->controller = new StatusController('encryptionTest',
			$this->requestMock,
			$this->l10nMock,
			$this->sessionMock,
			$this->encryptionManagerMock);

	}

	/**
	 * @dataProvider dataTestGetStatus
	 *
	 * @param string $status
	 * @param string $expectedStatus
	 */
	public function testGetStatus($status, $expectedStatus) {
		$this->sessionMock->expects($this->once())
			->method('getStatus')->willReturn($status);
		$result = $this->controller->getStatus();
		$data = $result->getData();
		$this->assertSame($expectedStatus, $data['status']);
	}

	public function dataTestGetStatus() {
		return [
			[Session::INIT_EXECUTED, 'interactionNeeded'],
			[Session::INIT_SUCCESSFUL, 'success'],
			[Session::NOT_INITIALIZED, 'interactionNeeded'],
			['unknown', 'error'],
		];
	}
}
