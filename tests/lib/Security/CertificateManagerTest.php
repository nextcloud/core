<?php
/**
 * Copyright (c) 2014 Lukas Reschke <lukas@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Security;

use OC\Files\View;
use OC\Security\CertificateManager;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ISecureRandom;

/**
 * Class CertificateManagerTest
 *
 * @group DB
 */
class CertificateManagerTest extends \Test\TestCase {
	use \Test\Traits\UserTrait;
	use \Test\Traits\MountProviderTrait;

	/** @var CertificateManager */
	private $certificateManager;
	/** @var String */
	private $username;
	/** @var ISecureRandom */
	private $random;

	protected function setUp(): void {
		parent::setUp();

		$this->username = $this->getUniqueID('', 20);
		$this->createUser($this->username, '');

		$storage = new \OC\Files\Storage\Temporary();
		$this->registerMount($this->username, $storage, '/' . $this->username . '/');

		\OC_Util::tearDownFS();
		\OC_User::setUserId('');
		\OC\Files\Filesystem::tearDown();
		\OC_Util::setupFS($this->username);

		$config = $this->createMock(IConfig::class);
		$config->expects($this->any())->method('getSystemValue')
			->with('installed', false)->willReturn(true);

		$this->random = $this->createMock(ISecureRandom::class);
		$this->random->method('generate')
			->willReturn('random');

		$this->certificateManager = new CertificateManager(
			new \OC\Files\View(),
			$config,
			$this->createMock(ILogger::class),
			$this->random
		);
	}

	protected function tearDown(): void {
		$user = \OC::$server->getUserManager()->get($this->username);
		if ($user !== null) {
			$user->delete();
		}
		parent::tearDown();
	}

	protected function assertEqualsArrays($expected, $actual) {
		sort($expected);
		sort($actual);

		$this->assertEquals($expected, $actual);
	}

	public function testListCertificates() {
		// Test empty certificate bundle
		$this->assertSame([], $this->certificateManager->listCertificates());

		// Add some certificates
		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/goodCertificate.crt'), 'GoodCertificate');
		$certificateStore = [];
		$certificateStore[] = new \OC\Security\Certificate(file_get_contents(__DIR__ . '/../../data/certificates/goodCertificate.crt'), 'GoodCertificate');
		$this->assertEqualsArrays($certificateStore, $this->certificateManager->listCertificates());

		// Add another certificates
		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/expiredCertificate.crt'), 'ExpiredCertificate');
		$certificateStore[] = new \OC\Security\Certificate(file_get_contents(__DIR__ . '/../../data/certificates/expiredCertificate.crt'), 'ExpiredCertificate');
		$this->assertEqualsArrays($certificateStore, $this->certificateManager->listCertificates());
	}


	public function testAddInvalidCertificate() {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Certificate could not get parsed.');

		$this->certificateManager->addCertificate('InvalidCertificate', 'invalidCertificate');
	}

	/**
	 * @return array
	 */
	public function dangerousFileProvider() {
		return [
			['.htaccess'],
			['../../foo.txt'],
			['..\..\foo.txt'],
		];
	}

	/**
	 * @dataProvider dangerousFileProvider
	 * @param string $filename
	 */
	public function testAddDangerousFile($filename) {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Filename is not valid');

		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/expiredCertificate.crt'), $filename);
	}

	public function testRemoveDangerousFile() {
		$this->assertFalse($this->certificateManager->removeCertificate('../../foo.txt'));
	}

	public function testRemoveExistingFile() {
		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/goodCertificate.crt'), 'GoodCertificate');
		$this->assertTrue($this->certificateManager->removeCertificate('GoodCertificate'));
	}

	public function testGetCertificateBundle() {
		$this->assertSame('/files_external/rootcerts.crt', $this->certificateManager->getCertificateBundle());
	}

	/**
	 * @dataProvider dataTestNeedRebundling
	 *
	 * @param int $CaBundleMtime
	 * @param int $targetBundleMtime
	 * @param int $targetBundleExists
	 * @param bool $expected
	 */
	public function testNeedRebundling($CaBundleMtime,
								$targetBundleMtime,
								$targetBundleExists,
								$expected
	) {
		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$config = $this->createMock(IConfig::class);

		/** @var CertificateManager | \PHPUnit\Framework\MockObject\MockObject $certificateManager */
		$certificateManager = $this->getMockBuilder('OC\Security\CertificateManager')
			->setConstructorArgs([$view, $config, $this->createMock(ILogger::class), $this->random])
			->setMethods(['getFilemtimeOfCaBundle', 'getCertificateBundle'])
			->getMock();

		$certificateManager->expects($this->any())->method('getFilemtimeOfCaBundle')
			->willReturn($CaBundleMtime);

		$certificateManager->expects($this->at(0))->method('getCertificateBundle')
			->willReturn('targetBundlePath');

		$view->expects($this->any())->method('file_exists')
			->with('targetBundlePath')
			->willReturn($targetBundleExists);


		$view->expects($this->any())->method('filemtime')
			->willReturnCallback(function ($path) use ($targetBundleMtime) {
				if ($path === 'targetBundlePath') {
					return $targetBundleMtime;
				}
				throw new \Exception('unexpected path');
			});


		$this->assertSame($expected,
			$this->invokePrivate($certificateManager, 'needsRebundling')
		);
	}

	public function dataTestNeedRebundling() {
		return [
			//values: CaBundleMtime, targetBundleMtime, targetBundleExists, expected

			[10, 30, true, false],
			[10, 15, true, false],
			[10, 8, true, true],
			[10, 30, true, false],
			[10, 8, true, true],

			// if no target bundle exists we always build a new one
			[10, 30, false, true],
			[10, 15, false, true],
			[10, 8, false, true],
			[10, 30, false, true],
			[10, 8, false, true],
		];
	}
}
