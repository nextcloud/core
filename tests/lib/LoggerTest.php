<?php
/**
 * Copyright (c) 2014 Thomas Müller <thomas.mueller@tmit.eu>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\Log;
use OCP\ILogger;
use OCP\Log\IWriter;

class LoggerTest extends TestCase implements IWriter {

	/** @var \OC\SystemConfig|\PHPUnit\Framework\MockObject\MockObject */
	private $config;

	/** @var \OCP\Support\CrashReport\IRegistry|\PHPUnit\Framework\MockObject\MockObject */
	private $registry;

	/** @var \OCP\ILogger */
	private $logger;

	/** @var array */
	private $logs = [];

	protected function setUp(): void {
		parent::setUp();

		$this->logs = [];
		$this->config = $this->createMock(\OC\SystemConfig::class);
		$this->registry = $this->createMock(\OCP\Support\CrashReport\IRegistry::class);
		$this->logger = new Log($this, $this->config, null, $this->registry);
	}

	public function testInterpolation() {
		$logger = $this->logger;
		$logger->warning('{Message {nothing} {user} {foo.bar} a}', ['user' => 'Bob', 'foo.bar' => 'Bar']);

		$expected = ['2 {Message {nothing} Bob Bar a}'];
		$this->assertEquals($expected, $this->getLogs());
	}

	public function testAppCondition() {
		$this->config->expects($this->any())
			->method('getValue')
			->will(($this->returnValueMap([
				['loglevel', ILogger::WARN, ILogger::WARN],
				['log.condition', [], ['apps' => ['files']]]
			])));
		$logger = $this->logger;

		$logger->info('Don\'t display info messages');
		$logger->info('Show info messages of files app', ['app' => 'files']);
		$logger->warning('Show warning messages of other apps');

		$expected = [
			'1 Show info messages of files app',
			'2 Show warning messages of other apps',
		];
		$this->assertEquals($expected, $this->getLogs());
	}

	private function getLogs() {
		return $this->logs;
	}

	public function write(string $app, $message, int $level) {
		$this->logs[] = "$level $message";
	}

	public function userAndPasswordData(): array {
		return [
			['mySpecialUsername', 'MySuperSecretPassword'],
			['my-user', '324324()#ä234'],
			['my-user', ')qwer'],
			['my-user', 'qwer)asdf'],
			['my-user', 'qwer)'],
			['my-user', '(qwer'],
			['my-user', 'qwer(asdf'],
			['my-user', 'qwer('],
		];
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectlogin(string $user, string $password): void {
		$e = new \Exception('test');
		$this->registry->expects($this->once())
			->method('delegateReport')
			->with($e, ['level' => 3]);

		$this->logger->logException($e);

		$logLines = $this->getLogs();
		foreach ($logLines as $logLine) {
			if (is_array($logLine)) {
				$logLine = json_encode($logLine);
			}
			$this->assertStringNotContainsString($user, $logLine);
			$this->assertStringNotContainsString($password, $logLine);
			$this->assertStringContainsString('*** sensitive parameters replaced ***', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectcheckPassword(string $user, string $password): void {
		$e = new \Exception('test');
		$this->registry->expects($this->once())
			->method('delegateReport')
			->with($e, ['level' => 3]);

		$this->logger->logException($e);

		$logLines = $this->getLogs();
		foreach ($logLines as $logLine) {
			if (is_array($logLine)) {
				$logLine = json_encode($logLine);
			}
			$this->assertStringNotContainsString($user, $logLine);
			$this->assertStringNotContainsString($password, $logLine);
			$this->assertStringContainsString('*** sensitive parameters replaced ***', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectvalidateUserPass(string $user, string $password): void {
		$e = new \Exception('test');
		$this->registry->expects($this->once())
			->method('delegateReport')
			->with($e, ['level' => 3]);

		$this->logger->logException($e);

		$logLines = $this->getLogs();
		foreach ($logLines as $logLine) {
			if (is_array($logLine)) {
				$logLine = json_encode($logLine);
			}
			$this->assertStringNotContainsString($user, $logLine);
			$this->assertStringNotContainsString($password, $logLine);
			$this->assertStringContainsString('*** sensitive parameters replaced ***', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetecttryLogin(string $user, string $password): void {
		$e = new \Exception('test');
		$this->registry->expects($this->once())
			->method('delegateReport')
			->with($e, ['level' => 3]);

		$this->logger->logException($e);

		$logLines = $this->getLogs();
		foreach ($logLines as $logLine) {
			if (is_array($logLine)) {
				$logLine = json_encode($logLine);
			}
			$this->assertStringNotContainsString($user, $logLine);
			$this->assertStringNotContainsString($password, $logLine);
			$this->assertStringContainsString('*** sensitive parameters replaced ***', $logLine);
		}
	}

	/**
	 * @dataProvider userAndPasswordData
	 */
	public function testDetectclosure(string $user, string $password): void {
		$a = function ($user, $password) {
			throw new \Exception('test');
		};
		$this->registry->expects($this->once())
			->method('delegateReport');

		try {
			$a($user, $password);
		} catch (\Exception $e) {
			$this->logger->logException($e);
		}

		$logLines = $this->getLogs();
		foreach ($logLines as $logLine) {
			if (is_array($logLine)) {
				$logLine = json_encode($logLine);
			}
			$log = explode('\n', $logLine);
			unset($log[1]); // Remove `testDetectclosure(` because we are not testing this here, but the closure on stack trace 0
			$logLine = implode('\n', $log);

			$this->assertStringNotContainsString($user, $logLine);
			$this->assertStringNotContainsString($password, $logLine);
			$this->assertStringContainsString('*** sensitive parameters replaced ***', $logLine);
		}
	}
}
