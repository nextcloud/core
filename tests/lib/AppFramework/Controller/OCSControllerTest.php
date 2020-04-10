<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2015 Bernhard Posselt <dev@bernhard-posselt.com>
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

namespace Test\AppFramework\Controller;

use OC\AppFramework\Http\Request;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\Security\ISecureRandom;

class ChildOCSController extends OCSController {}


class OCSControllerTest extends \Test\TestCase {
	public function testCors() {
		$request = new Request(
			[
				'server' => [
					'HTTP_ORIGIN' => 'test',
				],
			],
			$this->getMockBuilder(ISecureRandom::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		);
		$controller = new ChildOCSController('app', $request, 'verbs',
			'headers', 100);

		$response = $controller->preflightedCors();

		$headers = $response->getHeaders();

		$this->assertEquals('test', $headers['Access-Control-Allow-Origin']);
		$this->assertEquals('verbs', $headers['Access-Control-Allow-Methods']);
		$this->assertEquals('headers', $headers['Access-Control-Allow-Headers']);
		$this->assertEquals('false', $headers['Access-Control-Allow-Credentials']);
		$this->assertEquals(100, $headers['Access-Control-Max-Age']);
	}


	public function testXML() {
		$controller = new ChildOCSController('app', new Request(
			[],
			$this->getMockBuilder(ISecureRandom::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		));
		$controller->setOCSVersion(1);

		$expected = "<?xml version=\"1.0\"?>\n" .
		"<ocs>\n" .
		" <meta>\n" .
		"  <status>ok</status>\n" .
		"  <statuscode>100</statuscode>\n" .
		"  <message>OK</message>\n" .
		"  <totalitems></totalitems>\n" .
		"  <itemsperpage></itemsperpage>\n" .
		" </meta>\n" .
		" <data>\n" .
		"  <test>hi</test>\n" .
		" </data>\n" .
		"</ocs>\n";

		$params = new DataResponse(['test' => 'hi']);

		$response = $controller->buildResponse($params, 'xml');
		$this->assertSame(EmptyContentSecurityPolicy::class, get_class($response->getContentSecurityPolicy()));
		$this->assertEquals($expected, $response->render());
	}

	public function testJSON() {
		$controller = new ChildOCSController('app', new Request(
			[],
			$this->getMockBuilder(ISecureRandom::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		));
		$controller->setOCSVersion(1);
		$expected = '{"ocs":{"meta":{"status":"ok","statuscode":100,"message":"OK",' .
					'"totalitems":"","itemsperpage":""},"data":{"test":"hi"}}}';
		$params = new DataResponse(['test' => 'hi']);

		$response = $controller->buildResponse($params, 'json');
		$this->assertSame(EmptyContentSecurityPolicy::class, get_class($response->getContentSecurityPolicy()));
		$this->assertEquals($expected, $response->render());
		$this->assertEquals($expected, $response->render());
	}

	public function testXMLV2() {
		$controller = new ChildOCSController('app', new Request(
			[],
			$this->getMockBuilder(ISecureRandom::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		));
		$controller->setOCSVersion(2);

		$expected = "<?xml version=\"1.0\"?>\n" .
			"<ocs>\n" .
			" <meta>\n" .
			"  <status>ok</status>\n" .
			"  <statuscode>200</statuscode>\n" .
			"  <message>OK</message>\n" .
			" </meta>\n" .
			" <data>\n" .
			"  <test>hi</test>\n" .
			" </data>\n" .
			"</ocs>\n";

		$params = new DataResponse(['test' => 'hi']);

		$response = $controller->buildResponse($params, 'xml');
		$this->assertSame(EmptyContentSecurityPolicy::class, get_class($response->getContentSecurityPolicy()));
		$this->assertEquals($expected, $response->render());
	}

	public function testJSONV2() {
		$controller = new ChildOCSController('app', new Request(
			[],
			$this->getMockBuilder(ISecureRandom::class)
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder(IConfig::class)
				->disableOriginalConstructor()
				->getMock()
		));
		$controller->setOCSVersion(2);
		$expected = '{"ocs":{"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":{"test":"hi"}}}';
		$params = new DataResponse(['test' => 'hi']);

		$response = $controller->buildResponse($params, 'json');
		$this->assertSame(EmptyContentSecurityPolicy::class, get_class($response->getContentSecurityPolicy()));
		$this->assertEquals($expected, $response->render());
	}
}
