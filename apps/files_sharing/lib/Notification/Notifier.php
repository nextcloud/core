<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Sharing\Notification;

use OCP\Files\IRootFolder;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;

class Notifier implements INotifier {
	public const INCOMING_USER_SHARE = 'incoming_user_share';
	public const INCOMING_GROUP_SHARE = 'incoming_group_share';

	/** @var IFactory */
	protected $l10nFactory;
	/** @var IManager */
	private $shareManager;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IGroupManager  */
	protected $groupManager;
	/** @var IUserManager  */
	protected $userManager;
	/** @var IURLGenerator */
	protected $url;

	public function __construct(IFactory $l10nFactory,
								IManager $shareManager,
								IRootFolder $rootFolder,
								IGroupManager $groupManager,
								IUserManager $userManager,
								IURLGenerator $url) {
		$this->l10nFactory = $l10nFactory;
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->url = $url;
	}

	/**
	 * Identifier of the notifier, only use [a-z0-9_]
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getID(): string {
		return 'files_sharing';
	}

	/**
	 * Human readable name describing the notifier
	 *
	 * @return string
	 * @since 17.0.0
	 */
	public function getName(): string {
		return $this->l10nFactory->get('files_sharing')->t('File sharing');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 * @throws AlreadyProcessedException When the notification is not needed anymore and should be deleted
	 * @since 9.0.0
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'files_sharing' ||
			($notification->getSubject() !== 'expiresTomorrow' &&
				$notification->getObjectType() !== 'share')) {
			throw new \InvalidArgumentException('Unhandled app or subject');
		}

		$l = $this->l10nFactory->get('files_sharing', $languageCode);
		$attemptId = $notification->getObjectId();

		try {
			$share = $this->shareManager->getShareById($attemptId, $notification->getUser());
		} catch (ShareNotFound $e) {
			throw new AlreadyProcessedException();
		}

		if ($notification->getSubject() === 'expiresTomorrow') {
			$notification = $this->parseShareExpiration($share, $notification, $l);
		} else {
			$notification = $this->parseShareInvitation($share, $notification, $l);
		}
		return $notification;
	}

	protected function parseShareExpiration(IShare $share, INotification $notification, IL10N $l): INotification {
		$node = $share->getNode();
		$userFolder = $this->rootFolder->getUserFolder($notification->getUser());
		$path = $userFolder->getRelativePath($node->getPath());

		$notification
			->setParsedSubject($l->t('Share will expire tomorrow'))
			->setParsedMessage($l->t('One or more of your shares will expire tomorrow'))
			->setRichMessage(
				$l->t('Your share of {node} will expire tomorrow'),
				[
					'node' => [
						'type' => 'file',
						'id' => $node->getId(),
						'name' => $node->getName(),
						'path' => $path,
					],
				]
			);

		return $notification;
	}

	protected function parseShareInvitation(IShare $share, INotification $notification, IL10N $l): INotification {
		if ($share->getShareType() === IShare::TYPE_USER) {
			if ($share->getStatus() !== IShare::STATUS_PENDING) {
				throw new AlreadyProcessedException();
			}
		} elseif ($share->getShareType() === IShare::TYPE_GROUP) {
			if ($share->getStatus() !== IShare::STATUS_PENDING) {
				throw new AlreadyProcessedException();
			}
		}

		switch ($notification->getSubject()) {
			case self::INCOMING_USER_SHARE:
				if ($share->getSharedWith() !== $notification->getUser()) {
					throw new AlreadyProcessedException();
				}

				$sharer = $this->userManager->get($share->getSharedBy());
				if (!$sharer instanceof IUser) {
					throw new \InvalidArgumentException('Temporary failure');
				}

				$subject = $l->t('You received {share} as a share by {user}');
				$subjectParameters = [
					'share' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $share->getTarget(),
					],
					'user' => [
						'type' => 'user',
						'id' => $sharer->getUID(),
						'name' => $sharer->getDisplayName(),
					],
				];
				break;

			case self::INCOMING_GROUP_SHARE:
				$user = $this->userManager->get($notification->getUser());
				if (!$user instanceof IUser) {
					throw new AlreadyProcessedException();
				}

				$group = $this->groupManager->get($share->getSharedWith());
				if (!$group->inGroup($user)) {
					throw new AlreadyProcessedException();
				}

				if ($share->getPermissions() === 0) {
					// Already rejected
					throw new AlreadyProcessedException();
				}

				$sharer = $this->userManager->get($share->getSharedBy());
				if (!$sharer instanceof IUser) {
					throw new \InvalidArgumentException('Temporary failure');
				}

				$subject = $l->t('You received {share} to group {group} as a share by {user}');
				$subjectParameters = [
					'share' => [
						'type' => 'highlight',
						'id' => $notification->getObjectId(),
						'name' => $share->getTarget(),
					],
					'group' => [
						'type' => 'user-group',
						'id' => $group->getGID(),
						'name' => $group->getDisplayName(),
					],
					'user' => [
						'type' => 'user',
						'id' => $sharer->getUID(),
						'name' => $sharer->getDisplayName(),
					],
				];
				break;

			default:
				throw new \InvalidArgumentException('Invalid subject');
		}

		$placeholders = $replacements = [];
		foreach ($subjectParameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$notification->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $subjectParameters)
			->setIcon($this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/share.svg')));

		$acceptAction = $notification->createAction();
		$acceptAction->setParsedLabel($l->t('Accept'))
			->setLink($this->url->linkToOCSRouteAbsolute('files_sharing.ShareAPI.acceptShare', ['id' => $share->getId()]), 'POST')
			->setPrimary(true);
		$notification->addParsedAction($acceptAction);

		$rejectAction = $notification->createAction();
		$rejectAction->setParsedLabel($l->t('Reject'))
			->setLink($this->url->linkToOCSRouteAbsolute('files_sharing.ShareAPI.deleteShare', ['id' => $share->getId()]), 'DELETE')
			->setPrimary(false);
		$notification->addParsedAction($rejectAction);

		return $notification;
	}
}
