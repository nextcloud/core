<?php
/**
 * @copyright Copyright (c) 2017 Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\CalDAV\BirthdayCalendar;

use OCA\DAV\CalDAV\BirthdayCalendar\EnablePlugin;
use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CalDAV\Calendar;
use OCA\DAV\CalDAV\CalendarHome;
use OCP\IConfig;
use Test\TestCase;

class EnablePluginTest extends TestCase {

	/** @var \Sabre\DAV\Server|\PHPUnit_Framework_MockObject_MockObject */
	protected $server;

	/** @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var BirthdayService |\PHPUnit_Framework_MockObject_MockObject */
	protected $birthdayService;

	/** @var \OCA\DAV\CalDAV\BirthdayCalendar\EnablePlugin $plugin */
	protected $plugin;

	protected $request;

	protected $response;

	protected function setUp(): void {
		parent::setUp();

		$this->server = $this->createMock(\Sabre\DAV\Server::class);
		$this->server->tree = $this->createMock(\Sabre\DAV\Tree::class);
		$this->server->httpResponse = $this->createMock(\Sabre\HTTP\Response::class);
		$this->server->xml = $this->createMock(\Sabre\DAV\Xml\Service::class);

		$this->config = $this->createMock(IConfig::class);
		$this->birthdayService = $this->createMock(BirthdayService::class);

		$this->plugin = new EnablePlugin($this->config, $this->birthdayService);
		$this->plugin->initialize($this->server);

		$this->request = $this->createMock(\Sabre\HTTP\RequestInterface::class);
		$this->response = $this->createMock(\Sabre\HTTP\ResponseInterface::class);
	}

	public function testGetFeatures() {
		$this->assertEquals(['nc-enable-birthday-calendar'], $this->plugin->getFeatures());
	}

	public function testGetName() {
		$this->assertEquals('nc-enable-birthday-calendar', $this->plugin->getPluginName());
	}

	public function testInitialize() {
		$server = $this->createMock(\Sabre\DAV\Server::class);

		$plugin = new EnablePlugin($this->config, $this->birthdayService);

		$server->expects($this->at(0))
			->method('on')
			->with('method:POST', [$plugin, 'httpPost']);

		$plugin->initialize($server);
	}

	public function testHttpPostNoCalendarHome() {
		$calendar = $this->createMock(Calendar::class);

		$this->server->expects($this->once())
			->method('getRequestUri')
			->willReturn('/bar/foo');
		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('/bar/foo')
			->willReturn($calendar);

		$this->config->expects($this->never())
			->method('setUserValue');

		$this->birthdayService->expects($this->never())
			->method('syncUser');

		$this->plugin->httpPost($this->request, $this->response);
	}

	public function testHttpPostWrongRequest() {
		$calendarHome = $this->createMock(CalendarHome::class);

		$this->server->expects($this->once())
			->method('getRequestUri')
			->willReturn('/bar/foo');
		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('/bar/foo')
			->willReturn($calendarHome);

		$this->request->expects($this->at(0))
			->method('getBodyAsString')
			->willReturn('<nc:disable-birthday-calendar xmlns:nc="http://nextcloud.com/ns"/>');

		$this->request->expects($this->at(1))
			->method('getUrl')
			->willReturn('url_abc');

		$this->server->xml->expects($this->once())
			->method('parse')
			->willReturnCallback(function ($requestBody, $url, &$documentType) {
				$documentType =  '{http://nextcloud.com/ns}disable-birthday-calendar';
			});

		$this->config->expects($this->never())
			->method('setUserValue');

		$this->birthdayService->expects($this->never())
			->method('syncUser');

		$this->plugin->httpPost($this->request, $this->response);
	}

	public function testHttpPost() {
		$calendarHome = $this->createMock(CalendarHome::class);

		$this->server->expects($this->once())
			->method('getRequestUri')
			->willReturn('/bar/foo');
		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('/bar/foo')
			->willReturn($calendarHome);

		$calendarHome->expects($this->once())
			->method('getOwner')
			->willReturn('principals/users/BlaBlub');

		$this->request->expects($this->at(0))
			->method('getBodyAsString')
			->willReturn('<nc:enable-birthday-calendar xmlns:nc="http://nextcloud.com/ns"/>');

		$this->request->expects($this->at(1))
			->method('getUrl')
			->willReturn('url_abc');

		$this->server->xml->expects($this->once())
			->method('parse')
			->willReturnCallback(function ($requestBody, $url, &$documentType) {
				$documentType = '{http://nextcloud.com/ns}enable-birthday-calendar';
			});

		$this->config->expects($this->once())
			->method('setUserValue')
			->with('BlaBlub', 'dav', 'generateBirthdayCalendar', 'yes');

		$this->birthdayService->expects($this->once())
			->method('syncUser')
			->with('BlaBlub');

		$this->server->httpResponse->expects($this->once())
			->method('setStatus')
			->with(204);

		$result = $this->plugin->httpPost($this->request, $this->response);

		$this->assertEquals(false, $result);
	}
}
