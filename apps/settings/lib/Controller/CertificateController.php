<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCA\Settings\Controller;

use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ICertificateManager;
use OCP\IL10N;
use OCP\IRequest;

class CertificateController extends Controller {
	/** @var ICertificateManager */
	private $userCertificateManager;
	/** @var ICertificateManager  */
	private $systemCertificateManager;
	/** @var IL10N */
	private $l10n;
	/** @var IAppManager */
	private $appManager;

	/**
	 * @param string $appName
	 * @param IRequest $request
	 * @param ICertificateManager $userCertificateManager
	 * @param ICertificateManager $systemCertificateManager
	 * @param IL10N $l10n
	 * @param IAppManager $appManager
	 */
	public function __construct($appName,
								IRequest $request,
								ICertificateManager $userCertificateManager,
								ICertificateManager $systemCertificateManager,
								IL10N $l10n,
								IAppManager $appManager) {
		parent::__construct($appName, $request);
		$this->userCertificateManager = $userCertificateManager;
		$this->systemCertificateManager = $systemCertificateManager;
		$this->l10n = $l10n;
		$this->appManager = $appManager;
	}

	/**
	 * Add a new personal root certificate to the users' trust store
	 *
	 * @NoAdminRequired
	 * @NoSubadminRequired
	 * @return DataResponse
	 */
	public function addPersonalRootCertificate() {
		return $this->addCertificate($this->userCertificateManager);
	}

	/**
	 * Add a new root certificate to a trust store
	 *
	 * @param ICertificateManager $certificateManager
	 * @return DataResponse
	 */
	private function addCertificate(ICertificateManager $certificateManager) {
		$headers = [];

		if ($this->isCertificateImportAllowed() === false) {
			return new DataResponse(['message' => 'Individual certificate management disabled'], Http::STATUS_FORBIDDEN, $headers);
		}

		$file = $this->request->getUploadedFile('rootcert_import');
		if (empty($file)) {
			return new DataResponse(['message' => 'No file uploaded'], Http::STATUS_UNPROCESSABLE_ENTITY, $headers);
		}

		try {
			$certificate = $certificateManager->addCertificate(file_get_contents($file['tmp_name']), $file['name']);
			return new DataResponse(
				[
					'name' => $certificate->getName(),
					'commonName' => $certificate->getCommonName(),
					'organization' => $certificate->getOrganization(),
					'validFrom' => $certificate->getIssueDate()->getTimestamp(),
					'validTill' => $certificate->getExpireDate()->getTimestamp(),
					'validFromString' => $this->l10n->l('date', $certificate->getIssueDate()),
					'validTillString' => $this->l10n->l('date', $certificate->getExpireDate()),
					'issuer' => $certificate->getIssuerName(),
					'issuerOrganization' => $certificate->getIssuerOrganization(),
				],
				Http::STATUS_OK,
				$headers
			);
		} catch (\Exception $e) {
			return new DataResponse(['An error occurred.'], Http::STATUS_UNPROCESSABLE_ENTITY, $headers);
		}
	}

	/**
	 * Removes a personal root certificate from the users' trust store
	 *
	 * @NoAdminRequired
	 * @NoSubadminRequired
	 * @param string $certificateIdentifier
	 * @return DataResponse
	 */
	public function removePersonalRootCertificate($certificateIdentifier) {

		if ($this->isCertificateImportAllowed() === false) {
			return new DataResponse(['Individual certificate management disabled'], Http::STATUS_FORBIDDEN);
		}

		$this->userCertificateManager->removeCertificate($certificateIdentifier);
		return new DataResponse();
	}

	/**
	 * check if certificate import is allowed
	 *
	 * @return bool
	 */
	protected function isCertificateImportAllowed() {
		$externalStorageEnabled = $this->appManager->isEnabledForUser('files_external');
		if ($externalStorageEnabled) {
			/** @var \OCA\Files_External\Service\BackendService $backendService */
			$backendService = \OC_Mount_Config::$app->getContainer()->query('\OCA\Files_External\Service\BackendService');
			if ($backendService->isUserMountingAllowed()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add a new personal root certificate to the system's trust store
	 *
	 * @return DataResponse
	 */
	public function addSystemRootCertificate() {
		return $this->addCertificate($this->systemCertificateManager);
	}

	/**
	 * Removes a personal root certificate from the users' trust store
	 *
	 * @param string $certificateIdentifier
	 * @return DataResponse
	 */
	public function removeSystemRootCertificate($certificateIdentifier) {

		if ($this->isCertificateImportAllowed() === false) {
			return new DataResponse(['Individual certificate management disabled'], Http::STATUS_FORBIDDEN);
		}

		$this->systemCertificateManager->removeCertificate($certificateIdentifier);
		return new DataResponse();
	}
}
