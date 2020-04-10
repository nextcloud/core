<?php
/**
 * @copyright 2014 Lukas Reschke lukas@owncloud.com
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Settings\Tests\Middleware;

use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCA\Settings\Middleware\SubadminMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;

/**
 * Verifies whether an user has at least subadmin rights.
 * To bypass use the `@NoSubadminRequired` annotation
 *
 * @package Tests\Settings\Middleware
 */
class SubadminMiddlewareTest extends \Test\TestCase {
	/** @var SubadminMiddleware */
	private $subadminMiddlewareAsSubAdmin;
	/** @var SubadminMiddleware */
	private $subadminMiddleware;
	/** @var ControllerMethodReflector */
	private $reflector;
	/** @var Controller */
	private $controller;
	/** @var IL10N */
	private $l10n;

	protected function setUp(): void {
		parent::setUp();
		$this->reflector = $this->getMockBuilder(ControllerMethodReflector::class)
			->disableOriginalConstructor()->getMock();
		$this->controller = $this->getMockBuilder(Controller::class)
			->disableOriginalConstructor()->getMock();
		$this->l10n = $this->createMock(IL10N::class);

		$this->subadminMiddlewareAsSubAdmin = new SubadminMiddleware($this->reflector, true, $this->l10n);
		$this->subadminMiddleware = new SubadminMiddleware($this->reflector, false, $this->l10n);
	}

	
	public function testBeforeControllerAsUserWithExemption() {
		$this->expectException(\OC\AppFramework\Middleware\Security\Exceptions\NotAdminException::class);

		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('NoSubadminRequired')
			->willReturn(false);
		$this->subadminMiddleware->beforeController($this->controller, 'foo');
	}


	public function testBeforeControllerAsUserWithoutExemption() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('NoSubadminRequired')
			->willReturn(true);
		$this->subadminMiddleware->beforeController($this->controller, 'foo');
	}

	public function testBeforeControllerAsSubAdminWithoutExemption() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('NoSubadminRequired')
			->willReturn(false);
		$this->subadminMiddlewareAsSubAdmin->beforeController($this->controller, 'foo');
	}

	public function testBeforeControllerAsSubAdminWithExemption() {
		$this->reflector
			->expects($this->once())
			->method('hasAnnotation')
			->with('NoSubadminRequired')
			->willReturn(true);
		$this->subadminMiddlewareAsSubAdmin->beforeController($this->controller, 'foo');
	}

	public function testAfterNotAdminException() {
		$expectedResponse = new TemplateResponse('core', '403', [], 'guest');
		$expectedResponse->setStatus(403);
		$this->assertEquals($expectedResponse, $this->subadminMiddleware->afterException($this->controller, 'foo', new NotAdminException('')));
	}

	
	public function testAfterRegularException() {
		$this->expectException(\Exception::class);

		$expectedResponse = new TemplateResponse('core', '403', [], 'guest');
		$expectedResponse->setStatus(403);
		$this->subadminMiddleware->afterException($this->controller, 'foo', new \Exception());
	}
}
