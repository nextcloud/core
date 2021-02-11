<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Encryption\Command;

use OCA\Encryption\Util;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class EnableMasterKey extends Command {

	/** @var Util */
	protected $util;

	/** @var IConfig */
	protected $config;

	/** @var  QuestionHelper */
	protected $questionHelper;

	/**
	 * @param Util $util
	 * @param IConfig $config
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(Util $util,
								IConfig $config,
								QuestionHelper $questionHelper) {
		$this->util = $util;
		$this->config = $config;
		$this->questionHelper = $questionHelper;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('encryption:enable-master-key')
			->setDescription('Enable the master key. Only available for fresh installations with no existing encrypted data! There is also no way to disable it again.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isAlreadyEnabled = $this->util->isMasterKeyEnabled();

		if ($isAlreadyEnabled) {
			$output->writeln('Master key already enabled');
		} else {
			$question = new ConfirmationQuestion(
				'Warning: Only available for fresh installations with no existing encrypted data! '
			. 'There is also no way to disable it again. Do you want to continue? (y/n) ', false);
			if ($this->questionHelper->ask($input, $output, $question)) {
				$this->config->setAppValue('encryption', 'useMasterKey', '1');
				$output->writeln('Master key successfully enabled.');
			} else {
				$output->writeln('aborted.');
				return 1;
			}
		}
		return 0;
	}
}
