<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace Tests\Core\Controller;

use OC\Core\Controller\NavigationController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use Test\TestCase;

class NavigationControllerTest extends TestCase {

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var INavigationManager|\PHPUnit_Framework_MockObject_MockObject */
	private $navigationManager;

	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;

	/** @var NavigationController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->navigationManager = $this->createMock(INavigationManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$this->controller = new NavigationController(
			'core',
			$this->request,
			$this->navigationManager,
			$this->urlGenerator
		);
	}

	public function dataGetNavigation() {
		return [
			[false], [true]
		];
	}
	/** @dataProvider dataGetNavigation */
	public function testGetAppNavigation($absolute) {
		$this->navigationManager->expects($this->once())
			->method('getAll')
			->with('link')
			->willReturn(['files' => ['id' => 'files', 'href' => '/index.php/apps/files', 'icon' => 'icon' ] ]);
		if ($absolute) {
			$this->urlGenerator->expects($this->any())
				->method('getBaseURL')
				->willReturn('http://localhost/');
			$this->urlGenerator->expects($this->at(1))
				->method('getAbsoluteURL')
				->with('/index.php/apps/files')
				->willReturn('http://localhost/index.php/apps/files');
			$this->urlGenerator->expects($this->at(3))
				->method('getAbsoluteURL')
				->with('icon')
				->willReturn('http://localhost/icon');
			$actual = $this->controller->getAppsNavigation($absolute);
			$this->assertInstanceOf(DataResponse::class, $actual);
			$this->assertEquals('http://localhost/index.php/apps/files', $actual->getData()[0]['href']);
			$this->assertEquals('http://localhost/icon', $actual->getData()[0]['icon']);


		} else {
			$actual = $this->controller->getAppsNavigation($absolute);
			$this->assertInstanceOf(DataResponse::class, $actual);
			$this->assertEquals('/index.php/apps/files', $actual->getData()[0]['href']);
			$this->assertEquals('icon', $actual->getData()[0]['icon']);

		}
	}

	/** @dataProvider dataGetNavigation */
	public function testGetSettingsNavigation($absolute) {
		$this->navigationManager->expects($this->once())
			->method('getAll')
			->with('settings')
			->willReturn(['settings' => ['id' => 'settings', 'href' => '/index.php/settings/user', 'icon' => '/core/img/settings.svg'] ]);
		if ($absolute) {
			$this->urlGenerator->expects($this->any())
				->method('getBaseURL')
				->willReturn('http://localhost/');
			$this->urlGenerator->expects($this->at(1))
				->method('getAbsoluteURL')
				->with('/index.php/settings/user')
				->willReturn('http://localhost/index.php/settings/user');
			$this->urlGenerator->expects($this->at(3))
				->method('getAbsoluteURL')
				->with('/core/img/settings.svg')
				->willReturn('http://localhost/core/img/settings.svg');
			$actual = $this->controller->getSettingsNavigation($absolute);
			$this->assertInstanceOf(DataResponse::class, $actual);
			$this->assertEquals('http://localhost/index.php/settings/user', $actual->getData()[0]['href']);
			$this->assertEquals('http://localhost/core/img/settings.svg', $actual->getData()[0]['icon']);
		} else {
			$actual = $this->controller->getSettingsNavigation($absolute);
			$this->assertInstanceOf(DataResponse::class, $actual);
			$this->assertEquals('/index.php/settings/user', $actual->getData()[0]['href']);
			$this->assertEquals('/core/img/settings.svg', $actual->getData()[0]['icon']);
		}
	}

	public function testGetAppNavigationEtagMatch() {
		$navigation = [ ['id' => 'files', 'href' => '/index.php/apps/files', 'icon' => 'icon' ] ];
		$this->request->expects($this->once())
			->method('getHeader')
			->with('If-None-Match')
			->willReturn(md5(json_encode($navigation)));
		$this->navigationManager->expects($this->once())
			->method('getAll')
			->with('link')
			->willReturn($navigation);
			$actual = $this->controller->getAppsNavigation();
			$this->assertInstanceOf(DataResponse::class, $actual);
			$this->assertEquals(Http::STATUS_NOT_MODIFIED, $actual->getStatus());
	}

	public function testGetSettingsNavigationEtagMatch() {
		$navigation = [ ['id' => 'logout', 'href' => '/index.php/apps/files', 'icon' => 'icon' ] ];
		$this->request->expects($this->once())
			->method('getHeader')
			->with('If-None-Match')
			->willReturn(md5(json_encode([ ['id' => 'logout', 'href' => 'logout', 'icon' => 'icon' ] ])));
		$this->navigationManager->expects($this->once())
			->method('getAll')
			->with('settings')
			->willReturn($navigation);
		$actual = $this->controller->getSettingsNavigation();
		$this->assertInstanceOf(DataResponse::class, $actual);
		$this->assertEquals(Http::STATUS_NOT_MODIFIED, $actual->getStatus());
	}

}
