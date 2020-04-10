<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\Files\BackgroundJob;

use OCA\Files\AppInfo\Application;
use OCA\Files\Db\TransferOwnership as Transfer;
use OCA\Files\Db\TransferOwnershipMapper;
use OCA\Files\Exception\TransferOwnershipException;
use OCA\Files\Service\OwnershipTransferService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\IRootFolder;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use function ltrim;

class TransferOwnership extends QueuedJob {

	/** @var IUserManager $userManager */
	private $userManager;

	/** @var OwnershipTransferService */
	private $transferService;

	/** @var ILogger */
	private $logger;

	/** @var NotificationManager */
	private $notificationManager;

	/** @var TransferOwnershipMapper */
	private $mapper;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(ITimeFactory $timeFactory,
								IUserManager $userManager,
								OwnershipTransferService $transferService,
								ILogger $logger,
								NotificationManager $notificationManager,
								TransferOwnershipMapper $mapper,
								IRootFolder $rootFolder) {
		parent::__construct($timeFactory);

		$this->userManager = $userManager;
		$this->transferService = $transferService;
		$this->logger = $logger;
		$this->notificationManager = $notificationManager;
		$this->mapper = $mapper;
		$this->rootFolder = $rootFolder;
	}

	protected function run($argument) {
		$id = $argument['id'];

		$transfer = $this->mapper->getById($id);
		$sourceUser = $transfer->getSourceUser();
		$destinationUser = $transfer->getTargetUser();
		$fileId = $transfer->getFileId();

		$userFolder = $this->rootFolder->getUserFolder($sourceUser);
		$nodes = $userFolder->getById($fileId);

		if (empty($nodes)) {
			$this->logger->alert('Could not transfer ownership: Node not found');
			$this->failedNotication($transfer);
			return;
		}
		$path = $userFolder->getRelativePath($nodes[0]->getPath());

		$sourceUserObject = $this->userManager->get($sourceUser);
		$destinationUserObject = $this->userManager->get($destinationUser);

		if (!$sourceUserObject instanceof IUser) {
			$this->logger->alert('Could not transfer ownership: Unknown source user ' . $sourceUser);
			$this->failedNotication($transfer);
			return;
		}

		if (!$destinationUserObject instanceof IUser) {
			$this->logger->alert("Unknown destination user $destinationUser");
			$this->failedNotication($transfer);
			return;
		}

		try {
			$this->transferService->transfer(
				$sourceUserObject,
				$destinationUserObject,
				ltrim($path, '/')
			);
			$this->successNotification($transfer);
		} catch (TransferOwnershipException $e) {
			$this->logger->logException($e);
			$this->failedNotication($transfer);
		}

		$this->mapper->delete($transfer);

	}

	private function failedNotication(Transfer $transfer): void {
		// Send notification to source user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transfer->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('transferOwnershipFailedSource', [
				'sourceUser' => $transfer->getSourceUser(),
				'targetUser' => $transfer->getTargetUser(),
				'nodeName' => $transfer->getNodeName(),
			])
			->setObject('transfer', (string)$transfer->getId());
		$this->notificationManager->notify($notification);

		// Send notification to source user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transfer->getTargetUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('transferOwnershipFailedTarget', [
				'sourceUser' => $transfer->getSourceUser(),
				'targetUser' => $transfer->getTargetUser(),
				'nodeName' => $transfer->getNodeName(),
			])
			->setObject('transfer', (string)$transfer->getId());
		$this->notificationManager->notify($notification);
	}

	private function successNotification(Transfer $transfer): void {
		// Send notification to source user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transfer->getSourceUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('transferOwnershipDoneSource', [
				'sourceUser' => $transfer->getSourceUser(),
				'targetUser' => $transfer->getTargetUser(),
				'nodeName' => $transfer->getNodeName(),
			])
			->setObject('transfer', (string)$transfer->getId());
		$this->notificationManager->notify($notification);

		// Send notification to source user
		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transfer->getTargetUser())
			->setApp(Application::APP_ID)
			->setDateTime($this->time->getDateTime())
			->setSubject('transferOwnershipDoneTarget', [
				'sourceUser' => $transfer->getSourceUser(),
				'targetUser' => $transfer->getTargetUser(),
				'nodeName' => $transfer->getNodeName(),
			])
			->setObject('transfer', (string)$transfer->getId());
		$this->notificationManager->notify($notification);
	}
}
