<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Tests\Db;

use OCA\TwoFactorBackupCodes\Db\BackupCode;
use OCA\TwoFactorBackupCodes\Db\BackupCodeMapper;
use OCP\IDBConnection;
use OCP\IUser;
use Test\TestCase;

/**
 * @group DB
 */
class BackupCodeMapperTest extends TestCase {

	/** @var IDBConnection */
	private $db;

	/** @var BackupCodeMapper */
	private $mapper;

	/** @var string */
	private $testUID = 'test123456';

	private function resetDB() {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->mapper->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($this->testUID)));
		$qb->execute();
	}

	protected function setUp(): void {
		parent::setUp();

		$this->db = \OC::$server->getDatabaseConnection();
		$this->mapper = \OC::$server->query(BackupCodeMapper::class);

		$this->resetDB();
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->resetDB();
	}

	public function testGetBackupCodes() {
		$code1 = new BackupCode();
		$code1->setUserId($this->testUID);
		$code1->setCode('1|$2y$10$Fyo.DkMtkaHapVvRVbQBeeIdi5x/6nmPnxiBzD0GDKa08NMus5xze');
		$code1->setUsed(1);

		$code2 = new BackupCode();
		$code2->setUserId($this->testUID);
		$code2->setCode('1|$2y$10$nj3sZaCqGN8t6.SsnNADt.eX34UCkdX6FPx.r.rIwE6Jj3vi5wyt2');
		$code2->setUsed(0);

		$this->mapper->insert($code1);
		$this->mapper->insert($code2);

		$user = $this->getMockBuilder(IUser::class)->getMock();
		$user->expects($this->once())
			->method('getUID')
			->willReturn($this->testUID);

		$dbCodes = $this->mapper->getBackupCodes($user);

		$this->assertCount(2, $dbCodes);
		$this->assertInstanceOf(BackupCode::class, $dbCodes[0]);
		$this->assertInstanceOf(BackupCode::class, $dbCodes[1]);
	}

	public function testDeleteCodes() {
		$code = new BackupCode();
		$code->setUserId($this->testUID);
		$code->setCode('1|$2y$10$CagG8pEhZL.xDirtCCP/KuuWtnsAasgq60zY9rU46dBK4w8yW0Z/y');
		$code->setUsed(1);
		$user = $this->getMockBuilder(IUser::class)->getMock();
		$user->expects($this->any())
			->method('getUID')
			->willReturn($this->testUID);

		$this->mapper->insert($code);

		$this->assertCount(1, $this->mapper->getBackupCodes($user));

		$this->mapper->deleteCodes($user);

		$this->assertCount(0, $this->mapper->getBackupCodes($user));
	}

	public function testInsertArgonEncryptedCodes() {
		$code = new BackupCode();
		$code->setUserId($this->testUID);
		$code->setCode('2|$argon2i$v=19$m=1024,t=2,p=2$MjJWUjRFWndtMm5BWGxOag$BusVxLeFyiLLWtaVvX/JRFBiPdZcjRrzpQ/rAhn6vqY');
		$code->setUsed(1);

		$this->mapper->insert($code);
	}

}
