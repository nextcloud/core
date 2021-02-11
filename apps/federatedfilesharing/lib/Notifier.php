<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use OCP\Contacts\IManager;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	/** @var IFactory */
	protected $factory;
	/** @var IManager */
	protected $contactsManager;
	/** @var IURLGenerator */
	protected $url;
	/** @var array */
	protected $federatedContacts;
	/** @var ICloudIdManager */
	protected $cloudIdManager;

	/**
	 * @param IFactory $factory
	 * @param IManager $contactsManager
	 * @param IURLGenerator $url
	 * @param ICloudIdManager $cloudIdManager
	 */
	public function __construct(IFactory $factory, IManager $contactsManager, IURLGenerator $url, ICloudIdManager $cloudIdManager) {
		$this->factory = $factory;
		$this->contactsManager = $contactsManager;
		$this->url = $url;
		$this->cloudIdManager = $cloudIdManager;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'federatedfilesharing';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->factory->get('federatedfilesharing')->t('Federated sharing');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'files_sharing' || $notification->getObjectType() !== 'remote_share') {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		// Read the language from the notification
		$l = $this->factory->get('files_sharing', $languageCode);

		switch ($notification->getSubject()) {
			// Deal with known subjects
			case 'remote_share':
				$notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));

				$params = $notification->getSubjectParameters();
				if ($params[0] !== $params[1] && $params[1] !== null) {
					$remoteInitiator = $this->createRemoteUser($params[0]);
					$remoteOwner = $this->createRemoteUser($params[1]);
					$params[3] = $remoteInitiator['name'] . '@' . $remoteInitiator['server'];
					$params[4] = $remoteOwner['name'] . '@' . $remoteOwner['server'];

					$notification->setParsedSubject(
						$l->t('You received "%3$s" as a remote share from %4$s (%1$s) (on behalf of %5$s (%2$s))', $params)
					);

					$notification->setRichSubject(
						$l->t('You received {share} as a remote share from {user} (on behalf of {behalf})'),
						[
							'share' => [
								'type' => 'pending-federated-share',
								'id' => $notification->getObjectId(),
								'name' => $params[2],
							],
							'user' => $remoteInitiator,
							'behalf' => $remoteOwner,
						]
					);
				} else {
					$remoteOwner = $this->createRemoteUser($params[0]);
					$params[3] = $remoteOwner['name'] . '@' . $remoteOwner['server'];

					$notification->setParsedSubject(
						$l->t('You received "%3$s" as a remote share from %4$s (%1$s)', $params)
					);


					$notification->setRichSubject(
						$l->t('You received {share} as a remote share from {user}'),
						[
							'share' => [
								'type' => 'pending-federated-share',
								'id' => $notification->getObjectId(),
								'name' => $params[2],
							],
							'user' => $remoteOwner,
						]
					);
				}

				// Deal with the actions for a known subject
				foreach ($notification->getActions() as $action) {
					switch ($action->getLabel()) {
						case 'accept':
							$action->setParsedLabel(
								$l->t('Accept')
							)
								->setPrimary(true);
							break;

						case 'decline':
							$action->setParsedLabel(
								$l->t('Decline')
							);
							break;
					}

					$notification->addParsedAction($action);
				}
				return $notification;

			default:
				// Unknown subject => Unknown notification => throw
				throw new \InvalidArgumentException();
		}
	}

	/**
	 * @param string $cloudId
	 * @return array
	 */
	protected function createRemoteUser($cloudId, $displayName = null) {
		try {
			$resolvedId = $this->cloudIdManager->resolveCloudId($cloudId);
			if ($displayName === null) {
				$displayName = $this->getDisplayName($resolvedId);
			}
			$user = $resolvedId->getUser();
			$server = $resolvedId->getRemote();
		} catch (HintException $e) {
			$user = $cloudId;
			$displayName = $cloudId;
			$server = '';
		}

		return [
			'type' => 'user',
			'id' => $user,
			'name' => $displayName,
			'server' => $server,
		];
	}

	/**
	 * Try to find the user in the contacts
	 *
	 * @param ICloudId $cloudId
	 * @return string
	 */
	protected function getDisplayName(ICloudId $cloudId): string {
		$server = $cloudId->getRemote();
		$user = $cloudId->getUser();
		if (strpos($server, 'http://') === 0) {
			$server = substr($server, strlen('http://'));
		} elseif (strpos($server, 'https://') === 0) {
			$server = substr($server, strlen('https://'));
		}

		try {
			// contains protocol in the  ID
			return $this->getDisplayNameFromContact($cloudId->getId());
		} catch (\OutOfBoundsException $e) {
		}

		try {
			// does not include protocol, as stored in addressbooks
			return $this->getDisplayNameFromContact($cloudId->getDisplayId());
		} catch (\OutOfBoundsException $e) {
		}

		try {
			return $this->getDisplayNameFromContact($user . '@http://' . $server);
		} catch (\OutOfBoundsException $e) {
		}

		try {
			return $this->getDisplayNameFromContact($user . '@https://' . $server);
		} catch (\OutOfBoundsException $e) {
		}

		return $cloudId->getId();
	}

	/**
	 * Try to find the user in the contacts
	 *
	 * @param string $federatedCloudId
	 * @return string
	 * @throws \OutOfBoundsException when there is no contact for the id
	 */
	protected function getDisplayNameFromContact($federatedCloudId) {
		if (isset($this->federatedContacts[$federatedCloudId])) {
			if ($this->federatedContacts[$federatedCloudId] !== '') {
				return $this->federatedContacts[$federatedCloudId];
			} else {
				throw new \OutOfBoundsException('No contact found for federated cloud id');
			}
		}

		$addressBookEntries = $this->contactsManager->search($federatedCloudId, ['CLOUD']);
		foreach ($addressBookEntries as $entry) {
			if (isset($entry['CLOUD'])) {
				foreach ($entry['CLOUD'] as $cloudID) {
					if ($cloudID === $federatedCloudId) {
						$this->federatedContacts[$federatedCloudId] = $entry['FN'];
						return $entry['FN'];
					}
				}
			}
		}

		$this->federatedContacts[$federatedCloudId] = '';
		throw new \OutOfBoundsException('No contact found for federated cloud id');
	}
}
