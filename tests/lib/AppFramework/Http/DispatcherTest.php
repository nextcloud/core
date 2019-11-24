<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace Test\AppFramework\Http;

use OC\AppFramework\Http\Dispatcher;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Middleware\MiddlewareDispatcher;
use OC\AppFramework\Utility\ControllerMethodReflector;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Controller;
use OCP\IConfig;


class TestController extends Controller {
	/**
	 * @param string $appName
	 * @param \OCP\IRequest $request
	 */
	public function __construct($appName, $request) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param int $int
	 * @param bool $bool
	 * @param int $test
	 * @param int $test2
	 * @return array
	 */
	public function exec($int, $bool, $test=4, $test2=1) {
		$this->registerResponder('text', function($in) {
			return new JSONResponse(array('text' => $in));
		});
		return array($int, $bool, $test, $test2);
	}


	/**
	 * @param int $int
	 * @param bool $bool
	 * @param int $test
	 * @param int $test2
	 * @return DataResponse
	 */
	public function execDataResponse($int, $bool, $test=4, $test2=1) {
		return new DataResponse(array(
			'text' => array($int, $bool, $test, $test2)
		));
	}

}


class DispatcherTest extends \Test\TestCase {
	/** @var MiddlewareDispatcher */
	private $middlewareDispatcher;
	/** @var Dispatcher */
	private $dispatcher;
	private $controllerMethod;
	private $response;
	private $request;
	private $lastModified;
	private $etag;
	private $http;
	private $reflector;

	protected function setUp() {
		parent::setUp();
		$this->controllerMethod = 'test';

		$app = $this->getMockBuilder(
			'OC\AppFramework\DependencyInjection\DIContainer')
			->disableOriginalConstructor()
			->getMock();
		$request = $this->getMockBuilder(
			'\OC\AppFramework\Http\Request')
			->disableOriginalConstructor()
			->getMock();
		$this->http = $this->getMockBuilder(
			'\OC\AppFramework\Http')
			->disableOriginalConstructor()
			->getMock();

		$this->middlewareDispatcher = $this->getMockBuilder(
			'\OC\AppFramework\Middleware\MiddlewareDispatcher')
			->disableOriginalConstructor()
			->getMock();
		$this->controller = $this->getMockBuilder(
			'\OCP\AppFramework\Controller')
			->setMethods([$this->controllerMethod])
			->setConstructorArgs([$app, $request])
			->getMock();

		$this->request = $this->getMockBuilder(
			'\OC\AppFramework\Http\Request')
			->disableOriginalConstructor()
			->getMock();

		$this->reflector = new ControllerMethodReflector();

		$this->dispatcher = new Dispatcher(
			$this->http,
			$this->middlewareDispatcher,
			$this->reflector
		);

		$this->response = $this->createMock(Response::class);

		$this->lastModified = new \DateTime(null, new \DateTimeZone('GMT'));
		$this->etag = 'hi';
	}


