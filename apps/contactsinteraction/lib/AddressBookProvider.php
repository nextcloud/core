<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace OCA\ContactsInteraction;

use OCA\ContactsInteraction\AppInfo\Application;
use OCA\ContactsInteraction\Db\RecentContactMapper;
use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;
use OCP\IL10N;

class AddressBookProvider implements IAddressBookProvider {

	/** @var RecentContactMapper */
	private $mapper;

	/** @var IL10N */
	private $l10n;

	public function __construct(RecentContactMapper $mapper, IL10N $l10n) {
		$this->mapper = $mapper;
		$this->l10n = $l10n;
	}

	/**
	 * @inheritDoc
	 */
	public function getAppId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchAllForAddressBookHome(string $principalUri): array {
		return [
			new AddressBook($this->mapper, $this->l10n, $principalUri)
		];
	}

	/**
	 * @inheritDoc
	 */
	public function hasAddressBookInAddressBookHome(string $principalUri, string $uri): bool {
		return $uri === AddressBook::URI;
	}

	/**
	 * @inheritDoc
	 */
	public function getAddressBookInAddressBookHome(string $principalUri, string $uri): ?ExternalAddressBook {
		if ($uri === AddressBook::URI) {
			return new AddressBook($this->mapper, $this->l10n, $principalUri);
		}

		return null;
	}

}
