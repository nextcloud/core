<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Test\Authentication\TwoFactorAuth\Db;

use OC;
use OC\Authentication\TwoFactorAuth\Db\ProviderUserAssignmentDao;
use OCP\IDBConnection;
use Test\TestCase;

/**
 * @group DB
 */
class ProviderUserAssignmentDaoTest extends TestCase {

	/** @var IDBConnection */
	private $dbConn;

	/** @var ProviderUserAssignmentDao */
	private $dao;

	protected function setUp(): void {
		parent::setUp();

		$this->dbConn = OC::$server->getDatabaseConnection();
		$qb = $this->dbConn->getQueryBuilder();
		$q = $qb->delete(ProviderUserAssignmentDao::TABLE_NAME);
		$q->execute();

		$this->dao = new ProviderUserAssignmentDao($this->dbConn);
	}

	public function testGetState() {
		$qb = $this->dbConn->getQueryBuilder();
		$q1 = $qb->insert(ProviderUserAssignmentDao::TABLE_NAME)->values([
			'provider_id' => $qb->createNamedParameter('twofactor_u2f'),
			'uid' => $qb->createNamedParameter('user123'),
			'enabled' => $qb->createNamedParameter(1),
		]);
		$q1->execute();
		$q2 = $qb->insert(ProviderUserAssignmentDao::TABLE_NAME)->values([
			'provider_id' => $qb->createNamedParameter('twofactor_totp'),
			'uid' => $qb->createNamedParameter('user123'),
			'enabled' => $qb->createNamedParameter(0),
		]);
		$q2->execute();
		$expected = [
			'twofactor_u2f' => true,
			'twofactor_totp' => false,
		];

		$state = $this->dao->getState('user123');

		$this->assertEquals($expected, $state);
	}

	public function testPersist() {
		$qb = $this->dbConn->getQueryBuilder();

		$this->dao->persist('twofactor_totp', 'user123', 0);

		$q = $qb
			->select('*')
			->from(ProviderUserAssignmentDao::TABLE_NAME)
			->where($qb->expr()->eq('provider_id', $qb->createNamedParameter('twofactor_totp')))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter('user123')))
			->andWhere($qb->expr()->eq('enabled', $qb->createNamedParameter(0)));
		$res = $q->execute();
		$data = $res->fetchAll();
		$res->closeCursor();
		$this->assertCount(1, $data);
	}

	public function testPersistTwice() {
		$qb = $this->dbConn->getQueryBuilder();

		$this->dao->persist('twofactor_totp', 'user123', 0);
		$this->dao->persist('twofactor_totp', 'user123', 1);

		$q = $qb
			->select('*')
			->from(ProviderUserAssignmentDao::TABLE_NAME)
			->where($qb->expr()->eq('provider_id', $qb->createNamedParameter('twofactor_totp')))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter('user123')))
			->andWhere($qb->expr()->eq('enabled', $qb->createNamedParameter(1)));
		$res = $q->execute();
		$data = $res->fetchAll();
		$res->closeCursor();

		$this->assertCount(1, $data);
	}

	public function testPersistSameStateTwice() {
		$qb = $this->dbConn->getQueryBuilder();

		$this->dao->persist('twofactor_totp', 'user123', 1);
		$this->dao->persist('twofactor_totp', 'user123', 1);

		$q = $qb
			->select('*')
			->from(ProviderUserAssignmentDao::TABLE_NAME)
			->where($qb->expr()->eq('provider_id', $qb->createNamedParameter('twofactor_totp')))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter('user123')))
			->andWhere($qb->expr()->eq('enabled', $qb->createNamedParameter(1)));
		$res = $q->execute();
		$data = $res->fetchAll();
		$res->closeCursor();

		$this->assertCount(1, $data);
	}

	public function testDeleteByUser() {
		$this->dao->persist('twofactor_fail', 'user1', 1);
		$this->dao->persist('twofactor_u2f', 'user1', 1);
		$this->dao->persist('twofactor_fail', 'user2', 0);
		$this->dao->persist('twofactor_u2f', 'user2', 0);

		$deleted = $this->dao->deleteByUser('user1');

		$this->assertEquals(
			[
				[
					'uid' => 'user1',
					'provider_id' => 'twofactor_fail',
					'enabled' => true,
				],
				[
					'uid' => 'user1',
					'provider_id' => 'twofactor_u2f',
					'enabled' => true,
				],
			],
			$deleted
		);
		$statesUser1 = $this->dao->getState('user1');
		$statesUser2 = $this->dao->getState('user2');
		$this->assertCount(0, $statesUser1);
		$this->assertCount(2, $statesUser2);
	}

	public function testDeleteAll() {
		$this->dao->persist('twofactor_fail', 'user1', 1);
		$this->dao->persist('twofactor_u2f', 'user1', 1);
		$this->dao->persist('twofactor_fail', 'user2', 0);
		$this->dao->persist('twofactor_u2f', 'user1', 0);

		$this->dao->deleteAll('twofactor_fail');

		$statesUser1 = $this->dao->getState('user1');
		$statesUser2 = $this->dao->getState('user2');
		$this->assertCount(1, $statesUser1);
		$this->assertCount(0, $statesUser2);
	}
}
