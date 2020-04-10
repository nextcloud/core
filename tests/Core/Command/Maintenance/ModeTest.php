<?php

namespace Tests\Core\Command\Maintenance;

use OC\Core\Command\Maintenance\Mode;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

/**
 * This class provides tests methods for the Mode command.
 *
 * @package Tests\Core\Command\Maintenance
 */
class ModeTest extends TestCase {
	/**
	 * A config mock passed to the command.
	 *
	 * @var IConfig|MockObject
	 */
	private $config;

	/**
	 * Holds a Mode command instance with a config mock.
	 *
	 * @var Mode
	 */
	private $mode;

	/**
	 * An input mock for tests.
	 *
	 * @var InputInterface|MockObject
	 */
	private $input;

	/**
	 * An output mock for tests.
	 *
	 * @var OutputInterface|MockObject
	 */
	private $output;

	/**
	 * Setups the test environment.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->getMockBuilder(IConfig::class)
			->getMock();
		$this->mode = new Mode($this->config);
		$this->input = $this->getMockBuilder(InputInterface::class)
			->getMock();
		$this->output = $this->getMockBuilder(OutputInterface::class)
			->getMock();
	}

	/**
	 * Provides test data for the execute test.
	 *
	 * @return array
	 */
	public function getExecuteTestData(): array {
		return [
			'off -> on' => [
				'on', // command option
				false, // current maintenance mode state
				true, // expected maintenance mode state, null for no change
				'Maintenance mode enabled', // expected output
			],
			'on -> off' => [
				'off',
				true,
				false,
				'Maintenance mode disabled',
			],
			'on -> on' => [
				'on',
				true,
				null,
				'Maintenance mode already enabled',
			],
			'off -> off' => [
				'off',
				false,
				null,
				'Maintenance mode already disabled',
			],
			'no option, maintenance enabled' => [
				'',
				true,
				null,
				'Maintenance mode is currently enabled',
			],
			'no option, maintenance disabled' => [
				'',
				false,
				null,
				'Maintenance mode is currently disabled',
			],
		];
	}

	/**
	 * Asserts that execute works as expected.
	 *
	 * @dataProvider getExecuteTestData
	 * @param string $option The command option.
	 * @param bool $currentMaintenanceState The current maintenance state.
	 * @param null|bool $expectedMaintenanceState
	 * 					The expected maintenance state. Null for no change.
	 * @param string $expectedOutput The expected command output.
	 * @throws \Exception
	 */
	public function testExecute(
		string $option,
		bool $currentMaintenanceState,
		$expectedMaintenanceState,
		string $expectedOutput
	) {
		$this->config->expects($this->any())
			->method('getSystemValueBool')
			->willReturn($currentMaintenanceState);

		if ($expectedMaintenanceState !== null) {
			$this->config->expects($this->once())
				->method('setSystemValue')
				->with('maintenance', $expectedMaintenanceState);
		}

		$this->input->expects($this->any())
			->method('getOption')
			->willReturnCallback(function ($callOption) use ($option) {
				return $callOption === $option;
			});

		$this->output->expects($this->once())
			->method('writeln')
			->with($expectedOutput);

		$this->mode->run($this->input, $this->output);
	}
}
