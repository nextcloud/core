<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files_Sharing\Tests;

use OCA\Files_Sharing\ExpireSharesJob;

/**
 * Class ExpireSharesJobTest
 *
 * @group DB
 *
 * @package OCA\Files_Sharing\Tests
 */
class ExpireSharesJobTest extends \Test\TestCase {

	/**
	 * @var ExpireSharesJob
	 */
	private $job;

	/**
	 * @var \OCP\IDBConnection
	 */
	private $connection;

	/**
	 * @var string
	 */
	private $user1;

	/**
	 * @var string
	 */
	private $user2;

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		// clear occasional leftover shares from other tests
		$this->connection->executeUpdate('DELETE FROM `*PREFIX*share`');

		$this->user1 = $this->getUniqueID('user1_');
		$this->user2 = $this->getUniqueID('user2_');

		$userManager = \OC::$server->getUserManager();
		$userManager->createUser($this->user1, 'pass');
		$userManager->createUser($this->user2, 'pass');

		\OC::registerShareHooks();

		$this->job = new ExpireSharesJob();
	}

	protected function tearDown(): void {
		$this->connection->executeUpdate('DELETE FROM `*PREFIX*share`');

		$userManager = \OC::$server->getUserManager();
		$user1 = $userManager->get($this->user1);
		if($user1) {
			$user1->delete();
		}
		$user2 = $userManager->get($this->user2);
		if($user2) {
			$user2->delete();
		}

		$this->logout();

		parent::tearDown();
	}

	private function getShares() {
		$shares = [];
		$qb = $this->connection->getQueryBuilder();

		$result = $qb->select('*')
			->from('share')
			->execute();

		while ($row = $result->fetch()) {
			$shares[] = $row;
		}
		$result->closeCursor();
		return $shares;
	}

	public function dataExpireLinkShare() {
		return [
			[false,   '', false, false],
			[false,   '',  true, false],
			[true, 'P1D', false,  true],
			[true, 'P1D',  true, false],
			[true, 'P1W', false,  true],
			[true, 'P1W',  true, false],
			[true, 'P1M', false,  true],
			[true, 'P1M',  true, false],
			[true, 'P1Y', false,  true],
			[true, 'P1Y',  true, false],
		];
	}

	/**
	 * @dataProvider dataExpireLinkShare
	 *
	 * @param bool addExpiration Should we add an expire date
	 * @param string $interval The dateInterval
	 * @param bool $addInterval If true add to the current time if false subtract
	 * @param bool $shouldExpire Should this share be expired
	 */
	public function testExpireLinkShare($addExpiration, $interval, $addInterval, $shouldExpire) {
		$this->loginAsUser($this->user1);

		$user1Folder = \OC::$server->getUserFolder($this->user1);
		$testFolder = $user1Folder->newFolder('test');

		$shareManager = \OC::$server->getShareManager();
		$share = $shareManager->newShare();

		$share->setNode($testFolder)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setSharedBy($this->user1);

		$shareManager->createShare($share);

		$shares = $this->getShares();
		$this->assertCount(1, $shares);
		reset($shares);
		$share = current($shares);

		if ($addExpiration) {
			$expire = new \DateTime();
			$expire->setTime(0, 0, 0);
			if ($addInterval) {
				$expire->add(new \DateInterval($interval));
			} else {
				$expire->sub(new \DateInterval($interval));
			}
			$expire = $expire->format('Y-m-d 00:00:00');

			// Set expiration date to yesterday
			$qb = $this->connection->getQueryBuilder();
			$qb->update('share')
				->set('expiration', $qb->createParameter('expiration'))
				->where($qb->expr()->eq('id', $qb->createParameter('id')))
				->setParameter('id', $share['id'])
				->setParameter('expiration', $expire)
				->execute();

			$shares = $this->getShares();
			$this->assertCount(1, $shares);
		}

		$this->logout();

		$this->job->run([]);

		$shares = $this->getShares();

		if ($shouldExpire) {
			$this->assertCount(0, $shares);
		} else {
			$this->assertCount(1, $shares);
		}
	}

	public function testDoNotExpireOtherShares() {
		$this->loginAsUser($this->user1);

		$user1Folder = \OC::$server->getUserFolder($this->user1);
		$testFolder = $user1Folder->newFolder('test');

		$shareManager = \OC::$server->getShareManager();
		$share = $shareManager->newShare();

		$share->setNode($testFolder)
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setPermissions(\OCP\Constants::PERMISSION_READ)
			->setSharedBy($this->user1)
			->setSharedWith($this->user2);

		$shareManager->createShare($share);

		$shares = $this->getShares();
		$this->assertCount(1, $shares);

		$this->logout();

		$this->job->run([]);

		$shares = $this->getShares();
		$this->assertCount(1, $shares);
	}

}
