<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\DAV\CardDAV;

use OCP\IConfig;
use OCP\IL10N;
use Sabre\CardDAV\Backend\BackendInterface;

class SystemAddressbook extends AddressBook {
	/** @var IConfig */
	private $config;

	public function __construct(BackendInterface $carddavBackend, array $addressBookInfo, IL10N $l10n, IConfig $config) {
		parent::__construct($carddavBackend, $addressBookInfo, $l10n);
		$this->config = $config;
	}

	public function getChildren() {
		$shareEnumeration = $this->config->getAppValue('core', 'shareapi_allow_share_dialog_user_enumeration', 'yes') === 'yes';
		$restrictShareEnumeration = $this->config->getAppValue('core', 'shareapi_restrict_user_enumeration_to_group', 'no') === 'yes';
		if (!$shareEnumeration || ($shareEnumeration && $restrictShareEnumeration)) {
			return [];
		}

		return parent::getChildren();
	}
}
