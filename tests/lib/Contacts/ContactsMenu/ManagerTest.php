<?php

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace Tests\Contacts\ContactsMenu;

use OC\Contacts\ContactsMenu\ActionProviderStore;
use OC\Contacts\ContactsMenu\ContactsStore;
use OC\Contacts\ContactsMenu\Manager;
use OCP\App\IAppManager;
use OCP\Constants;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\Contacts\ContactsMenu\IProvider;
use OCP\IConfig;
use OCP\IUser;
use Test\TestCase;

class ManagerTest extends TestCase {

	/** @var ContactsStore|\PHPUnit\Framework\MockObject\MockObject */
	private $contactsStore;

	/** @var IAppManager|\PHPUnit\Framework\MockObject\MockObject */
	private $appManager;

	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var ActionProviderStore|\PHPUnit\Framework\MockObject\MockObject */
	private $actionProviderStore;

	/** @var Manager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->contactsStore = $this->createMock(ContactsStore::class);
		$this->actionProviderStore = $this->createMock(ActionProviderStore::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->config = $this->createMock(IConfig::class);

		$this->manager = new Manager($this->contactsStore, $this->actionProviderStore, $this->appManager, $this->config);
	}

	private function generateTestEntries() {
		$entries = [];
		foreach (range('Z', 'A') as $char) {
			$entry = $this->createMock(IEntry::class);
			$entry->expects($this->any())
				->method('getFullName')
				->willReturn('Contact ' . $char);
			$entries[] = $entry;
		}
		return $entries;
	}

	public function testGetFilteredEntries() {
		$filter = 'con';
		$user = $this->createMock(IUser::class);
		$entries = $this->generateTestEntries();
		$provider = $this->createMock(IProvider::class);

		$this->config->expects($this->at(0))
			->method('getSystemValueInt')
			->with('sharing.maxAutocompleteResults', Constants::SHARING_MAX_AUTOCOMPLETE_RESULTS_DEFAULT)
			->willReturn(25);
		$this->config->expects($this->at(1))
			->method('getSystemValueInt')
			->with('sharing.minSearchStringLength', 0)
			->willReturn(0);
		$this->contactsStore->expects($this->once())
			->method('getContacts')
			->with($user, $filter)
			->willReturn($entries);
		$this->actionProviderStore->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([$provider]);
		$provider->expects($this->exactly(25))
			->method('process');
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with($this->equalTo('contacts'), $user)
			->willReturn(false);
		$expected = [
			'contacts' => array_slice($entries, 0, 25),
			'contactsAppEnabled' => false,
		];

		$data = $this->manager->getEntries($user, $filter);

		$this->assertEquals($expected, $data);
	}

	public function testGetFilteredEntriesLimit() {
		$filter = 'con';
		$user = $this->createMock(IUser::class);
		$entries = $this->generateTestEntries();
		$provider = $this->createMock(IProvider::class);

		$this->config->expects($this->at(0))
			->method('getSystemValueInt')
			->with('sharing.maxAutocompleteResults', Constants::SHARING_MAX_AUTOCOMPLETE_RESULTS_DEFAULT)
			->willReturn(3);
		$this->config->expects($this->at(1))
			->method('getSystemValueInt')
			->with('sharing.minSearchStringLength', 0)
			->willReturn(0);
		$this->contactsStore->expects($this->once())
			->method('getContacts')
			->with($user, $filter)
			->willReturn($entries);
		$this->actionProviderStore->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([$provider]);
		$provider->expects($this->exactly(3))
			->method('process');
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with($this->equalTo('contacts'), $user)
			->willReturn(false);
		$expected = [
			'contacts' => array_slice($entries, 0, 3),
			'contactsAppEnabled' => false,
		];

		$data = $this->manager->getEntries($user, $filter);

		$this->assertEquals($expected, $data);
	}

	public function testGetFilteredEntriesMinSearchStringLength() {
		$filter = 'con';
		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);

		$this->config->expects($this->at(0))
			->method('getSystemValueInt')
			->with('sharing.maxAutocompleteResults', Constants::SHARING_MAX_AUTOCOMPLETE_RESULTS_DEFAULT)
			->willReturn(3);
		$this->config->expects($this->at(1))
			->method('getSystemValueInt')
			->with('sharing.minSearchStringLength', 0)
			->willReturn(4);
		$this->appManager->expects($this->once())
			->method('isEnabledForUser')
			->with($this->equalTo('contacts'), $user)
			->willReturn(false);
		$expected = [
			'contacts' => [],
			'contactsAppEnabled' => false,
		];

		$data = $this->manager->getEntries($user, $filter);

		$this->assertEquals($expected, $data);
	}

	public function testFindOne() {
		$shareTypeFilter = 42;
		$shareWithFilter = 'foobar';

		$user = $this->createMock(IUser::class);
		$entry = current($this->generateTestEntries());
		$provider = $this->createMock(IProvider::class);
		$this->contactsStore->expects($this->once())
			->method('findOne')
			->with($user, $shareTypeFilter, $shareWithFilter)
			->willReturn($entry);
		$this->actionProviderStore->expects($this->once())
			->method('getProviders')
			->with($user)
			->willReturn([$provider]);
		$provider->expects($this->once())
			->method('process');

		$data = $this->manager->findOne($user, $shareTypeFilter, $shareWithFilter);

		$this->assertEquals($entry, $data);
	}

	public function testFindOne404() {
		$shareTypeFilter = 42;
		$shareWithFilter = 'foobar';

		$user = $this->createMock(IUser::class);
		$provider = $this->createMock(IProvider::class);
		$this->contactsStore->expects($this->once())
			->method('findOne')
			->with($user, $shareTypeFilter, $shareWithFilter)
			->willReturn(null);
		$this->actionProviderStore->expects($this->never())
			->method('getProviders')
			->with($user)
			->willReturn([$provider]);
		$provider->expects($this->never())
			->method('process');

		$data = $this->manager->findOne($user, $shareTypeFilter, $shareWithFilter);

		$this->assertEquals(null, $data);
	}
}
