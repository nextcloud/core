<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\Provisioning_API\Tests\Middleware;

use OCA\Provisioning_API\Middleware\Exceptions\NotSubAdminException;
use OCA\Provisioning_API\Middleware\ProvisioningApiMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use Test\TestCase;

class ProvisioningApiMiddlewareTest extends TestCase {

	/** @var IControllerMethodReflector|\PHPUnit_Framework_MockObject_MockObject */
	private $reflector;

	protected function setUp(): void {
		parent::setUp();

		$this->reflector = $this->createMock(IControllerMethodReflector::class);
	}

	public function dataAnnotation() {
		return [
			[false, false, false, false],
			[false, false,  true, false],
			[false,  true,  true, false],
			[ true, false, false,  true],
			[ true, false,  true, false],
			[ true,  true, false, false],
			[ true,  true,  true, false],
		];
	}

	/**
	 * @dataProvider dataAnnotation
	 *
	 * @param bool $subadminRequired
	 * @param bool $isAdmin
	 * @param bool $isSubAdmin
	 * @param bool $shouldThrowException
	 */
	public function testBeforeController($subadminRequired, $isAdmin, $isSubAdmin, $shouldThrowException) {
		$middleware = new ProvisioningApiMiddleware(
			$this->reflector,
			$isAdmin,
			$isSubAdmin
		);

		$this->reflector->method('hasAnnotation')
			->with('NoSubAdminRequired')
			->willReturn(!$subadminRequired);

		try {
			$middleware->beforeController(
				$this->createMock(Controller::class),
				'myMethod'
			);
			$this->assertFalse($shouldThrowException);
		} catch (NotSubAdminException $e) {
			$this->assertTrue($shouldThrowException);
		}
	}

	public function dataAfterException() {
		return [
			[new NotSubAdminException(), false],
			[new \Exception('test', 42), true],
		];
	}

	/**
	 * @dataProvider dataAfterException
	 *
	 * @param \Exception $e
	 * @param bool $forwared
	 */
	public function testAfterException(\Exception $exception, $forwared) {
		$middleware = new ProvisioningApiMiddleware(
			$this->reflector,
			false,
			false
		);

		try {
			$middleware->afterException(
				$this->createMock(Controller::class),
				'myMethod',
				$exception
			);
			$this->fail();
		} catch (OCSException $e) {
			$this->assertFalse($forwared);
			$this->assertSame($exception->getMessage(), $e->getMessage());
			$this->assertSame(\OCP\API::RESPOND_UNAUTHORISED, $e->getCode());
		} catch (\Exception $e) {
			$this->assertTrue($forwared);
			$this->assertSame($exception, $e);
		}

	}
}
