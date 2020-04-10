<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Files\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;
	/** @var IL10N */
	protected $activityLang;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/** @var IUserManager */
	protected $userManager;

	/** @var IRootFolder */
	protected $rootFolder;

	/** @var IEventMerger */
	protected $eventMerger;

	/** @var ICloudIdManager */
	protected $cloudIdManager;

	/** @var IContactsManager */
	protected $contactsManager;

	/** @var string[] cached displayNames - key is the cloud id and value the displayname */
	protected $displayNames = [];

	protected $fileIsEncrypted = false;

	public function __construct(IFactory $languageFactory,
								IURLGenerator $url,
								IManager $activityManager,
								IUserManager $userManager,
								IRootFolder $rootFolder,
								ICloudIdManager $cloudIdManager,
								IContactsManager $contactsManager,
								IEventMerger $eventMerger) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->activityManager = $activityManager;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->cloudIdManager = $cloudIdManager;
		$this->contactsManager = $contactsManager;
		$this->eventMerger = $eventMerger;
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
		if ($event->getApp() !== 'files') {
			throw new \InvalidArgumentException();
		}

		$this->l = $this->languageFactory->get('files', $language);
		$this->activityLang = $this->languageFactory->get('activity', $language);

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event, $previousEvent);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event, $previousEvent);
	}

	protected function setIcon(IEvent $event, string $icon, string $app = 'files') {
		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath($app, $icon . '.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath($app, $icon . '.svg')));
		}
	}

	/**
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event, IEvent $previousEvent = null) {
		$parsedParameters = $this->getParameters($event);

		if ($event->getSubject() === 'created_by') {
			$subject = $this->l->t('Created by {user}');
			$this->setIcon($event, 'add-color');
		} else if ($event->getSubject() === 'changed_by') {
			$subject = $this->l->t('Changed by {user}');
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'deleted_by') {
			$subject = $this->l->t('Deleted by {user}');
			$this->setIcon($event, 'delete-color');
		} else if ($event->getSubject() === 'restored_by') {
			$subject = $this->l->t('Restored by {user}');
			$this->setIcon($event, 'actions/history', 'core');
		} else if ($event->getSubject() === 'renamed_by') {
			$subject = $this->l->t('Renamed by {user}');
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'moved_by') {
			$subject = $this->l->t('Moved by {user}');
			$this->setIcon($event, 'change');
		} else {
			throw new \InvalidArgumentException();
		}

		if (!isset($parsedParameters['user'])) {
			// External user via public link share
			$subject = str_replace('{user}', $this->activityLang->t('"remote user"'), $subject);
		}

		$this->setSubjects($event, $subject, $parsedParameters);

		return $this->eventMerger->mergeEvents('user', $event, $previousEvent);
	}

	/**
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event, IEvent $previousEvent = null) {
		$this->fileIsEncrypted = false;
		$parsedParameters = $this->getParameters($event);

		if ($event->getSubject() === 'created_self') {
			$subject = $this->l->t('You created {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('You created an encrypted file in {file}');
			}
			$this->setIcon($event, 'add-color');
		} else if ($event->getSubject() === 'created_by') {
			$subject = $this->l->t('{user} created {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('{user} created an encrypted file in {file}');
			}
			$this->setIcon($event, 'add-color');
		} else if ($event->getSubject() === 'created_public') {
			$subject = $this->l->t('{file} was created in a public folder');
			$this->setIcon($event, 'add-color');
		} else if ($event->getSubject() === 'changed_self') {
			$subject = $this->l->t('You changed {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('You changed an encrypted file in {file}');
			}
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'changed_by') {
			$subject = $this->l->t('{user} changed {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('{user} changed an encrypted file in {file}');
			}
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'deleted_self') {
			$subject = $this->l->t('You deleted {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('You deleted an encrypted file in {file}');
			}
			$this->setIcon($event, 'delete-color');
		} else if ($event->getSubject() === 'deleted_by') {
			$subject = $this->l->t('{user} deleted {file}');
			if ($this->fileIsEncrypted) {
				$subject = $this->l->t('{user} deleted an encrypted file in {file}');
			}
			$this->setIcon($event, 'delete-color');
		} else if ($event->getSubject() === 'restored_self') {
			$subject = $this->l->t('You restored {file}');
			$this->setIcon($event, 'actions/history', 'core');
		} else if ($event->getSubject() === 'restored_by') {
			$subject = $this->l->t('{user} restored {file}');
			$this->setIcon($event, 'actions/history', 'core');
		} else if ($event->getSubject() === 'renamed_self') {
			$subject = $this->l->t('You renamed {oldfile} to {newfile}');
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'renamed_by') {
			$subject = $this->l->t('{user} renamed {oldfile} to {newfile}');
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'moved_self') {
			$subject = $this->l->t('You moved {oldfile} to {newfile}');
			$this->setIcon($event, 'change');
		} else if ($event->getSubject() === 'moved_by') {
			$subject = $this->l->t('{user} moved {oldfile} to {newfile}');
			$this->setIcon($event, 'change');
		} else {
			throw new \InvalidArgumentException();
		}

		if ($this->fileIsEncrypted) {
			$event->setSubject($event->getSubject() . '_enc', $event->getSubjectParameters());
		}

		if (!isset($parsedParameters['user'])) {
			// External user via public link share
			$subject = str_replace('{user}', $this->activityLang->t('"remote user"'), $subject);
		}

		$this->setSubjects($event, $subject, $parsedParameters);

		if ($event->getSubject() === 'moved_self' || $event->getSubject() === 'moved_by') {
			$event = $this->eventMerger->mergeEvents('oldfile', $event, $previousEvent);
		} else {
			$event = $this->eventMerger->mergeEvents('file', $event, $previousEvent);
		}

		if ($event->getChildEvent() === null) {
			// Couldn't group by file, maybe we can group by user
			$event = $this->eventMerger->mergeEvents('user', $event, $previousEvent);
		}

		return $event;
	}

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
	 * @param IEvent $event
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function getParameters(IEvent $event) {
		$parameters = $event->getSubjectParameters();
		switch ($event->getSubject()) {
			case 'created_self':
			case 'created_public':
			case 'changed_self':
			case 'deleted_self':
			case 'restored_self':
				return [
					'file' => $this->getFile($parameters[0], $event),
				];
			case 'created_by':
			case 'changed_by':
			case 'deleted_by':
			case 'restored_by':
				if ($parameters[1] === '') {
					// External user via public link share
					return [
						'file' => $this->getFile($parameters[0], $event),
					];
				}
				return [
					'file' => $this->getFile($parameters[0], $event),
					'user' => $this->getUser($parameters[1]),
				];
			case 'renamed_self':
			case 'moved_self':
				return [
					'newfile' => $this->getFile($parameters[0]),
					'oldfile' => $this->getFile($parameters[1]),
				];
			case 'renamed_by':
			case 'moved_by':
				if ($parameters[1] === '') {
					// External user via public link share
					return [
						'newfile' => $this->getFile($parameters[0]),
						'oldfile' => $this->getFile($parameters[2]),
					];
				}
				return [
					'newfile' => $this->getFile($parameters[0]),
					'user' => $this->getUser($parameters[1]),
					'oldfile' => $this->getFile($parameters[2]),
				];
		}
		return [];
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

		$encryptionContainer = $this->getEndToEndEncryptionContainer($id, $path);
		if ($encryptionContainer instanceof Folder) {
			$this->fileIsEncrypted = true;
			try {
				$fullPath = rtrim($encryptionContainer->getPath(), '/');
				// Remove /user/files/...
				list(,,, $path) = explode('/', $fullPath, 4);
				if (!$path) {
					throw new InvalidPathException('Path could not be split correctly');
				}

				return [
					'type' => 'file',
					'id' => $encryptionContainer->getId(),
					'name' => $encryptionContainer->getName(),
					'path' => $path,
					'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $encryptionContainer->getId()]),
				];
			} catch (\Exception $e) {
				// fall back to the normal one
				$this->fileIsEncrypted = false;
			}
		}

		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => trim($path, '/'),
			'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $id]),
		];
	}

	protected $fileEncrypted = [];

	/**
	 * Check if a file is end2end encrypted
	 * @param int $fileId
	 * @param string $path
	 * @return Folder|null
	 */
	protected function getEndToEndEncryptionContainer($fileId, $path) {
		if (isset($this->fileEncrypted[$fileId])) {
			return $this->fileEncrypted[$fileId];
		}

		$fileName = basename($path);
		if (!preg_match('/^[0-9a-fA-F]{32}$/', $fileName)) {
			$this->fileEncrypted[$fileId] = false;
			return $this->fileEncrypted[$fileId];
		}

		$userFolder = $this->rootFolder->getUserFolder($this->activityManager->getCurrentUserId());
		$files = $userFolder->getById($fileId);
		if (empty($files)) {
			try {
				// Deleted, try with parent
				$file = $this->findExistingParent($userFolder, dirname($path));
			} catch (NotFoundException $e) {
				return null;
			}

			if (!$file instanceof Folder || !$file->isEncrypted()) {
				return null;
			}

			$this->fileEncrypted[$fileId] = $file;
			return $file;
		}

		$file = array_shift($files);

		if ($file instanceof Folder && $file->isEncrypted()) {
			// If the folder is encrypted, it is the Container,
			// but can be the name is just fine.
			$this->fileEncrypted[$fileId] = true;
			return null;
		}

		$this->fileEncrypted[$fileId] = $this->getParentEndToEndEncryptionContainer($userFolder, $file);
		return $this->fileEncrypted[$fileId];
	}

	/**
	 * @param Folder $userFolder
	 * @param string $path
	 * @return Folder
	 * @throws NotFoundException
	 */
	protected function findExistingParent(Folder $userFolder, $path) {
		if ($path === '/') {
			throw new NotFoundException('Reached the root');
		}

		try {
			$folder = $userFolder->get(dirname($path));
		} catch (NotFoundException $e) {
			return $this->findExistingParent($userFolder, dirname($path));
		}

		return $folder;
	}

	/**
	 * Check all parents until the user's root folder if one is encrypted
	 *
	 * @param Folder $userFolder
	 * @param Node $file
	 * @return Node|null
	 */
	protected function getParentEndToEndEncryptionContainer(Folder $userFolder, Node $file) {
		try {
			$parent = $file->getParent();

			if ($userFolder->getId() === $parent->getId()) {
				return null;
			}
		} catch (\Exception $e) {
			return null;
		}

		if ($parent->isEncrypted()) {
			return $parent;
		}

		return $this->getParentEndToEndEncryptionContainer($userFolder, $parent);
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
