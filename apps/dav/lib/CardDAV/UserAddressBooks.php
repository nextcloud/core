<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

use OCA\DAV\AppInfo\PluginManager;
use OCA\DAV\CardDAV\Integration\IAddressBookProvider;
use OCA\DAV\CardDAV\Integration\ExternalAddressBook;
use OCP\IConfig;
use OCP\IL10N;
use Sabre\CardDAV\Backend;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\CardDAV\IAddressBook;
use function array_map;
use Sabre\DAV\MkCol;

class UserAddressBooks extends \Sabre\CardDAV\AddressBookHome {

	/** @var IL10N */
	protected $l10n;

	/** @var IConfig */
	protected $config;

	/** @var PluginManager */
	private $pluginManager;

	public function __construct(Backend\BackendInterface $carddavBackend,
								string $principalUri,
								PluginManager $pluginManager) {
		parent::__construct($carddavBackend, $principalUri);
		$this->pluginManager = $pluginManager;
	}

	/**
	 * Returns a list of address books
	 *
	 * @return IAddressBook[]
	 */
	function getChildren() {
		if ($this->l10n === null) {
			$this->l10n = \OC::$server->getL10N('dav');
		}
		if ($this->config === null) {
			$this->config = \OC::$server->getConfig();
		}

		$addressBooks = $this->carddavBackend->getAddressBooksForUser($this->principalUri);
		/** @var IAddressBook[] $objects */
		$objects = array_map(function (array $addressBook) {
			if ($addressBook['principaluri'] === 'principals/system/system') {
				return new SystemAddressbook($this->carddavBackend, $addressBook, $this->l10n, $this->config);
			}

			return new AddressBook($this->carddavBackend, $addressBook, $this->l10n);
		}, $addressBooks);
		/** @var IAddressBook[][] $objectsFromPlugins */
		$objectsFromPlugins = array_map(function (IAddressBookProvider $plugin): array {
			return $plugin->fetchAllForAddressBookHome($this->principalUri);
		}, $this->pluginManager->getAddressBookPlugins());

		return array_merge($objects, ...$objectsFromPlugins);
	}

	public function createExtendedCollection($name, MkCol $mkCol) {
		if (ExternalAddressBook::doesViolateReservedName($name)) {
			throw new MethodNotAllowed('The resource you tried to create has a reserved name');
		}

		parent::createExtendedCollection($name, $mkCol);
	}

	/**
	 * Returns a list of ACE's for this node.
	 *
	 * Each ACE has the following properties:
	 *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
	 *     currently the only supported privileges
	 *   * 'principal', a url to the principal who owns the node
	 *   * 'protected' (optional), indicating that this ACE is not allowed to
	 *      be updated.
	 *
	 * @return array
	 */
	function getACL() {

		$acl = parent::getACL();
		if ($this->principalUri === 'principals/system/system') {
			$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => '{DAV:}authenticated',
				'protected' => true,
			];
		}

		return $acl;
	}

}
