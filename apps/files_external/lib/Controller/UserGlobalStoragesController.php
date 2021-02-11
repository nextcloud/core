<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_External\Controller;

use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Auth\IUserProvided;
use OCA\Files_External\Lib\Auth\Password\UserGlobalAuth;
use OCA\Files_External\Lib\Backend\Backend;
use OCA\Files_External\Lib\InsufficientDataForMeaningfulAnswerException;
use OCA\Files_External\Lib\StorageConfig;
use OCA\Files_External\NotFoundException;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * User global storages controller
 */
class UserGlobalStoragesController extends StoragesController {
	/**
	 * Creates a new user global storages controller.
	 *
	 * @param string $AppName application name
	 * @param IRequest $request request object
	 * @param IL10N $l10n l10n service
	 * @param UserGlobalStoragesService $userGlobalStoragesService storage service
	 * @param ILogger $logger
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IL10N $l10n,
		UserGlobalStoragesService $userGlobalStoragesService,
		ILogger $logger,
		IUserSession $userSession,
		IGroupManager $groupManager
	) {
		parent::__construct(
			$AppName,
			$request,
			$l10n,
			$userGlobalStoragesService,
			$logger,
			$userSession,
			$groupManager
		);
	}

	/**
	 * Get all storage entries
	 *
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function index() {
		$storages = $this->formatStoragesForUI($this->service->getUniqueStorages());

		// remove configuration data, this must be kept private
		foreach ($storages as $storage) {
			$this->sanitizeStorage($storage);
		}

		return new DataResponse(
			$storages,
			Http::STATUS_OK
		);
	}

	protected function manipulateStorageConfig(StorageConfig $storage) {
		/** @var AuthMechanism */
		$authMechanism = $storage->getAuthMechanism();
		$authMechanism->manipulateStorageConfig($storage, $this->userSession->getUser());
		/** @var Backend */
		$backend = $storage->getBackend();
		$backend->manipulateStorageConfig($storage, $this->userSession->getUser());
	}

	/**
	 * Get an external storage entry.
	 *
	 * @param int $id storage id
	 * @param bool $testOnly whether to storage should only test the connection or do more things
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function show($id, $testOnly = true) {
		try {
			$storage = $this->service->getStorage($id);

			$this->updateStorageStatus($storage, $testOnly);
		} catch (NotFoundException $e) {
			return new DataResponse(
				[
					'message' => $this->l10n->t('Storage with ID "%d" not found', [$id])
				],
				Http::STATUS_NOT_FOUND
			);
		}

		$this->sanitizeStorage($storage);

		$data = $this->formatStorageForUI($storage)->jsonSerialize();
		$isAdmin = $this->groupManager->isAdmin($this->userSession->getUser()->getUID());
		$data['can_edit'] = $storage->getType() === StorageConfig::MOUNT_TYPE_PERSONAl || $isAdmin;

		return new DataResponse(
			$data,
			Http::STATUS_OK
		);
	}

	/**
	 * Update an external storage entry.
	 * Only allows setting user provided backend fields
	 *
	 * @param int $id storage id
	 * @param array $backendOptions backend-specific options
	 * @param bool $testOnly whether to storage should only test the connection or do more things
	 *
	 * @return DataResponse
	 *
	 * @NoAdminRequired
	 */
	public function update(
		$id,
		$backendOptions,
		$testOnly = true
	) {
		try {
			$storage = $this->service->getStorage($id);
			$authMechanism = $storage->getAuthMechanism();
			if ($authMechanism instanceof IUserProvided || $authMechanism instanceof  UserGlobalAuth) {
				$authMechanism->saveBackendOptions($this->userSession->getUser(), $id, $backendOptions);
				$authMechanism->manipulateStorageConfig($storage, $this->userSession->getUser());
			} else {
				return new DataResponse(
					[
						'message' => $this->l10n->t('Storage with ID "%d" is not user editable', [$id])
					],
					Http::STATUS_FORBIDDEN
				);
			}
		} catch (NotFoundException $e) {
			return new DataResponse(
				[
					'message' => $this->l10n->t('Storage with ID "%d" not found', [$id])
				],
				Http::STATUS_NOT_FOUND
			);
		}

		$this->updateStorageStatus($storage, $testOnly);
		$this->sanitizeStorage($storage);

		return new DataResponse(
			$this->formatStorageForUI($storage),
			Http::STATUS_OK
		);
	}

	/**
	 * Remove sensitive data from a StorageConfig before returning it to the user
	 *
	 * @param StorageConfig $storage
	 */
	protected function sanitizeStorage(StorageConfig $storage) {
		$storage->setBackendOptions([]);
		$storage->setMountOptions([]);

		if ($storage->getAuthMechanism() instanceof IUserProvided) {
			try {
				$storage->getAuthMechanism()->manipulateStorageConfig($storage, $this->userSession->getUser());
			} catch (InsufficientDataForMeaningfulAnswerException $e) {
				// not configured yet
			}
		}
	}
}