	/**
	 * @param string $out
	 * @param string $httpHeaders
	 */
	private function setMiddlewareExpectations($out=null,
		$httpHeaders=null, $responseHeaders=array(),
		$ex=false, $catchEx=true) {

		if($ex) {
			$exception = new \Exception();
			$this->middlewareDispatcher->expects($this->once())
				->method('beforeController')
				->with($this->equalTo($this->controller),
					$this->equalTo($this->controllerMethod))
				->will($this->throwException($exception));
			if($catchEx) {
				$this->middlewareDispatcher->expects($this->once())
					->method('afterException')
					->with($this->equalTo($this->controller),
						$this->equalTo($this->controllerMethod),
						$this->equalTo($exception))
					->will($this->returnValue($this->response));
			} else {
				$this->middlewareDispatcher->expects($this->once())
					->method('afterException')
					->with($this->equalTo($this->controller),
						$this->equalTo($this->controllerMethod),
						$this->equalTo($exception))
					->willThrowException($exception);
				return;
			}
		} else {
			$this->middlewareDispatcher->expects($this->once())
				->method('beforeController')
				->with($this->equalTo($this->controller),
					$this->equalTo($this->controllerMethod));
			$this->controller->expects($this->once())
				->method($this->controllerMethod)
				->will($this->returnValue($this->response));
		}

		$this->response->expects($this->once())
			->method('render')
			->will($this->returnValue($out));
		$this->response->expects($this->once())
			->method('getStatus')
			->will($this->returnValue(Http::STATUS_OK));
		$this->response->expects($this->once())
			->method('getLastModified')
			->will($this->returnValue($this->lastModified));
		$this->response->expects($this->once())
			->method('getETag')
			->will($this->returnValue($this->etag));
		$this->response->expects($this->once())
			->method('getHeaders')
			->will($this->returnValue($responseHeaders));
		$this->http->expects($this->once())
			->method('getStatusHeader')
			->with($this->equalTo(Http::STATUS_OK),
				$this->equalTo($this->lastModified),
				$this->equalTo($this->etag))
			->will($this->returnValue($httpHeaders));

		$this->middlewareDispatcher->expects($this->once())
			->method('afterController')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod),
				$this->equalTo($this->response))
			->will($this->returnValue($this->response));

		$this->middlewareDispatcher->expects($this->once())
			->method('afterController')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod),
				$this->equalTo($this->response))
			->will($this->returnValue($this->response));

		$this->middlewareDispatcher->expects($this->once())
			->method('beforeOutput')
			->with($this->equalTo($this->controller),
				$this->equalTo($this->controllerMethod),
				$this->equalTo($out))
			->will($this->returnValue($out));
	}


	public function testDispatcherReturnsArrayWith2Entries() {
		$this->setMiddlewareExpectations('');

		$response = $this->dispatcher->dispatch($this->controller, $this->controllerMethod, $this->request);
		$this->assertNull($response[0]);
		$this->assertEquals(array(), $response[1]);
		$this->assertNull($response[2]);
	}


	public function testHeadersAndOutputAreReturned(){
		$out = 'yo';
		$httpHeaders = 'Http';
		$responseHeaders = array('hell' => 'yeah');
		$this->setMiddlewareExpectations($out, $httpHeaders, $responseHeaders);

		$response = $this->dispatcher->dispatch($this->controller,
			$this->controllerMethod, $this->request);

		$this->assertEquals($httpHeaders, $response[0]);
		$this->assertEquals($responseHeaders, $response[1]);
		$this->assertEquals($out, $response[3]);
	}


	public function testExceptionCallsAfterException() {
		$out = 'yo';
		$httpHeaders = 'Http';
		$responseHeaders = array('hell' => 'yeah');
		$this->setMiddlewareExpectations($out, $httpHeaders, $responseHeaders, true);

		$response = $this->dispatcher->dispatch($this->controller,
			$this->controllerMethod, $this->request);

		$this->assertEquals($httpHeaders, $response[0]);
		$this->assertEquals($responseHeaders, $response[1]);
		$this->assertEquals($out, $response[3]);
	}


	public function testExceptionThrowsIfCanNotBeHandledByAfterException() {
		$out = 'yo';
		$httpHeaders = 'Http';
		$responseHeaders = ['hell' => 'yeah'];
		$this->setMiddlewareExpectations($out, $httpHeaders, $responseHeaders, true, false);

		$this->expectException(\Exception::class);
		$this->dispatcher->dispatch(
			$this->controller,
			$this->controllerMethod,
			$this->request
		);

	}


	private function dispatcherPassthrough() {
		$this->middlewareDispatcher->expects($this->once())
				->method('beforeController');
		$this->middlewareDispatcher->expects($this->once())
			->method('afterController')
			->will($this->returnCallback(function($a, $b, $in) {
				return $in;
			}));
		$this->middlewareDispatcher->expects($this->once())
			->method('beforeOutput')
			->will($this->returnCallback(function($a, $b, $in) {
				return $in;
			}));
	}


	public function testControllerParametersInjected() {
		$this->request = new Request(
			[
				'post' => [
				'int' => '3',
				'bool' => 'false'
				],
				'method' => 'POST'
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'exec', $this->request);

		$this->assertEquals('[3,true,4,1]', $response[3]);
	}


	public function testControllerParametersInjectedDefaultOverwritten() {
		$this->request = new Request(
			[
				'post' => [
					'int' => '3',
					'bool' => 'false',
					'test2' => 7
				],
				'method' => 'POST',
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'exec', $this->request);

		$this->assertEquals('[3,true,4,7]', $response[3]);
	}



	public function testResponseTransformedByUrlFormat() {
		$this->request = new Request(
			[
				'post' => [
					'int' => '3',
					'bool' => 'false'
				],
				'urlParams' => [
					'format' => 'text'
				],
				'method' => 'GET'
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'exec', $this->request);

		$this->assertEquals('{"text":[3,false,4,1]}', $response[3]);
	}


	public function testResponseTransformsDataResponse() {
		$this->request = new Request(
			[
				'post' => [
					'int' => '3',
					'bool' => 'false'
				],
				'urlParams' => [
					'format' => 'json'
				],
				'method' => 'GET'
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'execDataResponse', $this->request);

		$this->assertEquals('{"text":[3,false,4,1]}', $response[3]);
	}


	public function testResponseTransformedByAcceptHeader() {
		$this->request = new Request(
			[
				'post' => [
					'int' => '3',
					'bool' => 'false'
				],
				'server' => [
					'HTTP_ACCEPT' => 'application/text, test',
					'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded'
				],
				'method' => 'PUT'
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'exec', $this->request);

		$this->assertEquals('{"text":[3,false,4,1]}', $response[3]);
	}


	public function testResponsePrimarilyTransformedByParameterFormat() {
		$this->request = new Request(
			[
				'post' => [
					'int' => '3',
					'bool' => 'false'
				],
				'get' => [
					'format' => 'text'
				],
				'server' => [
					'HTTP_ACCEPT' => 'application/json, test'
				],
				'method' => 'POST'
			],
			$this->getMockBuilder('\OCP\Security\ISecureRandom')
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$this->dispatcher = new Dispatcher(
			$this->http, $this->middlewareDispatcher, $this->reflector
		);
		$controller = new TestController('app', $this->request);

		// reflector is supposed to be called once
		$this->dispatcherPassthrough();
		$response = $this->dispatcher->dispatch($controller, 'exec', $this->request);

		$this->assertEquals('{"text":[3,true,4,1]}', $response[3]);
	}




}
