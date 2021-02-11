<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Daniel Kesselberg <mail@danielkesselberg.de>
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

namespace OCA\ContactsInteraction\Tests\Db;

use OCA\ContactsInteraction\Db\RecentContact;
use OCA\ContactsInteraction\Db\RecentContactMapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUser;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\UUIDUtil;
use Test\TestCase;

/**
 * @group DB
 */
class RecentContactMapperTest extends TestCase {

	/** @var RecentContactMapper */
	private $recentContactMapper;

	/** @var ITimeFactory */
	private $time;

	protected function setUp(): void {
		parent::setUp();

		$this->recentContactMapper = \OC::$server->get(RecentContactMapper::class);
		$this->time = \OC::$server->get(ITimeFactory::class);
	}

	protected function tearDown(): void {
		parent::tearDown();

		$this->recentContactMapper->cleanUp(time());
	}

	public function testCreateRecentContact(): void {
		$contact = $this->createRecentContact();
		$this->assertNotNull($contact->getId());
	}

	public function testFindAll(): void {
		$this->createRecentContact();
		$this->createRecentContact();

		$contacts = $this->recentContactMapper->findAll('admin');
		$this->assertCount(2, $contacts);
	}

	public function testFindMatchByEmail(): void {
		$this->createRecentContact();
		$contact = $this->createRecentContact('foo@bar');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('admin');

		$result = $this->recentContactMapper->findMatch($user, null, 'foo@bar', null);

		$this->assertCount(1, $result);
		$this->assertEquals($contact->getId(), $result[0]->getId());
	}

	public function testFindMatchByFederatedCloudId(): void {
		$this->createRecentContact();
		$contact = $this->createRecentContact(null, 'foo.bar');

		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('admin');

		$result = $this->recentContactMapper->findMatch($user, null, null, 'foo.bar');

		$this->assertCount(1, $result);
		$this->assertEquals($contact->getId(), $result[0]->getId());
	}

	public function testCleanUp(): void {
		$this->createRecentContact();
		$this->createRecentContact();
		$this->assertCount(2, $this->recentContactMapper->findAll('admin'));

		$this->recentContactMapper->cleanUp(time());
		$this->assertCount(0, $this->recentContactMapper->findAll('admin'));
	}

	protected function createRecentContact(string $email = null, string $federatedCloudId = null): RecentContact {
		$props = [
			'URI' => UUIDUtil::getUUID(),
			'FN' => 'Foo Bar',
			'CATEGORIES' => 'Recently contacted',
		];

		$time = $this->time->getDateTime();
		$time->modify('-14days');

		$contact = new RecentContact();
		$contact->setActorUid('admin');
		$contact->setCard((new VCard($props))->serialize());
		$contact->setLastContact($time->getTimestamp());

		if ($email !== null) {
			$contact->setEmail($email);
		}

		if ($federatedCloudId !== null) {
			$contact->setFederatedCloudId($federatedCloudId);
		}

		return $this->recentContactMapper->insert($contact);
	}
}
