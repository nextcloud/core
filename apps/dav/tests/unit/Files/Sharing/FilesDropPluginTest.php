<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\Files\Sharing;

use OC\Files\View;
use OCA\DAV\Files\Sharing\FilesDropPlugin;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Server;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Test\TestCase;

class FilesDropPluginTest extends TestCase {

	/** @var View|\PHPUnit_Framework_MockObject_MockObject */
	private $view;

	/** @var Server|\PHPUnit_Framework_MockObject_MockObject */
	private $server;

	/** @var FilesDropPlugin */
	private $plugin;

	/** @var RequestInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var ResponseInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $response;

	protected function setUp(): void {
		parent::setUp();

		$this->view = $this->createMock(View::class);
		$this->server = $this->createMock(Server::class);
		$this->plugin = new FilesDropPlugin();

		$this->request = $this->createMock(RequestInterface::class);
		$this->response = $this->createMock(ResponseInterface::class);

		$this->response->expects($this->never())
			->method($this->anything());
	}

	public function testInitialize() {
		$this->server->expects($this->once())
			->method('on')
			->with(
				$this->equalTo('beforeMethod:*'),
				$this->equalTo([$this->plugin, 'beforeMethod']),
				$this->equalTo(999)
			);

		$this->plugin->initialize($this->server);
	}

	public function testNotEnabled() {
		$this->view->expects($this->never())
			->method($this->anything());

		$this->request->expects($this->never())
			->method($this->anything());

		$this->plugin->beforeMethod($this->request, $this->response);
	}

	public function testValid() {
		$this->plugin->enable();
		$this->plugin->setView($this->view);

		$this->request->method('getMethod')
			->willReturn('PUT');

		$this->request->method('getPath')
			->willReturn('file.txt');

		$this->request->method('getBaseUrl')
			->willReturn('https://example.com');

		$this->view->method('file_exists')
			->with('/file.txt')
			->willReturn(false);

		$this->request->expects($this->once())
			->method('setUrl')
			->with('https://example.com/file.txt');

		$this->plugin->beforeMethod($this->request, $this->response);
	}

	public function testFileAlreadyExistsValid() {
		$this->plugin->enable();
		$this->plugin->setView($this->view);

		$this->request->method('getMethod')
			->willReturn('PUT');

		$this->request->method('getPath')
			->willReturn('file.txt');

		$this->request->method('getBaseUrl')
			->willReturn('https://example.com');

		$this->view->method('file_exists')
			->willReturnCallback(function ($path) {
				if ($path === 'file.txt' || $path === '/file.txt') {
					return true;
				} else {
					return false;
				}
			});

		$this->request->expects($this->once())
			->method('setUrl')
			->with($this->equalTo('https://example.com/file (2).txt'));

		$this->plugin->beforeMethod($this->request, $this->response);
	}

	public function testNoMKCOL() {
		$this->plugin->enable();
		$this->plugin->setView($this->view);

		$this->request->method('getMethod')
			->willReturn('MKCOL');

		$this->expectException(MethodNotAllowed::class);

		$this->plugin->beforeMethod($this->request, $this->response);
	}

	public function testNoSubdirPut() {
		$this->plugin->enable();
		$this->plugin->setView($this->view);

		$this->request->method('getMethod')
			->willReturn('PUT');

		$this->request->method('getPath')
			->willReturn('folder/file.txt');

		$this->request->method('getBaseUrl')
			->willReturn('https://example.com');

		$this->view->method('file_exists')
			->willReturnCallback(function ($path) {
				if ($path === 'file.txt' || $path === '/file.txt') {
					return true;
				} else {
					return false;
				}
			});

		$this->request->expects($this->once())
			->method('setUrl')
			->with($this->equalTo('https://example.com/file (2).txt'));

		$this->plugin->beforeMethod($this->request, $this->response);
	}
}
