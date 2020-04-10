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

namespace OCA\Files_Sharing\Tests\Middleware;

use OCA\Files_Sharing\Controller\ShareAPIController;
use OCA\Files_Sharing\Middleware\OCSShareAPIMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\Share\IManager;

/**
 * @package OCA\Files_Sharing\Middleware\SharingCheckMiddleware
 */
class OCSShareAPIMiddlewareTest extends \Test\TestCase {

	/** @var IManager|\PHPUnit_Framework_MockObject_MockObject */
	private $shareManager;
	/** @var IL10N */
	private $l;
	/** @var OCSShareAPIMiddleware */
	private $middleware;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = $this->createMock(IManager::class);
		$this->l = $this->createMock(IL10N::class);

		$this->l->method('t')->willReturnArgument(0);

		$this->middleware = new OCSShareAPIMiddleware($this->shareManager, $this->l);
	}

	public function dataBeforeController() {
		return [
			[
				$this->createMock(Controller::class),
				false,
				false
			],
			[
				$this->createMock(Controller::class),
				true,
				false
			],
			[
				$this->createMock(OCSController::class),
				false,
				false
			],
			[
				$this->createMock(OCSController::class),
				true,
				false
			],
			[
				$this->createMock(ShareAPIController::class),
				false,
				true
			],
			[
				$this->createMock(ShareAPIController::class),
				true,
				false
			],
		];
	}

	/**
	 * @dataProvider dataBeforeController
	 *
	 * @param Controller $controller
	 * @param bool $enabled
	 * @param bool $exception
	 */
	public function testBeforeController(Controller $controller, $enabled, $exception) {
		$this->shareManager->method('shareApiEnabled')->willReturn($enabled);

		try {
			$this->middleware->beforeController($controller, 'foo');
			$this->assertFalse($exception);
		} catch (OCSNotFoundException $e) {
			$this->assertTrue($exception);
		}
	}

	public function dataAfterController() {
		return [
			[
				$this->createMock(Controller::class),
			],
			[
				$this->createMock(OCSController::class),
			],
			[
				$this->createMock(ShareAPIController::class),
			],
		];
	}

	/**
	 * @dataProvider dataAfterController
	 *
	 * @param Controller $controller
	 * @param bool $called
	 */
	public function testAfterController(Controller $controller) {
		if ($controller instanceof ShareAPIController) {
			$controller->expects($this->once())->method('cleanup');
		}

		$response = $this->getMockBuilder('OCP\AppFramework\Http\Response')
			->disableOriginalConstructor()
			->getMock();
		$this->middleware->afterController($controller, 'foo', $response);
		$this->addToAssertionCount(1);
	}
}
