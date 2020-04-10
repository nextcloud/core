<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Federation;

use OC\OCS\DiscoveryService;
use OCA\DAV\CardDAV\SyncService;
use OCP\AppFramework\Http;
use OCP\OCS\IDiscoveryService;

class SyncFederationAddressBooks {

	/** @var DbHandler */
	protected $dbHandler;

	/** @var SyncService */
	private $syncService;

	/** @var  DiscoveryService */
	private $ocsDiscoveryService;

	/**
	 * @param DbHandler $dbHandler
	 * @param SyncService $syncService
	 * @param IDiscoveryService $ocsDiscoveryService
	 */
	public function __construct(DbHandler $dbHandler,
								SyncService $syncService,
								IDiscoveryService $ocsDiscoveryService
	) {
		$this->syncService = $syncService;
		$this->dbHandler = $dbHandler;
		$this->ocsDiscoveryService = $ocsDiscoveryService;
	}

	/**
	 * @param \Closure $callback
	 */
	public function syncThemAll(\Closure $callback) {

		$trustedServers = $this->dbHandler->getAllServer();
		foreach ($trustedServers as $trustedServer) {
			$url = $trustedServer['url'];
			$callback($url, null);
			$sharedSecret = $trustedServer['shared_secret'];
			$syncToken = $trustedServer['sync_token'];

			$endPoints = $this->ocsDiscoveryService->discover($url, 'FEDERATED_SHARING');
			$cardDavUser = isset($endPoints['carddav-user']) ? $endPoints['carddav-user'] : 'system';
			$addressBookUrl = isset($endPoints['system-address-book']) ? trim($endPoints['system-address-book'], '/') : 'remote.php/dav/addressbooks/system/system/system';

			if (is_null($sharedSecret)) {
				continue;
			}
			$targetBookId = $trustedServer['url_hash'];
			$targetPrincipal = "principals/system/system";
			$targetBookProperties = [
				'{DAV:}displayname' => $url
			];
			try {
				$newToken = $this->syncService->syncRemoteAddressBook($url, $cardDavUser, $addressBookUrl, $sharedSecret, $syncToken, $targetBookId, $targetPrincipal, $targetBookProperties);
				if ($newToken !== $syncToken) {
					$this->dbHandler->setServerStatus($url, TrustedServers::STATUS_OK, $newToken);
				}
			} catch (\Exception $ex) {
				if ($ex->getCode() === Http::STATUS_UNAUTHORIZED) {
					$this->dbHandler->setServerStatus($url, TrustedServers::STATUS_ACCESS_REVOKED);
				}
				$callback($url, $ex);
			}
		}
	}
}
