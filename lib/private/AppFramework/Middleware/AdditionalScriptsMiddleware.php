<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\AppFramework\Middleware;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\PublicShareController;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AdditionalScriptsMiddleware extends Middleware {
	/** @var EventDispatcherInterface */
	private $legacyDispatcher;
	/** @var IUserSession */
	private $userSession;
	/** @var IEventDispatcher */
	private $dispatcher;

	public function __construct(EventDispatcherInterface $legacyDispatcher, IUserSession $userSession, IEventDispatcher $dispatcher) {
		$this->legacyDispatcher = $legacyDispatcher;
		$this->userSession = $userSession;
		$this->dispatcher = $dispatcher;
	}

	public function afterController($controller, $methodName, Response $response): Response {
		if ($response instanceof TemplateResponse) {
			if (!$controller instanceof PublicShareController) {
				/*
				 * The old event was not dispatched on the public share controller as there was
				 * OCA\Files_Sharing::loadAdditionalScripts for that. This is kept for compatibility reasons
				 * only for the old event as this is now also included in BeforeTemplateRenderedEvent
				 */
				$this->legacyDispatcher->dispatch(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS, new GenericEvent());
			}

			if (!($response instanceof StandaloneTemplateResponse) && $this->userSession->isLoggedIn()) {
				$this->legacyDispatcher->dispatch(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN, new GenericEvent());
				$isLoggedIn = true;
			} else {
				$isLoggedIn = false;
			}

			$this->dispatcher->dispatchTyped(new BeforeTemplateRenderedEvent($isLoggedIn, $response));
		}

		return $response;
	}
}
