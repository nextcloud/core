<?php
/**
 * @copyright Copyright (c) 2020, NextCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Laurens Post <lkpost@scept.re>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sujith H <sharidasan@owncloud.com>
 * @author Sean Molenaar <sean@seanmolenaar.eu>
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

namespace OC\Core\Command\User;

use OC\Authentication\Token\IProvider;
use OC\Authentication\Token\IToken;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AddAppPassword extends Command {

	/** @var IUserManager */
	protected $userManager;
	/** @var IProvider */
	protected $tokenProvider;
	/** @var ISecureRandom */
	private $random;
	/** @var ICrypto */
	private $crypto;

	public function __construct(IUserManager $userManager,
								IProvider $tokenProvider,
								ISecureRandom $random,
								ICrypto $crypto) {
		$this->tokenProvider = $tokenProvider;
		$this->userManager = $userManager;
		$this->random = $random;
		$this->crypto = $crypto;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('user:add-app-password')
			->setDescription('Add app password for the named user')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'Username to add app password for'
			)
			->addOption(
				'password-from-env',
				null,
				InputOption::VALUE_NONE,
				'read password from environment variable NC_PASS/OC_PASS'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$username = $input->getArgument('user');

		$user = $this->userManager->get($username);
		if (is_null($user)) {
			$output->writeln('<error>User does not exist</error>');
			return 1;
		}

		if ($input->getOption('password-from-env')) {
			$password = getenv('NC_PASS') ?? getenv('OC_PASS');
			if (!$password) {
				$output->writeln('<error>--password-from-env given, but NC_PASS is empty!</error>');
				return 1;
			}
		} elseif ($input->isInteractive()) {
			/** @var QuestionHelper $helper */
			$helper = $this->getHelper('question');

			$question = new Question('Enter the user password: ');
			$question->setHidden(true);
			$password = $helper->ask($input, $output, $question);

			if ($password === null) {
				$output->writeln("<error>Password cannot be empty!</error>");
				return 1;
			}
		} else {
			$output->writeln("<error>Interactive input or --password-from-env is needed for entering a new password!</error>");
			return 1;
		}

		$output->writeln('<info>The password is not validated so what you provide is what gets recorded in the token</info>');


		$token = $this->random->generate(72, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS);
		$this->tokenProvider->generateToken(
			$token,
			$user->getUID(),
			$user->getDisplayName(),
			$password,
			'cli',
			IToken::PERMANENT_TOKEN,
			IToken::DO_NOT_REMEMBER
		);

		$output->writeln('app password:');
		$output->writeln($token);

		return 0;
	}
}
