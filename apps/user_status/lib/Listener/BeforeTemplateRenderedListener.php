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

namespace OCA\UserStatus\Listener;

use OCA\UserStatus\AppInfo\Application;
use OCA\UserStatus\Service\JSDataService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IInitialStateService;

class BeforeTemplateRenderedListener implements IEventListener {

	/** @var IInitialStateService */
	private $initialState;

	/** @var JSDataService */
	private $jsDataService;

	/**
	 * BeforeTemplateRenderedListener constructor.
	 *
	 * @param IInitialStateService $initialState
	 * @param JSDataService $jsDataService
	 */
	public function __construct(IInitialStateService $initialState,
								JSDataService $jsDataService) {
		$this->initialState = $initialState;
		$this->jsDataService = $jsDataService;
	}

	/**
	 * @inheritDoc
	 */
	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			// Unrelated
			return;
		}

		if (!$event->isLoggedIn() || $event->getResponse()->getRenderAs() !== TemplateResponse::RENDER_AS_USER) {
			return;
		}

		$this->initialState->provideLazyInitialState(Application::APP_ID, 'status', function () {
			return $this->jsDataService;
		});

		\OCP\Util::addScript('user_status', 'user-status-menu');
		\OCP\Util::addStyle('user_status', 'user-status-menu');
	}
}
