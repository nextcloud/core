<?php

namespace Test\AppFramework\Routing;

use OC\AppFramework\DependencyInjection\DIContainer;
use OC\AppFramework\Routing\RouteConfig;
use OC\Route\Route;
use OC\Route\Router;
use OCP\ILogger;
use OCP\Route\IRouter;
use PHPUnit\Framework\MockObject\MockObject;

class RoutingTest extends \Test\TestCase {
	public function testSimpleRoute() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'GET']
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'GET', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleRouteWithUnderScoreNames() {
		$routes = ['routes' => [
			['name' => 'admin_folders#open_current', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'root' => '']
		]];

		$this->assertSimpleRoute($routes, 'admin_folders.open_current', 'DELETE', '/folders/{folderId}/open', 'AdminFoldersController', 'openCurrent', [], [], '', true);
	}

	public function testSimpleOCSRoute() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'GET']
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'GET', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleRouteWithMissingVerb() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open']
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'GET', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleOCSRouteWithMissingVerb() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open']
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'GET', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleRouteWithLowercaseVerb() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete']
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleOCSRouteWithLowercaseVerb() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete']
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open');
	}

	public function testSimpleRouteWithRequirements() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'requirements' => ['something']]
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', ['something']);
	}

	public function testSimpleOCSRouteWithRequirements() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'requirements' => ['something']]
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', ['something']);
	}

	public function testSimpleRouteWithDefaults() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', [], 'defaults' => ['param' => 'foobar']]
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', [], ['param' => 'foobar']);
	}


	public function testSimpleOCSRouteWithDefaults() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'defaults' => ['param' => 'foobar']]
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', [], ['param' => 'foobar']);
	}

	public function testSimpleRouteWithPostfix() {
		$routes = ['routes' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'postfix' => '_something']
		]];

		$this->assertSimpleRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', [], [], '_something');
	}

	public function testSimpleOCSRouteWithPostfix() {
		$routes = ['ocs' => [
			['name' => 'folders#open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete', 'postfix' => '_something']
		]
		];

		$this->assertSimpleOCSRoute($routes, 'folders.open', 'DELETE', '/apps/app1/folders/{folderId}/open', 'FoldersController', 'open', [], [], '_something');
	}


	public function testSimpleRouteWithBrokenName() {
		$this->expectException(\UnexpectedValueException::class);

		$routes = ['routes' => [
			['name' => 'folders_open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete']
		]];

		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// load route configuration
		$container = new DIContainer('app1');
		$config = new RouteConfig($container, $router, $routes);

		$config->register();
	}


	public function testSimpleOCSRouteWithBrokenName() {
		$this->expectException(\UnexpectedValueException::class);

		$routes = ['ocs' => [
			['name' => 'folders_open', 'url' => '/folders/{folderId}/open', 'verb' => 'delete']
		]];

		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// load route configuration
		$container = new DIContainer('app1');
		$config = new RouteConfig($container, $router, $routes);

		$config->register();
	}

	public function testSimpleOCSRouteWithUnderScoreNames() {
		$routes = ['ocs' => [
			['name' => 'admin_folders#open_current', 'url' => '/folders/{folderId}/open', 'verb' => 'delete']
		]];

		$this->assertSimpleOCSRoute($routes, 'admin_folders.open_current', 'DELETE', '/apps/app1/folders/{folderId}/open', 'AdminFoldersController', 'openCurrent');
	}

	public function testOCSResource() {
		$routes = ['ocs-resources' => ['account' => ['url' => '/accounts']]];

		$this->assertOCSResource($routes, 'account', '/apps/app1/accounts', 'AccountController', 'id');
	}

	public function testOCSResourceWithUnderScoreName() {
		$routes = ['ocs-resources' => ['admin_accounts' => ['url' => '/admin/accounts']]];

		$this->assertOCSResource($routes, 'admin_accounts', '/apps/app1/admin/accounts', 'AdminAccountsController', 'id');
	}

	public function testOCSResourceWithRoot() {
		$routes = ['ocs-resources' => ['admin_accounts' => ['url' => '/admin/accounts', 'root' => '/core/endpoint']]];

		$this->assertOCSResource($routes, 'admin_accounts', '/core/endpoint/admin/accounts', 'AdminAccountsController', 'id');
	}

	public function testResource() {
		$routes = ['resources' => ['account' => ['url' => '/accounts']]];

		$this->assertResource($routes, 'account', '/apps/app1/accounts', 'AccountController', 'id');
	}

	public function testResourceWithUnderScoreName() {
		$routes = ['resources' => ['admin_accounts' => ['url' => '/admin/accounts']]];

		$this->assertResource($routes, 'admin_accounts', '/apps/app1/admin/accounts', 'AdminAccountsController', 'id');
	}

	private function assertSimpleRoute($routes, $name, $verb, $url, $controllerName, $actionName, array $requirements = [], array $defaults = [], $postfix = '', $allowRootUrl = false): void {
		if ($postfix) {
			$name .= $postfix;
		}

		// route mocks
		$container = new DIContainer('app1');
		$route = $this->mockRoute($container, $verb, $controllerName, $actionName, $requirements, $defaults);

		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// we expect create to be called once:
		$router
			->expects($this->once())
			->method('create')
			->with($this->equalTo('app1.' . $name), $this->equalTo($url))
			->willReturn($route);

		// load route configuration
		$config = new RouteConfig($container, $router, $routes);
		if ($allowRootUrl) {
			self::invokePrivate($config, 'rootUrlApps', [['app1']]);
		}

		$config->register();
	}

	/**
	 * @param $routes
	 * @param string $name
	 * @param string $verb
	 * @param string $url
	 * @param string $controllerName
	 * @param string $actionName
	 * @param array $requirements
	 * @param array $defaults
	 * @param string $postfix
	 */
	private function assertSimpleOCSRoute($routes,
										  $name,
										  $verb,
										  $url,
										  $controllerName,
										  $actionName,
										  array $requirements = [],
										  array $defaults = [],
										  $postfix = '') {
		if ($postfix) {
			$name .= $postfix;
		}

		// route mocks
		$container = new DIContainer('app1');
		$route = $this->mockRoute($container, $verb, $controllerName, $actionName, $requirements, $defaults);

		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// we expect create to be called once:
		$router
			->expects($this->once())
			->method('create')
			->with($this->equalTo('ocs.app1.' . $name), $this->equalTo($url))
			->willReturn($route);

		// load route configuration
		$config = new RouteConfig($container, $router, $routes);

		$config->register();
	}

	/**
	 * @param array $yaml
	 * @param string $resourceName
	 * @param string $url
	 * @param string $controllerName
	 * @param string $paramName
	 */
	private function assertOCSResource($yaml, $resourceName, $url, $controllerName, $paramName): void {
		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// route mocks
		$container = new DIContainer('app1');
		$indexRoute = $this->mockRoute($container, 'GET', $controllerName, 'index');
		$showRoute = $this->mockRoute($container, 'GET', $controllerName, 'show');
		$createRoute = $this->mockRoute($container, 'POST', $controllerName, 'create');
		$updateRoute = $this->mockRoute($container, 'PUT', $controllerName, 'update');
		$destroyRoute = $this->mockRoute($container, 'DELETE', $controllerName, 'destroy');

		$urlWithParam = $url . '/{' . $paramName . '}';

		// we expect create to be called once:
		$router
			->expects($this->at(0))
			->method('create')
			->with($this->equalTo('ocs.app1.' . $resourceName . '.index'), $this->equalTo($url))
			->willReturn($indexRoute);

		$router
			->expects($this->at(1))
			->method('create')
			->with($this->equalTo('ocs.app1.' . $resourceName . '.show'), $this->equalTo($urlWithParam))
			->willReturn($showRoute);

		$router
			->expects($this->at(2))
			->method('create')
			->with($this->equalTo('ocs.app1.' . $resourceName . '.create'), $this->equalTo($url))
			->willReturn($createRoute);

		$router
			->expects($this->at(3))
			->method('create')
			->with($this->equalTo('ocs.app1.' . $resourceName . '.update'), $this->equalTo($urlWithParam))
			->willReturn($updateRoute);

		$router
			->expects($this->at(4))
			->method('create')
			->with($this->equalTo('ocs.app1.' . $resourceName . '.destroy'), $this->equalTo($urlWithParam))
			->willReturn($destroyRoute);

		// load route configuration
		$config = new RouteConfig($container, $router, $yaml);

		$config->register();
	}

	/**
	 * @param string $resourceName
	 * @param string $url
	 * @param string $controllerName
	 * @param string $paramName
	 */
	private function assertResource($yaml, $resourceName, $url, $controllerName, $paramName) {
		/** @var IRouter|MockObject $router */
		$router = $this->getMockBuilder(Router::class)
			->onlyMethods(['create'])
			->setConstructorArgs([$this->createMock(ILogger::class)])
			->getMock();

		// route mocks
		$container = new DIContainer('app1');
		$indexRoute = $this->mockRoute($container, 'GET', $controllerName, 'index');
		$showRoute = $this->mockRoute($container, 'GET', $controllerName, 'show');
		$createRoute = $this->mockRoute($container, 'POST', $controllerName, 'create');
		$updateRoute = $this->mockRoute($container, 'PUT', $controllerName, 'update');
		$destroyRoute = $this->mockRoute($container, 'DELETE', $controllerName, 'destroy');

		$urlWithParam = $url . '/{' . $paramName . '}';

		// we expect create to be called once:
		$router
			->expects($this->at(0))
			->method('create')
			->with($this->equalTo('app1.' . $resourceName . '.index'), $this->equalTo($url))
			->willReturn($indexRoute);

		$router
			->expects($this->at(1))
			->method('create')
			->with($this->equalTo('app1.' . $resourceName . '.show'), $this->equalTo($urlWithParam))
			->willReturn($showRoute);

		$router
			->expects($this->at(2))
			->method('create')
			->with($this->equalTo('app1.' . $resourceName . '.create'), $this->equalTo($url))
			->willReturn($createRoute);

		$router
			->expects($this->at(3))
			->method('create')
			->with($this->equalTo('app1.' . $resourceName . '.update'), $this->equalTo($urlWithParam))
			->willReturn($updateRoute);

		$router
			->expects($this->at(4))
			->method('create')
			->with($this->equalTo('app1.' . $resourceName . '.destroy'), $this->equalTo($urlWithParam))
			->willReturn($destroyRoute);

		// load route configuration
		$config = new RouteConfig($container, $router, $yaml);

		$config->register();
	}

	/**
	 * @param DIContainer $container
	 * @param string $verb
	 * @param string $controllerName
	 * @param string $actionName
	 * @param array $requirements
	 * @param array $defaults
	 * @return MockObject
	 */
	private function mockRoute(
		DIContainer $container,
		$verb,
		$controllerName,
		$actionName,
		array $requirements = [],
		array $defaults = []
	) {
		$route = $this->getMockBuilder(Route::class)
			->onlyMethods(['method', 'requirements', 'defaults'])
			->disableOriginalConstructor()
			->getMock();
		$route
			->expects($this->once())
			->method('method')
			->with($this->equalTo($verb))
			->willReturn($route);

		if (count($requirements) > 0) {
			$route
				->expects($this->once())
				->method('requirements')
				->with($this->equalTo($requirements))
				->willReturn($route);
		}

		$route->expects($this->once())
			->method('defaults')
			->with($this->callback(function (array $def) use ($defaults, $controllerName, $actionName) {
				$defaults['caller'] = ['app1', $controllerName, $actionName];

				$this->assertEquals($defaults, $def);
				return true;
			}))
			->willReturn($route);

		return $route;
	}
}
