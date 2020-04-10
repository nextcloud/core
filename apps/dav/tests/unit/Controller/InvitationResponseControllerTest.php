<?php

declare(strict_types=1);

/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Tests\Unit\DAV\Controller;

use OCA\DAV\CalDAV\InvitationResponse\InvitationResponseServer;
use OCA\DAV\Controller\InvitationResponseController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IRequest;
use Sabre\VObject\ITip\Message;
use Test\TestCase;

class InvitationResponseControllerTest extends TestCase {

	/** @var InvitationResponseController */
	private $controller;

	/** @var IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	private $dbConnection;

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var ITimeFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $timeFactory;

	/** @var InvitationResponseServer|\PHPUnit_Framework_MockObject_MockObject */
	private $responseServer;

	protected function setUp(): void {
		parent::setUp();

		$this->dbConnection = $this->createMock(IDBConnection::class);
		$this->request = $this->createMock(IRequest::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->responseServer = $this->getMockBuilder(InvitationResponseServer::class)
			->disableOriginalConstructor()
			->getMock();

		$this->controller = new InvitationResponseController(
			'appName',
			$this->request,
			$this->dbConnection,
			$this->timeFactory,
			$this->responseServer
		);
	}

	public function testAccept() {
		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => null,
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => null,
			'token' => 'TOKEN123',
			'expiration' => 420000,
		], 1337);

		$expected = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:attendee@foo.bar
ORGANIZER:mailto:organizer@foo.bar
UID:this-is-the-events-uid
SEQUENCE:0
REQUEST-STATUS:2.0;Success
DTSTAMP:19700101T002217Z
END:VEVENT
END:VCALENDAR

EOF;
		$expected = preg_replace('~\R~u', "\r\n", $expected);

		$called = false;
		$this->responseServer->expects($this->once())
			->method('handleITipMessage')
			->willReturnCallback(function (Message $iTipMessage) use (&$called, $expected) {
				$called = true;
				$this->assertEquals('this-is-the-events-uid', $iTipMessage->uid);
				$this->assertEquals('VEVENT', $iTipMessage->component);
				$this->assertEquals('REPLY', $iTipMessage->method);
				$this->assertEquals(null, $iTipMessage->sequence);
				$this->assertEquals('mailto:attendee@foo.bar', $iTipMessage->sender);
				$this->assertEquals('mailto:organizer@foo.bar', $iTipMessage->recipient);

				$iTipMessage->scheduleStatus = '1.2;Message delivered locally';

				$this->assertEquals($expected, $iTipMessage->message->serialize());
			});



		$response = $this->controller->accept('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-success', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
		$this->assertTrue($called);
	}

	public function testAcceptSequence() {
		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => null,
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => 1337,
			'token' => 'TOKEN123',
			'expiration' => 420000,
		], 1337);

		$expected = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:attendee@foo.bar
ORGANIZER:mailto:organizer@foo.bar
UID:this-is-the-events-uid
SEQUENCE:1337
REQUEST-STATUS:2.0;Success
DTSTAMP:19700101T002217Z
END:VEVENT
END:VCALENDAR

EOF;
		$expected = preg_replace('~\R~u', "\r\n", $expected);

		$called = false;
		$this->responseServer->expects($this->once())
			->method('handleITipMessage')
			->willReturnCallback(function (Message $iTipMessage) use (&$called, $expected) {
				$called = true;
				$this->assertEquals('this-is-the-events-uid', $iTipMessage->uid);
				$this->assertEquals('VEVENT', $iTipMessage->component);
				$this->assertEquals('REPLY', $iTipMessage->method);
				$this->assertEquals(1337, $iTipMessage->sequence);
				$this->assertEquals('mailto:attendee@foo.bar', $iTipMessage->sender);
				$this->assertEquals('mailto:organizer@foo.bar', $iTipMessage->recipient);

				$iTipMessage->scheduleStatus = '1.2;Message delivered locally';

				$this->assertEquals($expected, $iTipMessage->message->serialize());
			});



		$response = $this->controller->accept('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-success', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
		$this->assertTrue($called);
	}

	public function testAcceptRecurrenceId() {
		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => "RECURRENCE-ID;TZID=Europe/Berlin:20180726T150000\n",
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => null,
			'token' => 'TOKEN123',
			'expiration' => 420000,
		], 1337);

		$expected = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=ACCEPTED:mailto:attendee@foo.bar
