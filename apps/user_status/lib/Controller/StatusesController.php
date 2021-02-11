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

namespace OCA\UserStatus\Controller;

use OCA\UserStatus\Db\UserStatus;
use OCA\UserStatus\Service\StatusService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\UserStatus\IUserStatus;

class StatusesController extends OCSController {

	/** @var StatusService */
	private $service;

	/**
	 * StatusesController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param StatusService $service
	 */
	public function __construct(string $appName,
								IRequest $request,
								StatusService $service) {
		parent::__construct($appName, $request);
		$this->service = $service;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int|null $limit
	 * @param int|null $offset
	 * @return DataResponse
	 */
	public function findAll(?int $limit = null, ?int $offset = null): DataResponse {
		$allStatuses = $this->service->findAll($limit, $offset);

		return new DataResponse(array_map(function ($userStatus) {
			return $this->formatStatus($userStatus);
		}, $allStatuses));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	public function find(string $userId): DataResponse {
		try {
			$userStatus = $this->service->findByUserId($userId);
		} catch (DoesNotExistException $ex) {
			throw new OCSNotFoundException('No status for the requested userId');
		}

		return new DataResponse($this->formatStatus($userStatus));
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param UserStatus $status
	 * @return array
	 */
	private function formatStatus(UserStatus $status): array {
		$visibleStatus = $status->getStatus();
		if ($visibleStatus === IUserStatus::INVISIBLE) {
			$visibleStatus = IUserStatus::OFFLINE;
		}

		return [
			'userId' => $status->getUserId(),
			'message' => $status->getCustomMessage(),
			'icon' => $status->getCustomIcon(),
			'clearAt' => $status->getClearAt(),
			'status' => $visibleStatus,
		];
	}
}
