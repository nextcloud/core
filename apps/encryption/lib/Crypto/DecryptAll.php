<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Encryption\Crypto;

use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class DecryptAll {

	/** @var Util  */
	protected $util;

	/** @var QuestionHelper  */
	protected $questionHelper;

	/** @var  Crypt */
	protected $crypt;

	/** @var  KeyManager */
	protected $keyManager;

	/** @var Session  */
	protected $session;

	/**
	 * @param Util $util
	 * @param KeyManager $keyManager
	 * @param Crypt $crypt
	 * @param Session $session
	 * @param QuestionHelper $questionHelper
	 */
	public function __construct(
		Util $util,
		KeyManager $keyManager,
		Crypt $crypt,
		Session $session,
		QuestionHelper $questionHelper
	) {
		$this->util = $util;
		$this->keyManager = $keyManager;
		$this->crypt = $crypt;
		$this->session = $session;
		$this->questionHelper = $questionHelper;
	}

	/**
	 * prepare encryption module to decrypt all files
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param $user
	 * @return bool
	 */
	public function prepare(InputInterface $input, OutputInterface $output, $user) {
		$question = new Question('Please enter the recovery key password: ');

		if ($this->util->isMasterKeyEnabled()) {
			$output->writeln('Use master key to decrypt all files');
			$user = $this->keyManager->getMasterKeyId();
			$password = $this->keyManager->getMasterKeyPassword();
		} else {
			$recoveryKeyId = $this->keyManager->getRecoveryKeyId();
			if (!empty($user)) {
				$output->writeln('You can only decrypt the users files if you know');
				$output->writeln('the users password or if he activated the recovery key.');
				$output->writeln('');
				$questionUseLoginPassword = new ConfirmationQuestion(
					'Do you want to use the users login password to decrypt all files? (y/n) ',
					false
				);
				$useLoginPassword = $this->questionHelper->ask($input, $output, $questionUseLoginPassword);
				if ($useLoginPassword) {
					$question = new Question('Please enter the user\'s login password: ');
				} elseif ($this->util->isRecoveryEnabledForUser($user) === false) {
					$output->writeln('No recovery key available for user ' . $user);
					return false;
				} else {
					$user = $recoveryKeyId;
				}
			} else {
				$output->writeln('You can only decrypt the files of all users if the');
				$output->writeln('recovery key is enabled by the admin and activated by the users.');
				$output->writeln('');
				$user = $recoveryKeyId;
			}

			$question->setHidden(true);
			$question->setHiddenFallback(false);
			$password = $this->questionHelper->ask($input, $output, $question);
		}

		$privateKey = $this->getPrivateKey($user, $password);
		if ($privateKey !== false) {
			$this->updateSession($user, $privateKey);
			return true;
		} else {
			$output->writeln('Could not decrypt private key, maybe you entered the wrong password?');
		}


		return false;
	}

	/**
	 * get the private key which will be used to decrypt all files
	 *
	 * @param string $user
	 * @param string $password
	 * @return bool|string
	 * @throws \OCA\Encryption\Exceptions\PrivateKeyMissingException
	 */
	protected function getPrivateKey($user, $password) {
		$recoveryKeyId = $this->keyManager->getRecoveryKeyId();
		$masterKeyId = $this->keyManager->getMasterKeyId();
		if ($user === $recoveryKeyId) {
			$recoveryKey = $this->keyManager->getSystemPrivateKey($recoveryKeyId);
			$privateKey = $this->crypt->decryptPrivateKey($recoveryKey, $password);
		} elseif ($user === $masterKeyId) {
			$masterKey = $this->keyManager->getSystemPrivateKey($masterKeyId);
			$privateKey = $this->crypt->decryptPrivateKey($masterKey, $password, $masterKeyId);
		} else {
			$userKey = $this->keyManager->getPrivateKey($user);
			$privateKey = $this->crypt->decryptPrivateKey($userKey, $password, $user);
		}

		return $privateKey;
	}

	protected function updateSession($user, $privateKey) {
		$this->session->prepareDecryptAll($user, $privateKey);
	}
}
