<?php
/**
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Settings\Tests\Controller;

use OCA\Settings\Controller\CertificateController;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\ICertificateManager;
use OCP\IL10N;
use OCP\IRequest;

/**
 * Class CertificateControllerTest
 *
 * @package Tests\Settings\Controller
 */
class CertificateControllerTest extends \Test\TestCase {
	/** @var CertificateController */
	private $certificateController;
	/** @var IRequest */
	private $request;
	/** @var ICertificateManager */
	private $certificateManager;
	/** @var IL10N */
	private $l10n;
	/** @var IAppManager */
	private $appManager;
	/** @var  ICertificateManager */
	private $systemCertificateManager;

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)->getMock();
		$this->certificateManager = $this->getMockBuilder(ICertificateManager::class)->getMock();
		$this->systemCertificateManager = $this->getMockBuilder(ICertificateManager::class)->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)->getMock();
		$this->appManager = $this->getMockBuilder(IAppManager::class)->getMock();

		$this->certificateController = $this->getMockBuilder(CertificateController::class)
			->setConstructorArgs(
				[
					'settings',
					$this->request,
					$this->certificateManager,
					$this->systemCertificateManager,
					$this->l10n,
					$this->appManager
				]
			)->setMethods(['isCertificateImportAllowed'])->getMock();

		$this->certificateController->expects($this->any())
			->method('isCertificateImportAllowed')->willReturn(true);
	}

	public function testAddPersonalRootCertificateWithEmptyFile() {
		$this->request
			->expects($this->once())
			->method('getUploadedFile')
			->with('rootcert_import')
			->willReturn(null);

		$expected = new DataResponse(['message' => 'No file uploaded'], Http::STATUS_UNPROCESSABLE_ENTITY);
		$this->assertEquals($expected, $this->certificateController->addPersonalRootCertificate());
	}

	public function testAddPersonalRootCertificateValidCertificate() {
		$uploadedFile = [
			'tmp_name' => __DIR__ . '/../../../../tests/data/certificates/goodCertificate.crt',
			'name' => 'goodCertificate.crt',
		];

		$certificate = $this->getMockBuilder('\OCP\ICertificate')->getMock();
		$certificate
			->expects($this->once())
			->method('getName')
			->willReturn('Name');
		$certificate
			->expects($this->once())
			->method('getCommonName')
			->willReturn('CommonName');
		$certificate
			->expects($this->once())
			->method('getOrganization')
			->willReturn('Organization');
		$certificate
			->expects($this->exactly(2))
			->method('getIssueDate')
			->willReturn(new \DateTime('@1429099555'));
		$certificate
			->expects($this->exactly(2))
			->method('getExpireDate')
			->willReturn(new \DateTime('@1529099555'));
		$certificate
			->expects($this->once())
			->method('getIssuerName')
			->willReturn('Issuer');
		$certificate
			->expects($this->once())
			->method('getIssuerOrganization')
			->willReturn('IssuerOrganization');

		$this->request
			->expects($this->once())
			->method('getUploadedFile')
			->with('rootcert_import')
			->willReturn($uploadedFile);
		$this->certificateManager
			->expects($this->once())
			->method('addCertificate')
			->with(file_get_contents($uploadedFile['tmp_name'], 'goodCertificate.crt'))
			->willReturn($certificate);

		$this->l10n
			->expects($this->at(0))
			->method('l')
			->with('date', new \DateTime('@1429099555'))
			->willReturn('Valid From as String');
		$this->l10n
			->expects($this->at(1))
			->method('l')
			->with('date', new \DateTime('@1529099555'))
			->willReturn('Valid Till as String');


		$expected = new DataResponse([
			'name' => 'Name',
			'commonName' => 'CommonName',
			'organization' => 'Organization',
			'validFrom' => 1429099555,
			'validTill' => 1529099555,
			'validFromString' => 'Valid From as String',
			'validTillString' => 'Valid Till as String',
			'issuer' => 'Issuer',
			'issuerOrganization' => 'IssuerOrganization',
		]);
		$this->assertEquals($expected, $this->certificateController->addPersonalRootCertificate());
	}

	public function testAddPersonalRootCertificateInvalidCertificate() {
		$uploadedFile = [
			'tmp_name' => __DIR__ . '/../../../../tests/data/certificates/badCertificate.crt',
			'name' => 'badCertificate.crt',
		];

		$this->request
			->expects($this->once())
			->method('getUploadedFile')
			->with('rootcert_import')
			->willReturn($uploadedFile);
		$this->certificateManager
			->expects($this->once())
			->method('addCertificate')
			->with(file_get_contents($uploadedFile['tmp_name'], 'badCertificate.crt'))
			->will($this->throwException(new \Exception()));

		$expected = new DataResponse(['An error occurred.'], Http::STATUS_UNPROCESSABLE_ENTITY);
		$this->assertEquals($expected, $this->certificateController->addPersonalRootCertificate());
	}

	public function testRemoveCertificate() {
		$this->certificateManager
			->expects($this->once())
			->method('removeCertificate')
			->with('CertificateToRemove');

		$this->assertEquals(new DataResponse(), $this->certificateController->removePersonalRootCertificate('CertificateToRemove'));
	}

}
