<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\FederatedFileSharing;

use OC\HintException;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * Class AddressHandler - parse, modify and construct federated sharing addresses
 *
 * @package OCA\FederatedFileSharing
 */
class AddressHandler {

	/** @var IL10N */
	private $l;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ICloudIdManager */
	private $cloudIdManager;

	/**
	 * AddressHandler constructor.
	 *
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $il10n
	 * @param ICloudIdManager $cloudIdManager
	 */
	public function __construct(
		IURLGenerator $urlGenerator,
		IL10N $il10n,
		ICloudIdManager $cloudIdManager
	) {
		$this->l = $il10n;
		$this->urlGenerator = $urlGenerator;
		$this->cloudIdManager = $cloudIdManager;
	}

	/**
	 * split user and remote from federated cloud id
	 *
	 * @param string $address federated share address
	 * @return array [user, remoteURL]
	 * @throws HintException
	 */
	public function splitUserRemote($address) {
		try {
			$cloudId = $this->cloudIdManager->resolveCloudId($address);
			return [$cloudId->getUser(), $cloudId->getRemote()];
		} catch (\InvalidArgumentException $e) {
			$hint = $this->l->t('Invalid Federated Cloud ID');
			throw new HintException('Invalid Federated Cloud ID', $hint, 0, $e);
		}
	}

	/**
	 * generate remote URL part of federated ID
	 *
	 * @return string url of the current server
	 */
	public function generateRemoteURL() {
		return $this->urlGenerator->getAbsoluteURL('/');
	}

	/**
	 * check if two federated cloud IDs refer to the same user
	 *
	 * @param string $user1
	 * @param string $server1
	 * @param string $user2
	 * @param string $server2
	 * @return bool true if both users and servers are the same
	 */
	public function compareAddresses($user1, $server1, $user2, $server2) {
		$normalizedServer1 = strtolower($this->removeProtocolFromUrl($server1));
		$normalizedServer2 = strtolower($this->removeProtocolFromUrl($server2));

		if (rtrim($normalizedServer1, '/') === rtrim($normalizedServer2, '/')) {
			// FIXME this should be a method in the user management instead
			\OCP\Util::emitHook(
				'\OCA\Files_Sharing\API\Server2Server',
				'preLoginNameUsedAsUserName',
				['uid' => &$user1]
			);
			\OCP\Util::emitHook(
				'\OCA\Files_Sharing\API\Server2Server',
				'preLoginNameUsedAsUserName',
				['uid' => &$user2]
			);

			if ($user1 === $user2) {
				return true;
			}
		}

		return false;
	}

	/**
	 * remove protocol from URL
	 *
	 * @param string $url
	 * @return string
	 */
	public function removeProtocolFromUrl($url) {
		if (strpos($url, 'https://') === 0) {
			return substr($url, strlen('https://'));
		} else if (strpos($url, 'http://') === 0) {
			return substr($url, strlen('http://'));
		}

		return $url;
	}

	/**
	 * check if the url contain the protocol (http or https)
	 *
	 * @param string $url
	 * @return bool
	 */
	public function urlContainProtocol($url) {
		if (strpos($url, 'https://') === 0 ||
			strpos($url, 'http://') === 0) {

			return true;
		}

		return false;
	}
}
