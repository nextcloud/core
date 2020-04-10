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

use Exception;
use OCA\ContactsInteraction\AppInfo\Application;
use OCA\ContactsInteraction\Db\RecentContact;
use OCA\ContactsInteraction\Db\RecentContactMapper;
use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCA\DAV\DAV\Sharing\Plugin;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\ACLTrait;
use Sabre\DAVACL\IACL;

class AddressBook extends ExternalAddressBook implements IACL {

	public const URI = 'recent';

	use ACLTrait;

	/** @var RecentContactMapper */
	private $mapper;

	/** @var IL10N */
	private $l10n;

	/** @var string */
	private $principalUri;

	public function __construct(RecentContactMapper $mapper,
								IL10N $l10n,
								string $principalUri) {
		parent::__construct(Application::APP_ID, self::URI);

		$this->mapper = $mapper;
		$this->l10n = $l10n;
		$this->principalUri = $principalUri;
	}

	/**
	 * @inheritDoc
	 */
	public function delete(): void {
		throw new Exception("This addressbook is immutable");
	}

	/**
	 * @inheritDoc
	 */
	function createFile($name, $data = null) {
		throw new Exception("This addressbook is immutable");
	}

	/**
	 * @inheritDoc
	 * @throws NotFound
	 */
	public function getChild($name) {
		try {
			return new Card(
				$this->mapper->find(
					$this->getUid(),
					(int)$name
				),
				$this->principalUri,
				$this->getACL()
			);
		} catch (DoesNotExistException $ex) {
			throw new NotFound("Contact does not exist: " . $ex->getMessage(), 0, $ex);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getChildren(): array {
		return array_map(
			function (RecentContact $contact) {
				return new Card(
					$contact,
					$this->principalUri,
					$this->getACL()
				);
			},
			$this->mapper->findAll($this->getUid())
		);
	}

	/**
	 * @inheritDoc
	 */
	public function childExists($name) {
		try {
			$this->mapper->find(
				$this->getUid(),
				(int)$name
			);
			return true;
		} catch (DoesNotExistException $e) {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getLastModified() {
		throw new NotImplemented();
	}

	/**
	 * @inheritDoc
	 */
	public function propPatch(PropPatch $propPatch) {
		throw new Exception("This addressbook is immutable");
	}

	/**
	 * @inheritDoc
	 */
	public function getProperties($properties) {
		return [
			'principaluri' => $this->principalUri,
			'{DAV:}displayname' => $this->l10n->t('Recently contacted'),
			'{' . Plugin::NS_OWNCLOUD . '}read-only' => true,
		];
	}

	public function getOwner(): string {
		return $this->principalUri;
	}

	/**
	 * @inheritDoc
	 */
	public function getACL() {
		return [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
		];
	}

	private function getUid(): string {
		list(, $uid) = \Sabre\Uri\split($this->principalUri);
		return $uid;
	}

}
