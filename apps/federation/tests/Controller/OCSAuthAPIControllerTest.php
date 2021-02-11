<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Federation\Tests\Controller;

use OC\BackgroundJob\JobList;
use OCA\Federation\Controller\OCSAuthAPIController;
use OCA\Federation\DbHandler;
use OCA\Federation\TrustedServers;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ILogger;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use Test\TestCase;

class OCSAuthAPIControllerTest extends TestCase {

	/** @var \PHPUnit\Framework\MockObject\MockObject|IRequest */
	private $request;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ISecureRandom  */
	private $secureRandom;

	/** @var \PHPUnit\Framework\MockObject\MockObject|JobList */
	private $jobList;

	/** @var \PHPUnit\Framework\MockObject\MockObject|TrustedServers */
	private $trustedServers;

	/** @var \PHPUnit\Framework\MockObject\MockObject|DbHandler */
	private $dbHandler;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ILogger */
	private $logger;

	/** @var \PHPUnit\Framework\MockObject\MockObject|ITimeFactory */
	private $timeFactory;


	/** @var  OCSAuthAPIController */
	private $ocsAuthApi;

	/** @var int simulated timestamp */
	private $currentTime = 1234567;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->secureRandom = $this->createMock(ISecureRandom::class);
		$this->trustedServers = $this->createMock(TrustedServers::class);
		$this->dbHandler = $this->createMock(DbHandler::class);
		$this->jobList = $this->createMock(JobList::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);


		$this->ocsAuthApi = new OCSAuthAPIController(
			'federation',
			$this->request,
			$this->secureRandom,
			$this->jobList,
			$this->trustedServers,
			$this->dbHandler,
			$this->logger,
			$this->timeFactory
		);

		$this->timeFactory->method('getTime')
			->willReturn($this->currentTime);
	}

	/**
	 * @dataProvider dataTestRequestSharedSecret
	 *
	 * @param string $token
	 * @param string $localToken
	 * @param bool $isTrustedServer
	 * @param bool $ok
	 */
	public function testRequestSharedSecret($token, $localToken, $isTrustedServer, $ok) {
		$url = 'url';

		$this->trustedServers
			->expects($this->once())
			->method('isTrustedServer')->with($url)->willReturn($isTrustedServer);
		$this->dbHandler->expects($this->any())
			->method('getToken')->with($url)->willReturn($localToken);

		if ($ok) {
			$this->jobList->expects($this->once())->method('add')
				->with('OCA\Federation\BackgroundJob\GetSharedSecret', ['url' => $url, 'token' => $token, 'created' => $this->currentTime]);
		} else {
			$this->jobList->expects($this->never())->method('add');
			$this->jobList->expects($this->never())->method('remove');
		}

		try {
			$this->ocsAuthApi->requestSharedSecret($url, $token);
			$this->assertTrue($ok);
		} catch (OCSForbiddenException $e) {
			$this->assertFalse($ok);
		}
	}

	public function dataTestRequestSharedSecret() {
		return [
			['token2', 'token1', true, true],
			['token1', 'token2', false, false],
			['token1', 'token2', true, false],
		];
	}

	/**
	 * @dataProvider dataTestGetSharedSecret
	 *
	 * @param bool $isTrustedServer
	 * @param bool $isValidToken
	 * @param bool $ok
	 */
	public function testGetSharedSecret($isTrustedServer, $isValidToken, $ok) {
		$url = 'url';
		$token = 'token';

		/** @var OCSAuthAPIController | \PHPUnit\Framework\MockObject\MockObject $ocsAuthApi */
		$ocsAuthApi = $this->getMockBuilder('OCA\Federation\Controller\OCSAuthAPIController')
			->setConstructorArgs(
				[
					'federation',
					$this->request,
					$this->secureRandom,
					$this->jobList,
					$this->trustedServers,
					$this->dbHandler,
					$this->logger,
					$this->timeFactory
				]
			)->setMethods(['isValidToken'])->getMock();

		$this->trustedServers
			->expects($this->any())
			->method('isTrustedServer')->with($url)->willReturn($isTrustedServer);
		$ocsAuthApi->expects($this->any())
			->method('isValidToken')->with($url, $token)->willReturn($isValidToken);

		if ($ok) {
			$this->secureRandom->expects($this->once())->method('generate')->with(32)
				->willReturn('secret');
			$this->trustedServers->expects($this->once())
				->method('addSharedSecret')->willReturn($url, 'secret');
		} else {
			$this->secureRandom->expects($this->never())->method('generate');
			$this->trustedServers->expects($this->never())->method('addSharedSecret');
		}

		try {
			$result = $ocsAuthApi->getSharedSecret($url, $token);
			$this->assertTrue($ok);
			$data = $result->getData();
			$this->assertSame('secret', $data['sharedSecret']);
		} catch (OCSForbiddenException $e) {
			$this->assertFalse($ok);
		}
	}

	public function dataTestGetSharedSecret() {
		return [
			[true, true, true],
			[false, true, false],
			[true, false, false],
			[false, false, false],
		];
	}
}
