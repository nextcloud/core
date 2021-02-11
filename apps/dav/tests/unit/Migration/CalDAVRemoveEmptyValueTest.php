<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\Tests\Unit\DAV\Migration;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\Migration\CalDAVRemoveEmptyValue;
use OCP\ILogger;
use OCP\Migration\IOutput;
use Sabre\VObject\InvalidDataException;
use Test\TestCase;

/**
 * Class CalDAVRemoveEmptyValueTest
 *
 * @package OCA\DAV\Tests\Unit\DAV\Migration
 * @group DB
 */
class CalDAVRemoveEmptyValueTest extends TestCase {

	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;

	/** @var CalDavBackend|\PHPUnit\Framework\MockObject\MockObject */
	private $backend;

	/** @var IOutput|\PHPUnit\Framework\MockObject\MockObject */
	private $output;

	/** @var string */
	private $invalid = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Apple Inc.//Mac OS X 10.11.2//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
TRANSP:OPAQUE
DTEND;VALUE=:20151223T223000Z
LAST-MODIFIED:20151214T091032Z
ORGANIZER;CN="User 1":mailto:user1@example.com
UID:1234567890@example.com
DTSTAMP:20151214T091032Z
STATUS:CONFIRMED
SEQUENCE:0
SUMMARY:Ein Geburtstag
DTSTART;VALUE=:20151223T173000Z
X-APPLE-TRAVEL-ADVISORY-BEHAVIOR:AUTOMATIC
CREATED;VALUE=:20151214T091032Z
END:VEVENT
END:VCALENDAR';

	/** @var string */
	private $valid = 'BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Apple Inc.//Mac OS X 10.11.2//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
TRANSP:OPAQUE
DTEND:20151223T223000Z
LAST-MODIFIED:20151214T091032Z
ORGANIZER;CN="User 1":mailto:user1@example.com
UID:1234567890@example.com
DTSTAMP:20151214T091032Z
STATUS:CONFIRMED
SEQUENCE:0
SUMMARY:Ein Geburtstag
DTSTART:20151223T173000Z
X-APPLE-TRAVEL-ADVISORY-BEHAVIOR:AUTOMATIC
CREATED:20151214T091032Z
END:VEVENT
END:VCALENDAR';

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(ILogger::class);
		$this->backend = $this->createMock(CalDavBackend::class);
		$this->output = $this->createMock(IOutput::class);
	}

	public function testRunAllValid() {
		/** @var CalDAVRemoveEmptyValue|\PHPUnit\Framework\MockObject\MockObject $step */
		$step = $this->getMockBuilder(CalDAVRemoveEmptyValue::class)
			->setConstructorArgs([
				\OC::$server->getDatabaseConnection(),
				$this->backend,
				$this->logger
			])
			->setMethods(['getInvalidObjects'])
			->getMock();

		$step->expects($this->once())
			->method('getInvalidObjects')
			->willReturn([]);

		$this->output->expects($this->once())
			->method('startProgress')
			->with(0);
		$this->output->expects($this->once())
			->method('finishProgress');

		$step->run($this->output);
	}

	public function testRunInvalid() {
		/** @var CalDAVRemoveEmptyValue|\PHPUnit\Framework\MockObject\MockObject $step */
		$step = $this->getMockBuilder(CalDAVRemoveEmptyValue::class)
			->setConstructorArgs([
				\OC::$server->getDatabaseConnection(),
				$this->backend,
				$this->logger
			])
			->setMethods(['getInvalidObjects'])
			->getMock();

		$step->expects($this->once())
			->method('getInvalidObjects')
			->willReturn([
				['calendarid' => '42', 'uri' => 'myuri'],
			]);

		$this->output->expects($this->once())
			->method('startProgress')
			->with(1);
		$this->output->expects($this->once())
			->method('finishProgress');

		$this->backend->expects($this->exactly(1))
			->method('getCalendarObject')
			->with(42, 'myuri')
			->willReturn([
				'calendardata' => $this->invalid
			]);

		$this->output->expects($this->exactly(1))
			->method('advance');
		$this->backend->expects($this->exactly(1))
			->method('getDenormalizedData')
			->with($this->valid);

		$this->backend->expects($this->exactly(1))
			->method('updateCalendarObject')
			->with(42, 'myuri', $this->valid);

		$step->run($this->output);
	}

	public function testRunValid() {
		/** @var CalDAVRemoveEmptyValue|\PHPUnit\Framework\MockObject\MockObject $step */
		$step = $this->getMockBuilder(CalDAVRemoveEmptyValue::class)
			->setConstructorArgs([
				\OC::$server->getDatabaseConnection(),
				$this->backend,
				$this->logger
			])
			->setMethods(['getInvalidObjects'])
			->getMock();

		$step->expects($this->once())
			->method('getInvalidObjects')
			->willReturn([
				['calendarid' => '42', 'uri' => 'myuri'],
			]);

		$this->output->expects($this->once())
			->method('startProgress')
			->with(1);
		$this->output->expects($this->once())
			->method('finishProgress');


		$this->backend->expects($this->exactly(1))
			->method('getCalendarObject')
			->with(42, 'myuri')
			->willReturn([
				'calendardata' => $this->valid
			]);

		$this->output->expects($this->never())
			->method('advance');
		$this->backend->expects($this->never())
			->method('getDenormalizedData');

		$this->backend->expects($this->never())
			->method('updateCalendarObject');

		$step->run($this->output);
	}

	public function testRunStillInvalid() {
		/** @var CalDAVRemoveEmptyValue|\PHPUnit\Framework\MockObject\MockObject $step */
		$step = $this->getMockBuilder(CalDAVRemoveEmptyValue::class)
			->setConstructorArgs([
				\OC::$server->getDatabaseConnection(),
				$this->backend,
				$this->logger
			])
			->setMethods(['getInvalidObjects'])
			->getMock();

		$step->expects($this->once())
			->method('getInvalidObjects')
			->willReturn([
				['calendarid' => '42', 'uri' => 'myuri'],
			]);

		$this->output->expects($this->once())
			->method('startProgress')
			->with(1);
		$this->output->expects($this->once())
			->method('finishProgress');


		$this->backend->expects($this->exactly(1))
			->method('getCalendarObject')
			->with(42, 'myuri')
			->willReturn([
				'calendardata' => $this->invalid
			]);

		$this->output->expects($this->exactly(1))
			->method('advance');
		$this->backend->expects($this->exactly(1))
			->method('getDenormalizedData')
			->with($this->valid)
			->willThrowException(new InvalidDataException());

		$this->backend->expects($this->never())
			->method('updateCalendarObject');

		$step->run($this->output);
	}
}
