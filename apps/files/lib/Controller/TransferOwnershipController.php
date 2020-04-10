<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files\Controller;

use OCA\Files\BackgroundJob\TransferOwnership;
use OCA\Files\Db\TransferOwnership as TransferOwnershipEntity;
use OCA\Files\Db\TransferOwnershipMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;

class TransferOwnershipController extends OCSController {

	/** @var string */
	private $userId;
	/** @var NotificationManager */
	private $notificationManager;
	/** @var ITimeFactory */
	private $timeFactory;
	/** @var IJobList */
	private $jobList;
	/** @var TransferOwnershipMapper */
	private $mapper;
	/** @var IUserManager */
	private $userManager;
	/** @var IRootFolder */
	private $rootFolder;

	public function __construct(string $appName,
								IRequest $request,
								string $userId,
								NotificationManager $notificationManager,
								ITimeFactory $timeFactory,
								IJobList $jobList,
								TransferOwnershipMapper $mapper,
								IUserManager $userManager,
								IRootFolder $rootFolder) {
		parent::__construct($appName, $request);

		$this->userId = $userId;
		$this->notificationManager = $notificationManager;
		$this->timeFactory = $timeFactory;
		$this->jobList = $jobList;
		$this->mapper = $mapper;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
	}


	/**
	 * @NoAdminRequired
	 */
	public function transfer(string $recipient, string $path): DataResponse {
		$recipientUser = $this->userManager->get($recipient);

		if ($recipientUser === null) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$userRoot = $this->rootFolder->getUserFolder($this->userId);

		try {
			$node = $userRoot->get($path);
		} catch (\Exception $e) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		if ($node->getOwner()->getUID() !== $this->userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$transferOwnership = new TransferOwnershipEntity();
		$transferOwnership->setSourceUser($this->userId);
		$transferOwnership->setTargetUser($recipient);
		$transferOwnership->setFileId($node->getId());
		$transferOwnership->setNodeName($node->getName());
		$transferOwnership = $this->mapper->insert($transferOwnership);

		$notification = $this->notificationManager->createNotification();
		$notification->setUser($recipient)
			->setApp($this->appName)
			->setDateTime($this->timeFactory->getDateTime())
			->setSubject('transferownershipRequest', [
				'sourceUser' => $this->userId,
				'targetUser' => $recipient,
				'nodeName' => $node->getName(),
			])
			->setObject('transfer', (string)$transferOwnership->getId());

		$this->notificationManager->notify($notification);

		return new DataResponse([]);
	}

	/**
	 * @NoAdminRequired
	 */
	public function accept(int $id): DataResponse {
		try {
			$transferOwnership = $this->mapper->getById($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($transferOwnership->getTargetUser() !== $this->userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('files')
			->setObject('transfer', (string)$id);
		$this->notificationManager->markProcessed($notification);

		$newTransferOwnership = new TransferOwnershipEntity();
		$newTransferOwnership->setNodeName($transferOwnership->getNodeName());
		$newTransferOwnership->setFileId($transferOwnership->getFileId());
		$newTransferOwnership->setSourceUser($transferOwnership->getSourceUser());
		$newTransferOwnership->setTargetUser($transferOwnership->getTargetUser());
		$this->mapper->insert($newTransferOwnership);

		$this->jobList->add(TransferOwnership::class, [
			'id' => $newTransferOwnership->getId(),
		]);

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * @NoAdminRequired
	 */
	public function reject(int $id): DataResponse {
		try {
			$transferOwnership = $this->mapper->getById($id);
		} catch (DoesNotExistException $e) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		}

		if ($transferOwnership->getTargetUser() !== $this->userId) {
			return new DataResponse([], Http::STATUS_FORBIDDEN);
		}

		$notification = $this->notificationManager->createNotification();
		$notification->setApp('files')
			->setObject('transfer', (string)$id);
		$this->notificationManager->markProcessed($notification);

		$notification = $this->notificationManager->createNotification();
		$notification->setUser($transferOwnership->getSourceUser())
			->setApp($this->appName)
			->setDateTime($this->timeFactory->getDateTime())
			->setSubject('transferownershipRequestDenied', [
				'sourceUser' => $transferOwnership->getSourceUser(),
				'targetUser' => $transferOwnership->getTargetUser(),
				'nodeName' => $transferOwnership->getNodeName()
			])
			->setObject('transfer', (string)$transferOwnership->getId());
		$this->notificationManager->notify($notification);

		$this->mapper->delete($transferOwnership);

		return new DataResponse([], Http::STATUS_OK);
	}

}
