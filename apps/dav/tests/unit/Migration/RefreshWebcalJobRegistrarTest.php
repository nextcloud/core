<?php
/**
 * @copyright Copyright (c) 2018, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\Tests\unit\DAV\Migration;

use OCA\DAV\BackgroundJob\RefreshWebcalJob;
use OCA\DAV\Migration\RefreshWebcalJobRegistrar;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

class RefreshWebcalJobRegistrarTest extends TestCase {

	/** @var IDBConnection | \PHPUnit\Framework\MockObject\MockObject */
	private $db;

	/** @var IJobList | \PHPUnit\Framework\MockObject\MockObject */
	private $jobList;

	/** @var RefreshWebcalJobRegistrar */
	private $migration;

	protected function setUp(): void {
		parent::setUp();

		$this->db = $this->createMock(IDBConnection::class);
		$this->jobList = $this->createMock(IJobList::class);

		$this->migration = new RefreshWebcalJobRegistrar($this->db, $this->jobList);
	}

	public function testGetName() {
		$this->assertEquals($this->migration->getName(), 'Registering background jobs to update cache for webcal calendars');
	}

	public function testRun() {
		$output = $this->createMock(IOutput::class);

		$queryBuilder = $this->createMock(IQueryBuilder::class);
		$statement = $this->createMock(\Doctrine\DBAL\Driver\Statement::class);

		$this->db->expects($this->once())
			->method('getQueryBuilder')
			->willReturn($queryBuilder);

		$queryBuilder->expects($this->at(0))
			->method('select')
			->with(['principaluri', 'uri'])
			->willReturn($queryBuilder);
		$queryBuilder->expects($this->at(1))
			->method('from')
			->with('calendarsubscriptions')
			->willReturn($queryBuilder);
		$queryBuilder->expects($this->at(2))
			->method('execute')
			->willReturn($statement);

		$statement->expects($this->at(0))
			->method('fetch')
			->with(\PDO::FETCH_ASSOC)
			->willReturn([
				'principaluri' => 'foo1',
				'uri' => 'bar1',
			]);
		$statement->expects($this->at(1))
			->method('fetch')
			->with(\PDO::FETCH_ASSOC)
			->willReturn([
				'principaluri' => 'foo2',
				'uri' => 'bar2',
			]);
		$statement->expects($this->at(2))
			->method('fetch')
			->with(\PDO::FETCH_ASSOC)
			->willReturn([
				'principaluri' => 'foo3',
				'uri' => 'bar3',
			]);
		$statement->expects($this->at(0))
			->method('fetch')
			->with(\PDO::FETCH_ASSOC)
			->willReturn(null);

		$this->jobList->expects($this->at(0))
			->method('has')
			->with(RefreshWebcalJob::class, [
				'principaluri' => 'foo1',
				'uri' => 'bar1',
			])
			->willReturn(false);
		$this->jobList->expects($this->at(1))
			->method('add')
			->with(RefreshWebcalJob::class, [
				'principaluri' => 'foo1',
				'uri' => 'bar1',
			]);
		$this->jobList->expects($this->at(2))
			->method('has')
			->with(RefreshWebcalJob::class, [
				'principaluri' => 'foo2',
				'uri' => 'bar2',
			])
			->willReturn(true);
		$this->jobList->expects($this->at(3))
			->method('has')
			->with(RefreshWebcalJob::class, [
				'principaluri' => 'foo3',
				'uri' => 'bar3',
			])
			->willReturn(false);
		$this->jobList->expects($this->at(4))
			->method('add')
			->with(RefreshWebcalJob::class, [
				'principaluri' => 'foo3',
				'uri' => 'bar3',
			]);

		$output->expects($this->once())
			->method('info')
			->with('Added 2 background jobs to update webcal calendars');

		$this->migration->run($output);
	}
}
