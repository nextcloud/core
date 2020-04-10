<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\DAV\CardDAV;

use OC\Accounts\AccountManager;
use OCP\IImage;
use OCP\IUser;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property\Text;

class Converter {

	/** @var AccountManager */
	private $accountManager;

	/**
	 * Converter constructor.
	 *
	 * @param AccountManager $accountManager
	 */
	public function __construct(AccountManager $accountManager) {
		$this->accountManager = $accountManager;
	}

	/**
	 * @param IUser $user
	 * @return VCard|null
	 */
	public function createCardFromUser(IUser $user) {

		$userData = $this->accountManager->getUser($user);

		$uid = $user->getUID();
		$cloudId = $user->getCloudId();
		$image = $this->getAvatarImage($user);

		$vCard = new VCard();
		$vCard->VERSION = '3.0';
		$vCard->UID = $uid;

		$publish = false;

		if ($image !== null && isset($userData[AccountManager::PROPERTY_AVATAR])) {
			$userData[AccountManager::PROPERTY_AVATAR]['value'] = true;
		}

		foreach ($userData as $property => $value) {

			$shareWithTrustedServers =
				$value['scope'] === AccountManager::VISIBILITY_CONTACTS_ONLY ||
				$value['scope'] === AccountManager::VISIBILITY_PUBLIC;

			$emptyValue = !isset($value['value']) || $value['value'] === '';

			if ($shareWithTrustedServers && !$emptyValue) {
				$publish = true;
				switch ($property) {
					case AccountManager::PROPERTY_DISPLAYNAME:
						$vCard->add(new Text($vCard, 'FN', $value['value']));
						$vCard->add(new Text($vCard, 'N', $this->splitFullName($value['value'])));
						break;
					case AccountManager::PROPERTY_AVATAR:
						if ($image !== null) {
							$vCard->add('PHOTO', $image->data(), ['ENCODING' => 'b', 'TYPE' => $image->mimeType()]);
						}
						break;
					case AccountManager::PROPERTY_EMAIL:
						$vCard->add(new Text($vCard, 'EMAIL', $value['value'], ['TYPE' => 'OTHER']));
						break;
					case AccountManager::PROPERTY_WEBSITE:
						$vCard->add(new Text($vCard, 'URL', $value['value']));
						break;
					case AccountManager::PROPERTY_PHONE:
						$vCard->add(new Text($vCard, 'TEL', $value['value'], ['TYPE' => 'OTHER']));
						break;
					case AccountManager::PROPERTY_ADDRESS:
						$vCard->add(new Text($vCard, 'ADR', $value['value'], ['TYPE' => 'OTHER']));
						break;
					case AccountManager::PROPERTY_TWITTER:
						$vCard->add(new Text($vCard, 'X-SOCIALPROFILE', $value['value'], ['TYPE' => 'TWITTER']));
						break;
				}
			}
		}

		if ($publish && !empty($cloudId)) {
			$vCard->add(new Text($vCard, 'CLOUD', $cloudId));
			$vCard->validate();
			return $vCard;
		}

		return null;
	}

	/**
	 * @param string $fullName
	 * @return string[]
	 */
	public function splitFullName($fullName) {
		// Very basic western style parsing. I'm not gonna implement
		// https://github.com/android/platform_packages_providers_contactsprovider/blob/master/src/com/android/providers/contacts/NameSplitter.java ;)

		$elements = explode(' ', $fullName);
		$result = ['', '', '', '', ''];
		if (count($elements) > 2) {
			$result[0] = implode(' ', array_slice($elements, count($elements)-1));
			$result[1] = $elements[0];
			$result[2] = implode(' ', array_slice($elements, 1, count($elements)-2));
		} elseif (count($elements) === 2) {
			$result[0] = $elements[1];
			$result[1] = $elements[0];
		} else {
			$result[0] = $elements[0];
		}

		return $result;
	}

	/**
	 * @param IUser $user
	 * @return null|IImage
	 */
	private function getAvatarImage(IUser $user) {
		try {
			return $user->getAvatarImage(-1);
		} catch (\Exception $ex) {
			return null;
		}
	}

}
