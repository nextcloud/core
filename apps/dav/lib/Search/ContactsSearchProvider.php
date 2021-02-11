<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
namespace OCA\DAV\Search;

use OCA\DAV\CardDAV\CardDavBackend;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

class ContactsSearchProvider implements IProvider {

	/** @var IAppManager */
	private $appManager;

	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var CardDavBackend */
	private $backend;

	/**
	 * @var string[]
	 */
	private static $searchProperties = [
		'N',
		'FN',
		'NICKNAME',
		'EMAIL',
		'TEL',
		'ADR',
		'TITLE',
		'ORG',
		'NOTE',
	];

	/**
	 * ContactsSearchProvider constructor.
	 *
	 * @param IAppManager $appManager
	 * @param IL10N $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param CardDavBackend $backend
	 */
	public function __construct(IAppManager $appManager,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								CardDavBackend $backend) {
		$this->appManager = $appManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->backend = $backend;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'contacts';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Contacts');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'contacts.Page.index') {
			return -1;
		}
		return 25;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('contacts', $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$principalUri = 'principals/users/' . $user->getUID();
		$addressBooks = $this->backend->getAddressBooksForUser($principalUri);
		$addressBooksById = [];
		foreach ($addressBooks as $addressBook) {
			$addressBooksById[(int) $addressBook['id']] = $addressBook;
		}

		$searchResults = $this->backend->searchPrincipalUri(
			$principalUri,
			$query->getTerm(),
			self::$searchProperties,
			[
				'limit' => $query->getLimit(),
				'offset' => $query->getCursor(),
			]
		);
		$formattedResults = \array_map(function (array $contactRow) use ($addressBooksById):SearchResultEntry {
			$addressBook = $addressBooksById[$contactRow['addressbookid']];

			/** @var VCard $vCard */
			$vCard = Reader::read($contactRow['carddata']);
			$thumbnailUrl = '';
			if ($vCard->PHOTO) {
				$thumbnailUrl = $this->getDavUrlForContact($addressBook['principaluri'], $addressBook['uri'], $contactRow['uri']) . '?photo';
			}

			$title = (string)$vCard->FN;
			$subline = $this->generateSubline($vCard);
			$resourceUrl = $this->getDeepLinkToContactsApp($addressBook['uri'], (string) $vCard->UID);

			return new SearchResultEntry($thumbnailUrl, $title, $subline, $resourceUrl, 'icon-contacts-dark', true);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
		);
	}

	/**
	 * @param string $principalUri
	 * @param string $addressBookUri
	 * @param string $contactsUri
	 * @return string
	 */
	protected function getDavUrlForContact(string $principalUri,
										   string $addressBookUri,
										   string $contactsUri): string {
		[, $principalType, $principalId] = explode('/', $principalUri, 3);

		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkTo('', 'remote.php') . '/dav/addressbooks/'
				. $principalType . '/'
				. $principalId . '/'
				. $addressBookUri . '/'
				. $contactsUri
		);
	}

	/**
	 * @param string $addressBookUri
	 * @param string $contactUid
	 * @return string
	 */
	protected function getDeepLinkToContactsApp(string $addressBookUri,
												string $contactUid): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('contacts.contacts.direct', [
				'contact' => $contactUid . '~' . $addressBookUri
			])
		);
	}

	/**
	 * @param VCard $vCard
	 * @return string
	 */
	protected function generateSubline(VCard $vCard): string {
		$emailAddresses = $vCard->select('EMAIL');
		if (!is_array($emailAddresses) || empty($emailAddresses)) {
			return '';
		}

		return (string)$emailAddresses[0];
	}
}
