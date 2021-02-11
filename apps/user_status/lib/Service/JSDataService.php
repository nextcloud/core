<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\UserStatus\Service;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use OCP\UserStatus\IUserStatus;

class JSDataService implements \JsonSerializable {

	/** @var IUserSession */
	private $userSession;

	/** @var StatusService */
	private $statusService;

	/**
	 * JSDataService constructor.
	 *
	 * @param IUserSession $userSession
	 * @param StatusService $statusService
	 */
	public function __construct(IUserSession $userSession,
								StatusService $statusService) {
		$this->userSession = $userSession;
		$this->statusService = $statusService;
	}

	public function jsonSerialize() {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return [];
		}

		try {
			$status = $this->statusService->findByUserId($user->getUID());
		} catch (DoesNotExistException $ex) {
			return [
				'userId' => $user->getUID(),
				'message' => null,
				'messageId' => null,
				'messageIsPredefined' => false,
				'icon' => null,
				'clearAt' => null,
				'status' => IUserStatus::OFFLINE,
				'statusIsUserDefined' => false,
			];
		}

		return [
			'userId' => $status->getUserId(),
			'message' => $status->getCustomMessage(),
			'messageId' => $status->getMessageId(),
			'messageIsPredefined' => $status->getMessageId() !== null,
			'icon' => $status->getCustomIcon(),
			'clearAt' => $status->getClearAt(),
			'status' => $status->getStatus(),
			'statusIsUserDefined' => $status->getIsUserDefined(),
		];
	}
}
