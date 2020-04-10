<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\PublicShareController;
use OCP\EventDispatcher\GenericEvent;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AdditionalScriptsMiddleware extends Middleware {
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var IUserSession */
	private $userSession;

	public function __construct(EventDispatcherInterface $dispatcher, IUserSession $userSession) {
		$this->dispatcher = $dispatcher;
		$this->userSession = $userSession;
	}

	public function afterController($controller, $methodName, Response $response): Response {
		/*
		 * There is no need to emit these signals on a public share page
		 * There is a separate event for that already
		 */
		if ($controller instanceof PublicShareController) {
			return $response;
		}

		if ($response instanceof TemplateResponse) {
			$this->dispatcher->dispatch(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS, new GenericEvent());

			if (!($response instanceof StandaloneTemplateResponse) && $this->userSession->isLoggedIn()) {
				$this->dispatcher->dispatch(TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN, new GenericEvent());
			}
		}

		return $response;
	}

}
