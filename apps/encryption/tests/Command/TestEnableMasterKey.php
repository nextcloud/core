<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Encryption\Tests\Command;

use OCA\Encryption\Command\EnableMasterKey;
use OCA\Encryption\Util;
use OCP\IConfig;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class TestEnableMasterKey extends TestCase {

	/** @var  EnableMasterKey */
	protected $enableMasterKey;

	/** @var  Util | \PHPUnit_Framework_MockObject_MockObject */
	protected $util;

	/** @var \OCP\IConfig | \PHPUnit_Framework_MockObject_MockObject  */
	protected $config;

	/** @var \Symfony\Component\Console\Helper\QuestionHelper | \PHPUnit_Framework_MockObject_MockObject */
	protected $questionHelper;

	/** @var  \Symfony\Component\Console\Output\OutputInterface | \PHPUnit_Framework_MockObject_MockObject */
	protected $output;

	/** @var  \Symfony\Component\Console\Input\InputInterface | \PHPUnit_Framework_MockObject_MockObject */
	protected $input;

	protected function setUp(): void {
		parent::setUp();

		$this->util = $this->getMockBuilder(Util::class)
			->disableOriginalConstructor()->getMock();
		$this->config = $this->getMockBuilder(IConfig::class)
			->disableOriginalConstructor()->getMock();
		$this->questionHelper = $this->getMockBuilder(QuestionHelper::class)
			->disableOriginalConstructor()->getMock();
		$this->output = $this->getMockBuilder(OutputInterface::class)
			->disableOriginalConstructor()->getMock();
		$this->input = $this->getMockBuilder(InputInterface::class)
			->disableOriginalConstructor()->getMock();

		$this->enableMasterKey = new EnableMasterKey($this->util, $this->config, $this->questionHelper);
	}

	/**
	 * @dataProvider dataTestExecute
	 *
	 * @param bool $isAlreadyEnabled
	 * @param string $answer
	 */
	public function testExecute($isAlreadyEnabled, $answer) {

		$this->util->expects($this->once())->method('isMasterKeyEnabled')
			->willReturn($isAlreadyEnabled);

		if ($isAlreadyEnabled) {
			$this->output->expects($this->once())->method('writeln')
				->with('Master key already enabled');
		} else {
			if ($answer === 'y') {
				$this->questionHelper->expects($this->once())->method('ask')->willReturn(true);
				$this->config->expects($this->once())->method('setAppValue')
					->with('encryption', 'useMasterKey', '1');
			} else {
				$this->questionHelper->expects($this->once())->method('ask')->willReturn(false);
				$this->config->expects($this->never())->method('setAppValue');

			}
		}

		$this->invokePrivate($this->enableMasterKey, 'execute', [$this->input, $this->output]);
	}

	public function dataTestExecute() {
		return [
			[true, ''],
			[false, 'y'],
			[false, 'n'],
			[false, '']
		];
	}
}
