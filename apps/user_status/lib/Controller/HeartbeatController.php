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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\User\Events\UserLiveStatusEvent;
use OCP\UserStatus\IUserStatus;

class HeartbeatController extends Controller {

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var IUserSession */
	private $userSession;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * HeartbeatController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IEventDispatcher $eventDispatcher
	 * @param IUserSession $userSession
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(string $appName,
								IRequest $request,
								IEventDispatcher $eventDispatcher,
								IUserSession $userSession,
								ITimeFactory $timeFactory) {
		parent::__construct($appName, $request);
		$this->eventDispatcher = $eventDispatcher;
		$this->userSession = $userSession;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $status
	 * @return JSONResponse
	 */
	public function heartbeat(string $status): JSONResponse {
		if (!\in_array($status, [IUserStatus::ONLINE, IUserStatus::AWAY], true)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$this->eventDispatcher->dispatchTyped(
			new UserLiveStatusEvent(
				$user,
				$status,
				$this->timeFactory->getTime()
			)
		);

		return new JSONResponse([], Http::STATUS_NO_CONTENT);
	}
}
