<?php
/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Contacts\ContactsMenu;

use OCP\App\IAppManager;
use OCP\Constants;
use OCP\Contacts\ContactsMenu\IEntry;
use OCP\IConfig;
use OCP\IUser;

class Manager {

	/** @var ContactsStore */
	private $store;

	/** @var ActionProviderStore */
	private $actionProviderStore;

	/** @var IAppManager */
	private $appManager;

	/** @var IConfig */
	private $config;

	/**
	 * @param ContactsStore $store
	 * @param ActionProviderStore $actionProviderStore
	 * @param IAppManager $appManager
	 */
	public function __construct(ContactsStore $store, ActionProviderStore $actionProviderStore, IAppManager $appManager, IConfig $config) {
		$this->store = $store;
		$this->actionProviderStore = $actionProviderStore;
		$this->appManager = $appManager;
		$this->config = $config;
	}

	/**
	 * @param IUser $user
	 * @param string $filter
	 * @return array
	 */
	public function getEntries(IUser $user, $filter) {
		$maxAutocompleteResults = max(0, $this->config->getSystemValueInt('sharing.maxAutocompleteResults', Constants::SHARING_MAX_AUTOCOMPLETE_RESULTS_DEFAULT));
		$minSearchStringLength = $this->config->getSystemValueInt('sharing.minSearchStringLength', 0);
		$topEntries = [];
		if (strlen($filter) >= $minSearchStringLength) {
			$entries = $this->store->getContacts($user, $filter, $maxAutocompleteResults);

			$sortedEntries = $this->sortEntries($entries);
			$topEntries = array_slice($sortedEntries, 0, $maxAutocompleteResults);
			$this->processEntries($topEntries, $user);
		}

		$contactsEnabled = $this->appManager->isEnabledForUser('contacts', $user);
		return [
			'contacts' => $topEntries,
			'contactsAppEnabled' => $contactsEnabled,
		];
	}

	/**
	 * @param IUser $user
	 * @param integer $shareType
	 * @param string $shareWith
	 * @return IEntry
	 */
	public function findOne(IUser $user, $shareType, $shareWith) {
		$entry = $this->store->findOne($user, $shareType, $shareWith);
		if ($entry) {
			$this->processEntries([$entry], $user);
		}

		return $entry;
	}

	/**
	 * @param IEntry[] $entries
	 * @return IEntry[]
	 */
	private function sortEntries(array $entries) {
		usort($entries, function (IEntry $entryA, IEntry $entryB) {
			return strcasecmp($entryA->getFullName(), $entryB->getFullName());
		});
		return $entries;
	}

	/**
	 * @param IEntry[] $entries
	 * @param IUser $user
	 */
	private function processEntries(array $entries, IUser $user) {
		$providers = $this->actionProviderStore->getProviders($user);
		foreach ($entries as $entry) {
			foreach ($providers as $provider) {
				$provider->process($entry);
			}
		}
	}
}
