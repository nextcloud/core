<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Http\Response;
use OCP\DirectEditing\IManager;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\IRequest;

class DirectEditingViewController extends Controller {

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var IManager */
	private $directEditingManager;

	/** @var ILogger */
	private $logger;

	public function __construct($appName, IRequest $request, IEventDispatcher $eventDispatcher, IManager $manager, ILogger $logger) {
		parent::__construct($appName, $request);

		$this->eventDispatcher = $eventDispatcher;
		$this->directEditingManager = $manager;
		$this->logger = $logger;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @return Response
	 */
	public function edit(string $token): Response {
		$this->eventDispatcher->dispatchTyped(new RegisterDirectEditorEvent($this->directEditingManager));
		try {
			return $this->directEditingManager->edit($token);
		} catch (Exception $e) {
			$this->logger->logException($e);
			return new NotFoundResponse();
		}
	}
}
