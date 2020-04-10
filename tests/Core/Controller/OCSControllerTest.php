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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\Controller;

use OC\CapabilitiesManager;
use OC\Security\IdentityProof\Key;
use OC\Security\IdentityProof\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

class OCSControllerTest extends TestCase {
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var CapabilitiesManager|\PHPUnit_Framework_MockObject_MockObject */
	private $capabilitiesManager;
	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;
	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
	private $keyManager;
	/** @var OCSController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->capabilitiesManager = $this->createMock(CapabilitiesManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->keyManager = $this->createMock(Manager::class);

		$this->controller = new OCSController(
			'core',
			$this->request,
			$this->capabilitiesManager,
			$this->userSession,
			$this->userManager,
			$this->keyManager
		);
	}

	public function testGetConfig() {
		$this->request->method('getServerHost')
			->willReturn('awesomehost.io');

		$data = [
			'version' => '1.7',
			'website' => 'Nextcloud',
			'host' => 'awesomehost.io',
			'contact' => '',
			'ssl' => 'false',
		];

		$expected = new DataResponse($data);
		$this->assertEquals($expected, $this->controller->getConfig());

		return new DataResponse($data);
	}

	public function testGetCapabilities() {
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(true);
		list($major, $minor, $micro) = \OCP\Util::getVersion();

		$result = [];
		$result['version'] = [
			'major' => $major,
			'minor' => $minor,
			'micro' => $micro,
			'string' => \OC_Util::getVersionString(),
			'edition' => '',
			'extendedSupport' => false
		];

		$capabilities = [
			'foo' => 'bar',
			'a' => [
				'b' => true,
				'c' => 11,
			]
		];
		$this->capabilitiesManager->method('getCapabilities')
			->willReturn($capabilities);

		$result['capabilities'] = $capabilities;

		$expected = new DataResponse($result);
		$this->assertEquals($expected, $this->controller->getCapabilities());
	}

	public function testGetCapabilitiesPublic() {
		$this->userSession->expects($this->once())
			->method('isLoggedIn')
			->willReturn(false);
		list($major, $minor, $micro) = \OCP\Util::getVersion();

		$result = [];
		$result['version'] = [
			'major' => $major,
			'minor' => $minor,
			'micro' => $micro,
			'string' => \OC_Util::getVersionString(),
			'edition' => '',
			'extendedSupport' => false
		];

		$capabilities = [
			'foo' => 'bar',
			'a' => [
				'b' => true,
				'c' => 11,
			]
		];
		$this->capabilitiesManager->method('getCapabilities')
			->with(true)
			->willReturn($capabilities);

		$result['capabilities'] = $capabilities;

		$expected = new DataResponse($result);
		$this->assertEquals($expected, $this->controller->getCapabilities());
	}

	public function testPersonCheckValid() {
		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('pass')
			)->willReturn($this->createMock(IUser::class));

		$expected = new DataResponse([
			'person' => [
				'personid' => 'user'
			]
		]);
		$this->assertEquals($expected, $this->controller->personCheck('user', 'pass'));
	}

	public function testPersonInvalid() {
		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('wrongpass')
			)->willReturn(false);

		$expected = new DataResponse([], 102);
		$expected->throttle();
		$this->assertEquals($expected, $this->controller->personCheck('user', 'wrongpass'));
	}

	public function testPersonNoLogin() {
		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('wrongpass')
			)->willReturn(false);

		$expected = new DataResponse([], 101);
		$this->assertEquals($expected, $this->controller->personCheck('', ''));
	}

	public function testGetIdentityProofWithNotExistingUser() {
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('NotExistingUser')
			->willReturn(null);

		$expected = new DataResponse(['User not found'], 404);
		$this->assertEquals($expected, $this->controller->getIdentityProof('NotExistingUser'));
	}

	public function testGetIdentityProof() {
		$user = $this->createMock(IUser::class);
		$key = $this->createMock(Key::class);
		$this->userManager
			->expects($this->once())
			->method('get')
			->with('ExistingUser')
			->willReturn($user);
		$this->keyManager
			->expects($this->once())
			->method('getKey')
			->with($user)
			->willReturn($key);
		$key
			->expects($this->once())
			->method('getPublic')
			->willReturn('Existing Users public key');

		$expected = new DataResponse([
			'public' => 'Existing Users public key',
		]);
		$this->assertEquals($expected, $this->controller->getIdentityProof('ExistingUser'));
	}
}
