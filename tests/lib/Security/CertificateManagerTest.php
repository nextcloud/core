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
			$this->username,
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

	function testListCertificates() {
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

	
	function testAddInvalidCertificate() {
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
	function testAddDangerousFile($filename) {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Filename is not valid');

		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/expiredCertificate.crt'), $filename);
	}

	function testRemoveDangerousFile() {
		$this->assertFalse($this->certificateManager->removeCertificate('../../foo.txt'));
	}

	function testRemoveExistingFile() {
		$this->certificateManager->addCertificate(file_get_contents(__DIR__ . '/../../data/certificates/goodCertificate.crt'), 'GoodCertificate');
		$this->assertTrue($this->certificateManager->removeCertificate('GoodCertificate'));
	}

	function testGetCertificateBundle() {
		$this->assertSame('/' . $this->username . '/files_external/rootcerts.crt', $this->certificateManager->getCertificateBundle());
	}

	/**
	 * @dataProvider dataTestNeedRebundling
	 *
	 * @param string $uid
	 * @param int $CaBundleMtime
	 * @param int $systemWideMtime
	 * @param int $targetBundleMtime
	 * @param int $targetBundleExists
	 * @param bool $expected
	 */
	function testNeedRebundling($uid,
								$CaBundleMtime,
								$systemWideMtime,
								$targetBundleMtime,
								$targetBundleExists,
								$expected
	) {

		$view = $this->getMockBuilder(View::class)
			->disableOriginalConstructor()->getMock();
		$config = $this->createMock(IConfig::class);

		/** @var CertificateManager | \PHPUnit_Framework_MockObject_MockObject $certificateManager */
		$certificateManager = $this->getMockBuilder('OC\Security\CertificateManager')
			->setConstructorArgs([$uid, $view, $config, $this->createMock(ILogger::class), $this->random])
			->setMethods(['getFilemtimeOfCaBundle', 'getCertificateBundle'])
			->getMock();

		$certificateManager->expects($this->any())->method('getFilemtimeOfCaBundle')
			->willReturn($CaBundleMtime);

		$certificateManager->expects($this->at(1))->method('getCertificateBundle')
			->with($uid)->willReturn('targetBundlePath');

		$view->expects($this->any())->method('file_exists')
			->with('targetBundlePath')
			->willReturn($targetBundleExists);


		if ($uid !== null && $targetBundleExists) {
			$certificateManager->expects($this->at(2))->method('getCertificateBundle')
				->with(null)->willReturn('SystemBundlePath');

		}

		$view->expects($this->any())->method('filemtime')
			->willReturnCallback(function ($path) use ($systemWideMtime, $targetBundleMtime) {
				if ($path === 'SystemBundlePath') {
					return $systemWideMtime;
				} elseif ($path === 'targetBundlePath') {
					return $targetBundleMtime;
				}
				throw new \Exception('unexpected path');
			});


		$this->assertSame($expected,
			$this->invokePrivate($certificateManager, 'needsRebundling', [$uid])
		);

	}

	function dataTestNeedRebundling() {
		return [
			//values: uid, CaBundleMtime, systemWideMtime, targetBundleMtime, targetBundleExists, expected

			// compare minimum of CaBundleMtime and systemWideMtime with targetBundleMtime
			['user1', 10, 20, 30, true, false],
			['user1', 10, 20, 15, true, true],
			['user1', 10, 5, 30, true, false],
			['user1', 10, 5, 8, true, true],

			// if no user exists we ignore 'systemWideMtime' because this is the bundle we re-build
			[null, 10, 20, 30, true, false],
			[null, 10, 20, 15, true, false],
			[null, 10, 20, 8, true, true],
			[null, 10, 5, 30, true, false],
			[null, 10, 5, 8, true, true],

			// if no target bundle exists we always build a new one
			['user1', 10, 20, 30, false, true],
			['user1', 10, 20, 15, false, true],
			['user1', 10, 5, 30, false, true],
			['user1', 10, 5, 8, false, true],
			[null, 10, 20, 30, false, true],
			[null, 10, 20, 15, false, true],
			[null, 10, 20, 8, false, true],
			[null, 10, 5, 30, false, true],
			[null, 10, 5, 8, false, true],
		];
	}
}
