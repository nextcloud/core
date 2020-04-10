<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

namespace Test\Security\RateLimiting;

use OC\Security\RateLimiting\Backend\IBackend;
use OC\Security\RateLimiting\Limiter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use Test\TestCase;

class LimiterTest extends TestCase {
	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;
	/** @var IBackend|\PHPUnit_Framework_MockObject_MockObject */
	private $backend;
	/** @var Limiter */
	private $limiter;

	protected function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->backend = $this->createMock(IBackend::class);

		$this->limiter = new Limiter(
			$this->timeFactory,
			$this->backend
		);
	}

	
	public function testRegisterAnonRequestExceeded() {
		$this->expectException(\OC\Security\RateLimiting\Exception\RateLimitExceededException::class);
		$this->expectExceptionMessage('Rate limit exceeded');

		$this->backend
			->expects($this->once())
			->method('getAttempts')
			->with(
				'MyIdentifier',
				'4664f0d9c88dcb7552be47b37bb52ce35977b2e60e1ac13757cf625f31f87050a41f3da064887fa87d49fd042e4c8eb20de8f10464877d3959677ab011b73a47',
				100
			)
			->willReturn(101);

		$this->limiter->registerAnonRequest('MyIdentifier', 100, 100, '127.0.0.1');
	}

	public function testRegisterAnonRequestSuccess() {
		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(2000);
		$this->backend
			->expects($this->once())
			->method('getAttempts')
			->with(
				'MyIdentifier',
				'4664f0d9c88dcb7552be47b37bb52ce35977b2e60e1ac13757cf625f31f87050a41f3da064887fa87d49fd042e4c8eb20de8f10464877d3959677ab011b73a47',
				100
			)
			->willReturn(99);
		$this->backend
			->expects($this->once())
			->method('registerAttempt')
			->with(
				'MyIdentifier',
				'4664f0d9c88dcb7552be47b37bb52ce35977b2e60e1ac13757cf625f31f87050a41f3da064887fa87d49fd042e4c8eb20de8f10464877d3959677ab011b73a47',
				2000
			);

		$this->limiter->registerAnonRequest('MyIdentifier', 100, 100, '127.0.0.1');
	}

	
	public function testRegisterUserRequestExceeded() {
		$this->expectException(\OC\Security\RateLimiting\Exception\RateLimitExceededException::class);
		$this->expectExceptionMessage('Rate limit exceeded');

		/** @var IUser|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');
		$this->backend
			->expects($this->once())
			->method('getAttempts')
			->with(
				'MyIdentifier',
				'ddb2ec50fa973fd49ecf3d816f677c8095143e944ad10485f30fb3dac85c13a346dace4dae2d0a15af91867320957bfd38a43d9eefbb74fe6919e15119b6d805',
				100
			)
			->willReturn(101);

		$this->limiter->registerUserRequest('MyIdentifier', 100, 100, $user);
	}

	public function testRegisterUserRequestSuccess() {
		/** @var IUser|\PHPUnit_Framework_MockObject_MockObject $user */
		$user = $this->createMock(IUser::class);
		$user
			->expects($this->once())
			->method('getUID')
			->willReturn('MyUid');

		$this->timeFactory
			->expects($this->once())
			->method('getTime')
			->willReturn(2000);
		$this->backend
			->expects($this->once())
			->method('getAttempts')
			->with(
				'MyIdentifier',
				'ddb2ec50fa973fd49ecf3d816f677c8095143e944ad10485f30fb3dac85c13a346dace4dae2d0a15af91867320957bfd38a43d9eefbb74fe6919e15119b6d805',
				100
			)
			->willReturn(99);
		$this->backend
			->expects($this->once())
			->method('registerAttempt')
			->with(
				'MyIdentifier',
				'ddb2ec50fa973fd49ecf3d816f677c8095143e944ad10485f30fb3dac85c13a346dace4dae2d0a15af91867320957bfd38a43d9eefbb74fe6919e15119b6d805',
				2000
			);

		$this->limiter->registerUserRequest('MyIdentifier', 100, 100, $user);
	}
}
