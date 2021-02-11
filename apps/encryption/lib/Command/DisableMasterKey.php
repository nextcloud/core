<?php
/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
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

class DisableMasterKey extends Command {

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
			->setName('encryption:disable-master-key')
			->setDescription('Disable the master key and use per-user keys instead. Only available for fresh installations with no existing encrypted data! There is no way to enable it again.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isMasterKeyEnabled = $this->util->isMasterKeyEnabled();

		if (!$isMasterKeyEnabled) {
			$output->writeln('Master key already disabled');
		} else {
			$question = new ConfirmationQuestion(
				'Warning: Only perform this operation for a fresh installations with no existing encrypted data! '
				. 'There is no way to enable the master key again. '
				. 'We strongly recommend to keep the master key, it provides significant performance improvements '
				. 'and is easier to handle for both, users and administrators. '
				. 'Do you really want to switch to per-user keys? (y/n) ', false);
			if ($this->questionHelper->ask($input, $output, $question)) {
				$this->config->setAppValue('encryption', 'useMasterKey', '0');
				$output->writeln('Master key successfully disabled.');
			} else {
				$output->writeln('aborted.');
				return 1;
			}
		}
		return 0;
	}
}
