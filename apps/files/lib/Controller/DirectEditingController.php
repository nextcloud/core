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
use OCA\Files\Service\DirectEditingService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\DirectEditing\IManager;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;

class DirectEditingController extends OCSController {

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var IManager */
	private $directEditingManager;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ILogger */
	private $logger;

	/** @var DirectEditingService */
	private $directEditingService;

	public function __construct($appName, IRequest $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge,
								IEventDispatcher $eventDispatcher, IURLGenerator $urlGenerator, IManager $manager, DirectEditingService $directEditingService, ILogger $logger) {
		parent::__construct($appName, $request, $corsMethods, $corsAllowedHeaders, $corsMaxAge);

		$this->eventDispatcher = $eventDispatcher;
		$this->directEditingManager = $manager;
		$this->directEditingService = $directEditingService;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 */
	public function info(): DataResponse {
		$response = new DataResponse($this->directEditingService->getDirectEditingCapabilitites());
		$response->setETag($this->directEditingService->getDirectEditingETag());
		return $response;
	}

	/**
	 * @NoAdminRequired
	 */
	public function create(string $path, string $editorId, string $creatorId, string $templateId = null): DataResponse {
		if (!$this->directEditingManager->isEnabled()) {
			return new DataResponse(['message' => 'Direct editing is not enabled'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$this->eventDispatcher->dispatchTyped(new RegisterDirectEditorEvent($this->directEditingManager));

		try {
			$token = $this->directEditingManager->create($path, $editorId, $creatorId, $templateId);
			return new DataResponse([
				'url' => $this->urlGenerator->linkToRouteAbsolute('files.DirectEditingView.edit', ['token' => $token])
			]);
		} catch (Exception $e) {
			$this->logger->logException($e, ['message' => 'Exception when creating a new file through direct editing']);
			return new DataResponse(['message' => 'Failed to create file: ' . $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/**
	 * @NoAdminRequired
	 */
	public function open(string $path, string $editorId = null): DataResponse {
		if (!$this->directEditingManager->isEnabled()) {
			return new DataResponse(['message' => 'Direct editing is not enabled'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$this->eventDispatcher->dispatchTyped(new RegisterDirectEditorEvent($this->directEditingManager));

		try {
			$token = $this->directEditingManager->open($path, $editorId);
			return new DataResponse([
				'url' => $this->urlGenerator->linkToRouteAbsolute('files.DirectEditingView.edit', ['token' => $token])
			]);
		} catch (Exception $e) {
			$this->logger->logException($e, ['message' => 'Exception when opening a file through direct editing']);
			return new DataResponse(['message' => 'Failed to open file: ' . $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}



	/**
	 * @NoAdminRequired
	 */
	public function templates(string $editorId, string $creatorId): DataResponse {
		if (!$this->directEditingManager->isEnabled()) {
			return new DataResponse(['message' => 'Direct editing is not enabled'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$this->eventDispatcher->dispatchTyped(new RegisterDirectEditorEvent($this->directEditingManager));

		try {
			return new DataResponse($this->directEditingManager->getTemplates($editorId, $creatorId));
		} catch (Exception $e) {
			$this->logger->logException($e);
			return new DataResponse(['message' => 'Failed to obtain template list: ' . $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
