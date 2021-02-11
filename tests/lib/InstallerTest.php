<?php
/**
 * Copyright (c) 2014 Georg Ehrke <georg@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\App\AppStore\Fetcher\AppFetcher;
use OC\Archive\ZIP;
use OC\Installer;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\ILogger;
use OCP\ITempManager;

/**
 * Class InstallerTest
 *
 * @package Test
 * @group DB
 */
class InstallerTest extends TestCase {
	private static $appid = 'testapp';
	private $appstore;
	/** @var AppFetcher|\PHPUnit\Framework\MockObject\MockObject */
	private $appFetcher;
	/** @var IClientService|\PHPUnit\Framework\MockObject\MockObject */
	private $clientService;
	/** @var ITempManager|\PHPUnit\Framework\MockObject\MockObject */
	private $tempManager;
	/** @var ILogger|\PHPUnit\Framework\MockObject\MockObject */
	private $logger;
	/** @var IConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	protected function setUp(): void {
		parent::setUp();

		$this->appFetcher = $this->createMock(AppFetcher::class);
		$this->clientService = $this->createMock(IClientService::class);
		$this->tempManager = $this->createMock(ITempManager::class);
		$this->logger = $this->createMock(ILogger::class);
		$this->config = $this->createMock(IConfig::class);

		$config = \OC::$server->getConfig();
		$this->appstore = $config->setSystemValue('appstoreenabled', true);
		$config->setSystemValue('appstoreenabled', true);
		$installer = new Installer(
			\OC::$server->getAppFetcher(),
			\OC::$server->getHTTPClientService(),
			\OC::$server->getTempManager(),
			\OC::$server->getLogger(),
			$config,
			false
		);
		$installer->removeApp(self::$appid);
	}

	protected function getInstaller() {
		return new Installer(
			$this->appFetcher,
			$this->clientService,
			$this->tempManager,
			$this->logger,
			$this->config,
			false
		);
	}

	protected function tearDown(): void {
		$installer = new Installer(
			\OC::$server->getAppFetcher(),
			\OC::$server->getHTTPClientService(),
			\OC::$server->getTempManager(),
			\OC::$server->getLogger(),
			\OC::$server->getConfig(),
			false
		);
		$installer->removeApp(self::$appid);
		\OC::$server->getConfig()->setSystemValue('appstoreenabled', $this->appstore);

		parent::tearDown();
	}

	public function testInstallApp() {
		// Read the current version of the app to check for bug #2572
		\OC_App::getAppVersion('testapp');

		// Extract app
		$pathOfTestApp = __DIR__ . '/../data/testapp.zip';
		$tar = new ZIP($pathOfTestApp);
		$tar->extract(\OC_App::getInstallPath());

		// Install app
		$installer = new Installer(
			\OC::$server->getAppFetcher(),
			\OC::$server->getHTTPClientService(),
			\OC::$server->getTempManager(),
			\OC::$server->getLogger(),
			\OC::$server->getConfig(),
			false
		);
		$this->assertNull(\OC::$server->getConfig()->getAppValue('testapp', 'enabled', null), 'Check that the app is not listed before installation');
		$this->assertSame('testapp', $installer->installApp(self::$appid));
		$this->assertSame('no', \OC::$server->getConfig()->getAppValue('testapp', 'enabled', null), 'Check that the app is listed after installation');
		$this->assertSame('0.9', \OC::$server->getConfig()->getAppValue('testapp', 'installed_version'));
		$installer->removeApp(self::$appid);
	}

	public function updateArrayProvider() {
		return [
			// Update available
			[
				[
					[
						'id' => 'files',
						'releases' => [
							[
								'version' => '1111.0'
							],
						],
					],
				],
				'1111.0',
			],
			// No update available
			[
				[
					[
						'id' => 'files',
						'releases' => [
							[
								'version' => '1.0'
							],
						],
					],
				],
				false,
			],
		];
	}

	/**
	 * @dataProvider updateArrayProvider
	 * @param array $appArray
	 * @param string|bool $updateAvailable
	 */
	public function testIsUpdateAvailable(array $appArray, $updateAvailable) {
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);

