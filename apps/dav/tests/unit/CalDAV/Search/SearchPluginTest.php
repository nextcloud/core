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

namespace OCA\DAV\Tests\unit\CalDAV\Search;

use OCA\DAV\CalDAV\CalendarHome;
use OCA\DAV\CalDAV\Search\SearchPlugin;
use OCA\DAV\CalDAV\Search\Xml\Request\CalendarSearchReport;
use Sabre\Xml\Service;
use Test\TestCase;

class SearchPluginTest extends TestCase {

	protected $server;

	/** @var \OCA\DAV\CalDAV\Search\SearchPlugin $plugin */
	protected $plugin;

	protected function setUp(): void {
		parent::setUp();

		$this->server = $this->createMock(\Sabre\DAV\Server::class);
		$this->server->tree = $this->createMock(\Sabre\DAV\Tree::class);
		$this->server->httpResponse = $this->createMock(\Sabre\HTTP\Response::class);
		$this->server->xml = new Service();

		$this->plugin = new SearchPlugin();
		$this->plugin->initialize($this->server);
	}

	public function testGetFeatures() {
		$this->assertEquals(['nc-calendar-search'], $this->plugin->getFeatures());
	}

	public function testGetName() {
		$this->assertEquals('nc-calendar-search', $this->plugin->getPluginName());
	}

	public function testInitialize() {
		$server = $this->createMock(\Sabre\DAV\Server::class);

		$plugin = new SearchPlugin();

		$server->expects($this->at(0))
			->method('on')
			->with('report', [$plugin, 'report']);
		$server->xml = new Service();

		$plugin->initialize($server);

		$this->assertEquals(
			$server->xml->elementMap['{http://nextcloud.com/ns}calendar-search'],
			'OCA\\DAV\\CalDAV\\Search\\Xml\\Request\\CalendarSearchReport'
		);
	}

	public function testReportUnknown() {
		$result = $this->plugin->report('{urn:ietf:params:xml:ns:caldav}calendar-query', 'REPORT', null);
		$this->assertEquals($result, null);
		$this->assertNotEquals($this->server->transactionType, 'report-nc-calendar-search');
	}

	public function testReport() {
		$report = $this->createMock(CalendarSearchReport::class);
		$report->filters = [];
		$calendarHome = $this->createMock(CalendarHome::class);
		$this->server->expects($this->at(0))
			->method('getRequestUri')
			->with()
			->willReturn('/re/quest/u/r/i');
		$this->server->tree->expects($this->at(0))
			->method('getNodeForPath')
			->with('/re/quest/u/r/i')
			->willReturn($calendarHome);
		$this->server->expects($this->at(1))
			->method('getHTTPDepth')
			->with(2)
			->willReturn(2);
		$this->server
			->method('getHTTPPrefer')
			->willReturn([
				'return' => null
			]);
		$calendarHome->expects($this->at(0))
			->method('calendarSearch')
			->willReturn([]);

		$this->plugin->report('{http://nextcloud.com/ns}calendar-search', $report, '');
	}

	public function testSupportedReportSetNoCalendarHome() {
		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('/foo/bar')
			->willReturn(null);

		$reports = $this->plugin->getSupportedReportSet('/foo/bar');
		$this->assertEquals([], $reports);
	}

	public function testSupportedReportSet() {
		$calendarHome = $this->createMock(CalendarHome::class);

		$this->server->tree->expects($this->once())
			->method('getNodeForPath')
			->with('/bar/foo')
			->willReturn($calendarHome);

		$reports = $this->plugin->getSupportedReportSet('/bar/foo');
		$this->assertEquals([
			'{http://nextcloud.com/ns}calendar-search'
		], $reports);
	}
}
