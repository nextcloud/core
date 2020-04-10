<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\unit\DAV;

use OCA\DAV\Files\BrowserErrorPagePlugin;
use PHPUnit_Framework_MockObject_MockObject;
use Sabre\DAV\Exception\NotFound;
use Sabre\HTTP\Response;

class BrowserErrorPagePluginTest extends \Test\TestCase {

	/**
	 * @dataProvider providesExceptions
	 * @param $expectedCode
	 * @param $exception
	 */
	public function test($expectedCode, $exception) {
		/** @var BrowserErrorPagePlugin | PHPUnit_Framework_MockObject_MockObject $plugin */
		$plugin = $this->getMockBuilder(BrowserErrorPagePlugin::class)->setMethods(['sendResponse', 'generateBody'])->getMock();
		$plugin->expects($this->once())->method('generateBody')->willReturn(':boom:');
		$plugin->expects($this->once())->method('sendResponse');
		/** @var \Sabre\DAV\Server | PHPUnit_Framework_MockObject_MockObject $server */
		$server = $this->getMockBuilder('Sabre\DAV\Server')->disableOriginalConstructor()->getMock();
		$server->expects($this->once())->method('on');
		$httpResponse = $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock();
		$httpResponse->expects($this->once())->method('addHeaders');
		$httpResponse->expects($this->once())->method('setStatus')->with($expectedCode);
		$httpResponse->expects($this->once())->method('setBody')->with(':boom:');
		$server->httpResponse = $httpResponse;
		$plugin->initialize($server);
		$plugin->logException($exception);
	}

	public function providesExceptions() {
		return [
			[ 404, new NotFound()],
			[ 500, new \RuntimeException()],
		];
	}
}
