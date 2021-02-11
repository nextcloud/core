<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
namespace OCA\DAV\Tests\unit\Search;

use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\Search\TasksSearchProvider;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Sabre\VObject\Reader;
use Test\TestCase;

class TasksSearchProviderTest extends TestCase {

	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;

	/** @var IL10N|\PHPUnit\Framework\MockObject\MockObject */
	private $l10n;

	/** @var IURLGenerator|\PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;

	/** @var CalDavBackend|\PHPUnit\Framework\MockObject\MockObject */
	private $backend;

	/** @var TasksSearchProvider */
	private $provider;

	// NO DUE NOR COMPLETED NOR SUMMARY
	private $vTodo0 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'PRODID:TEST'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'BEGIN:VTODO'.PHP_EOL.
		'UID:20070313T123432Z-456553@example.com'.PHP_EOL.
		'DTSTAMP:20070313T123432Z'.PHP_EOL.
		'STATUS:NEEDS-ACTION'.PHP_EOL.
		'END:VTODO'.PHP_EOL.
		'END:VCALENDAR';

	// DUE AND COMPLETED
	private $vTodo1 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'PRODID:TEST'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'BEGIN:VTODO'.PHP_EOL.
		'UID:20070313T123432Z-456553@example.com'.PHP_EOL.
		'DTSTAMP:20070313T123432Z'.PHP_EOL.
		'COMPLETED:20070707T100000Z'.PHP_EOL.
		'DUE;VALUE=DATE:20070501'.PHP_EOL.
		'SUMMARY:Task title'.PHP_EOL.
		'STATUS:NEEDS-ACTION'.PHP_EOL.
		'END:VTODO'.PHP_EOL.
		'END:VCALENDAR';

	// COMPLETED ONLY
	private $vTodo2 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'PRODID:TEST'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'BEGIN:VTODO'.PHP_EOL.
		'UID:20070313T123432Z-456553@example.com'.PHP_EOL.
		'DTSTAMP:20070313T123432Z'.PHP_EOL.
		'COMPLETED:20070707T100000Z'.PHP_EOL.
		'SUMMARY:Task title'.PHP_EOL.
		'STATUS:NEEDS-ACTION'.PHP_EOL.
		'END:VTODO'.PHP_EOL.
		'END:VCALENDAR';

	// DUE DATE
	private $vTodo3 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'PRODID:TEST'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'BEGIN:VTODO'.PHP_EOL.
		'UID:20070313T123432Z-456553@example.com'.PHP_EOL.
		'DTSTAMP:20070313T123432Z'.PHP_EOL.
		'DUE;VALUE=DATE:20070501'.PHP_EOL.
		'SUMMARY:Task title'.PHP_EOL.
		'STATUS:NEEDS-ACTION'.PHP_EOL.
		'END:VTODO'.PHP_EOL.
		'END:VCALENDAR';

	// DUE DATETIME
	private $vTodo4 = 'BEGIN:VCALENDAR'.PHP_EOL.
		'PRODID:TEST'.PHP_EOL.
		'VERSION:2.0'.PHP_EOL.
		'BEGIN:VTODO'.PHP_EOL.
		'UID:20070313T123432Z-456553@example.com'.PHP_EOL.
		'DTSTAMP:20070313T123432Z'.PHP_EOL.
		'DUE:20070709T130000Z'.PHP_EOL.
		'SUMMARY:Task title'.PHP_EOL.
		'STATUS:NEEDS-ACTION'.PHP_EOL.
		'END:VTODO'.PHP_EOL.
		'END:VCALENDAR';

