<?php
/**
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Settings\Tests\AppInfo;

use OC\User\Session;
use OCA\Settings\AppInfo\Application;
use OCA\Settings\Controller\AdminSettingsController;
use OCA\Settings\Controller\AppSettingsController;
use OCA\Settings\Controller\AuthSettingsController;
use OCA\Settings\Controller\CertificateController;
use OCA\Settings\Controller\CheckSetupController;
use OCA\Settings\Controller\LogSettingsController;
use OCA\Settings\Controller\MailSettingsController;
use OCA\Settings\Controller\UsersController;
use OCA\Settings\Middleware\SubadminMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Middleware;
use OCP\IUser;
use OCP\IUserSession;
use Test\TestCase;

/**
 * Class ApplicationTest
 *
 * @package Tests\Settings
 * @group DB
 */
class ApplicationTest extends TestCase {
	/** @var Application */
	protected $app;

	/** @var IAppContainer */
	protected $container;

	protected function setUp(): void {
		parent::setUp();
		$this->app = new Application();
		$this->container = $this->app->getContainer();
	}

	public function testContainerAppName() {
		$this->app = new Application();
		$this->assertEquals('settings', $this->container->getAppName());
	}

	public function dataContainerQuery() {
		return [
			[AdminSettingsController::class, Controller::class],
			[AppSettingsController::class, Controller::class],
			[AuthSettingsController::class, Controller::class],
			// Needs session: [CertificateController::class, Controller::class],
			[CheckSetupController::class, Controller::class],
			[LogSettingsController::class, Controller::class],
			[MailSettingsController::class, Controller::class],
			[UsersController::class, Controller::class],

			[SubadminMiddleware::class, Middleware::class],
		];
	}

	/**
	 * @dataProvider dataContainerQuery
	 * @param string $service
	 * @param string $expected
	 */
	public function testContainerQuery($service, $expected) {
		$this->assertTrue($this->container->query($service) instanceof $expected);
	}

	public function dataContainerQueryRequiresSession() {
		return [
			[CertificateController::class, Controller::class],
		];
	}

	/**
	 * @dataProvider dataContainerQueryRequiresSession
	 * @param string $service
	 * @param string $expected
	 */
	public function testContainerQueryRequiresSession($service, $expected) {
		$user = $this->createMock(IUser::class);
		$user->expects($this->once())
			->method('getUID')
			->willReturn('test');

		$session = $this->createMock(IUserSession::class);
		$session->expects($this->once())
			->method('getUser')
			->willReturn($user);

		$this->overwriteService(Session::class, $session);
		$this->assertTrue($this->container->query($service) instanceof $expected);
		$this->restoreService(Session::class);
	}
}
