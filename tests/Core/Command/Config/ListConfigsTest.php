<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Tests\Core\Command\Config;

use OC\Core\Command\Config\ListConfigs;
use OC\SystemConfig;
use OCP\IAppConfig;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class ListConfigsTest extends TestCase {
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $appConfig;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $systemConfig;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	/** @var \Symfony\Component\Console\Command\Command */
	protected $command;

	protected function setUp(): void {
		parent::setUp();

		$systemConfig = $this->systemConfig = $this->getMockBuilder(SystemConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$appConfig = $this->appConfig = $this->getMockBuilder(IAppConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)->getMock();

		/** @var \OC\SystemConfig $systemConfig */
		/** @var \OCP\IAppConfig $appConfig */
		$this->command = new ListConfigs($systemConfig, $appConfig);
	}

	public function listData() {
		return [
			[
				'all',
				// config.php
				[
					'secret',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', IConfig::SENSITIVE_VALUE],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				false,
				json_encode([
					'system' => [
						'secret' => IConfig::SENSITIVE_VALUE,
						'overwrite.cli.url' => 'http://localhost',
					],
					'apps' => [
						'core' => [
							'global_cache_gc_lastrun' => '1430388388',
						],
						'files' => [
							'enabled' => 'yes',
						],
					],
				]),
			],
			[
				'all',
				// config.php
				[
					'secret',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', 'my secret'],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				true,
				json_encode([
					'system' => [
						'secret' => 'my secret',
						'overwrite.cli.url' => 'http://localhost',
					],
					'apps' => [
						'core' => [
							'global_cache_gc_lastrun' => '1430388388',
						],
						'files' => [
							'enabled' => 'yes',
						],
					],
				]),
			],
			[
				'system',
				// config.php
				[
					'secret',
					'objectstore',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', IConfig::SENSITIVE_VALUE],
					['objectstore', 'N;', [
						'class' => 'OC\\Files\\ObjectStore\\Swift',
						'arguments' => [
							'username' => 'facebook100000123456789',
							'password' => IConfig::SENSITIVE_VALUE,
						],
					]],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				false,
				json_encode([
					'system' => [
						'secret' => IConfig::SENSITIVE_VALUE,
						'objectstore' => [
							'class' => 'OC\\Files\\ObjectStore\\Swift',
							'arguments' => [
								'username' => 'facebook100000123456789',
								'password' => IConfig::SENSITIVE_VALUE,
							],
						],
						'overwrite.cli.url' => 'http://localhost',
					],
				]),
			],
			[
				'system',
				// config.php
				[
					'secret',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', 'my secret'],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				true,
				json_encode([
					'system' => [
						'secret' => 'my secret',
						'overwrite.cli.url' => 'http://localhost',
					],
				]),
			],
			[
				'files',
				// config.php
				[
					'secret',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', 'my secret'],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				false,
				json_encode([
					'apps' => [
						'files' => [
							'enabled' => 'yes',
						],
					],
				]),
			],
			[
				'files',
				// config.php
				[
					'secret',
					'overwrite.cli.url',
				],
				[
					['secret', 'N;', 'my secret'],
					['overwrite.cli.url', 'N;', 'http://localhost'],
				],
				// app config
				[
					['files', false, [
						'enabled' => 'yes',
					]],
					['core', false, [
						'global_cache_gc_lastrun' => '1430388388',
					]],
				],
				true,
				json_encode([
					'apps' => [
						'files' => [
							'enabled' => 'yes',
						],
					],
				]),
			],
		];
	}

	/**
	 * @dataProvider listData
	 *
	 * @param string $app
	 * @param array $systemConfigs
	 * @param array $systemConfigMap
	 * @param array $appConfig
	 * @param bool $private
	 * @param string $expected
	 */
	public function testList($app, $systemConfigs, $systemConfigMap, $appConfig, $private, $expected) {
		$this->systemConfig->expects($this->any())
			->method('getKeys')
			->willReturn($systemConfigs);
		if ($private) {
			$this->systemConfig->expects($this->any())
				->method('getValue')
				->willReturnMap($systemConfigMap);
			$this->appConfig->expects($this->any())
				->method('getValues')
				->willReturnMap($appConfig);
		} else {
			$this->systemConfig->expects($this->any())
				->method('getFilteredValue')
				->willReturnMap($systemConfigMap);
			$this->appConfig->expects($this->any())
				->method('getFilteredValues')
				->willReturnMap($appConfig);
		}

		$this->appConfig->expects($this->any())
			->method('getApps')
			->willReturn(['core', 'files']);
		$this->appConfig->expects($this->any())
			->method('getValues')
			->willReturnMap($appConfig);

		$this->consoleInput->expects($this->once())
			->method('getArgument')
			->with('app')
			->willReturn($app);

		$this->consoleInput->expects($this->any())
			->method('getOption')
			->willReturnMap([
				['output', 'json'],
				['private', $private],
			]);

		global $output;

		$output = '';
		$this->consoleOutput->expects($this->any())
			->method('writeln')
			->willReturnCallback(function ($value) {
				global $output;
				$output .= $value . "\n";
				return $output;
			});

		$this->invokePrivate($this->command, 'execute', [$this->consoleInput, $this->consoleOutput]);

		$this->assertEquals($expected, trim($output, "\n"));
	}
}