	protected function setUp(): void {
		parent::setUp();

		$this->appManager = $this->createMock(IAppManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->backend = $this->createMock(CalDavBackend::class);

		$this->provider = new TasksSearchProvider(
			$this->appManager,
			$this->l10n,
			$this->urlGenerator,
			$this->backend
		);
	}

	public function testGetId(): void {
		$this->assertEquals('tasks', $this->provider->getId());
	}

	public function testGetName(): void {
		$this->l10n->expects($this->exactly(1))
			->method('t')
			->with('Tasks')
			->willReturnArgument(0);

		$this->assertEquals('Tasks', $this->provider->getName());
	}

	public function testSearchAppDisabled(): void {
		$user = $this->createMock(IUser::class);
		$query = $this->createMock(ISearchQuery::class);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('tasks', $user)
			->willReturn(false);
		$this->l10n->expects($this->exactly(1))
			->method('t')
			->willReturnArgument(0);
		$this->backend->expects($this->never())
			->method('getCalendarsForUser');
		$this->backend->expects($this->never())
			->method('getSubscriptionsForUser');
		$this->backend->expects($this->never())
			->method('searchPrincipalUri');

		$actual = $this->provider->search($user, $query);
		$data = $actual->jsonSerialize();
		$this->assertInstanceOf(SearchResult::class, $actual);
		$this->assertEquals('Tasks', $data['name']);
		$this->assertEmpty($data['entries']);
		$this->assertFalse($data['isPaginated']);
		$this->assertNull($data['cursor']);
	}

	public function testSearch(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('john.doe');
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('search term');
		$query->method('getLimit')->willReturn(5);
		$query->method('getCursor')->willReturn(20);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with('tasks', $user)
			->willReturn(true);
		$this->l10n->method('t')->willReturnArgument(0);

		$this->backend->expects($this->once())
			->method('getCalendarsForUser')
			->with('principals/users/john.doe')
			->willReturn([
				[
					'id' => 99,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'calendar-uri-99',
				], [
					'id' => 123,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'calendar-uri-123',
				]
			]);
		$this->backend->expects($this->once())
			->method('getSubscriptionsForUser')
			->with('principals/users/john.doe')
			->willReturn([
				[
					'id' => 1337,
					'principaluri' => 'principals/users/john.doe',
					'uri' => 'subscription-uri-1337',
				]
			]);
		$this->backend->expects($this->once())
			->method('searchPrincipalUri')
			->with('principals/users/john.doe', 'search term', ['VTODO'],
				['SUMMARY', 'DESCRIPTION', 'CATEGORIES'],
				[],
				['limit' => 5, 'offset' => 20])
			->willReturn([
				[
					'calendarid' => 99,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_CALENDAR,
					'uri' => 'todo0.ics',
					'calendardata' => $this->vTodo0,
				],
				[
					'calendarid' => 123,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_CALENDAR,
					'uri' => 'todo1.ics',
					'calendardata' => $this->vTodo1,
				],
				[
					'calendarid' => 1337,
					'calendartype' => CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION,
					'uri' => 'todo2.ics',
					'calendardata' => $this->vTodo2,
				]
			]);

		$provider = $this->getMockBuilder(TasksSearchProvider::class)
			->setConstructorArgs([
				$this->appManager,
				$this->l10n,
				$this->urlGenerator,
				$this->backend,
			])
			->setMethods([
				'getDeepLinkToTasksApp',
				'generateSubline',
			])
			->getMock();

		$provider->expects($this->exactly(3))
			->method('generateSubline')
			->willReturn('subline');
		$provider->expects($this->exactly(3))
			->method('getDeepLinkToTasksApp')
			->withConsecutive(
				['calendar-uri-99', 'todo0.ics'],
				['calendar-uri-123', 'todo1.ics'],
				['subscription-uri-1337', 'todo2.ics']
			)
			->willReturn('deep-link-to-tasks');

		$actual = $provider->search($user, $query);
		$data = $actual->jsonSerialize();
		$this->assertInstanceOf(SearchResult::class, $actual);
		$this->assertEquals('Tasks', $data['name']);
		$this->assertCount(3, $data['entries']);
		$this->assertTrue($data['isPaginated']);
		$this->assertEquals(23, $data['cursor']);

		$result0 = $data['entries'][0];
		$result0Data = $result0->jsonSerialize();
		$result1 = $data['entries'][1];
		$result1Data = $result1->jsonSerialize();
		$result2 = $data['entries'][2];
		$result2Data = $result2->jsonSerialize();

		$this->assertInstanceOf(SearchResultEntry::class, $result0);
		$this->assertEmpty($result0Data['thumbnailUrl']);
		$this->assertEquals('Untitled task', $result0Data['title']);
		$this->assertEquals('subline', $result0Data['subline']);
		$this->assertEquals('deep-link-to-tasks', $result0Data['resourceUrl']);
		$this->assertEquals('icon-checkmark', $result0Data['icon']);
		$this->assertFalse($result0Data['rounded']);

		$this->assertInstanceOf(SearchResultEntry::class, $result1);
		$this->assertEmpty($result1Data['thumbnailUrl']);
		$this->assertEquals('Task title', $result1Data['title']);
		$this->assertEquals('subline', $result1Data['subline']);
		$this->assertEquals('deep-link-to-tasks', $result1Data['resourceUrl']);
		$this->assertEquals('icon-checkmark', $result1Data['icon']);
		$this->assertFalse($result1Data['rounded']);

		$this->assertInstanceOf(SearchResultEntry::class, $result2);
		$this->assertEmpty($result2Data['thumbnailUrl']);
		$this->assertEquals('Task title', $result2Data['title']);
		$this->assertEquals('subline', $result2Data['subline']);
		$this->assertEquals('deep-link-to-tasks', $result2Data['resourceUrl']);
		$this->assertEquals('icon-checkmark', $result2Data['icon']);
		$this->assertFalse($result2Data['rounded']);
	}

	public function testGetDeepLinkToTasksApp(): void {
		$this->urlGenerator->expects($this->once())
			->method('linkToRoute')
			->with('tasks.page.index')
			->willReturn('link-to-route-tasks.index');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('link-to-route-tasks.index#/calendars/uri-john.doe/tasks/task-uri.ics')
			->willReturn('absolute-url-link-to-route-tasks.index#/calendars/uri-john.doe/tasks/task-uri.ics');

		$actual = self::invokePrivate($this->provider, 'getDeepLinkToTasksApp', ['uri-john.doe', 'task-uri.ics']);
		$this->assertEquals('absolute-url-link-to-route-tasks.index#/calendars/uri-john.doe/tasks/task-uri.ics', $actual);
	}

	/**
	 * @param string $ics
	 * @param string $expectedSubline
	 *
	 * @dataProvider generateSublineDataProvider
	 */
	public function testGenerateSubline(string $ics, string $expectedSubline): void {
		$vCalendar = Reader::read($ics, Reader::OPTION_FORGIVING);
		$taskComponent = $vCalendar->VTODO;

		$this->l10n->method('t')->willReturnArgument(0);
		$this->l10n->method('l')->willReturnArgument('');

		$actual = self::invokePrivate($this->provider, 'generateSubline', [$taskComponent]);
		$this->assertEquals($expectedSubline, $actual);
	}

	public function generateSublineDataProvider(): array {
		return [
			[$this->vTodo0, ''],
			[$this->vTodo1, 'Completed on %s'],
			[$this->vTodo2, 'Completed on %s'],
			[$this->vTodo3, 'Due on %s'],
			[$this->vTodo4, 'Due on %s by %s'],
		];
	}
}
