<?php
/**
 *
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\Tests\unit\CalDAV\Publishing;

use OCA\DAV\CalDAV\Calendar;
use OCA\DAV\CalDAV\Publishing\PublishPlugin;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Sabre\DAV\Server;
use Sabre\DAV\SimpleCollection;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;
use Test\TestCase;

class PluginTest extends TestCase {

	/** @var PublishPlugin */
	private $plugin;
	/** @var Server */
	private $server;
	/** @var Calendar | MockObject */
	private $book;
	/** @var IConfig | MockObject */
	private $config;
	/** @var IURLGenerator | MockObject */
	private $urlGenerator;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->getMockBuilder(IConfig::class)->
			disableOriginalConstructor()->
			getMock();
		$this->config->expects($this->any())->method('getSystemValue')
			->with($this->equalTo('secret'))
			->willReturn('mysecret');

		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->
			disableOriginalConstructor()->
			getMock();

		/** @var IRequest $request */
		$this->plugin = new PublishPlugin($this->config, $this->urlGenerator);

		$root = new SimpleCollection('calendars');
		$this->server = new Server($root);
		/** @var SimpleCollection $node */
		$this->book = $this->getMockBuilder(Calendar::class)->
			disableOriginalConstructor()->
			getMock();
		$this->book->method('getName')->willReturn('cal1');
		$root->addChild($this->book);
		$this->plugin->initialize($this->server);
	}

	public function testPublishing() {

		$this->book->expects($this->once())->method('addPublicLink')->with();

		// setup request
		$request = new Request('POST', 'cal1');
		$request->addHeader('Content-Type', 'application/xml');
		$request->setBody('<o:publish-calendar xmlns:o="http://calendarserver.org/ns/"/>');
		$response = new Response();
		$this->plugin->httpPost($request, $response);
	}

	public function testUnPublishing() {

		$this->book->expects($this->once())->method('removeAllPublicLinks')->with();

		// setup request
		$request = new Request('POST', 'cal1');
		$request->addHeader('Content-Type', 'application/xml');
		$request->setBody('<o:unpublish-calendar xmlns:o="http://calendarserver.org/ns/"/>');
		$response = new Response();
		$this->plugin->httpPost($request, $response);
	}

	public function testUnPublishingALink() {

		$this->book->expects($this->once())->method('removePublicLink')->with('urltounpublish');

		// setup request
		$request = new Request();
		$request->addHeader('Content-Type', 'application/xml');
		$request->setUrl('cal1');
		$request->setBody('<o:unpublish-calendar xmlns:o="http://nextcloud.com/ns/">urltounpublish</o:unpublish-calendar>');
		$response = new Response();
		$this->plugin->httpPost($request, $response);
	}
}
