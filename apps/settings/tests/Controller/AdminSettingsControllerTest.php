<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Settings\Tests\Controller;

use OCA\Settings\Controller\AdminSettingsController;
use OCA\Settings\Settings\Personal\ServerDevNotice;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Settings\IManager;
use OCP\Support\Subscription\IRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class AdminSettingsControllerTest
 *
 * @group DB
 *
 * @package Tests\Settings\Controller
 */
class AdminSettingsControllerTest extends TestCase {

	/** @var AdminSettingsController */
	private $adminSettingsController;
	/** @var IRequest|MockObject */
	private $request;
	/** @var INavigationManager|MockObject */
	private $navigationManager;
	/** @var IManager|MockObject */
	private $settingsManager;
	/** @var IUserSession|MockObject */
	private $userSession;
	/** @var IGroupManager|MockObject */
	private $groupManager;
	/** @var ISubAdmin|MockObject */
	private $subAdmin;
	/** @var string */
	private $adminUid = 'lololo';

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->navigationManager = $this->createMock(INavigationManager::class);
		$this->settingsManager = $this->createMock(IManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->subAdmin = $this->createMock(ISubAdmin::class);

		$this->adminSettingsController = new AdminSettingsController(
			'settings',
			$this->request,
			$this->navigationManager,
			$this->settingsManager,
			$this->userSession,
			$this->groupManager,
			$this->subAdmin
		);

		$user = \OC::$server->getUserManager()->createUser($this->adminUid, 'olo');
		\OC_User::setUserId($user->getUID());
		\OC::$server->getGroupManager()->createGroup('admin')->addUser($user);
	}

	protected function tearDown(): void {
		\OC::$server->getUserManager()->get($this->adminUid)->delete();

		parent::tearDown();
	}

	public function testIndex() {
		$user = $this->createMock(IUser::class);
		$registry = $this->createMock(IRegistry::class);
		$this->userSession
			->method('getUser')
			->willReturn($user);
		$user->method('getUID')->willReturn('user123');
		$this->groupManager
			->method('isAdmin')
			->with('user123')
			->willReturn(true);
		$this->subAdmin
			->method('isSubAdmin')
			->with($user)
			->willReturn(false);
		$this->settingsManager
			->expects($this->once())
			->method('getAdminSections')
			->willReturn([]);
		$this->settingsManager
			->expects($this->once())
			->method('getPersonalSections')
			->willReturn([]);
		$this->settingsManager
			->expects($this->once())
			->method('getAdminSettings')
			->with('test')
			->willReturn([5 => new ServerDevNotice($registry)]);

		$idx = $this->adminSettingsController->index('test');

		$expected = new TemplateResponse('settings', 'settings/frame', ['forms' => ['personal' => [], 'admin' => []], 'content' => '']);
		$this->assertEquals($expected, $idx);
	}
}
