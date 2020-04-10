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

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http\OCSResponse;

class OCSResponseTest extends \Test\TestCase {


	public function testHeadersJSON() {
		$response = new OCSResponse('json', 1, 2, 3);
		$type = $response->getHeaders()['Content-Type'];
		$this->assertEquals('application/json; charset=utf-8', $type);
	}


	public function testHeadersXML() {
		$response = new OCSResponse('xml', 1, 2, 3);
		$type = $response->getHeaders()['Content-Type'];
		$this->assertEquals('application/xml; charset=utf-8', $type);
	}


	public function testRender() {
		$response = new OCSResponse(
			'xml', 2, 'message', ['test' => 'hi'], 3, 4
		);
		$out = $response->render();
		$expected = "<?xml version=\"1.0\"?>\n" .
		"<ocs>\n" .
		" <meta>\n" .
		"  <status>failure</status>\n" .
		"  <statuscode>2</statuscode>\n" .
		"  <message>message</message>\n" .
		"  <totalitems>3</totalitems>\n" .
		"  <itemsperpage>4</itemsperpage>\n" .
		" </meta>\n" .
		" <data>\n" .
		"  <test>hi</test>\n" .
		" </data>\n" .
		"</ocs>\n";

		$this->assertEquals($expected, $out);

	}


}
