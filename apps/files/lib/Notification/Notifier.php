<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sascha Wiswedel <sascha.wiswedel@nextcloud.com>
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

namespace OCA\Files\Notification;

use OCA\Files\Db\TransferOwnershipMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\IAction;
use OCP\Notification\IDismissableNotifier;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier, IDismissableNotifier {

	/** @var IFactory */
	protected $l10nFactory;

	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var TransferOwnershipMapper */
	private $mapper;
	/** @var IManager */
	private $notificationManager;
	/** @var IUserManager */
	private $userManager;
	/** @var ITimeFactory */
	private $timeFactory;

	public function __construct(IFactory $l10nFactory,
								IURLGenerator $urlGenerator,
								TransferOwnershipMapper $mapper,
								IManager $notificationManager,
								IUserManager $userManager,
								ITimeFactory $timeFactory) {
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->mapper = $mapper;
		$this->notificationManager = $notificationManager;
		$this->userManager = $userManager;
		$this->timeFactory = $timeFactory;
	}

	public function getID(): string {
		return 'files';
	}

	public function getName(): string {
		return $this->l10nFactory->get('files')->t('Files');
	}

	/**
	 * @param INotification $notification
	 * @param string $languageCode The code of the language that should be used to prepare the notification
	 * @return INotification
	 * @throws \InvalidArgumentException When the notification was not prepared by a notifier
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== 'files') {
			throw new \InvalidArgumentException('Unhandled app');
		}

		if ($notification->getSubject() === 'transferownershipRequest') {
			return $this->handleTransferownershipRequest($notification, $languageCode);
		}
		if ($notification->getSubject() === 'transferOwnershipFailedSource') {
			return $this->handleTransferOwnershipFailedSource($notification, $languageCode);
		}
		if ($notification->getSubject() === 'transferOwnershipFailedTarget') {
			return $this->handleTransferOwnershipFailedTarget($notification, $languageCode);
		}
		if ($notification->getSubject() === 'transferOwnershipDoneSource') {
			return $this->handleTransferOwnershipDoneSource($notification, $languageCode);
		}
		if ($notification->getSubject() === 'transferOwnershipDoneTarget') {
			return $this->handleTransferOwnershipDoneTarget($notification, $languageCode);
		}

		throw new \InvalidArgumentException('Unhandled subject');
	}

	public function handleTransferownershipRequest(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get('files', $languageCode);
		$id = $notification->getObjectId();
		$param = $notification->getSubjectParameters();

		$approveAction = $notification->createAction()
			->setParsedLabel($l->t('Accept'))
			->setPrimary(true)
			->setLink(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkTo(
						'',
						'ocs/v2.php/apps/files/api/v1/transferownership/' . $id
					)
				),
				IAction::TYPE_POST
			);

		$disapproveAction = $notification->createAction()
			->setParsedLabel($l->t('Reject'))
			->setPrimary(false)
			->setLink(
				$this->urlGenerator->getAbsoluteURL(
					$this->urlGenerator->linkTo(
						'',
						'ocs/v2.php/apps/files/api/v1/transferownership/' . $id
					)
				),
				IAction::TYPE_DELETE
			);

		$sourceUser = $this->getUser($param['sourceUser']);
		$notification->addParsedAction($approveAction)
			->addParsedAction($disapproveAction)
			->setRichSubject(
				$l->t('Incoming ownership transfer from {user}'),
				[
					'user' => [
						'type' => 'user',
						'id' => $sourceUser->getUID(),
						'name' => $sourceUser->getDisplayName(),
					],
				])
			->setParsedSubject(str_replace('{user}', $sourceUser->getDisplayName(), $l->t('Incoming ownership transfer from {user}')))
			->setRichMessage(
				$l->t("Do you want to accept {path}?\n\nNote: The transfer process after accepting may take up to 1 hour."),
				[
					'path' => [
						'type' => 'highlight',
						'id' => $param['targetUser'] . '::' . $param['nodeName'],
						'name' => $param['nodeName'],
					]
				])
			->setParsedMessage(str_replace('{path}', $param['nodeName'], $l->t("Do you want to accept {path}?\n\nNote: The transfer process after accepting may take up to 1 hour.")));

		return $notification;
	}

	public function handleTransferOwnershipFailedSource(INotification $notification,  string $languageCode): INotification {
		$l = $this->l10nFactory->get('files', $languageCode);
		$param = $notification->getSubjectParameters();

		$targetUser = $this->getUser($param['targetUser']);
		$notification->setRichSubject($l->t('Ownership transfer failed'))
			->setParsedSubject($l->t('Ownership transfer failed'))

			->setRichMessage(
				$l->t('Your ownership transfer of {path} to {user} failed.'),
				[
					'path' => [
						'type' => 'highlight',
						'id' => $param['targetUser'] . '::' . $param['nodeName'],
						'name' => $param['nodeName'],
					],
					'user' => [
						'type' => 'user',
						'id' => $targetUser->getUID(),
						'name' => $targetUser->getDisplayName(),
					],
				])
			->setParsedMessage(str_replace(['{path}', '{user}'], [$param['nodeName'], $targetUser->getDisplayName()], $l->t('Your ownership transfer of {path} to {user} failed.')));
		return $notification;
	}

	public function handleTransferOwnershipFailedTarget(INotification $notification,  string $languageCode): INotification {
		$l = $this->l10nFactory->get('files', $languageCode);
		$param = $notification->getSubjectParameters();

		$sourceUser = $this->getUser($param['sourceUser']);
		$notification->setRichSubject($l->t('Ownership transfer failed'))
			->setParsedSubject($l->t('Ownership transfer failed'))

			->setRichMessage(
				$l->t('The ownership transfer of {path} from {user} failed.'),
				[
					'path' => [
						'type' => 'highlight',
						'id' => $param['sourceUser'] . '::' . $param['nodeName'],
						'name' => $param['nodeName'],
					],
					'user' => [
						'type' => 'user',
						'id' => $sourceUser->getUID(),
						'name' => $sourceUser->getDisplayName(),
					],
				])
			->setParsedMessage(str_replace(['{path}', '{user}'], [$param['nodeName'], $sourceUser->getDisplayName()], $l->t('The ownership transfer of {path} from {user} failed.')));

		return $notification;
	}

	public function handleTransferOwnershipDoneSource(INotification $notification,  string $languageCode): INotification {
		$l = $this->l10nFactory->get('files', $languageCode);
		$param = $notification->getSubjectParameters();

		$targetUser = $this->getUser($param['targetUser']);
		$notification->setRichSubject($l->t('Ownership transfer done'))
			->setParsedSubject($l->t('Ownership transfer done'))

			->setRichMessage(
				$l->t('Your ownership transfer of {path} to {user} has completed.'),
				[
					'path' => [
						'type' => 'highlight',
						'id' => $param['targetUser'] . '::' . $param['nodeName'],
						'name' => $param['nodeName'],
					],
					'user' => [
						'type' => 'user',
						'id' => $targetUser->getUID(),
						'name' => $targetUser->getDisplayName(),
					],
				])
			->setParsedMessage(str_replace(['{path}', '{user}'], [$param['nodeName'], $targetUser->getDisplayName()], $l->t('Your ownership transfer of {path} to {user} has completed.')));

		return $notification;
	}

	public function handleTransferOwnershipDoneTarget(INotification $notification,  string $languageCode): INotification {
		$l = $this->l10nFactory->get('files', $languageCode);
		$param = $notification->getSubjectParameters();

		$sourceUser = $this->getUser($param['sourceUser']);
		$notification->setRichSubject($l->t('Ownership transfer done'))
			->setParsedSubject($l->t('Ownership transfer done'))

			->setRichMessage(
				$l->t('The ownership transfer of {path} from {user} has completed.'),
				[
					'path' => [
						'type' => 'highlight',
						'id' => $param['sourceUser'] . '::' . $param['nodeName'],
						'name' => $param['nodeName'],
					],
					'user' => [
						'type' => 'user',
						'id' => $sourceUser->getUID(),
						'name' => $sourceUser->getDisplayName(),
					],
				])
			->setParsedMessage(str_replace(['{path}', '{user}'], [$param['nodeName'], $sourceUser->getDisplayName()], $l->t('The ownership transfer of {path} from {user} has completed.')));

		return $notification;
	}

	public function dismissNotification(INotification $notification): void {
		if ($notification->getApp() !== 'files') {
			throw new \InvalidArgumentException('Unhandled app');
		}

		// TODO: This should all be moved to a service that also the transferownershipContoller uses.
		try {
			$transferOwnership = $this->mapper->getById((int)$notification->getObjectId());
		} catch (DoesNotExistException $e) {
			return;
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transferOwnership->getSourceUser())
			->setApp('files')
			->setDateTime($this->timeFactory->getDateTime())
			->setSubject('transferownershipRequestDenied', [
				'sourceUser' => $transferOwnership->getSourceUser(),
				'targetUser' => $transferOwnership->getTargetUser(),
				'nodeName' => $transferOwnership->getNodeName()
			])
			->setObject('transfer', (string)$transferOwnership->getId());
		$this->notificationManager->notify($notification);

		$this->mapper->delete($transferOwnership);
	}

	protected function getUser(string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			return $user;
		}
		throw new \InvalidArgumentException('User not found');
	}
}
