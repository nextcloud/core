<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Encryption\Controller;

use OCA\Encryption\Session;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\Encryption\IManager;
use OCP\IL10N;
use OCP\IRequest;

class StatusController extends Controller {

	/** @var IL10N */
	private $l;

	/** @var Session */
	private $session;

	/** @var IManager */
	private $encryptionManager;

	/**
	 * @param string $AppName
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param Session $session
	 * @param IManager $encryptionManager
	 */
	public function __construct($AppName,
								IRequest $request,
								IL10N $l10n,
								Session $session,
								IManager $encryptionManager
								) {
		parent::__construct($AppName, $request);
		$this->l = $l10n;
		$this->session = $session;
		$this->encryptionManager = $encryptionManager;
	}

	/**
	 * @NoAdminRequired
	 * @return DataResponse
	 */
	public function getStatus() {
		$status = 'error';
		$message = 'no valid init status';
		switch ($this->session->getStatus()) {
			case Session::INIT_EXECUTED:
				$status = 'interactionNeeded';
				$message = $this->l->t(
					'Invalid private key for encryption app. Please update your private key password in your personal settings to recover access to your encrypted files.'
				);
				break;
			case Session::NOT_INITIALIZED:
				$status = 'interactionNeeded';
				if ($this->encryptionManager->isEnabled()) {
					$message = $this->l->t(
						'Encryption App is enabled, but your keys are not initialized. Please log-out and log-in again.'
					);
				} else {
					$message = $this->l->t(
						'Please enable server side encryption in the admin settings in order to use the encryption module.'
					);
				}
				break;
			case Session::INIT_SUCCESSFUL:
				$status = 'success';
				$message = $this->l->t('Encryption app is enabled and ready');
		}

		return new DataResponse(
			[
				'status' => $status,
				'data' => [
					'message' => $message]
			]
		);
	}
}
