<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
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
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class RecoverUser extends Command {

	/** @var Util */
	protected $util;

	/** @var IUserManager */
	protected $userManager;

	/** @var  QuestionHelper */
	protected $questionHelper;

	/**
	 * @param Util $util
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(Util $util,
								IConfig $config,
								IUserManager $userManager,
								QuestionHelper $questionHelper) {
		$this->util = $util;
		$this->questionHelper = $questionHelper;
		$this->userManager = $userManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('encryption:recover-user')
			->setDescription('Recover user data in case of password lost. This only works if the user enabled the recovery key.');

		$this->addArgument(
			'user',
			InputArgument::REQUIRED,
			'user which should be recovered'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$isMasterKeyEnabled = $this->util->isMasterKeyEnabled();

		if ($isMasterKeyEnabled) {
			$output->writeln('You use the master key, no individual user recovery needed.');
			return 0;
		}

		$uid = $input->getArgument('user');
		$userExists = $this->userManager->userExists($uid);
		if ($userExists === false) {
			$output->writeln('User "' . $uid . '" unknown.');
			return 1;
		}

		$recoveryKeyEnabled = $this->util->isRecoveryEnabledForUser($uid);
		if ($recoveryKeyEnabled === false) {
			$output->writeln('Recovery key is not enabled for: ' . $uid);
			return 1;
		}

		$question = new Question('Please enter the recovery key password: ');
		$question->setHidden(true);
		$question->setHiddenFallback(false);
		$recoveryPassword = $this->questionHelper->ask($input, $output, $question);

		$question = new Question('Please enter the new login password for the user: ');
		$question->setHidden(true);
		$question->setHiddenFallback(false);
		$newLoginPassword = $this->questionHelper->ask($input, $output, $question);

		$output->write('Start to recover users files... This can take some time...');
		$this->userManager->get($uid)->setPassword($newLoginPassword, $recoveryPassword);
		$output->writeln('Done.');
		return 0;
	}
}
