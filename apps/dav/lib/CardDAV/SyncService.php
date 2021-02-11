<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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
use OCP\AppFramework\Http;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use Sabre\DAV\Client;
use Sabre\DAV\Xml\Response\MultiStatus;
use Sabre\DAV\Xml\Service;
use Sabre\HTTP\ClientHttpException;
use Sabre\VObject\Reader;

class SyncService {

	/** @var CardDavBackend */
	private $backend;

	/** @var IUserManager */
	private $userManager;

	/** @var ILogger */
	private $logger;

	/** @var array */
	private $localSystemAddressBook;

	/** @var AccountManager */
	private $accountManager;

	/** @var string */
	protected $certPath;

	/**
	 * SyncService constructor.
	 *
	 * @param CardDavBackend $backend
	 * @param IUserManager $userManager
	 * @param ILogger $logger
	 * @param AccountManager $accountManager
	 */
	public function __construct(CardDavBackend $backend, IUserManager $userManager, ILogger $logger, AccountManager $accountManager) {
		$this->backend = $backend;
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->accountManager = $accountManager;
		$this->certPath = '';
	}

	/**
	 * @param string $url
	 * @param string $userName
	 * @param string $addressBookUrl
	 * @param string $sharedSecret
	 * @param string $syncToken
	 * @param int $targetBookId
	 * @param string $targetPrincipal
	 * @param array $targetProperties
	 * @return string
	 * @throws \Exception
	 */
	public function syncRemoteAddressBook($url, $userName, $addressBookUrl, $sharedSecret, $syncToken, $targetBookId, $targetPrincipal, $targetProperties) {
		// 1. create addressbook
		$book = $this->ensureSystemAddressBookExists($targetPrincipal, $targetBookId, $targetProperties);
		$addressBookId = $book['id'];

		// 2. query changes
		try {
			$response = $this->requestSyncReport($url, $userName, $addressBookUrl, $sharedSecret, $syncToken);
		} catch (ClientHttpException $ex) {
			if ($ex->getCode() === Http::STATUS_UNAUTHORIZED) {
				// remote server revoked access to the address book, remove it
				$this->backend->deleteAddressBook($addressBookId);
				$this->logger->info('Authorization failed, remove address book: ' . $url, ['app' => 'dav']);
				throw $ex;
			}
		}

		// 3. apply changes
		// TODO: use multi-get for download
		foreach ($response['response'] as $resource => $status) {
			$cardUri = basename($resource);
			if (isset($status[200])) {
				$vCard = $this->download($url, $userName, $sharedSecret, $resource);
				$existingCard = $this->backend->getCard($addressBookId, $cardUri);
				if ($existingCard === false) {
					$this->backend->createCard($addressBookId, $cardUri, $vCard['body']);
				} else {
					$this->backend->updateCard($addressBookId, $cardUri, $vCard['body']);
				}
			} else {
				$this->backend->deleteCard($addressBookId, $cardUri);
			}
		}

		return $response['token'];
	}

	/**
	 * @param string $principal
	 * @param string $id
	 * @param array $properties
	 * @return array|null
	 * @throws \Sabre\DAV\Exception\BadRequest
	 */
	public function ensureSystemAddressBookExists($principal, $id, $properties) {
		$book = $this->backend->getAddressBooksByUri($principal, $id);
		if (!is_null($book)) {
			return $book;
		}
		$this->backend->createAddressBook($principal, $id, $properties);

		return $this->backend->getAddressBooksByUri($principal, $id);
	}

	/**
	 * Check if there is a valid certPath we should use
	 *
	 * @return string
	 */
	protected function getCertPath() {

		// we already have a valid certPath
		if ($this->certPath !== '') {
			return $this->certPath;
		}

		$certManager = \OC::$server->getCertificateManager();
		$certPath = $certManager->getAbsoluteBundlePath();
		if (file_exists($certPath)) {
			$this->certPath = $certPath;
		}

		return $this->certPath;
	}

	/**
	 * @param string $url
	 * @param string $userName
	 * @param string $addressBookUrl
	 * @param string $sharedSecret
	 * @return Client
	 */
	protected function getClient($url, $userName, $sharedSecret) {
		$settings = [
			'baseUri' => $url . '/',
			'userName' => $userName,
			'password' => $sharedSecret,
		];
		$client = new Client($settings);
		$certPath = $this->getCertPath();
		$client->setThrowExceptions(true);

		if ($certPath !== '' && strpos($url, 'http://') !== 0) {
			$client->addCurlSetting(CURLOPT_CAINFO, $this->certPath);
		}

		return $client;
	}

