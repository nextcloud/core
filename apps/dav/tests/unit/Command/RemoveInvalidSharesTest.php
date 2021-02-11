<?php
/**
 * @copyright Copyright (c) 2018, ownCloud GmbH
 *
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

namespace OCA\DAV\Tests\Unit\Command;

use OCA\DAV\Command\RemoveInvalidShares;
use OCA\DAV\Connector\Sabre\Principal;
use OCP\Migration\IOutput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

/**
 * Class RemoveInvalidSharesTest
 *
 * @package OCA\DAV\Tests\Unit\Repair
 * @group DB
 */
class RemoveInvalidSharesTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$db = \OC::$server->getDatabaseConnection();

		$db->insertIfNotExist('*PREFIX*dav_shares', [
			'principaluri' => 'principal:unknown',
			'type' => 'calendar',
			'access' => 2,
			'resourceid' => 666,
		]);
	}

	public function test() {
		$db = \OC::$server->getDatabaseConnection();
		/** @var Principal | \PHPUnit\Framework\MockObject\MockObject $principal */
		$principal = $this->createMock(Principal::class);

		/** @var IOutput | \PHPUnit\Framework\MockObject\MockObject $output */
		$output = $this->createMock(IOutput::class);

		$repair = new RemoveInvalidShares($db, $principal);
		$this->invokePrivate($repair, 'run', [$this->createMock(InputInterface::class), $this->createMock(OutputInterface::class)]);

		$query = $db->getQueryBuilder();
		$result = $query->select('*')->from('dav_shares')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter('principal:unknown')))->execute();
		$data = $result->fetchAll();
		$result->closeCursor();
		$this->assertEquals(0, count($data));
	}
}
