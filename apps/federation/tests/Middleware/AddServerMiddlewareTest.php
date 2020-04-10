<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Federation\Tests\Middleware;

use OC\HintException;
use OCA\Federation\Controller\SettingsController;
use OCA\Federation\Middleware\AddServerMiddleware;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\ILogger;
use Test\TestCase;

class AddServerMiddlewareTest extends TestCase {

	/** @var  \PHPUnit_Framework_MockObject_MockObject | ILogger */
	private $logger;

	/** @var \PHPUnit_Framework_MockObject_MockObject | \OCP\IL10N */
	private $l10n;

	/** @var  AddServerMiddleware */
	private $middleware;

	/** @var  \PHPUnit_Framework_MockObject_MockObject | SettingsController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)->getMock();
		$this->controller = $this->getMockBuilder(SettingsController::class)
			->disableOriginalConstructor()->getMock();

		$this->middleware = new AddServerMiddleware(
			'AddServerMiddlewareTest',
			$this->l10n,
			$this->logger
		);
	}

	/**
	 * @dataProvider dataTestAfterException
	 *
	 * @param \Exception $exception
	 * @param string $hint
	 */
	public function testAfterException($exception, $hint) {

		$this->logger->expects($this->once())->method('logException');

		$this->l10n->expects($this->any())->method('t')
			->willReturnCallback(
				function ($message) {
					return $message;
				}
			);

		$result = $this->middleware->afterException($this->controller, 'method', $exception);

		$this->assertSame(Http::STATUS_BAD_REQUEST,
			$result->getStatus()
		);

		$data = $result->getData();

		$this->assertSame($hint,
			$data['message']
		);
	}

	public function dataTestAfterException() {
		return [
			[new HintException('message', 'hint'), 'hint'],
			[new \Exception('message'), 'message'],
		];
	}

}
