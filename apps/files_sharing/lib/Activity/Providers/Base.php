<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Sharing\Activity\Providers;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Federation\ICloudIdManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

abstract class Base implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var IContactsManager */
	protected $contactsManager;

	/** @var ICloudIdManager */
	protected $cloudIdManager;

	/** @var array */
	protected $displayNames = [];

	public function __construct(IFactory $languageFactory,
								IURLGenerator $url,
								IManager $activityManager,
								IUserManager $userManager,
								ICloudIdManager $cloudIdManager,
								IContactsManager $contactsManager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->cloudIdManager = $cloudIdManager;
		$this->contactsManager = $contactsManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'files_sharing') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('files_sharing', $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	abstract protected function parseShortVersion(IEvent $event);

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	abstract protected function parseLongVersion(IEvent $event);

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 */
	protected function setSubjects(IEvent $event, $subject, array $parameters) {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'file') {
				$replacements[] = $parameter['path'];
			} else {
				$replacements[] = $parameter['name'];
			}
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}

	/**
	 * @param array|string $parameter
	 * @param IEvent|null $event
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function getFile($parameter, IEvent $event = null) {
		if (is_array($parameter)) {
			$path = reset($parameter);
			$id = (string) key($parameter);
		} else if ($event !== null) {
			// Legacy from before ownCloud 8.2
			$path = $parameter;
			$id = $event->getObjectId();
		} else {
			throw new \InvalidArgumentException('Could not generate file parameter');
		}

		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => trim($path, '/'),
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	/**
	 * @param string $uid
	 * @return array
	 */
	protected function getUser($uid) {
		// First try local user
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return [
				'type' => 'user',
				'id' => $user->getUID(),
				'name' => $user->getDisplayName(),
			];
		}

		// Then a contact from the addressbook
		if ($this->cloudIdManager->isValidCloudId($uid)) {
			$cloudId = $this->cloudIdManager->resolveCloudId($uid);
			return [
				'type' => 'user',
				'id' => $cloudId->getUser(),
				'name' => $this->getDisplayNameFromAddressBook($cloudId->getDisplayId()),
				'server' => $cloudId->getRemote(),
			];
		}

		// Fallback to empty dummy data
		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $uid,
		];
	}

	protected function getDisplayNameFromAddressBook(string $search): string {
		if (isset($this->displayNames[$search])) {
			return $this->displayNames[$search];
		}

		$addressBookContacts = $this->contactsManager->search($search, ['CLOUD']);
		foreach ($addressBookContacts as $contact) {
			if (isset($contact['isLocalSystemBook'])) {
				continue;
			}

			if (isset($contact['CLOUD'])) {
				$cloudIds = $contact['CLOUD'];
				if (is_string($cloudIds)) {
					$cloudIds = [$cloudIds];
				}

				$lowerSearch = strtolower($search);
				foreach ($cloudIds as $cloudId) {
					if (strtolower($cloudId) === $lowerSearch) {
						$this->displayNames[$search] = $contact['FN'] . " ($cloudId)";
						return $this->displayNames[$search];
					}
				}
			}
		}

		return $search;
	}
}