	/**
	 * @param string $url
	 * @param string $userName
	 * @param string $addressBookUrl
	 * @param string $sharedSecret
	 * @param string $syncToken
	 * @return array
	 */
	protected function requestSyncReport($url, $userName, $addressBookUrl, $sharedSecret, $syncToken) {
		$client = $this->getClient($url, $userName, $sharedSecret);

		$body = $this->buildSyncCollectionRequestBody($syncToken);

		$response = $client->request('REPORT', $addressBookUrl, $body, [
			'Content-Type' => 'application/xml'
		]);

		return $this->parseMultiStatus($response['body']);
	}

	/**
	 * @param string $url
	 * @param string $userName
	 * @param string $sharedSecret
	 * @param string $resourcePath
	 * @return array
	 */
	protected function download($url, $userName, $sharedSecret, $resourcePath) {
		$client = $this->getClient($url, $userName, $sharedSecret);
		return $client->request('GET', $resourcePath);
	}

	/**
	 * @param string|null $syncToken
	 * @return string
	 */
	private function buildSyncCollectionRequestBody($syncToken) {
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;
		$root = $dom->createElementNS('DAV:', 'd:sync-collection');
		$sync = $dom->createElement('d:sync-token', $syncToken);
		$prop = $dom->createElement('d:prop');
		$cont = $dom->createElement('d:getcontenttype');
		$etag = $dom->createElement('d:getetag');

		$prop->appendChild($cont);
		$prop->appendChild($etag);
		$root->appendChild($sync);
		$root->appendChild($prop);
		$dom->appendChild($root);
		return $dom->saveXML();
	}

	/**
	 * @param string $body
	 * @return array
	 * @throws \Sabre\Xml\ParseException
	 */
	private function parseMultiStatus($body) {
		$xml = new Service();

		/** @var MultiStatus $multiStatus */
		$multiStatus = $xml->expect('{DAV:}multistatus', $body);

		$result = [];
		foreach ($multiStatus->getResponses() as $response) {
			$result[$response->getHref()] = $response->getResponseProperties();
		}

		return ['response' => $result, 'token' => $multiStatus->getSyncToken()];
	}

	/**
	 * @param IUser $user
	 */
	public function updateUser(IUser $user) {
		$systemAddressBook = $this->getLocalSystemAddressBook();
		$addressBookId = $systemAddressBook['id'];
		$converter = new Converter($this->accountManager);
		$name = $user->getBackendClassName();
		$userId = $user->getUID();

		$cardId = "$name:$userId.vcf";
		$card = $this->backend->getCard($addressBookId, $cardId);
		if ($user->isEnabled()) {
			if ($card === false) {
				$vCard = $converter->createCardFromUser($user);
				if ($vCard !== null) {
					$this->backend->createCard($addressBookId, $cardId, $vCard->serialize());
				}
			} else {
				$vCard = $converter->createCardFromUser($user);
				if (is_null($vCard)) {
					$this->backend->deleteCard($addressBookId, $cardId);
				} else {
					$this->backend->updateCard($addressBookId, $cardId, $vCard->serialize());
				}
			}
		} else {
			$this->backend->deleteCard($addressBookId, $cardId);
		}
	}

	/**
	 * @param IUser|string $userOrCardId
	 */
	public function deleteUser($userOrCardId) {
		$systemAddressBook = $this->getLocalSystemAddressBook();
		if ($userOrCardId instanceof IUser) {
			$name = $userOrCardId->getBackendClassName();
			$userId = $userOrCardId->getUID();

			$userOrCardId = "$name:$userId.vcf";
		}
		$this->backend->deleteCard($systemAddressBook['id'], $userOrCardId);
	}

	/**
	 * @return array|null
	 */
	public function getLocalSystemAddressBook() {
		if (is_null($this->localSystemAddressBook)) {
			$systemPrincipal = "principals/system/system";
			$this->localSystemAddressBook = $this->ensureSystemAddressBookExists($systemPrincipal, 'system', [
				'{' . Plugin::NS_CARDDAV . '}addressbook-description' => 'System addressbook which holds all users of this instance'
			]);
		}

		return $this->localSystemAddressBook;
	}

	public function syncInstance(\Closure $progressCallback = null) {
		$systemAddressBook = $this->getLocalSystemAddressBook();
		$this->userManager->callForAllUsers(function ($user) use ($systemAddressBook, $progressCallback) {
			$this->updateUser($user);
			if (!is_null($progressCallback)) {
				$progressCallback();
			}
		});

		// remove no longer existing
		$allCards = $this->backend->getCards($systemAddressBook['id']);
		foreach ($allCards as $card) {
			$vCard = Reader::read($card['carddata']);
			$uid = $vCard->UID->getValue();
			// load backend and see if user exists
			if (!$this->userManager->userExists($uid)) {
				$this->deleteUser($card['uri']);
			}
		}
	}
}