ORGANIZER:mailto:organizer@foo.bar
UID:this-is-the-events-uid
SEQUENCE:0
REQUEST-STATUS:2.0;Success
RECURRENCE-ID;TZID=Europe/Berlin:20180726T150000
DTSTAMP:19700101T002217Z
END:VEVENT
END:VCALENDAR

EOF;
		$expected = preg_replace('~\R~u', "\r\n", $expected);

		$called = false;
		$this->responseServer->expects($this->once())
			->method('handleITipMessage')
			->willReturnCallback(function (Message $iTipMessage) use (&$called, $expected) {
				$called = true;
				$this->assertEquals('this-is-the-events-uid', $iTipMessage->uid);
				$this->assertEquals('VEVENT', $iTipMessage->component);
				$this->assertEquals('REPLY', $iTipMessage->method);
				$this->assertEquals(0, $iTipMessage->sequence);
				$this->assertEquals('mailto:attendee@foo.bar', $iTipMessage->sender);
				$this->assertEquals('mailto:organizer@foo.bar', $iTipMessage->recipient);

				$iTipMessage->scheduleStatus = '1.2;Message delivered locally';

				$this->assertEquals($expected, $iTipMessage->message->serialize());
			});



		$response = $this->controller->accept('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-success', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
		$this->assertTrue($called);
	}

	public function testAcceptTokenNotFound() {
		$this->buildQueryExpects('TOKEN123', null, 1337);

		$response = $this->controller->accept('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-error', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
	}

	public function testAcceptExpiredToken() {
		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => null,
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => null,
			'token' => 'TOKEN123',
			'expiration' => 42,
		], 1337);

		$response = $this->controller->accept('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-error', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
	}

	public function testDecline() {
		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => null,
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => null,
			'token' => 'TOKEN123',
			'expiration' => 420000,
		], 1337);

		$expected = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=DECLINED:mailto:attendee@foo.bar
ORGANIZER:mailto:organizer@foo.bar
UID:this-is-the-events-uid
SEQUENCE:0
REQUEST-STATUS:2.0;Success
DTSTAMP:19700101T002217Z
END:VEVENT
END:VCALENDAR

