<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Files_Sharing\Notification;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var INotificationManager */
	protected $notificationManager;
	/** @var IShareManager */
	protected $shareManager;
	/** @var IGroupManager */
	protected $groupManager;

	public function __construct(
		INotificationManager $notificationManager,
		IShareManager $shareManager,
		IGroupManager $groupManager
	) {
		$this->notificationManager = $notificationManager;
		$this->shareManager = $shareManager;
		$this->groupManager = $groupManager;
	}

	/**
	 * @param GenericEvent $event
	 */
	public function shareNotification(GenericEvent $event): void {
		/** @var IShare $share */
		$share = $event->getSubject();
		$notification = $this->instantiateNotification($share);

		if ($share->getShareType() === IShare::TYPE_USER) {
			$notification->setSubject(Notifier::INCOMING_USER_SHARE)
				->setUser($share->getSharedWith());
			$this->notificationManager->notify($notification);
		} else if ($share->getShareType() === IShare::TYPE_GROUP) {
			$notification->setSubject(Notifier::INCOMING_GROUP_SHARE);
			$group = $this->groupManager->get($share->getSharedWith());

			foreach ($group->getUsers() as $user) {
				if ($user->getUID() === $share->getShareOwner() ||
					$user->getUID() === $share->getSharedBy()) {
					continue;
				}

				$notification->setUser($user->getUID());
				$this->notificationManager->notify($notification);
			}
		}
	}

	/**
	 * @param GenericEvent $event
	 */
	public function userAddedToGroup(GenericEvent $event): void {
		/** @var IGroup $group */
		$group = $event->getSubject();
		/** @var IUser $user */
		$user = $event->getArgument('user');

		$offset = 0;
		while (true) {
			$shares = $this->shareManager->getSharedWith($user->getUID(), IShare::TYPE_GROUP, null, 50, $offset);
			if (empty($shares)) {
				break;
			}

			foreach ($shares as $share) {
				if ($share->getSharedWith() !== $group->getGID()) {
					continue;
				}

				if ($user->getUID() === $share->getShareOwner() ||
					$user->getUID() === $share->getSharedBy()) {
					continue;
				}

				$notification = $this->instantiateNotification($share);
				$notification->setSubject(Notifier::INCOMING_GROUP_SHARE)
					->setUser($user->getUID());
				$this->notificationManager->notify($notification);
			}
			$offset += 50;
		}
	}

	/**
	 * @param IShare $share
	 * @return INotification
	 */
	protected function instantiateNotification(IShare $share): INotification {
		$notification = $this->notificationManager->createNotification();
		$notification
			->setApp('files_sharing')
			->setObject('share', $share->getFullId())
			->setDateTime($share->getShareTime());

		return $notification;
	}
}
