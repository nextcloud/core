<?php
/**
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author nhirokinet <nhirokinet@nhiroki.net>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sylvia van Os <sylvia@hackerchick.me>
 * @author Timo Förster <tfoerster@webfoersterei.de>
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

use bantu\IniGetWrapper\IniGetWrapper;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use OC;
use OC\DB\Connection;
use OC\IntegrityCheck\Checker;
use OC\MemoryInfo;
use OC\Security\SecureRandom;
use OCA\Settings\Controller\CheckSetupController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Test\TestCase;

/**
 * Class CheckSetupControllerTest
 *
 * @backupStaticAttributes
 * @package Tests\Settings\Controller
 */
class CheckSetupControllerTest extends TestCase {
	/** @var CheckSetupController | \PHPUnit\Framework\MockObject\MockObject */
	private $checkSetupController;
	/** @var IRequest | \PHPUnit\Framework\MockObject\MockObject */
	private $request;
	/** @var IConfig | \PHPUnit\Framework\MockObject\MockObject */
	private $config;
	/** @var IClientService | \PHPUnit\Framework\MockObject\MockObject*/
	private $clientService;
	/** @var IURLGenerator | \PHPUnit\Framework\MockObject\MockObject */
	private $urlGenerator;
	/** @var IL10N | \PHPUnit\Framework\MockObject\MockObject */
	private $l10n;
	/** @var ILogger */
	private $logger;
	/** @var Checker|\PHPUnit\Framework\MockObject\MockObject */
	private $checker;
	/** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
	private $dispatcher;
	/** @var Connection|\PHPUnit\Framework\MockObject\MockObject */
	private $db;
	/** @var ILockingProvider|\PHPUnit\Framework\MockObject\MockObject */
	private $lockingProvider;
	/** @var IDateTimeFormatter|\PHPUnit\Framework\MockObject\MockObject */
	private $dateTimeFormatter;
	/** @var MemoryInfo|MockObject */
	private $memoryInfo;
	/** @var SecureRandom|\PHPUnit\Framework\MockObject\MockObject */
	private $secureRandom;
	/** @var IniGetWrapper|\PHPUnit\Framework\MockObject\MockObject */
	private $iniGetWrapper;
	/** @var IDBConnection|\PHPUnit\Framework\MockObject\MockObject */
	private $connection;

	/**
	 * Holds a list of directories created during tests.
	 *
	 * @var array
	 */
	private $dirsToRemove = [];

