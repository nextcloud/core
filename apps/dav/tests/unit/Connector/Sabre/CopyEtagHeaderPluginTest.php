<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\DAV\Tests\unit\Connector\Sabre;

use OCA\DAV\Connector\Sabre\CopyEtagHeaderPlugin;
use OCA\DAV\Connector\Sabre\File;
use Sabre\DAV\Server;
use Sabre\DAV\Tree;
use Test\TestCase;

/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
class CopyEtagHeaderPluginTest extends TestCase {

	/** @var CopyEtagHeaderPlugin */
	private $plugin;

	/** @var Server */
	private $server;

	protected function setUp(): void {
		parent::setUp();
		$this->server = new \Sabre\DAV\Server();
		$this->plugin = new CopyEtagHeaderPlugin();
		$this->plugin->initialize($this->server);
	}

	public function testCopyEtag() {
		$request = new \Sabre\Http\Request('GET', 'dummy.file');
		$response = new \Sabre\Http\Response();
		$response->setHeader('Etag', 'abcd');

		$this->plugin->afterMethod($request, $response);

		$this->assertEquals('abcd', $response->getHeader('OC-Etag'));
	}

	public function testNoopWhenEmpty() {
		$request = new \Sabre\Http\Request('GET', 'dummy.file');
		$response = new \Sabre\Http\Response();

		$this->plugin->afterMethod($request, $response);

		$this->assertNull($response->getHeader('OC-Etag'));
	}

	public function testAfterMove() {
		$node = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$node->expects($this->once())
			->method('getETag')
			->willReturn('123456');
		$tree = $this->getMockBuilder(Tree::class)
			->disableOriginalConstructor()
			->getMock();
		$tree->expects($this->once())
			->method('getNodeForPath')
			->with('test.txt')
			->willReturn($node);

		$this->server->tree = $tree;
		$this->plugin->afterMove('', 'test.txt');

		$this->assertEquals('123456', $this->server->httpResponse->getHeader('OC-Etag'));
		$this->assertEquals('123456', $this->server->httpResponse->getHeader('Etag'));
	}
}
