<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre\Exception;

use OCA\DAV\Connector\Sabre\Exception\Forbidden;

class ForbiddenTest extends \Test\TestCase {

	public function testSerialization() {

		// create xml doc
		$DOM = new \DOMDocument('1.0','utf-8');
		$DOM->formatOutput = true;
		$error = $DOM->createElementNS('DAV:','d:error');
		$error->setAttribute('xmlns:s', \Sabre\DAV\Server::NS_SABREDAV);
		$DOM->appendChild($error);

		// serialize the exception
		$message = "1234567890";
		$retry = false;
		$expectedXml = <<<EOD
<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns" xmlns:o="http://owncloud.org/ns">
  <o:retry xmlns:o="o:">false</o:retry>
  <o:reason xmlns:o="o:">1234567890</o:reason>
</d:error>

EOD;

		$ex = new Forbidden($message, $retry);
		$server = $this->getMockBuilder('Sabre\DAV\Server')
			->disableOriginalConstructor()
			->getMock();
		$ex->serialize($server, $error);

		// assert
		$xml = $DOM->saveXML();
		$this->assertEquals($expectedXml, $xml);
	}
}
