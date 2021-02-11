<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing\Tests\Migration;

use OCA\Files_Sharing\Migration\SetPasswordColumn;
use OCA\Files_Sharing\Tests\TestCase;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Share\IShare;

/**
 * Class SetPasswordColumnTest
 *
 * @group DB
 */
class SetPasswordColumnTest extends TestCase {

	/** @var \OCP\IDBConnection */
	private $connection;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var SetPasswordColumn */
	private $migration;

	private $table = 'share';

	protected function setUp(): void {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->migration = new SetPasswordColumn($this->connection, $this->config);

		$this->cleanDB();
	}

	protected function tearDown(): void {
		parent::tearDown();
		$this->cleanDB();
	}

	private function cleanDB() {
		$query = $this->connection->getQueryBuilder();
		$query->delete($this->table)->execute();
	}

	public function testAddPasswordColumn() {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('files_sharing', 'installed_version', '0.0.0')
			->willReturn('1.3.0');

		$shareTypes = [IShare::TYPE_USER, IShare::TYPE_GROUP, IShare::TYPE_REMOTE, IShare::TYPE_EMAIL, IShare::TYPE_LINK];

		foreach ($shareTypes as $shareType) {
			for ($i = 0; $i < 5; $i++) {
				$query = $this->connection->getQueryBuilder();
				$query->insert($this->table)
					->values([
						'share_type' => $query->createNamedParameter($shareType),
						'share_with' => $query->createNamedParameter('shareWith'),
						'uid_owner' => $query->createNamedParameter('user' . $i),
						'uid_initiator' => $query->createNamedParameter(null),
						'parent' => $query->createNamedParameter(0),
						'item_type' => $query->createNamedParameter('file'),
						'item_source' => $query->createNamedParameter('2'),
						'item_target' => $query->createNamedParameter('/2'),
						'file_source' => $query->createNamedParameter(2),
						'file_target' => $query->createNamedParameter('/foobar'),
						'permissions' => $query->createNamedParameter(31),
						'stime' => $query->createNamedParameter(time()),
					]);

				$this->assertSame(1, $query->execute());
			}
		}

		/** @var IOutput $output */
		$output = $this->createMock(IOutput::class);
		$this->migration->run($output);

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('share');
		$result = $query->execute();
		$allShares = $result->fetchAll();
		$result->closeCursor();

		foreach ($allShares as $share) {
			if ((int)$share['share_type'] === IShare::TYPE_LINK) {
				$this->assertNull($share['share_with']);
				$this->assertSame('shareWith', $share['password']);
			} else {
				$this->assertSame('shareWith', $share['share_with']);
				$this->assertNull($share['password']);
			}
		}
	}
}
