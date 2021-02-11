<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Tests\unit\DAV\Sharing;

use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\DAV\Sharing\IShareable;
use OCA\DAV\DAV\Sharing\Plugin;
use OCP\IConfig;
use OCP\IRequest;
use Sabre\DAV\Server;
use Sabre\DAV\SimpleCollection;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Test\TestCase;

class PluginTest extends TestCase {

	/** @var Plugin */
	private $plugin;
	/** @var Server */
	private $server;
	/** @var IShareable | \PHPUnit\Framework\MockObject\MockObject */
	private $book;

	protected function setUp(): void {
		parent::setUp();

		/** @var Auth | \PHPUnit\Framework\MockObject\MockObject $authBackend */
		$authBackend = $this->getMockBuilder(Auth::class)->disableOriginalConstructor()->getMock();
		$authBackend->method('isDavAuthenticated')->willReturn(true);

		/** @var IRequest $request */
		$request = $this->getMockBuilder(IRequest::class)->disableOriginalConstructor()->getMock();
		$config = $this->createMock(IConfig::class);
		$this->plugin = new Plugin($authBackend, $request, $config);

		$root = new SimpleCollection('root');
		$this->server = new \Sabre\DAV\Server($root);
		/** @var SimpleCollection $node */
		$this->book = $this->getMockBuilder(IShareable::class)->
			disableOriginalConstructor()->
			getMock();
		$this->book->method('getName')->willReturn('addressbook1.vcf');
		$root->addChild($this->book);
		$this->plugin->initialize($this->server);
	}

	public function testSharing() {
		$this->book->expects($this->once())->method('updateShares')->with([[
			'href' => 'principal:principals/admin',
			'commonName' => null,
			'summary' => null,
			'readOnly' => false
		]], ['mailto:wilfredo@example.com']);

		// setup request
		$request = new Request('POST', 'addressbook1.vcf');
		$request->addHeader('Content-Type', 'application/xml');
		$request->setBody('<?xml version="1.0" encoding="utf-8" ?><CS:share xmlns:D="DAV:" xmlns:CS="http://owncloud.org/ns"><CS:set><D:href>principal:principals/admin</D:href><CS:read-write/></CS:set> <CS:remove><D:href>mailto:wilfredo@example.com</D:href></CS:remove></CS:share>');
		$response = new Response();
		$this->plugin->httpPost($request, $response);
	}
}
