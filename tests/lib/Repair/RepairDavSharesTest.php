<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Repair;

use OC\Repair\RepairDavShares;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use Test\TestCase;
use OCP\Migration\IOutput;
use function in_array;

class RepairDavSharesTest extends TestCase {

	/** @var IOutput|\PHPUnit\Framework\MockObject\MockObject */
	protected $output;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	protected $config;
	/** @var IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	protected $dbc;
	/** @var IGroupManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $groupManager;
	/** @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface */
	protected $logger;
	/** @var RepairDavSharesTest */
	protected $repair;

	public function setUp(): void {
		parent::setUp();

		$this->output = $this->createMock(IOutput::class);

		$this->config = $this->createMock(IConfig::class);
		$this->dbc = $this->createMock(IDBConnection::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->repair = new RepairDavShares(
			$this->config,
			$this->dbc,
			$this->groupManager,
			$this->logger
		);
	}

	public function testRun() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('20.0.2');

		$this->output->expects($this->atLeastOnce())
			->method('info');

		$existingGroups = [
			'Innocent',
			'Wants Repair',
			'Well förmed',
			'family+friends',
			'family friends',
		];

		$shareResultData = [
			[
				// No update, nothing to escape
				'id' => 0,
				'principaluri' => 'principals/groups/Innocent',
			],
			[
				// Update
				'id' => 1,
				'principaluri' => 'principals/groups/Wants Repair',
			],
			[
				// No update, already proper
				'id' => 2,
				'principaluri' => 'principals/groups/Well+f%C3%B6rmed',
			],
			[
				// No update, unknown group
				'id' => 3,
				'principaluri' => 'principals/groups/Not known',
			],
			[
				// No update, unknown group
				'id' => 4,
				'principaluri' => 'principals/groups/Also%2F%2FNot%23Known',
			],
			[
				// No update, group exists in both forms
				'id' => 5,
				'principaluri' => 'principals/groups/family+friends',
			],
			[
				// No update, already proper
				'id' => 6,
				'principaluri' => 'principals/groups/family%2Bfriends',
			],
			[
				// Update
				'id' => 7,
				'principaluri' => 'principals/groups/family friends',
			],
		];

		$shareResults = $this->createMock(IResult::class);
		$shareResults->expects($this->any())
			->method('fetch')
			->willReturnCallback(function () use (&$shareResultData) {
				return array_pop($shareResultData);
			});

		$expressionBuilder = $this->createMock(IExpressionBuilder::class);

		$selectMock = $this->createMock(IQueryBuilder::class);
		$selectMock->expects($this->any())
			->method('expr')
			->willReturn($expressionBuilder);
		$selectMock->expects($this->once())
			->method('select')
			->willReturnSelf();
		$selectMock->expects($this->once())
			->method('from')
			->willReturnSelf();
		$selectMock->expects($this->once())
			->method('where')
			->willReturnSelf();
		$selectMock->expects($this->once())
			->method('execute')
			->willReturn($shareResults);

		$updateMock = $this->createMock(IQueryBuilder::class);
		$updateMock->expects($this->any())
			->method('expr')
			->willReturn($expressionBuilder);
		$updateMock->expects($this->once())
			->method('update')
			->willReturnSelf();
		$updateMock->expects($this->any())
			->method('set')
			->willReturnSelf();
		$updateMock->expects($this->once())
			->method('where')
			->willReturnSelf();
		$updateMock->expects($this->exactly(4))
			->method('setParameter')
			->withConsecutive(
				['updatedPrincipalUri', 'principals/groups/' . urlencode('family friends')],
				['shareId', 7],
				['updatedPrincipalUri', 'principals/groups/' . urlencode('Wants Repair')],
				['shareId', 1],
			)
			->willReturnSelf();
		$updateMock->expects($this->exactly(2))
			->method('execute');

		$this->dbc->expects($this->atLeast(2))
			->method('getQueryBuilder')
			->willReturnOnConsecutiveCalls($selectMock, $updateMock);

		$this->groupManager->expects($this->any())
			->method('groupExists')
			->willReturnCallback(function (string $gid) use ($existingGroups) {
				return in_array($gid, $existingGroups);
			});

		$this->repair->run($this->output);
	}
}
