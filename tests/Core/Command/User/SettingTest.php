<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
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

namespace Tests\Core\Command\User;

use OC\Core\Command\User\Setting;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class SettingTest extends TestCase {
	/** @var \OCP\IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $userManager;
	/** @var \OCP\IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;
	/** @var \OCP\IDBConnection|\PHPUnit_Framework_MockObject_MockObject */
	protected $connection;
	/** @var \Symfony\Component\Console\Input\InputInterface|\PHPUnit_Framework_MockObject_MockObject */
	protected $consoleInput;
	/** @var \Symfony\Component\Console\Output\OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
	protected $consoleOutput;

	protected function setUp(): void {
		parent::setUp();

		$this->userManager = $this->getMockBuilder(IUserManager::class)
			->disableOriginalConstructor()
			->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()
			->getMock();
		$this->connection = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleInput = $this->getMockBuilder(InputInterface::class)
			->disableOriginalConstructor()
			->getMock();
		$this->consoleOutput = $this->getMockBuilder(OutputInterface::class)
			->disableOriginalConstructor()
			->getMock();
	}

	public function getCommand(array $methods = []) {
		if (empty($methods)) {
			return new Setting($this->userManager, $this->config, $this->connection);
		} else {
			$mock = $this->getMockBuilder('OC\Core\Command\User\Setting')
				->setConstructorArgs([
					$this->userManager,
					$this->config,
					$this->connection,
				])
				->setMethods($methods)
				->getMock();
			return $mock;
		}

	}

	public function dataCheckInput() {
		return [
			[
				[['uid', 'username']],
				[['ignore-missing-user', true]],
				[],
				false,
				false,
			],
			[
				[['uid', 'username']],
				[['ignore-missing-user', false]],
				[],
				null,
				'The user "username" does not exist.',
			],

			[
				[['uid', 'username'], ['key', 'configkey']],
				[['ignore-missing-user', true]],
				[['--default-value', false, true]],
				false,
				false,
			],
			[
				[['uid', 'username'], ['key', '']],
				[['ignore-missing-user', true]],
				[['--default-value', false, true]],
				false,
				'The "default-value" option can only be used when specifying a key.',
			],

			[
				[['uid', 'username'], ['key', 'configkey'], ['value', '']],
				[['ignore-missing-user', true]],
				[],
				false,
				false,
			],
			[
				[['uid', 'username'], ['key', ''], ['value', '']],
				[['ignore-missing-user', true]],
				[],
				false,
				'The value argument can only be used when specifying a key.',
			],
			[
				[['uid', 'username'], ['key', 'configkey'], ['value', '']],
				[['ignore-missing-user', true]],
				[['--default-value', false, true]],
				false,
				'The value argument can not be used together with "default-value".',
			],
			[
				[['uid', 'username'], ['key', 'configkey'], ['value', '']],
				[['ignore-missing-user', true], ['update-only', true]],
				[],
				false,
				false,
			],
			[
				[['uid', 'username'], ['key', 'configkey'], ['value', null]],
				[['ignore-missing-user', true], ['update-only', true]],
				[],
				false,
				'The "update-only" option can only be used together with "value".',
			],

			[
				[['uid', 'username'], ['key', 'configkey']],
				[['ignore-missing-user', true], ['delete', true]],
				[],
				false,
				false,
			],
			[
				[['uid', 'username'], ['key', '']],
				[['ignore-missing-user', true], ['delete', true]],
				[],
				false,
				'The "delete" option can only be used when specifying a key.',
			],
			[
				[['uid', 'username'], ['key', 'configkey']],
				[['ignore-missing-user', true], ['delete', true]],
				[['--default-value', false, true]],
				false,
				'The "delete" option can not be used together with "default-value".',
			],
			[
				[['uid', 'username'], ['key', 'configkey'], ['value', '']],
				[['ignore-missing-user', true], ['delete', true]],
				[],
				false,
				'The "delete" option can not be used together with "value".',
			],
			[
				[['uid', 'username'], ['key', 'configkey']],
				[['ignore-missing-user', true], ['delete', true], ['error-if-not-exists', true]],
				[],
				false,
				false,
			],
			[
				[['uid', 'username'], ['key', 'configkey']],
				[['ignore-missing-user', true], ['delete', false], ['error-if-not-exists', true]],
				[],
				false,
				'The "error-if-not-exists" option can only be used together with "delete".',
			],
		];
	}

	/**
	 * @dataProvider dataCheckInput
	 *
	 * @param array $arguments
	 * @param array $options
	 * @param array $parameterOptions
	 * @param mixed $user
	 * @param string $expectedException
	 */
	public function testCheckInput($arguments, $options, $parameterOptions, $user, $expectedException) {
		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->willReturnMap($arguments);
		$this->consoleInput->expects($this->any())
			->method('getOption')
			->willReturnMap($options);
		$this->consoleInput->expects($this->any())
			->method('hasParameterOption')
			->willReturnMap($parameterOptions);

		if ($user !== false) {
			$this->userManager->expects($this->once())
				->method('userExists')
				->willReturn($user);
		}

		$command = $this->getCommand();
		try {
			$this->invokePrivate($command, 'checkInput', [$this->consoleInput]);
			$this->assertFalse($expectedException);
		} catch (\InvalidArgumentException $e) {
			$this->assertEquals($expectedException, $e->getMessage());
		}
	}

	public function testCheckInputExceptionCatch() {
		$command = $this->getCommand(['checkInput']);
		$command->expects($this->once())
			->method('checkInput')
			->willThrowException(new \InvalidArgumentException('test'));

		$this->consoleOutput->expects($this->once())
			->method('writeln')
			->with('<error>test</error>');

		$this->assertEquals(1, $this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]));
	}

	public function dataExecuteDelete() {
		return [
			['config', false, null, 0],
			['config', true, null, 0],
			[null, false, null, 0],
			[null, true, '<error>The setting does not exist for user "username".</error>', 1],
		];
	}

	/**
	 * @dataProvider dataExecuteDelete
	 *
	 * @param string|null $value
	 * @param bool $errorIfNotExists
	 * @param string $expectedLine
	 * @param int $expectedReturn
	 */
	public function testExecuteDelete($value, $errorIfNotExists, $expectedLine, $expectedReturn) {
		$command = $this->getCommand([
			'writeArrayInOutputFormat',
			'checkInput',
			'getUserSettings',
		]);

		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->willReturnMap([
				['uid', 'username'],
				['app', 'appname'],
				['key', 'configkey'],
			]);

		$command->expects($this->once())
			->method('checkInput');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('username', 'appname', 'configkey', null)
			->willReturn($value);

		$this->consoleInput->expects($this->atLeastOnce())
			->method('hasParameterOption')
			->willReturnMap([
				['--delete', false, true],
				['--error-if-not-exists', false, $errorIfNotExists],
			]);

		if ($expectedLine === null) {
			$this->consoleOutput->expects($this->never())
				->method('writeln');
			$this->config->expects($this->once())
				->method('deleteUserValue')
				->with('username', 'appname', 'configkey');

		} else {
			$this->consoleOutput->expects($this->once())
				->method('writeln')
				->with($expectedLine);
			$this->config->expects($this->never())
				->method('deleteUserValue');
		}

		$this->assertEquals($expectedReturn, $this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]));
	}

	public function dataExecuteSet() {
		return [
			['config', false, null, 0],
			['config', true, null, 0],
			[null, false, null, 0],
			[null, true, '<error>The setting does not exist for user "username".</error>', 1],
		];
	}

	/**
	 * @dataProvider dataExecuteSet
	 *
	 * @param string|null $value
	 * @param bool $updateOnly
	 * @param string $expectedLine
	 * @param int $expectedReturn
	 */
	public function testExecuteSet($value, $updateOnly, $expectedLine, $expectedReturn) {
		$command = $this->getCommand([
			'writeArrayInOutputFormat',
			'checkInput',
			'getUserSettings',
		]);

		$this->consoleInput->expects($this->atLeast(4))
			->method('getArgument')
			->willReturnMap([
				['uid', 'username'],
				['app', 'appname'],
				['key', 'configkey'],
				['value', 'setValue'],
			]);

		$command->expects($this->once())
			->method('checkInput');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('username', 'appname', 'configkey', null)
			->willReturn($value);

		$this->consoleInput->expects($this->atLeastOnce())
			->method('hasParameterOption')
			->willReturnMap([
				['--update-only', false, $updateOnly],
			]);

		if ($expectedLine === null) {
			$this->consoleOutput->expects($this->never())
				->method('writeln');

			$this->consoleInput->expects($this->never())
				->method('getOption');

			$this->config->expects($this->once())
				->method('setUserValue')
				->with('username', 'appname', 'configkey', 'setValue');
		} else {
			$this->consoleOutput->expects($this->once())
				->method('writeln')
				->with($expectedLine);
			$this->config->expects($this->never())
				->method('setUserValue');
		}

		$this->assertEquals($expectedReturn, $this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]));
	}

	public function dataExecuteGet() {
		return [
			['config', null, 'config', 0],
			[null, 'config', 'config', 0],
			[null, null, '<error>The setting does not exist for user "username".</error>', 1],
		];
	}

	/**
	 * @dataProvider dataExecuteGet
	 *
	 * @param string|null $value
	 * @param string|null $defaultValue
	 * @param string $expectedLine
	 * @param int $expectedReturn
	 */
	public function testExecuteGet($value, $defaultValue, $expectedLine, $expectedReturn) {
		$command = $this->getCommand([
			'writeArrayInOutputFormat',
			'checkInput',
			'getUserSettings',
		]);

		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->willReturnMap([
				['uid', 'username'],
				['app', 'appname'],
				['key', 'configkey'],
			]);

		$command->expects($this->once())
			->method('checkInput');

		$this->config->expects($this->once())
			->method('getUserValue')
			->with('username', 'appname', 'configkey', null)
			->willReturn($value);

		if ($value === null) {
			if ($defaultValue === null) {
				$this->consoleInput->expects($this->atLeastOnce())
					->method('hasParameterOption')
					->willReturnMap([
						['--default-value', false],
					]);
			} else {
				$this->consoleInput->expects($this->atLeastOnce())
					->method('hasParameterOption')
					->willReturnMap([
						['--default-value', false, true],
					]);
				$this->consoleInput->expects($this->once())
					->method('getOption')
					->with('default-value')
					->willReturn($defaultValue);
			}
		}

		$this->consoleOutput->expects($this->once())
			->method('writeln')
			->with($expectedLine);

		$this->assertEquals($expectedReturn, $this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]));
	}

	public function testExecuteList() {
		$command = $this->getCommand([
			'writeArrayInOutputFormat',
			'checkInput',
			'getUserSettings',
		]);

		$this->consoleInput->expects($this->any())
			->method('getArgument')
			->willReturnMap([
				['uid', 'username'],
				['app', 'appname'],
				['key', ''],
			]);

		$command->expects($this->once())
			->method('checkInput');
		$command->expects($this->once())
			->method('getUserSettings')
			->willReturn(['settings']);
		$command->expects($this->once())
			->method('writeArrayInOutputFormat')
			->with($this->consoleInput, $this->consoleOutput, ['settings']);


		$this->assertEquals(0, $this->invokePrivate($command, 'execute', [$this->consoleInput, $this->consoleOutput]));
	}
}