EOF;
		$expected = preg_replace('~\R~u', "\r\n", $expected);

		$called = false;
		$this->responseServer->expects($this->once())
			->method('handleITipMessage')
			->willReturnCallback(function (Message $iTipMessage) use (&$called, $expected) {
				$called = true;
				$this->assertEquals('this-is-the-events-uid', $iTipMessage->uid);
				$this->assertEquals('VEVENT', $iTipMessage->component);
				$this->assertEquals('REPLY', $iTipMessage->method);
				$this->assertEquals(null, $iTipMessage->sequence);
				$this->assertEquals('mailto:attendee@foo.bar', $iTipMessage->sender);
				$this->assertEquals('mailto:organizer@foo.bar', $iTipMessage->recipient);

				$iTipMessage->scheduleStatus = '1.2;Message delivered locally';

				$this->assertEquals($expected, $iTipMessage->message->serialize());
			});



		$response = $this->controller->decline('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-success', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
		$this->assertTrue($called);
	}

	public function testOptions() {
		$response = $this->controller->options('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-options', $response->getTemplateName());
		$this->assertEquals(['token' => 'TOKEN123'], $response->getParams());
	}

	public function testProcessMoreOptionsResult() {
		$this->request->expects($this->at(0))
			->method('getParam')
			->with('partStat')
			->willReturn('TENTATIVE');
		$this->request->expects($this->at(1))
			->method('getParam')
			->with('guests')
			->willReturn('7');
		$this->request->expects($this->at(2))
			->method('getParam')
			->with('comment')
			->willReturn('Foo bar Bli blub');

		$this->buildQueryExpects('TOKEN123', [
			'id' => 0,
			'uid' => 'this-is-the-events-uid',
			'recurrenceid' => null,
			'attendee' => 'mailto:attendee@foo.bar',
			'organizer' => 'mailto:organizer@foo.bar',
			'sequence' => null,
			'token' => 'TOKEN123',
			'expiration' => 420000,
		], 1337);

		$expected = <<<EOF
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
BEGIN:VEVENT
ATTENDEE;PARTSTAT=TENTATIVE;X-RESPONSE-COMMENT=Foo bar Bli blub;X-NUM-GUEST
 S=7:mailto:attendee@foo.bar
ORGANIZER:mailto:organizer@foo.bar
UID:this-is-the-events-uid
SEQUENCE:0
REQUEST-STATUS:2.0;Success
DTSTAMP:19700101T002217Z
COMMENT:Foo bar Bli blub
END:VEVENT
END:VCALENDAR

EOF;
		$expected = preg_replace('~\R~u', "\r\n", $expected);

		$called = false;
		$this->responseServer->expects($this->once())
			->method('handleITipMessage')
			->willReturnCallback(function (Message $iTipMessage) use (&$called, $expected) {
				$called = true;
				$this->assertEquals('this-is-the-events-uid', $iTipMessage->uid);
				$this->assertEquals('VEVENT', $iTipMessage->component);
				$this->assertEquals('REPLY', $iTipMessage->method);
				$this->assertEquals(null, $iTipMessage->sequence);
				$this->assertEquals('mailto:attendee@foo.bar', $iTipMessage->sender);
				$this->assertEquals('mailto:organizer@foo.bar', $iTipMessage->recipient);

				$iTipMessage->scheduleStatus = '1.2;Message delivered locally';

				$this->assertEquals($expected, $iTipMessage->message->serialize());
			});



		$response = $this->controller->processMoreOptionsResult('TOKEN123');
		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('schedule-response-success', $response->getTemplateName());
		$this->assertEquals([], $response->getParams());
		$this->assertTrue($called);
	}

	private function buildQueryExpects($token, $return, $time) {
		$queryBuilder = $this->createMock(IQueryBuilder::class);
		$stmt = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);

		$this->dbConnection->expects($this->once())
			->method('getQueryBuilder')
			->with()
			->willReturn($queryBuilder);
		$queryBuilder->method('expr')
			->willReturn($expr);
		$queryBuilder->method('createNamedParameter')
			->willReturnMap([
				[$token, \PDO::PARAM_STR, null, 'namedParameterToken']
			]);

		$stmt->expects($this->once())
			->method('fetch')
			->with(\PDO::FETCH_ASSOC)
			->willReturn($return);

		$expr->expects($this->once())
			->method('eq')
			->with('token', 'namedParameterToken')
			->willReturn('EQ STATEMENT');

		$this->dbConnection->expects($this->once())
			->method('getQueryBuilder')
			->with()
			->willReturn($queryBuilder);

		$queryBuilder->expects($this->at(0))
			->method('select')
			->with('*')
			->willReturn($queryBuilder);
		$queryBuilder->expects($this->at(1))
			->method('from')
			->with('calendar_invitations')
			->willReturn($queryBuilder);
		$queryBuilder->expects($this->at(4))
			->method('where')
			->with('EQ STATEMENT')
			->willReturn($queryBuilder);
		$queryBuilder->expects($this->at(5))
			->method('execute')
			->with()
			->willReturn($stmt);

		$this->timeFactory->method('getTime')
			->willReturn($time);
	}
}