		$installer = $this->getInstaller();
		$this->assertSame($updateAvailable, $installer->isUpdateAvailable('files'));
		$this->assertSame($updateAvailable, $installer->isUpdateAvailable('files'), 'Cached result should be returned and fetcher should be only called once');
	}


	public function testDownloadAppWithRevokedCertificate() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Certificate "4112" has been revoked');

		$appArray = [
			[
				'id' => 'news',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAQMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDAzMTMyNDM3WhcNMjcwMTA5MTMyNDM3WjASMRAwDgYD
VQQDDAdwYXNzbWFuMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEApEt+
KZGs+WqdZkHZflzqk+ophYWB8qB47XCzy+xdTGPFM84/9wXltRPbcQQWJJl5aOx0
FPbsyTGhIt/IYZ2Vl0XrDRJjsaxzPcrofrwpJ2tqforXjGohl6mZUBA0ESzFiPzT
SAZe8E14+Jk8rbF/ecrkqcWf2cTMV3Qfu9YvJo8WVs4lHc95r1F+Nalh/OLkHkzb
fYPno2Z5cco6U7BXunFQG2gqy3wWQwmlhDxh5fwrCoFzPWm7WhwSyK+eMoSDz+Vp
3kmtyijsqnda0zA9bfNzgW26czbJaObbnkdtDC2nfoAWXndlS/5YRI8yHd9miB5C
u1OC8LUWToDGNa9+FOxBSj7Nk6iyjbVfRXcTqThdkVZdOOPaBRMsL9R4UYywCbhA
yGNiQ0ahfXD8MZSb08rlQg8tAtcUZW1sYQcbtMGnu8OyC5J7N1efzv5mys4+9hBS
5ECeyCuQTuOkF4H/XS2BMSFZWF2xh7wzhMLca+5yauDW4i8baFEv74QTeY1DADgI
Lz29NJ6z9xYzEnPesjNrwIcJwIjV52EkdLTi+EIf83UjXLQdwDbLxu76qxqP7K0I
oMmwbl7UNA0wzq7nmgRhvqhow5RoCaSJjTz0EYQVSa1xelwiKeJiSKj2G9Mgt5Ms
Miuy3C3VAGvQJ2ocILPGOt54oVeNRFLpnCo1e3sCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEAkGYtg21rGpUVT/AokGUfI0PeyYAkcXKy2yuBAzfRk+uIXnRR0vK+OMpx
shBoYGR3JEGUHZcMTRh8wjAZ0wuyYlQONtJbFFF3bCfODXxCsw0Vm8/Ms+KCmE4Z
SyQafWEQf1sdqNw4VS4DYS2mlpDgAl+U9UY6HQKuT3+GFIxCsQSdS0GTaiYVKPVE
p/eKou739h+5dM4FEhIYZX+7PWlHmX6wPCFAjgNu3kiRGmF6LKmCNNXTySATEP86
tczQMzLtVdTg5z8XMi//6TkAPxRPjYi8Vef/s2mLo7KystTmofxI/HZePSieJ9tj
gLgK8d8sKL60JMmKHN3boHrsThKBVA==
-----END CERTIFICATE-----',
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);


		$installer = $this->getInstaller();
		$installer->downloadApp('news');
	}


	public function testDownloadAppWithNotNextcloudCertificate() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App with id news has a certificate not issued by a trusted Code Signing Authority');

		$appArray = [
			[
				'id' => 'news',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIID8TCCAdkCAhAAMA0GCSqGSIb3DQEBCwUAMG0xCzAJBgNVBAYTAlVTMQ8wDQYD
VQQIDAZCb3N0b24xFjAUBgNVBAoMDW93bkNsb3VkIEluYy4xNTAzBgNVBAMMLG93
bkNsb3VkIENvZGUgU2lnbmluZyBJbnRlcm1lZGlhdGUgQXV0aG9yaXR5MB4XDTE2
MDIwMzE3NTE0OVoXDTI2MDEzMTE3NTE0OVowDzENMAsGA1UEAwwEY29yZTCCASIw
DQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBAPHdSljnHI+ueQd27UyWPO9n4Lqt
bK0kdekiC3si7Mee7uXXJaGuqXJozHEZYB1LIFLdCU/itCxEk9hyLcyNzeT+nRT/
zDuOYdbLgCj7/A5bX+u3jc29UlCYybSFchfMdvn7a0njCna4dE+73b4yEj16tS2h
S1EUygSzgicWlJqMD3Z9Qc+zLEpdhq9oDdDB8HURi2NW4KzIraVncSH+zF1QduOh
nERDnF8x48D3FLdTxGA0W/Kg4gYsq4NRvU6g3DJNdp4YfqRSFMmLFDCgzDuhan7D
wgRlI9NAeHbnyoUPtrDBUceI7shIbC/i87xk9ptqV0AyFonkJtK6lWwZjNkCAwEA
ATANBgkqhkiG9w0BAQsFAAOCAgEAAMgymqZE1YaHYlRGwvTE7gGDY3gmFOMaxQL4
E5m0CnkBz4BdIPRsQFFdOv3l/MIWkw5ED3vUB925VpQZYFSiEuv5NbnlPaHZlIMI
n8AV/sTP5jue3LhtAN4EM63xNBhudAT6wVsvGwOuQOx9Xv+ptO8Po7sTuNYP0CMH
EOQN+/q8tYlSm2VW+dAlaJ+zVZwZldhVjL+lSH4E9ktWn3PmgNQeKfcnJISUbus6
ZtsYDF/X96/Z2ZQvMXOKksgvU6XlvIxllcyebC9Bxe/h0D63GCO2tqN5CWQzIIqn
apUynPX8BlLaaExqYGERwlUi/yOGaUVPUjEPVehviOQYgAqxlrkJk1dWeCrwUori
CXpi+IUYkidfgiJ9F88M3ElpwqIaXp7G3/4oHBuE2u6M+L+1/vqPJeTCAWUxxpJE
yYmM+db6D4TySFpQPENNzPS8bpR6T8w2hRumkldC42HrnyJJbpjOieTXhXzjdPvZ
IEP9JGtkhB2du6nBF2MNAq2TqRXpcfQrQEbnQ13aV9bl+roTwwO+SOWK/wgvdOMI
STQ0Xk0sTGlmQjPYPkibVceaWMR3sX4cNt5c33YhJys5jxHoAh42km4nN9tfykR5
crl5lBlKjXh2GP0+omSO3x1jX4+iQPCW2TWoyKkUdLu/hGHG2w8RrTeme+kATECH
YSu356M=
-----END CERTIFICATE-----',
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);

		$installer = $this->getInstaller();
		$installer->downloadApp('news');
	}


	public function testDownloadAppWithDifferentCN() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App with id news has a cert issued to passman');

		$appArray = [
			[
				'id' => 'news',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhDUMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTkwMTMwMTMwMjA5WhcNMjkwNTA3MTMwMjA5WjASMRAwDgYD
VQQDDAdwYXNzbWFuMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwT1/
hO/RSUkj0T9a879LPjy4c5V7FBDffEh6H/n1aiOEzofr8vqP3wJ4ZLwIvpZIZNFC
CY4HjBTIgk+QOlAv2oV2w/8XxkSQ708H3m99GFNRQg9EztjiIeKn7y1HhFOeiVaF
Eq6R1Tnre8cjzv6/yf1f1EFpPY3ptCefUjdLfpU/YrPhFxGLS+n5hyr8b6EszqKm
6NhGI09sd1Wb1y8o+dtQIQr24gWeo3l3QGLxjcJQqHCxE38rGdTNd04qDEm69BMD
Kjk4/JmUBBOn0svg9IAks+4nDnpr3IABfcnKYlmAucVEMNMYfA6kXXWEALsE2yo9
8Y7GeV8En5Ztn4w3Pt2HMNpJV2m7MWWocSbF+ocp8oJ0cIEcthBubiE2kJhdPi5a
Yo5Bwh54hx53an+XfiDn+bfidvNz5TsJtmZykB84gLcdBQyMHrZcDcD6g74KdW3X
du/AnNUlJujhIU0fsw3UUPB845Q8XjbsIK5WhgaQeXJum8HXnXWkEfh6mE4j9l2Z
6FJVe8fQlF5lspn6z3qYsWlYRalD3N9Qxy3vpRgCAYTPc3D+fbVP9KJw1cWux1+O
0X/hNWMDOTenhgyQS+mqhRvdWq71mFjP/RXjOSfED3ipFamPofCz9QC7EERtvZPE
Pr8tBnbxnyTbAOQrN9N2mA9UJbOhk2l5kCSkDpMCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEAdVaizjIBQLGFo3uzOVAjh5IoFt/DBiSjVYOOt4HacWDDkRKOmQp3AJaA
Zecbd+tF7OsIqrBzbbfK7wp1+y1pLrPNFQghhk7gVxBP9LscLQ1JmDK2vHv9rLAg
d/gv90gf3HVeRQWntAhGTqtiSZkafcXJIRCM4GygoPy2zxeWeCTt85yEbQnuGZ4Z
qtWS/yyJR5ZQEwYG7jFWRxaZicuUdWBhlUMafRUxzjxBhsQplGKSI66eFQ5VtB7L
u/spPSSVhaun5BA1FlphB2TkgnzlCmxJa63nFY045e/Jq+IKMcqqZl/092gbI2EQ
5EpZaQ1l6H5DBXwrz58a8WTPC2Mu8g==
-----END CERTIFICATE-----',
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);

		$installer = $this->getInstaller();
		$installer->downloadApp('news');
	}


	public function testDownloadAppWithInvalidSignature() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App with id passman has invalid signature');

		$appArray = [
			[
				'id' => 'passman',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhDUMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTkwMTMwMTMwMjA5WhcNMjkwNTA3MTMwMjA5WjASMRAwDgYD
VQQDDAdwYXNzbWFuMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwT1/
hO/RSUkj0T9a879LPjy4c5V7FBDffEh6H/n1aiOEzofr8vqP3wJ4ZLwIvpZIZNFC
CY4HjBTIgk+QOlAv2oV2w/8XxkSQ708H3m99GFNRQg9EztjiIeKn7y1HhFOeiVaF
Eq6R1Tnre8cjzv6/yf1f1EFpPY3ptCefUjdLfpU/YrPhFxGLS+n5hyr8b6EszqKm
6NhGI09sd1Wb1y8o+dtQIQr24gWeo3l3QGLxjcJQqHCxE38rGdTNd04qDEm69BMD
Kjk4/JmUBBOn0svg9IAks+4nDnpr3IABfcnKYlmAucVEMNMYfA6kXXWEALsE2yo9
8Y7GeV8En5Ztn4w3Pt2HMNpJV2m7MWWocSbF+ocp8oJ0cIEcthBubiE2kJhdPi5a
Yo5Bwh54hx53an+XfiDn+bfidvNz5TsJtmZykB84gLcdBQyMHrZcDcD6g74KdW3X
du/AnNUlJujhIU0fsw3UUPB845Q8XjbsIK5WhgaQeXJum8HXnXWkEfh6mE4j9l2Z
6FJVe8fQlF5lspn6z3qYsWlYRalD3N9Qxy3vpRgCAYTPc3D+fbVP9KJw1cWux1+O
0X/hNWMDOTenhgyQS+mqhRvdWq71mFjP/RXjOSfED3ipFamPofCz9QC7EERtvZPE
Pr8tBnbxnyTbAOQrN9N2mA9UJbOhk2l5kCSkDpMCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEAdVaizjIBQLGFo3uzOVAjh5IoFt/DBiSjVYOOt4HacWDDkRKOmQp3AJaA
Zecbd+tF7OsIqrBzbbfK7wp1+y1pLrPNFQghhk7gVxBP9LscLQ1JmDK2vHv9rLAg
d/gv90gf3HVeRQWntAhGTqtiSZkafcXJIRCM4GygoPy2zxeWeCTt85yEbQnuGZ4Z
qtWS/yyJR5ZQEwYG7jFWRxaZicuUdWBhlUMafRUxzjxBhsQplGKSI66eFQ5VtB7L
u/spPSSVhaun5BA1FlphB2TkgnzlCmxJa63nFY045e/Jq+IKMcqqZl/092gbI2EQ
5EpZaQ1l6H5DBXwrz58a8WTPC2Mu8g==
-----END CERTIFICATE-----',
				'releases' => [
					[
						'download' => 'https://example.com',
						'signature' => 'MySignature',
					],
					[
						'download' => 'https://nextcloud.com',
					],
				],
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);
		$realTmpFile = \OC::$server->getTempManager()->getTemporaryFile('.tar.gz');
		copy(__DIR__ . '/../data/testapp.tar.gz', $realTmpFile);
		$this->tempManager
			->expects($this->at(0))
			->method('getTemporaryFile')
			->with('.tar.gz')
			->willReturn($realTmpFile);
		$client = $this->createMock(IClient::class);
		$client
			->expects($this->once())
			->method('get')
			->with('https://example.com', ['sink' => $realTmpFile, 'timeout' => 120]);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$installer = $this->getInstaller();
		$installer->downloadApp('passman');
	}


	public function testDownloadAppWithMoreThanOneFolderDownloaded() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Extracted app testapp has more than 1 folder');

		$appArray = [
			[
				'id' => 'testapp',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAbMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDMxMTgxNTI2WhcNMjcwMjA2MTgxNTI2WjASMRAwDgYD
VQQDEwd0ZXN0YXBwMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAqa0x
FcVa0YcO/ABqSNdbf7Bzp2PBBJzVM9gI4/HzzBKU/NY9/RibBBpNjAIWEFAbTI4j
ilFSoxHDQ8HrboFOeKCrOIdp9ATQ8SnYVNIQ12Ym3LA/XxcG0gG0H7DeS9C0uACe
svN8fwD1wnKnLLU9GBzO77jwYkneed85wwKG4waHd3965gxQWq0N5gnYS0TTn7Yr
l1veRiw+ryefXvfWI0cN1WBZJ/4XAkwVlpG1HP60AunIpcwn9bfG4XCka+7x26E4
6Hw0Ot7D7j0yzVzimJDPB2h2buEtPVd6m+oNPueVvKGta+p6cEEaHlFVh2Pa9DI+
me3nb6aXE2kABWXav3BmK18A5Rg4ZY4VFYvmHmxkOhT/ulGZRqy6TccL/optqs52
KQ6P0e5dfmhLeoCvJObD+ZYKv+kJCRFtX1Hve/R4IHG6XSFKUfrRjyor9b6TX2L/
l2vV0mFjmy4g3l05vWHg1Edtq7M29S/xNA3/hF29NjBq6NoMbLGcBtFced1iK07Z
yHLjXRZRfURP671Svqqg8pjxuDqkJ2vIj/Vpod4kF2jeiZYXcfmNKhEhxpkccSe0
dI6p76Ne7XSUpf8yCPiSnWZLadqKZdEulcB4SlrZO2+/pycgqrqihofDrvDeWeeg
gQyvbZZKl4ylRNj6IRKnosKLVXNqMHQxLmxLHeUCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEALkKQwa40HfuP4Q6ShwBFJbXLyodIAXCT014kBVjReDKNl5oHtMXRjPxj
nj9doKu+3bLNuLCv9uU3H5+t/GFogReV3Av3z/fCqJ6wHv/KX+lacj31dWXZGD8G
z+RYibrxKkPN0V6q1mSvkg3hJOOE+/4FPIdc8PNlgratv3WS4dT8QwGSUavHW2Kx
89nIdnwtLEFpgML/bTG0dm8BH57xER8LCYixW1VmpV6A4IsoKVsnB7KUCRTK3iUJ
Zh8Xg8UMNrOtXc1Wx1Wmjaa4ZE9dY6/KkU2ny2UWyDHKU/9VE8QQ4HN93gxU4+H7
cUg0V1uAxqUvKytKkMfcyPWsz/AINA==
-----END CERTIFICATE-----',
				'releases' => [
					[
						'download' => 'https://example.com',
						'signature' => 'h8H3tUy2dDlwrV/hY/ZxqYqe8Vue+IINluLtAt1HxX2cjz3vdoVHJRINRkMYYcdz
VlndvHyKdqJHDAACphR8tVV6EFrPermn7gEgWk7a51LbUM7sAN7RV7ijEooUo+TQ
jNW9Ch48Wg3jvebMwWNr5t5U4MEXTP5f0YX/kxvkJoUrG3a3spt7ziEuHaq8IPvt
Jj/JSDFhvRNpom7yNNcI1Ijoq8yC11sg7RJBNfrHdGPHPZVz2SyBiY9OcvgGSpUU
bfvzhIZDCl/RRi5fs39jLLupAP69Ez6+jylNXEMsNwM0YL5+egSXFtkCvgOw8UBg
ZqNZZojcS22acuvHRnoa6PDDhwHdCH+zpifXSOhSQvue5n6q+FVX6aeD1LnCQkYB
D2wvNyZWwdADJtvDj03DKhm21g+TPy63XC94q4IqvjQ94pV8U+qrBBfkQ62NGjaC
oOU6y5sEmQeAdVRpWVo0Hewmjp4Adoj5JRwuqCVEynTC6DXHs3HvHxYlmib1F05a
GqEhdDmOHsxNaeJ08Hlptq5yLv3+0wEdtriVjgAZNVduHG1F1FkhPIrDHaB6pd67
0AFvO/pZgMSHDRHD+safBgaLb5dBZ895Qvudbq3RQevVnO+YZQYZkpmjoF/+TQ7/
YwDVP+QmNRzx72jtqAN/Kc3CvQ9nkgYhU65B95aX0xA=',
					],
					[
						'download' => 'https://nextcloud.com',
					],
				],
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);
		$realTmpFile = \OC::$server->getTempManager()->getTemporaryFile('.tar.gz');
		copy(__DIR__ . '/../data/testapp1.tar.gz', $realTmpFile);
		$this->tempManager
			->expects($this->at(0))
			->method('getTemporaryFile')
			->with('.tar.gz')
			->willReturn($realTmpFile);
		$realTmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		mkdir($realTmpFolder . '/testfolder');
		$this->tempManager
			->expects($this->at(1))
			->method('getTemporaryFolder')
			->willReturn($realTmpFolder);
		$client = $this->createMock(IClient::class);
		$client
			->expects($this->once())
			->method('get')
			->with('https://example.com', ['sink' => $realTmpFile, 'timeout' => 120]);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$installer = $this->getInstaller();
		$installer->downloadApp('testapp');
	}


	public function testDownloadAppWithMismatchingIdentifier() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App for id testapp has a wrong app ID in info.xml: testapp1');

		$appArray = [
			[
				'id' => 'testapp',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAbMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDMxMTgxNTI2WhcNMjcwMjA2MTgxNTI2WjASMRAwDgYD
VQQDEwd0ZXN0YXBwMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAqa0x
FcVa0YcO/ABqSNdbf7Bzp2PBBJzVM9gI4/HzzBKU/NY9/RibBBpNjAIWEFAbTI4j
ilFSoxHDQ8HrboFOeKCrOIdp9ATQ8SnYVNIQ12Ym3LA/XxcG0gG0H7DeS9C0uACe
svN8fwD1wnKnLLU9GBzO77jwYkneed85wwKG4waHd3965gxQWq0N5gnYS0TTn7Yr
l1veRiw+ryefXvfWI0cN1WBZJ/4XAkwVlpG1HP60AunIpcwn9bfG4XCka+7x26E4
6Hw0Ot7D7j0yzVzimJDPB2h2buEtPVd6m+oNPueVvKGta+p6cEEaHlFVh2Pa9DI+
me3nb6aXE2kABWXav3BmK18A5Rg4ZY4VFYvmHmxkOhT/ulGZRqy6TccL/optqs52
KQ6P0e5dfmhLeoCvJObD+ZYKv+kJCRFtX1Hve/R4IHG6XSFKUfrRjyor9b6TX2L/
l2vV0mFjmy4g3l05vWHg1Edtq7M29S/xNA3/hF29NjBq6NoMbLGcBtFced1iK07Z
yHLjXRZRfURP671Svqqg8pjxuDqkJ2vIj/Vpod4kF2jeiZYXcfmNKhEhxpkccSe0
dI6p76Ne7XSUpf8yCPiSnWZLadqKZdEulcB4SlrZO2+/pycgqrqihofDrvDeWeeg
gQyvbZZKl4ylRNj6IRKnosKLVXNqMHQxLmxLHeUCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEALkKQwa40HfuP4Q6ShwBFJbXLyodIAXCT014kBVjReDKNl5oHtMXRjPxj
nj9doKu+3bLNuLCv9uU3H5+t/GFogReV3Av3z/fCqJ6wHv/KX+lacj31dWXZGD8G
z+RYibrxKkPN0V6q1mSvkg3hJOOE+/4FPIdc8PNlgratv3WS4dT8QwGSUavHW2Kx
89nIdnwtLEFpgML/bTG0dm8BH57xER8LCYixW1VmpV6A4IsoKVsnB7KUCRTK3iUJ
Zh8Xg8UMNrOtXc1Wx1Wmjaa4ZE9dY6/KkU2ny2UWyDHKU/9VE8QQ4HN93gxU4+H7
cUg0V1uAxqUvKytKkMfcyPWsz/AINA==
-----END CERTIFICATE-----',
				'releases' => [
					[
						'download' => 'https://example.com',
						'signature' => 'h8H3tUy2dDlwrV/hY/ZxqYqe8Vue+IINluLtAt1HxX2cjz3vdoVHJRINRkMYYcdz
VlndvHyKdqJHDAACphR8tVV6EFrPermn7gEgWk7a51LbUM7sAN7RV7ijEooUo+TQ
jNW9Ch48Wg3jvebMwWNr5t5U4MEXTP5f0YX/kxvkJoUrG3a3spt7ziEuHaq8IPvt
Jj/JSDFhvRNpom7yNNcI1Ijoq8yC11sg7RJBNfrHdGPHPZVz2SyBiY9OcvgGSpUU
bfvzhIZDCl/RRi5fs39jLLupAP69Ez6+jylNXEMsNwM0YL5+egSXFtkCvgOw8UBg
ZqNZZojcS22acuvHRnoa6PDDhwHdCH+zpifXSOhSQvue5n6q+FVX6aeD1LnCQkYB
D2wvNyZWwdADJtvDj03DKhm21g+TPy63XC94q4IqvjQ94pV8U+qrBBfkQ62NGjaC
oOU6y5sEmQeAdVRpWVo0Hewmjp4Adoj5JRwuqCVEynTC6DXHs3HvHxYlmib1F05a
GqEhdDmOHsxNaeJ08Hlptq5yLv3+0wEdtriVjgAZNVduHG1F1FkhPIrDHaB6pd67
0AFvO/pZgMSHDRHD+safBgaLb5dBZ895Qvudbq3RQevVnO+YZQYZkpmjoF/+TQ7/
YwDVP+QmNRzx72jtqAN/Kc3CvQ9nkgYhU65B95aX0xA=',
					],
					[
						'download' => 'https://nextcloud.com',
					],
				],
			],
		];
		$this->appFetcher
			->expects($this->once())
			->method('get')
			->willReturn($appArray);
		$realTmpFile = \OC::$server->getTempManager()->getTemporaryFile('.tar.gz');
		copy(__DIR__ . '/../data/testapp1.tar.gz', $realTmpFile);
		$this->tempManager
			->expects($this->at(0))
			->method('getTemporaryFile')
			->with('.tar.gz')
			->willReturn($realTmpFile);
		$realTmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->tempManager
			->expects($this->at(1))
			->method('getTemporaryFolder')
			->willReturn($realTmpFolder);
		$client = $this->createMock(IClient::class);
		$client
			->expects($this->once())
			->method('get')
			->with('https://example.com', ['sink' => $realTmpFile, 'timeout' => 120]);
		$this->clientService
			->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$installer = $this->getInstaller();
		$installer->downloadApp('testapp');
	}

	public function testDownloadAppSuccessful() {
		$appArray = [
			[
				'id' => 'testapp',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAbMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDMxMTgxNTI2WhcNMjcwMjA2MTgxNTI2WjASMRAwDgYD
VQQDEwd0ZXN0YXBwMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAqa0x
FcVa0YcO/ABqSNdbf7Bzp2PBBJzVM9gI4/HzzBKU/NY9/RibBBpNjAIWEFAbTI4j
ilFSoxHDQ8HrboFOeKCrOIdp9ATQ8SnYVNIQ12Ym3LA/XxcG0gG0H7DeS9C0uACe
svN8fwD1wnKnLLU9GBzO77jwYkneed85wwKG4waHd3965gxQWq0N5gnYS0TTn7Yr
l1veRiw+ryefXvfWI0cN1WBZJ/4XAkwVlpG1HP60AunIpcwn9bfG4XCka+7x26E4
6Hw0Ot7D7j0yzVzimJDPB2h2buEtPVd6m+oNPueVvKGta+p6cEEaHlFVh2Pa9DI+
me3nb6aXE2kABWXav3BmK18A5Rg4ZY4VFYvmHmxkOhT/ulGZRqy6TccL/optqs52
KQ6P0e5dfmhLeoCvJObD+ZYKv+kJCRFtX1Hve/R4IHG6XSFKUfrRjyor9b6TX2L/
l2vV0mFjmy4g3l05vWHg1Edtq7M29S/xNA3/hF29NjBq6NoMbLGcBtFced1iK07Z
yHLjXRZRfURP671Svqqg8pjxuDqkJ2vIj/Vpod4kF2jeiZYXcfmNKhEhxpkccSe0
dI6p76Ne7XSUpf8yCPiSnWZLadqKZdEulcB4SlrZO2+/pycgqrqihofDrvDeWeeg
gQyvbZZKl4ylRNj6IRKnosKLVXNqMHQxLmxLHeUCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEALkKQwa40HfuP4Q6ShwBFJbXLyodIAXCT014kBVjReDKNl5oHtMXRjPxj
nj9doKu+3bLNuLCv9uU3H5+t/GFogReV3Av3z/fCqJ6wHv/KX+lacj31dWXZGD8G
z+RYibrxKkPN0V6q1mSvkg3hJOOE+/4FPIdc8PNlgratv3WS4dT8QwGSUavHW2Kx
89nIdnwtLEFpgML/bTG0dm8BH57xER8LCYixW1VmpV6A4IsoKVsnB7KUCRTK3iUJ
Zh8Xg8UMNrOtXc1Wx1Wmjaa4ZE9dY6/KkU2ny2UWyDHKU/9VE8QQ4HN93gxU4+H7
cUg0V1uAxqUvKytKkMfcyPWsz/AINA==
-----END CERTIFICATE-----',
				'releases' => [
					[
						'download' => 'https://example.com',
						'signature' => 'O5UWFRnSx4mSdEX83Uh9u7KW+Gl1OWU4uaFg6aYY19zc+lWP4rKCbAUH7Jo1Bohf
qxQbhXs4cMqGmoL8dW4zeFUqSJCRk52LA+ciLezjPFv275q+BxEgyWOylLnbhBaz
+v6lXLaeG0J/ry8wEdg+rwP8FCYPsvKlXSVbFjgubvCR/owKJJf5iL0B93noBwBN
jfbcxi7Kh16HAKy6f/gVZ6hf/4Uo7iEFMCPEHjidope+ejUpqbd8XhQg5/yh7TQ7
VKR7pkdDG2eFr5c3CpaECdNg5ZIGRbQNJHBXHT/wliorWpYJtwtNAQJ4xC635gLP
4klkKN4XtSj8bJUaJC6aaksLFgRSeKXaYAHai/XP6BkeyNzlSbsmyZk8cZbySx8F
gVOzPok1c94UGT57FjeW5eqRjtmzbYivQdP89Ouz6et7PY69yOCqiRFQanrqzwoX
MPLX6f5V9tCJtlH6ztmEcDROfvuVc0U3rEhqx2hphoyo+MZrPFpdcJL8KkIdMKbY
7yQWrsV7QvAzygAOFsC0TlSNJbmMCljouUk9di4CUZ+xsQ6n6TZtE7gsdljlKjPS
3Ys+e3V1HUaVzv8SaSmKwjRoQxQxHWLtXpJS2Yq+i+gq7LuC+aStzxAzV/h2plDW
358picx/PobNDi71Q97+/CAOq+4wDOwhKwls7lwudIs=',
					],
					[
						'download' => 'https://nextcloud.com',
					],
				],
			],
		];
		$this->appFetcher
			->expects($this->at(0))
			->method('get')
			->willReturn($appArray);
		$realTmpFile = \OC::$server->getTempManager()->getTemporaryFile('.tar.gz');
		copy(__DIR__ . '/../data/testapp.tar.gz', $realTmpFile);
		$this->tempManager
			->expects($this->at(0))
			->method('getTemporaryFile')
			->with('.tar.gz')
			->willReturn($realTmpFile);
		$realTmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->tempManager
			->expects($this->at(1))
			->method('getTemporaryFolder')
			->willReturn($realTmpFolder);
		$client = $this->createMock(IClient::class);
		$client
			->expects($this->once())
			->method('get')
			->with('https://example.com', ['sink' => $realTmpFile, 'timeout' => 120]);
		$this->clientService
			->expects($this->at(0))
			->method('newClient')
			->willReturn($client);

		$installer = $this->getInstaller();
		$installer->downloadApp('testapp');

		$this->assertTrue(file_exists(__DIR__ . '/../../apps/testapp/appinfo/info.xml'));
		$this->assertEquals('0.9', \OC_App::getAppVersionByPath(__DIR__ . '/../../apps/testapp/'));
	}


	public function testDownloadAppWithDowngrade() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('App for id testapp has version 0.9 and tried to update to lower version 0.8');

		$appArray = [
			[
				'id' => 'testapp',
				'certificate' => '-----BEGIN CERTIFICATE-----
MIIEAjCCAuoCAhAbMA0GCSqGSIb3DQEBCwUAMHsxCzAJBgNVBAYTAkRFMRswGQYD
VQQIDBJCYWRlbi1XdWVydHRlbWJlcmcxFzAVBgNVBAoMDk5leHRjbG91ZCBHbWJI
MTYwNAYDVQQDDC1OZXh0Y2xvdWQgQ29kZSBTaWduaW5nIEludGVybWVkaWF0ZSBB
dXRob3JpdHkwHhcNMTYxMDMxMTgxNTI2WhcNMjcwMjA2MTgxNTI2WjASMRAwDgYD
VQQDEwd0ZXN0YXBwMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAqa0x
FcVa0YcO/ABqSNdbf7Bzp2PBBJzVM9gI4/HzzBKU/NY9/RibBBpNjAIWEFAbTI4j
ilFSoxHDQ8HrboFOeKCrOIdp9ATQ8SnYVNIQ12Ym3LA/XxcG0gG0H7DeS9C0uACe
svN8fwD1wnKnLLU9GBzO77jwYkneed85wwKG4waHd3965gxQWq0N5gnYS0TTn7Yr
l1veRiw+ryefXvfWI0cN1WBZJ/4XAkwVlpG1HP60AunIpcwn9bfG4XCka+7x26E4
6Hw0Ot7D7j0yzVzimJDPB2h2buEtPVd6m+oNPueVvKGta+p6cEEaHlFVh2Pa9DI+
me3nb6aXE2kABWXav3BmK18A5Rg4ZY4VFYvmHmxkOhT/ulGZRqy6TccL/optqs52
KQ6P0e5dfmhLeoCvJObD+ZYKv+kJCRFtX1Hve/R4IHG6XSFKUfrRjyor9b6TX2L/
l2vV0mFjmy4g3l05vWHg1Edtq7M29S/xNA3/hF29NjBq6NoMbLGcBtFced1iK07Z
yHLjXRZRfURP671Svqqg8pjxuDqkJ2vIj/Vpod4kF2jeiZYXcfmNKhEhxpkccSe0
dI6p76Ne7XSUpf8yCPiSnWZLadqKZdEulcB4SlrZO2+/pycgqrqihofDrvDeWeeg
gQyvbZZKl4ylRNj6IRKnosKLVXNqMHQxLmxLHeUCAwEAATANBgkqhkiG9w0BAQsF
AAOCAQEALkKQwa40HfuP4Q6ShwBFJbXLyodIAXCT014kBVjReDKNl5oHtMXRjPxj
nj9doKu+3bLNuLCv9uU3H5+t/GFogReV3Av3z/fCqJ6wHv/KX+lacj31dWXZGD8G
z+RYibrxKkPN0V6q1mSvkg3hJOOE+/4FPIdc8PNlgratv3WS4dT8QwGSUavHW2Kx
89nIdnwtLEFpgML/bTG0dm8BH57xER8LCYixW1VmpV6A4IsoKVsnB7KUCRTK3iUJ
Zh8Xg8UMNrOtXc1Wx1Wmjaa4ZE9dY6/KkU2ny2UWyDHKU/9VE8QQ4HN93gxU4+H7
cUg0V1uAxqUvKytKkMfcyPWsz/AINA==
-----END CERTIFICATE-----',
				'releases' => [
					[
						'download' => 'https://example.com',
						'signature' => 'KMSao4cKdMIYxeT8Bm4lrmSeIQnk7YzJZh+Vz+4LVSBwF+OMmcujryQuWLXmbPfg
4hGI9zS025469VNjUoCprn01H8NBq3O1cXz+ewG1oxYWMMQFZDkOtUQ+XZ27b91t
y0l45H6C8j0sTeSrUb/LCjrdm+buUygkhC2RZxCI6tLi4rYWj0MiqDz98XkbB3te
pW3ZND6mG6Jxn1fnd35paqZ/+URMftoLQ4K+6vJoBVGnug9nk1RpGLouICI0zCrz
YPTsBHo0s2mPvQQ/ASacWYmSe5R6r5JCzNeGMpViGCqCYPbwuebgqK079s2zvSF9
mSLAm2Tk6gCM29N8Vdfr6ppCvIbuNzlLU/dGdYHAILgxEsm/odZjt1Fhs4lOo3A5
9ToaNl5+qOEkggwfE/QqceHAY2soW9V5d9izhTCDgXmxpPpPXkwPPTz04ZUpi1Yc
OdZZOswbEcc2jUC5T7a7Tnp0uBOkdqat6jB4oMGwU1ldYLCGRyy546cPPTXJw5kH
9WfeKJ/mavrSLVa7QqZ4RCcMigmijT1kdqbaEh05IZNrzs6VDcS2EIrbDX8SGXUk
uDDkPXZEXqNDEjyONfDXVRLiqDa52Gg+I4vW/l/4ZOFgAWdZkqPPuZFaqzZpsJXm
JXhrdaWDZ8fzpUjugrtC3qslsqL0dzgU37anS3HwrT8=',
					],
					[
						'download' => 'https://nextcloud.com',
					],
				],
			],
		];
		$this->appFetcher
			->expects($this->at(1))
			->method('get')
			->willReturn($appArray);
		$realTmpFile = \OC::$server->getTempManager()->getTemporaryFile('.tar.gz');
		copy(__DIR__ . '/../data/testapp.0.8.tar.gz', $realTmpFile);
		$this->tempManager
			->expects($this->at(2))
			->method('getTemporaryFile')
			->with('.tar.gz')
			->willReturn($realTmpFile);
		$realTmpFolder = \OC::$server->getTempManager()->getTemporaryFolder();
		$this->tempManager
			->expects($this->at(3))
			->method('getTemporaryFolder')
			->willReturn($realTmpFolder);
		$client = $this->createMock(IClient::class);
		$client
			->expects($this->once())
			->method('get')
			->with('https://example.com', ['sink' => $realTmpFile, 'timeout' => 120]);
		$this->clientService
			->expects($this->at(1))
			->method('newClient')
			->willReturn($client);
		$this->testDownloadAppSuccessful();
		$this->assertTrue(file_exists(__DIR__ . '/../../apps/testapp/appinfo/info.xml'));
		$this->assertEquals('0.9', \OC_App::getAppVersionByPath(__DIR__ . '/../../apps/testapp/'));

		$installer = $this->getInstaller();
		$installer->downloadApp('testapp');
		$this->assertTrue(file_exists(__DIR__ . '/../../apps/testapp/appinfo/info.xml'));
		$this->assertEquals('0.8', \OC_App::getAppVersionByPath(__DIR__ . '/../../apps/testapp/'));
	}
}
