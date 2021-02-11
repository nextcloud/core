<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
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

namespace Test\Federation;

use OC\Federation\CloudIdManager;
use OCP\Contacts\IManager;
use Test\TestCase;

class CloudIdManagerTest extends TestCase {
	/** @var IManager|\PHPUnit\Framework\MockObject\MockObject */
	protected $contactsManager;
	/** @var CloudIdManager */
	private $cloudIdManager;

	protected function setUp(): void {
		parent::setUp();

		$this->contactsManager = $this->createMock(IManager::class);

		$this->cloudIdManager = new CloudIdManager($this->contactsManager);
	}

	public function cloudIdProvider() {
		return [
			['test@example.com', 'test', 'example.com', 'test@example.com'],
			['test@example.com/cloud', 'test', 'example.com/cloud', 'test@example.com/cloud'],
			['test@example.com/cloud/', 'test', 'example.com/cloud', 'test@example.com/cloud'],
			['test@example.com/cloud/index.php', 'test', 'example.com/cloud', 'test@example.com/cloud'],
			['test@example.com@example.com', 'test@example.com', 'example.com', 'test@example.com@example.com'],
		];
	}

	/**
	 * @dataProvider cloudIdProvider
	 *
	 * @param string $cloudId
	 * @param string $user
	 * @param string $remote
	 */
	public function testResolveCloudId($cloudId, $user, $remote, $cleanId) {
		$displayName = 'Ample Ex';

		$this->contactsManager->expects($this->any())
			->method('search')
			->with($cleanId, ['CLOUD'])
			->willReturn([
				[
					'CLOUD' => [$cleanId],
					'FN' => 'Ample Ex',
				]
			]);

		$cloudId = $this->cloudIdManager->resolveCloudId($cloudId);

		$this->assertEquals($user, $cloudId->getUser());
		$this->assertEquals($remote, $cloudId->getRemote());
		$this->assertEquals($cleanId, $cloudId->getId());
		$this->assertEquals($displayName . '@' . $remote, $cloudId->getDisplayId());
	}

	public function invalidCloudIdProvider() {
		return [
			['example.com'],
			['test:foo@example.com'],
			['test/foo@example.com']
		];
	}

	/**
	 * @dataProvider invalidCloudIdProvider
	 *
	 * @param string $cloudId
	 *
	 */
	public function testInvalidCloudId($cloudId) {
		$this->expectException(\InvalidArgumentException::class);

		$this->contactsManager->expects($this->never())
			->method('search');

		$this->cloudIdManager->resolveCloudId($cloudId);
	}

	public function getCloudIdProvider() {
		return [
			['test', 'example.com', 'test@example.com'],
			['test@example.com', 'example.com', 'test@example.com@example.com'],
		];
	}

	/**
	 * @dataProvider getCloudIdProvider
	 *
	 * @param string $user
	 * @param string $remote
	 * @param string $id
	 */
	public function testGetCloudId($user, $remote, $id) {
		$this->contactsManager->expects($this->any())
			->method('search')
			->with($id, ['CLOUD'])
			->willReturn([
				[
					'CLOUD' => [$id],
					'FN' => 'Ample Ex',
				]
			]);

		$cloudId = $this->cloudIdManager->getCloudId($user, $remote);

		$this->assertEquals($id, $cloudId->getId());
	}
}
