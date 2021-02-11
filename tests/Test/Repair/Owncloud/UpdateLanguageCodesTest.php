<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace Test\Repair\Owncloud;

use OC\Repair\Owncloud\UpdateLanguageCodes;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Class UpdateLanguageCodesTest
 *
 * @group DB
 *
 * @package Test\Repair
 */
class UpdateLanguageCodesTest extends TestCase {
	/** @var \OCP\IDBConnection */
	protected $connection;

	/** @var IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $config;

	protected function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
	}

	public function testRun() {
		$users = [
			['userid' => 'user1', 'configvalue' => 'fi_FI'],
			['userid' => 'user2', 'configvalue' => 'de'],
			['userid' => 'user3', 'configvalue' => 'fi'],
			['userid' => 'user4', 'configvalue' => 'ja'],
			['userid' => 'user5', 'configvalue' => 'bg_BG'],
			['userid' => 'user6', 'configvalue' => 'ja'],
			['userid' => 'user7', 'configvalue' => 'th_TH'],
			['userid' => 'user8', 'configvalue' => 'th_TH'],
		];

		// insert test data
		$qb = $this->connection->getQueryBuilder();
		$qb->insert('preferences')
				->values([
					'userid' => $qb->createParameter('userid'),
					'appid' => $qb->createParameter('appid'),
					'configkey' => $qb->createParameter('configkey'),
					'configvalue' => $qb->createParameter('configvalue'),
				]);
		foreach ($users as $user) {
			$qb->setParameters([
				'userid' => $user['userid'],
				'appid' => 'core',
				'configkey' => 'lang',
				'configvalue' => $user['configvalue'],
			])->execute();
		}

		// check if test data is written to DB
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select(['userid', 'configvalue'])
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('core')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lang')))
			->execute();

		$rows = $result->fetchAll();
		$result->closeCursor();

		$this->assertSame($users, $rows, 'Asserts that the entries are the ones from the test data set');

		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->createMock(IOutput::class);
		$outputMock->expects($this->at(0))
			->method('info')
			->with('Changed 1 setting(s) from "bg_BG" to "bg" in preferences table.');
		$outputMock->expects($this->at(1))
			->method('info')
			->with('Changed 0 setting(s) from "cs_CZ" to "cs" in preferences table.');
		$outputMock->expects($this->at(2))
			->method('info')
			->with('Changed 1 setting(s) from "fi_FI" to "fi" in preferences table.');
		$outputMock->expects($this->at(3))
			->method('info')
			->with('Changed 0 setting(s) from "hu_HU" to "hu" in preferences table.');
		$outputMock->expects($this->at(4))
			->method('info')
			->with('Changed 0 setting(s) from "nb_NO" to "nb" in preferences table.');
		$outputMock->expects($this->at(5))
			->method('info')
			->with('Changed 0 setting(s) from "sk_SK" to "sk" in preferences table.');
		$outputMock->expects($this->at(6))
			->method('info')
			->with('Changed 2 setting(s) from "th_TH" to "th" in preferences table.');

		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('12.0.0.13');

		// run repair step
		$repair = new UpdateLanguageCodes($this->connection, $this->config);
		$repair->run($outputMock);

		// check if test data is correctly modified in DB
		$qb = $this->connection->getQueryBuilder();
		$result = $qb->select(['userid', 'configvalue'])
			->from('preferences')
			->where($qb->expr()->eq('appid', $qb->createNamedParameter('core')))
			->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lang')))
			->orderBy('userid')
			->execute();

		$rows = $result->fetchAll();
		$result->closeCursor();

		// value has changed for one user
		$users[0]['configvalue'] = 'fi';
		$users[4]['configvalue'] = 'bg';
		$users[6]['configvalue'] = 'th';
		$users[7]['configvalue'] = 'th';
		$this->assertSame($users, $rows, 'Asserts that the entries are updated correctly.');

		// remove test data
		foreach ($users as $user) {
			$qb = $this->connection->getQueryBuilder();
			$qb->delete('preferences')
				->where($qb->expr()->eq('userid', $qb->createNamedParameter($user['userid'])))
				->andWhere($qb->expr()->eq('appid', $qb->createNamedParameter('core')))
				->andWhere($qb->expr()->eq('configkey', $qb->createNamedParameter('lang')))
				->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($user['configvalue']), IQueryBuilder::PARAM_STR))
				->execute();
		}
	}

	public function testSecondRun() {
		/** @var IOutput|\PHPUnit_Framework_MockObject_MockObject $outputMock */
		$outputMock = $this->createMock(IOutput::class);
		$outputMock->expects($this->never())
			->method('info');

		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('version', '0.0.0')
			->willReturn('12.0.0.14');

		// run repair step
		$repair = new UpdateLanguageCodes($this->connection, $this->config);
		$repair->run($outputMock);
	}
}