	protected function setUp(): void {
		parent::setUp();

		$this->request = $this->getMockBuilder(IRequest::class)
			->disableOriginalConstructor()->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()->getMock();
		$this->clientService = $this->getMockBuilder(IClientService::class)
			->disableOriginalConstructor()->getMock();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)
			->disableOriginalConstructor()->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()->getMock();
		$this->l10n->expects($this->any())
			->method('t')
			->willReturnCallback(function ($message, array $replace) {
				return vsprintf($message, $replace);
			});
		$this->dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
			->disableOriginalConstructor()->getMock();
		$this->checker = $this->getMockBuilder('\OC\IntegrityCheck\Checker')
				->disableOriginalConstructor()->getMock();
		$this->logger = $this->getMockBuilder(ILogger::class)->getMock();
		$this->db = $this->getMockBuilder(Connection::class)
			->disableOriginalConstructor()->getMock();
		$this->lockingProvider = $this->getMockBuilder(ILockingProvider::class)->getMock();
		$this->dateTimeFormatter = $this->getMockBuilder(IDateTimeFormatter::class)->getMock();
		$this->memoryInfo = $this->getMockBuilder(MemoryInfo::class)
			->setMethods(['isMemoryLimitSufficient',])
			->getMock();
		$this->secureRandom = $this->getMockBuilder(SecureRandom::class)->getMock();
		$this->iniGetWrapper = $this->getMockBuilder(IniGetWrapper::class)->getMock();
		$this->connection = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()->getMock();
		$this->checkSetupController = $this->getMockBuilder(CheckSetupController::class)
			->setConstructorArgs([
				'settings',
				$this->request,
				$this->config,
				$this->clientService,
				$this->urlGenerator,
				$this->l10n,
				$this->checker,
				$this->logger,
				$this->dispatcher,
				$this->db,
				$this->lockingProvider,
				$this->dateTimeFormatter,
				$this->memoryInfo,
				$this->secureRandom,
				$this->iniGetWrapper,
				$this->connection,
			])
			->setMethods([
				'isReadOnlyConfig',
				'hasValidTransactionIsolationLevel',
				'hasFileinfoInstalled',
				'hasWorkingFileLocking',
				'getLastCronInfo',
				'getSuggestedOverwriteCliURL',
				'getCurlVersion',
				'isPhpOutdated',
				'isOpcacheProperlySetup',
				'hasFreeTypeSupport',
				'hasMissingIndexes',
				'hasMissingPrimaryKeys',
				'isSqliteUsed',
				'isPHPMailerUsed',
				'hasOpcacheLoaded',
				'getAppDirsWithDifferentOwner',
				'hasRecommendedPHPModules',
				'hasBigIntConversionPendingColumns',
				'isMysqlUsedWithoutUTF8MB4',
				'isEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed',
			])->getMock();
	}

	/**
	 * Removes directories created during tests.
	 *
	 * @after
	 * @return void
	 */
	public function removeTestDirectories() {
		foreach ($this->dirsToRemove as $dirToRemove) {
			rmdir($dirToRemove);
		}
		$this->dirsToRemove = [];
	}

	public function testIsInternetConnectionWorkingDisabledViaConfig() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(false);

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'hasInternetConnectivityProblems'
			)
		);
	}

	public function testIsInternetConnectionWorkingCorrectly() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);

		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with('connectivity_check_domains', ['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org'])
			->willReturn(['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org']);

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->any())
			->method('get');

		$this->clientService->expects($this->once())
			->method('newClient')
			->willReturn($client);


		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'hasInternetConnectivityProblems'
			)
		);
	}

	public function testIsInternetConnectionFail() {
		$this->config->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);

		$this->config->expects($this->at(1))
			->method('getSystemValue')
			->with('connectivity_check_domains', ['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org'])
			->willReturn(['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org']);

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->any())
			->method('get')
			->will($this->throwException(new \Exception()));

		$this->clientService->expects($this->exactly(4))
			->method('newClient')
			->willReturn($client);

		$this->assertTrue(
			self::invokePrivate(
				$this->checkSetupController,
				'hasInternetConnectivityProblems'
			)
		);
	}


	public function testIsMemcacheConfiguredFalse() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('memcache.local', null)
			->willReturn(null);

		$this->assertFalse(
			self::invokePrivate(
				$this->checkSetupController,
				'isMemcacheConfigured'
			)
		);
	}

	public function testIsMemcacheConfiguredTrue() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('memcache.local', null)
			->willReturn('SomeProvider');

		$this->assertTrue(
			self::invokePrivate(
				$this->checkSetupController,
				'isMemcacheConfigured'
			)
		);
	}

	public function testIsPhpSupportedFalse() {
		$this->checkSetupController
			->expects($this->once())
			->method('isPhpOutdated')
			->willReturn(true);

		$this->assertEquals(
			['eol' => true, 'version' => PHP_VERSION],
			self::invokePrivate($this->checkSetupController, 'isPhpSupported')
		);
	}

	public function testIsPhpSupportedTrue() {
		$this->checkSetupController
			->expects($this->exactly(2))
			->method('isPhpOutdated')
			->willReturn(false);

		$this->assertEquals(
			['eol' => false, 'version' => PHP_VERSION],
			self::invokePrivate($this->checkSetupController, 'isPhpSupported')
		);


		$this->assertEquals(
			['eol' => false, 'version' => PHP_VERSION],
			self::invokePrivate($this->checkSetupController, 'isPhpSupported')
		);
	}

	/**
	 * @dataProvider dataForwardedForHeadersWorking
	 *
	 * @param array $trustedProxies
	 * @param string $remoteAddrNotForwarded
	 * @param string $remoteAddr
	 * @param bool $result
	 */
	public function testForwardedForHeadersWorking(array $trustedProxies, string $remoteAddrNotForwarded, string $remoteAddr, bool $result) {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('trusted_proxies', [])
			->willReturn($trustedProxies);
		$this->request->expects($this->atLeastOnce())
			->method('getHeader')
			->willReturnMap([
				['REMOTE_ADDR', $remoteAddrNotForwarded],
				['X-Forwarded-Host', '']
			]);
		$this->request->expects($this->any())
			->method('getRemoteAddress')
			->willReturn($remoteAddr);

		$this->assertEquals(
			$result,
			self::invokePrivate($this->checkSetupController, 'forwardedForHeadersWorking')
		);
	}

	public function dataForwardedForHeadersWorking() {
		return [
			// description => trusted proxies, getHeader('REMOTE_ADDR'), getRemoteAddr, expected result
			'no trusted proxies' => [[], '2.2.2.2', '2.2.2.2', true],
			'trusted proxy, remote addr not trusted proxy' => [['1.1.1.1'], '2.2.2.2', '2.2.2.2', true],
			'trusted proxy, remote addr is trusted proxy, x-forwarded-for working' => [['1.1.1.1'], '1.1.1.1', '2.2.2.2', true],
			'trusted proxy, remote addr is trusted proxy, x-forwarded-for not set' => [['1.1.1.1'], '1.1.1.1', '1.1.1.1', false],
		];
	}

	public function testForwardedHostPresentButTrustedProxiesEmpty() {
		$this->config->expects($this->once())
			->method('getSystemValue')
			->with('trusted_proxies', [])
			->willReturn([]);
		$this->request->expects($this->atLeastOnce())
			->method('getHeader')
			->willReturnMap([
				['REMOTE_ADDR', '1.1.1.1'],
				['X-Forwarded-Host', 'nextcloud.test']
			]);
		$this->request->expects($this->any())
			->method('getRemoteAddress')
			->willReturn('1.1.1.1');

		$this->assertEquals(
			false,
			self::invokePrivate($this->checkSetupController, 'forwardedForHeadersWorking')
		);
	}

	public function testCheck() {
		$this->config->expects($this->at(0))
			->method('getAppValue')
			->with('files_external', 'user_certificate_scan', false)
			->willReturn('["a", "b"]');
		$this->config->expects($this->at(1))
			->method('getAppValue')
			->with('core', 'cronErrors')
			->willReturn('');
		$this->config->expects($this->at(3))
			->method('getSystemValue')
			->with('connectivity_check_domains', ['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org'])
			->willReturn(['www.nextcloud.com', 'www.startpage.com', 'www.eff.org', 'www.edri.org']);
		$this->config->expects($this->at(4))
			->method('getSystemValue')
			->with('memcache.local', null)
			->willReturn('SomeProvider');
		$this->config->expects($this->at(5))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config->expects($this->at(6))
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->willReturn(false);

		$this->request->expects($this->atLeastOnce())
			->method('getHeader')
			->willReturnMap([
				['REMOTE_ADDR', '4.3.2.1'],
				['X-Forwarded-Host', '']
			]);

		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$client->expects($this->at(0))
			->method('get')
			->with('http://www.nextcloud.com/', [])
			->will($this->throwException(new \Exception()));
		$client->expects($this->at(1))
			->method('get')
			->with('http://www.startpage.com/', [])
			->will($this->throwException(new \Exception()));
		$client->expects($this->at(2))
			->method('get')
			->with('http://www.eff.org/', [])
			->will($this->throwException(new \Exception()));
		$client->expects($this->at(3))
			->method('get')
			->with('http://www.edri.org/', [])
			->will($this->throwException(new \Exception()));
		$this->clientService->expects($this->exactly(4))
			->method('newClient')
			->willReturn($client);
		$this->checkSetupController
			->expects($this->once())
			->method('isPhpOutdated')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('isOpcacheProperlySetup')
			->willReturn(false);
		$this->checkSetupController
			->method('hasFreeTypeSupport')
			->willReturn(false);
		$this->checkSetupController
			->method('hasMissingIndexes')
			->willReturn([]);
		$this->checkSetupController
			->method('hasMissingPrimaryKeys')
			->willReturn([]);
		$this->checkSetupController
			->method('isSqliteUsed')
			->willReturn(false);
		$this->checkSetupController
			->expects($this->once())
			->method('isReadOnlyConfig')
			->willReturn(false);
		$this->checkSetupController
			->expects($this->once())
			->method('hasValidTransactionIsolationLevel')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('hasFileinfoInstalled')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('hasOpcacheLoaded')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('hasWorkingFileLocking')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getSuggestedOverwriteCliURL')
			->willReturn('');
		$this->checkSetupController
			->expects($this->once())
			->method('getLastCronInfo')
			->willReturn([
				'diffInSeconds' => 123,
				'relativeTime' => '2 hours ago',
				'backgroundJobsUrl' => 'https://example.org',
			]);
		$this->checker
			->expects($this->once())
			->method('hasPassedCheck')
			->willReturn(true);
		$this->memoryInfo
			->method('isMemoryLimitSufficient')
			->willReturn(true);

		$this->checkSetupController
			->expects($this->once())
			->method('getAppDirsWithDifferentOwner')
			->willReturn([]);

		$this->checkSetupController
			->expects($this->once())
			->method('hasRecommendedPHPModules')
			->willReturn([]);

		$this->checkSetupController
			->expects($this->once())
			->method('hasBigIntConversionPendingColumns')
			->willReturn([]);

		$this->checkSetupController
			->expects($this->once())
			->method('isMysqlUsedWithoutUTF8MB4')
			->willReturn(false);

		$this->checkSetupController
			->expects($this->once())
			->method('isEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed')
			->willReturn(true);

		$this->urlGenerator->method('linkToDocs')
			->willReturnCallback(function (string $key): string {
				if ($key === 'admin-performance') {
					return 'http://docs.example.org/server/go.php?to=admin-performance';
				}
				if ($key === 'admin-security') {
					return 'https://docs.example.org/server/8.1/admin_manual/configuration_server/hardening.html';
				}
				if ($key === 'admin-reverse-proxy') {
					return 'reverse-proxy-doc-link';
				}
				if ($key === 'admin-code-integrity') {
					return 'http://docs.example.org/server/go.php?to=admin-code-integrity';
				}
				if ($key === 'admin-php-opcache') {
					return 'http://docs.example.org/server/go.php?to=admin-php-opcache';
				}
				if ($key === 'admin-db-conversion') {
					return 'http://docs.example.org/server/go.php?to=admin-db-conversion';
				}
				return '';
			});

		$this->urlGenerator->method('getAbsoluteURL')
			->willReturnCallback(function (string $url): string {
				if ($url === 'index.php/settings/admin') {
					return 'https://server/index.php/settings/admin';
				}
				if ($url === 'index.php') {
					return 'https://server/index.php';
				}
				return '';
			});
		$sqlitePlatform = $this->getMockBuilder(SqlitePlatform::class)->getMock();
		$this->connection->method('getDatabasePlatform')
			->willReturn($sqlitePlatform);

		$expected = new DataResponse(
			[
				'isGetenvServerWorking' => true,
				'isReadOnlyConfig' => false,
				'hasValidTransactionIsolationLevel' => true,
				'hasFileinfoInstalled' => true,
				'hasWorkingFileLocking' => true,
				'suggestedOverwriteCliURL' => '',
				'cronInfo' => [
					'diffInSeconds' => 123,
					'relativeTime' => '2 hours ago',
					'backgroundJobsUrl' => 'https://example.org',
				],
				'cronErrors' => [],
				'serverHasInternetConnectionProblems' => true,
				'isMemcacheConfigured' => true,
				'memcacheDocs' => 'http://docs.example.org/server/go.php?to=admin-performance',
				'isRandomnessSecure' => self::invokePrivate($this->checkSetupController, 'isRandomnessSecure'),
				'securityDocs' => 'https://docs.example.org/server/8.1/admin_manual/configuration_server/hardening.html',
				'isUsedTlsLibOutdated' => '',
				'phpSupported' => [
					'eol' => true,
					'version' => PHP_VERSION
				],
				'forwardedForHeadersWorking' => true,
				'reverseProxyDocs' => 'reverse-proxy-doc-link',
				'isCorrectMemcachedPHPModuleInstalled' => true,
				'hasPassedCodeIntegrityCheck' => true,
				'codeIntegrityCheckerDocumentation' => 'http://docs.example.org/server/go.php?to=admin-code-integrity',
				'isOpcacheProperlySetup' => false,
				'hasOpcacheLoaded' => true,
				'phpOpcacheDocumentation' => 'http://docs.example.org/server/go.php?to=admin-php-opcache',
				'isSettimelimitAvailable' => true,
				'hasFreeTypeSupport' => false,
				'isSqliteUsed' => false,
				'databaseConversionDocumentation' => 'http://docs.example.org/server/go.php?to=admin-db-conversion',
				'missingIndexes' => [],
				'missingPrimaryKeys' => [],
				'missingColumns' => [],
				'isMemoryLimitSufficient' => true,
				'appDirsWithDifferentOwner' => [],
				'recommendedPHPModules' => [],
				'pendingBigIntConversionColumns' => [],
				'isMysqlUsedWithoutUTF8MB4' => false,
				'isEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed' => true,
				'reverseProxyGeneratedURL' => 'https://server/index.php',
				'OCA\Settings\SetupChecks\PhpDefaultCharset' => ['pass' => true, 'description' => 'PHP configuration option default_charset should be UTF-8', 'severity' => 'warning'],
				'OCA\Settings\SetupChecks\PhpOutputBuffering' => ['pass' => true, 'description' => 'PHP configuration option output_buffering must be disabled', 'severity' => 'error'],
				'OCA\Settings\SetupChecks\LegacySSEKeyFormat' => ['pass' => true, 'description' => 'The old server-side-encryption format is enabled. We recommend disabling this.', 'severity' => 'warning', 'linkToDocumentation' => ''],
				'OCA\Settings\SetupChecks\CheckUserCertificates' => ['pass' => false, 'description' => 'There are some user imported SSL certificates present, that are not used anymore with Nextcloud 21. They can be imported on the command line via "occ security:certificates:import" command. Their paths inside the data directory are shown below.', 'severity' => 'warning', 'elements' => ['a', 'b']],
				'imageMagickLacksSVGSupport' => false,
				'isDefaultPhoneRegionSet' => false,
				'OCA\Settings\SetupChecks\SupportedDatabase' => ['pass' => true, 'description' => '', 'severity' => 'info'],
			]
		);
		$this->assertEquals($expected, $this->checkSetupController->check());
	}

	public function testGetCurlVersion() {
		$checkSetupController = $this->getMockBuilder(CheckSetupController::class)
			->setConstructorArgs([
				'settings',
				$this->request,
				$this->config,
				$this->clientService,
				$this->urlGenerator,
				$this->l10n,
				$this->checker,
				$this->logger,
				$this->dispatcher,
				$this->db,
				$this->lockingProvider,
				$this->dateTimeFormatter,
				$this->memoryInfo,
				$this->secureRandom,
				$this->iniGetWrapper,
				$this->connection,
			])
			->setMethods(null)->getMock();

		$this->assertArrayHasKey('ssl_version', $this->invokePrivate($checkSetupController, 'getCurlVersion'));
	}

	public function testIsUsedTlsLibOutdatedWithAnotherLibrary() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'SSLlib']);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithMisbehavingCurl() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn([]);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithOlderOpenSsl() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'OpenSSL/1.0.1c']);
		$this->assertSame('cURL is using an outdated OpenSSL version (OpenSSL/1.0.1c). Please update your operating system or features such as installing and updating apps via the app store or Federated Cloud Sharing will not work reliably.', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithOlderOpenSslAndWithoutAppstore() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'OpenSSL/1.0.1c']);
		$this->assertSame('cURL is using an outdated OpenSSL version (OpenSSL/1.0.1c). Please update your operating system or features such as Federated Cloud Sharing will not work reliably.', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithOlderOpenSsl1() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'OpenSSL/1.0.2a']);
		$this->assertSame('cURL is using an outdated OpenSSL version (OpenSSL/1.0.2a). Please update your operating system or features such as installing and updating apps via the app store or Federated Cloud Sharing will not work reliably.', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithMatchingOpenSslVersion() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'OpenSSL/1.0.1d']);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithMatchingOpenSslVersion1() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'OpenSSL/1.0.2b']);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	/**
	 * Setups a temp directory and some subdirectories.
	 * Then calls the 'getAppDirsWithDifferentOwner' method.
	 * The result is expected to be empty since
	 * there are no directories with different owners than the current user.
	 *
	 * @return void
	 */
	public function testAppDirectoryOwnersOk() {
		$tempDir = tempnam(sys_get_temp_dir(), 'apps') . 'dir';
		mkdir($tempDir);
		mkdir($tempDir . DIRECTORY_SEPARATOR . 'app1');
		mkdir($tempDir . DIRECTORY_SEPARATOR . 'app2');
		$this->dirsToRemove[] = $tempDir . DIRECTORY_SEPARATOR . 'app1';
		$this->dirsToRemove[] = $tempDir . DIRECTORY_SEPARATOR . 'app2';
		$this->dirsToRemove[] = $tempDir;
		OC::$APPSROOTS = [
			[
				'path' => $tempDir,
				'url' => '/apps',
				'writable' => true,
			],
		];
		$this->assertSame(
			[],
			$this->invokePrivate($this->checkSetupController, 'getAppDirsWithDifferentOwner')
		);
	}

	/**
	 * Calls the check for a none existing app root that is marked as not writable.
	 * It's expected that no error happens since the check shouldn't apply.
	 *
	 * @return void
	 */
	public function testAppDirectoryOwnersNotWritable() {
		$tempDir = tempnam(sys_get_temp_dir(), 'apps') . 'dir';
		OC::$APPSROOTS = [
			[
				'path' => $tempDir,
				'url' => '/apps',
				'writable' => false,
			],
		];
		$this->assertSame(
			[],
			$this->invokePrivate($this->checkSetupController, 'getAppDirsWithDifferentOwner')
		);
	}

	public function testIsBuggyNss400() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'NSS/1.0.2b']);
		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$exception = $this->getMockBuilder('\GuzzleHttp\Exception\ClientException')
			->disableOriginalConstructor()->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
			->disableOriginalConstructor()->getMock();
		$response->expects($this->once())
			->method('getStatusCode')
			->willReturn(400);
		$exception->expects($this->once())
			->method('getResponse')
			->willReturn($response);

		$client->expects($this->at(0))
			->method('get')
			->with('https://nextcloud.com/', [])
			->will($this->throwException($exception));

		$this->clientService->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$this->assertSame('cURL is using an outdated NSS version (NSS/1.0.2b). Please update your operating system or features such as installing and updating apps via the app store or Federated Cloud Sharing will not work reliably.', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}


	public function testIsBuggyNss200() {
		$this->config->expects($this->any())
			->method('getSystemValue')
			->willReturn(true);
		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn(['ssl_version' => 'NSS/1.0.2b']);
		$client = $this->getMockBuilder('\OCP\Http\Client\IClient')
			->disableOriginalConstructor()->getMock();
		$exception = $this->getMockBuilder('\GuzzleHttp\Exception\ClientException')
			->disableOriginalConstructor()->getMock();
		$response = $this->getMockBuilder(ResponseInterface::class)
			->disableOriginalConstructor()->getMock();
		$response->expects($this->once())
			->method('getStatusCode')
			->willReturn(200);
		$exception->expects($this->once())
			->method('getResponse')
			->willReturn($response);

		$client->expects($this->at(0))
			->method('get')
			->with('https://nextcloud.com/', [])
			->will($this->throwException($exception));

		$this->clientService->expects($this->once())
			->method('newClient')
			->willReturn($client);

		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithInternetDisabled() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(false);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithAppstoreDisabledAndServerToServerSharingEnabled() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->willReturn(false);
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('files_sharing', 'outgoing_server2server_share_enabled', 'yes')
			->willReturn('no');
		$this->config
			->expects($this->at(3))
			->method('getAppValue')
			->with('files_sharing', 'incoming_server2server_share_enabled', 'yes')
			->willReturn('yes');

		$this->checkSetupController
			->expects($this->once())
			->method('getCurlVersion')
			->willReturn([]);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testIsUsedTlsLibOutdatedWithAppstoreDisabledAndServerToServerSharingDisabled() {
		$this->config
			->expects($this->at(0))
			->method('getSystemValue')
			->with('has_internet_connection', true)
			->willReturn(true);
		$this->config
			->expects($this->at(1))
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->willReturn(false);
		$this->config
			->expects($this->at(2))
			->method('getAppValue')
			->with('files_sharing', 'outgoing_server2server_share_enabled', 'yes')
			->willReturn('no');
		$this->config
			->expects($this->at(3))
			->method('getAppValue')
			->with('files_sharing', 'incoming_server2server_share_enabled', 'yes')
			->willReturn('no');

		$this->checkSetupController
			->expects($this->never())
			->method('getCurlVersion')
			->willReturn([]);
		$this->assertSame('', $this->invokePrivate($this->checkSetupController, 'isUsedTlsLibOutdated'));
	}

	public function testRescanFailedIntegrityCheck() {
		$this->checker
			->expects($this->once())
			->method('runInstanceVerification');
		$this->urlGenerator
			->expects($this->once())
			->method('linkToRoute')
			->with('settings.AdminSettings.index')
			->willReturn('/admin');

		$expected = new RedirectResponse('/admin');
		$this->assertEquals($expected, $this->checkSetupController->rescanFailedIntegrityCheck());
	}

	public function testGetFailedIntegrityCheckDisabled() {
		$this->checker
			->expects($this->once())
			->method('isCodeCheckEnforced')
			->willReturn(false);

		$expected = new DataDisplayResponse('Integrity checker has been disabled. Integrity cannot be verified.');
		$this->assertEquals($expected, $this->checkSetupController->getFailedIntegrityCheckFiles());
	}


	public function testGetFailedIntegrityCheckFilesWithNoErrorsFound() {
		$this->checker
			->expects($this->once())
			->method('isCodeCheckEnforced')
			->willReturn(true);
		$this->checker
			->expects($this->once())
			->method('getResults')
			->willReturn([]);

		$expected = new DataDisplayResponse(
				'No errors have been found.',
				Http::STATUS_OK,
				[
					'Content-Type' => 'text/plain',
				]
		);
		$this->assertEquals($expected, $this->checkSetupController->getFailedIntegrityCheckFiles());
	}

	public function testGetFailedIntegrityCheckFilesWithSomeErrorsFound() {
		$this->checker
			->expects($this->once())
			->method('isCodeCheckEnforced')
			->willReturn(true);
		$this->checker
				->expects($this->once())
				->method('getResults')
				->willReturn([ 'core' => [ 'EXTRA_FILE' => ['/testfile' => []], 'INVALID_HASH' => [ '/.idea/workspace.xml' => [ 'expected' => 'f1c5e2630d784bc9cb02d5a28f55d6f24d06dae2a0fee685f3c2521b050955d9d452769f61454c9ddfa9c308146ade10546cfa829794448eaffbc9a04a29d216', 'current' => 'ce08bf30bcbb879a18b49239a9bec6b8702f52452f88a9d32142cad8d2494d5735e6bfa0d8642b2762c62ca5be49f9bf4ec231d4a230559d4f3e2c471d3ea094', ], '/lib/private/integritycheck/checker.php' => [ 'expected' => 'c5a03bacae8dedf8b239997901ba1fffd2fe51271d13a00cc4b34b09cca5176397a89fc27381cbb1f72855fa18b69b6f87d7d5685c3b45aee373b09be54742ea', 'current' => '88a3a92c11db91dec1ac3be0e1c87f862c95ba6ffaaaa3f2c3b8f682187c66f07af3a3b557a868342ef4a271218fe1c1e300c478e6c156c5955ed53c40d06585', ], '/settings/controller/checksetupcontroller.php' => [ 'expected' => '3e1de26ce93c7bfe0ede7c19cb6c93cadc010340225b375607a7178812e9de163179b0dc33809f451e01f491d93f6f5aaca7929685d21594cccf8bda732327c4', 'current' => '09563164f9904a837f9ca0b5f626db56c838e5098e0ccc1d8b935f68fa03a25c5ec6f6b2d9e44a868e8b85764dafd1605522b4af8db0ae269d73432e9a01e63a', ], ], ], 'bookmarks' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'dav' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'encryption' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'external' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'federation' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_antivirus' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_drop' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_external' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_pdfviewer' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_sharing' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_trashbin' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_versions' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'files_videoviewer' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'firstrunwizard' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'gitsmart' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'logreader' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature could not get verified.', ], ], 'password_policy' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'provisioning_api' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'sketch' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'threatblock' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'two_factor_auth' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'user_ldap' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], 'user_shibboleth' => [ 'EXCEPTION' => [ 'class' => 'OC\\IntegrityCheck\\Exceptions\\InvalidSignatureException', 'message' => 'Signature data not found.', ], ], ]);

		$expected = new DataDisplayResponse(
				'Technical information
=====================
The following list covers which files have failed the integrity check. Please read
the previous linked documentation to learn more about the errors and how to fix
them.

Results
=======
- core
	- EXTRA_FILE
		- /testfile
	- INVALID_HASH
		- /.idea/workspace.xml
		- /lib/private/integritycheck/checker.php
		- /settings/controller/checksetupcontroller.php
- bookmarks
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- dav
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- encryption
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- external
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- federation
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_antivirus
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_drop
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_external
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_pdfviewer
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_sharing
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_trashbin
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_versions
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- files_videoviewer
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- firstrunwizard
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- gitsmart
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- logreader
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature could not get verified.
- password_policy
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- provisioning_api
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- sketch
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- threatblock
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- two_factor_auth
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- user_ldap
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.
- user_shibboleth
	- EXCEPTION
		- OC\IntegrityCheck\Exceptions\InvalidSignatureException
		- Signature data not found.

Raw output
==========
Array
(
    [core] => Array
        (
            [EXTRA_FILE] => Array
                (
                    [/testfile] => Array
                        (
                        )

                )

            [INVALID_HASH] => Array
                (
                    [/.idea/workspace.xml] => Array
                        (
                            [expected] => f1c5e2630d784bc9cb02d5a28f55d6f24d06dae2a0fee685f3c2521b050955d9d452769f61454c9ddfa9c308146ade10546cfa829794448eaffbc9a04a29d216
                            [current] => ce08bf30bcbb879a18b49239a9bec6b8702f52452f88a9d32142cad8d2494d5735e6bfa0d8642b2762c62ca5be49f9bf4ec231d4a230559d4f3e2c471d3ea094
                        )

                    [/lib/private/integritycheck/checker.php] => Array
                        (
                            [expected] => c5a03bacae8dedf8b239997901ba1fffd2fe51271d13a00cc4b34b09cca5176397a89fc27381cbb1f72855fa18b69b6f87d7d5685c3b45aee373b09be54742ea
                            [current] => 88a3a92c11db91dec1ac3be0e1c87f862c95ba6ffaaaa3f2c3b8f682187c66f07af3a3b557a868342ef4a271218fe1c1e300c478e6c156c5955ed53c40d06585
                        )

                    [/settings/controller/checksetupcontroller.php] => Array
                        (
                            [expected] => 3e1de26ce93c7bfe0ede7c19cb6c93cadc010340225b375607a7178812e9de163179b0dc33809f451e01f491d93f6f5aaca7929685d21594cccf8bda732327c4
                            [current] => 09563164f9904a837f9ca0b5f626db56c838e5098e0ccc1d8b935f68fa03a25c5ec6f6b2d9e44a868e8b85764dafd1605522b4af8db0ae269d73432e9a01e63a
                        )

                )

        )

    [bookmarks] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [dav] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [encryption] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [external] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [federation] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_antivirus] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_drop] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_external] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_pdfviewer] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_sharing] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_trashbin] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_versions] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [files_videoviewer] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [firstrunwizard] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [gitsmart] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [logreader] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature could not get verified.
                )

        )

    [password_policy] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [provisioning_api] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [sketch] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [threatblock] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [two_factor_auth] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [user_ldap] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

    [user_shibboleth] => Array
        (
            [EXCEPTION] => Array
                (
                    [class] => OC\IntegrityCheck\Exceptions\InvalidSignatureException
                    [message] => Signature data not found.
                )

        )

)
',
				Http::STATUS_OK,
				[
					'Content-Type' => 'text/plain',
				]
		);
		$this->assertEquals($expected, $this->checkSetupController->getFailedIntegrityCheckFiles());
	}

	public function dataForIsMysqlUsedWithoutUTF8MB4() {
		return [
			['sqlite', false, false],
			['sqlite', true, false],
			['postgres', false, false],
			['postgres', true, false],
			['oci', false, false],
			['oci', true, false],
			['mysql', false, true],
			['mysql', true, false],
		];
	}

	/**
	 * @dataProvider dataForIsMysqlUsedWithoutUTF8MB4
	 */
	public function testIsMysqlUsedWithoutUTF8MB4(string $db, bool $useUTF8MB4, bool $expected) {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) use ($db, $useUTF8MB4) {
				if ($key === 'dbtype') {
					return $db;
				}
				if ($key === 'mysql.utf8mb4') {
					return $useUTF8MB4;
				}
				return $default;
			});

		$checkSetupController = new CheckSetupController(
				'settings',
				$this->request,
				$this->config,
				$this->clientService,
				$this->urlGenerator,
				$this->l10n,
				$this->checker,
				$this->logger,
				$this->dispatcher,
				$this->db,
				$this->lockingProvider,
				$this->dateTimeFormatter,
				$this->memoryInfo,
				$this->secureRandom,
				$this->iniGetWrapper,
				$this->connection
			);

		$this->assertSame($expected, $this->invokePrivate($checkSetupController, 'isMysqlUsedWithoutUTF8MB4'));
	}

	public function dataForIsEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed() {
		return [
			['singlebucket', 'OC\\Files\\ObjectStore\\Swift', true],
			['multibucket', 'OC\\Files\\ObjectStore\\Swift', true],
			['singlebucket', 'OC\\Files\\ObjectStore\\Custom', true],
			['multibucket', 'OC\Files\\ObjectStore\\Custom', true],
			['singlebucket', 'OC\Files\ObjectStore\Swift', true],
			['multibucket', 'OC\Files\ObjectStore\Swift', true],
			['singlebucket', 'OC\Files\ObjectStore\Custom', true],
			['multibucket', 'OC\Files\ObjectStore\Custom', true],
		];
	}

	/**
	 * @dataProvider dataForIsEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed
	 */
	public function testIsEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed(string $mode, string $className, bool $expected) {
		$this->config->method('getSystemValue')
			->willReturnCallback(function ($key, $default) use ($mode, $className) {
				if ($key === 'objectstore' && $mode === 'singlebucket') {
					return ['class' => $className];
				}
				if ($key === 'objectstore_multibucket' && $mode === 'multibucket') {
					return ['class' => $className];
				}
				return $default;
			});

		$checkSetupController = new CheckSetupController(
			'settings',
			$this->request,
			$this->config,
			$this->clientService,
			$this->urlGenerator,
			$this->l10n,
			$this->checker,
			$this->logger,
			$this->dispatcher,
			$this->db,
			$this->lockingProvider,
			$this->dateTimeFormatter,
			$this->memoryInfo,
			$this->secureRandom,
			$this->iniGetWrapper,
			$this->connection
		);

		$this->assertSame($expected, $this->invokePrivate($checkSetupController, 'isEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed'));
	}
}
