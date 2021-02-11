<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Kenneth Newwood <kenneth@newwood.name>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

use OC\Encryption\Exceptions\DecryptionFailedException;
use OC\Files\View;
use OCA\Encryption\KeyManager;
use OCA\Encryption\Users\Setup;
use OCA\Encryption\Util;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class EncryptAll {

	/** @var Setup */
	protected $userSetup;

	/** @var IUserManager */
	protected $userManager;

	/** @var View */
	protected $rootView;

	/** @var KeyManager */
	protected $keyManager;

	/** @var Util */
	protected $util;

	/** @var array  */
	protected $userPasswords;

	/** @var  IConfig */
	protected $config;

	/** @var IMailer */
	protected $mailer;

	/** @var  IL10N */
	protected $l;

	/** @var  QuestionHelper */
	protected $questionHelper;

	/** @var  OutputInterface */
	protected $output;

	/** @var  InputInterface */
	protected $input;

	/** @var ISecureRandom */
	protected $secureRandom;

	/**
	 * @param Setup $userSetup
	 * @param IUserManager $userManager
	 * @param View $rootView
	 * @param KeyManager $keyManager
	 * @param Util $util
	 * @param IConfig $config
	 * @param IMailer $mailer
	 * @param IL10N $l
	 * @param QuestionHelper $questionHelper
	 * @param ISecureRandom $secureRandom
	 */
	public function __construct(
		Setup $userSetup,
		IUserManager $userManager,
		View $rootView,
		KeyManager $keyManager,
		Util $util,
		IConfig $config,
		IMailer $mailer,
		IL10N $l,
		QuestionHelper $questionHelper,
		ISecureRandom $secureRandom
	) {
		$this->userSetup = $userSetup;
		$this->userManager = $userManager;
		$this->rootView = $rootView;
		$this->keyManager = $keyManager;
		$this->util = $util;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->l = $l;
		$this->questionHelper = $questionHelper;
		$this->secureRandom = $secureRandom;
		// store one time passwords for the users
		$this->userPasswords = [];
	}

	/**
	 * start to encrypt all files
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	public function encryptAll(InputInterface $input, OutputInterface $output) {
		$this->input = $input;
		$this->output = $output;

		$headline = 'Encrypt all files with the ' . Encryption::DISPLAY_NAME;
		$this->output->writeln("\n");
		$this->output->writeln($headline);
		$this->output->writeln(str_pad('', strlen($headline), '='));
		$this->output->writeln("\n");

		if ($this->util->isMasterKeyEnabled()) {
			$this->output->writeln('Use master key to encrypt all files.');
			$this->keyManager->validateMasterKey();
		} else {
			//create private/public keys for each user and store the private key password
			$this->output->writeln('Create key-pair for every user');
			$this->output->writeln('------------------------------');
			$this->output->writeln('');
			$this->output->writeln('This module will encrypt all files in the users files folder initially.');
			$this->output->writeln('Already existing versions and files in the trash bin will not be encrypted.');
			$this->output->writeln('');
			$this->createKeyPairs();
		}


		// output generated encryption key passwords
		if ($this->util->isMasterKeyEnabled() === false) {
			//send-out or display password list and write it to a file
			$this->output->writeln("\n");
			$this->output->writeln('Generated encryption key passwords');
			$this->output->writeln('----------------------------------');
			$this->output->writeln('');
			$this->outputPasswords();
		}

		//setup users file system and encrypt all files one by one (take should encrypt setting of storage into account)
		$this->output->writeln("\n");
		$this->output->writeln('Start to encrypt users files');
		$this->output->writeln('----------------------------');
		$this->output->writeln('');
		$this->encryptAllUsersFiles();
		$this->output->writeln("\n");
	}

	/**
	 * create key-pair for every user
	 */
	protected function createKeyPairs() {
		$this->output->writeln("\n");
		$progress = new ProgressBar($this->output);
		$progress->setFormat(" %message% \n [%bar%]");
		$progress->start();

		foreach ($this->userManager->getBackends() as $backend) {
			$limit = 500;
			$offset = 0;
			do {
				$users = $backend->getUsers('', $limit, $offset);
				foreach ($users as $user) {
					if ($this->keyManager->userHasKeys($user) === false) {
						$progress->setMessage('Create key-pair for ' . $user);
						$progress->advance();
						$this->setupUserFS($user);
						$password = $this->generateOneTimePassword($user);
						$this->userSetup->setupUser($user, $password);
					} else {
						// users which already have a key-pair will be stored with a
						// empty password and filtered out later
						$this->userPasswords[$user] = '';
					}
				}
				$offset += $limit;
			} while (count($users) >= $limit);
		}

		$progress->setMessage('Key-pair created for all users');
		$progress->finish();
	}

	/**
	 * iterate over all user and encrypt their files
	 */
	protected function encryptAllUsersFiles() {
		$this->output->writeln("\n");
		$progress = new ProgressBar($this->output);
		$progress->setFormat(" %message% \n [%bar%]");
		$progress->start();
		$numberOfUsers = count($this->userPasswords);
		$userNo = 1;
		if ($this->util->isMasterKeyEnabled()) {
			$this->encryptAllUserFilesWithMasterKey($progress);
		} else {
			foreach ($this->userPasswords as $uid => $password) {
				$userCount = "$uid ($userNo of $numberOfUsers)";
				$this->encryptUsersFiles($uid, $progress, $userCount);
				$userNo++;
			}
		}
		$progress->setMessage("all files encrypted");
		$progress->finish();
	}

	/**
	 * encrypt all user files with the master key
	 *
	 * @param ProgressBar $progress
	 */
	protected function encryptAllUserFilesWithMasterKey(ProgressBar $progress) {
		$userNo = 1;
		foreach ($this->userManager->getBackends() as $backend) {
			$limit = 500;
			$offset = 0;
			do {
				$users = $backend->getUsers('', $limit, $offset);
				foreach ($users as $user) {
					$userCount = "$user ($userNo)";
					$this->encryptUsersFiles($user, $progress, $userCount);
					$userNo++;
				}
				$offset += $limit;
			} while (count($users) >= $limit);
		}
	}

	/**
	 * encrypt files from the given user
	 *
	 * @param string $uid
	 * @param ProgressBar $progress
	 * @param string $userCount
	 */
	protected function encryptUsersFiles($uid, ProgressBar $progress, $userCount) {
		$this->setupUserFS($uid);
		$directories = [];
		$directories[] = '/' . $uid . '/files';

		while ($root = array_pop($directories)) {
			$content = $this->rootView->getDirectoryContent($root);
			foreach ($content as $file) {
				$path = $root . '/' . $file['name'];
				if ($this->rootView->is_dir($path)) {
					$directories[] = $path;
					continue;
				} else {
					$progress->setMessage("encrypt files for user $userCount: $path");
					$progress->advance();
					if ($this->encryptFile($path) === false) {
						$progress->setMessage("encrypt files for user $userCount: $path (already encrypted)");
						$progress->advance();
					}
				}
			}
		}
	}

	/**
	 * encrypt file
	 *
	 * @param string $path
	 * @return bool
	 */
	protected function encryptFile($path) {

		// skip already encrypted files
		$fileInfo = $this->rootView->getFileInfo($path);
		if ($fileInfo !== false && $fileInfo->isEncrypted()) {
			return true;
		}

		$source = $path;
		$target = $path . '.encrypted.' . time();

		try {
			$this->rootView->copy($source, $target);
			$this->rootView->rename($target, $source);
		} catch (DecryptionFailedException $e) {
			if ($this->rootView->file_exists($target)) {
				$this->rootView->unlink($target);
			}
			return false;
		}

		return true;
	}

	/**
	 * output one-time encryption passwords
	 */
	protected function outputPasswords() {
		$table = new Table($this->output);
		$table->setHeaders(['Username', 'Private key password']);

		//create rows
		$newPasswords = [];
		$unchangedPasswords = [];
		foreach ($this->userPasswords as $uid => $password) {
			if (empty($password)) {
				$unchangedPasswords[] = $uid;
			} else {
				$newPasswords[] = [$uid, $password];
			}
		}

		if (empty($newPasswords)) {
			$this->output->writeln("\nAll users already had a key-pair, no further action needed.\n");
			return;
		}

		$table->setRows($newPasswords);
		$table->render();

		if (!empty($unchangedPasswords)) {
			$this->output->writeln("\nThe following users already had a key-pair which was reused without setting a new password:\n");
			foreach ($unchangedPasswords as $uid) {
				$this->output->writeln("    $uid");
			}
		}

		$this->writePasswordsToFile($newPasswords);

		$this->output->writeln('');
		$question = new ConfirmationQuestion('Do you want to send the passwords directly to the users by mail? (y/n) ', false);
		if ($this->questionHelper->ask($this->input, $this->output, $question)) {
			$this->sendPasswordsByMail();
		}
	}

	/**
	 * write one-time encryption passwords to a csv file
	 *
	 * @param array $passwords
	 */
	protected function writePasswordsToFile(array $passwords) {
		$fp = $this->rootView->fopen('oneTimeEncryptionPasswords.csv', 'w');
		foreach ($passwords as $pwd) {
			fputcsv($fp, $pwd);
		}
		fclose($fp);
		$this->output->writeln("\n");
		$this->output->writeln('A list of all newly created passwords was written to data/oneTimeEncryptionPasswords.csv');
		$this->output->writeln('');
		$this->output->writeln('Each of these users need to login to the web interface, go to the');
		$this->output->writeln('personal settings section "basic encryption module" and');
		$this->output->writeln('update the private key password to match the login password again by');
		$this->output->writeln('entering the one-time password into the "old log-in password" field');
		$this->output->writeln('and their current login password');
	}

	/**
	 * setup user file system
	 *
	 * @param string $uid
	 */
	protected function setupUserFS($uid) {
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($uid);
	}

	/**
	 * generate one time password for the user and store it in a array
	 *
	 * @param string $uid
	 * @return string password
	 */
	protected function generateOneTimePassword($uid) {
		$password = $this->secureRandom->generate(8);
		$this->userPasswords[$uid] = $password;
		return $password;
	}

	/**
	 * send encryption key passwords to the users by mail
	 */
	protected function sendPasswordsByMail() {
		$noMail = [];

		$this->output->writeln('');
		$progress = new ProgressBar($this->output, count($this->userPasswords));
		$progress->start();

		foreach ($this->userPasswords as $uid => $password) {
			$progress->advance();
			if (!empty($password)) {
				$recipient = $this->userManager->get($uid);
				$recipientDisplayName = $recipient->getDisplayName();
				$to = $recipient->getEMailAddress();

				if ($to === '') {
					$noMail[] = $uid;
					continue;
				}

				$subject = $this->l->t('one-time password for server-side-encryption');
				list($htmlBody, $textBody) = $this->createMailBody($password);

				// send it out now
				try {
					$message = $this->mailer->createMessage();
					$message->setSubject($subject);
					$message->setTo([$to => $recipientDisplayName]);
					$message->setHtmlBody($htmlBody);
					$message->setPlainBody($textBody);
					$message->setFrom([
						\OCP\Util::getDefaultEmailAddress('admin-noreply')
					]);

					$this->mailer->send($message);
				} catch (\Exception $e) {
					$noMail[] = $uid;
				}
			}
		}

		$progress->finish();

		if (empty($noMail)) {
			$this->output->writeln("\n\nPassword successfully send to all users");
		} else {
			$table = new Table($this->output);
			$table->setHeaders(['Username', 'Private key password']);
			$this->output->writeln("\n\nCould not send password to following users:\n");
			$rows = [];
			foreach ($noMail as $uid) {
				$rows[] = [$uid, $this->userPasswords[$uid]];
			}
			$table->setRows($rows);
			$table->render();
		}
	}

	/**
	 * create mail body for plain text and html mail
	 *
	 * @param string $password one-time encryption password
	 * @return array an array of the html mail body and the plain text mail body
	 */
	protected function createMailBody($password) {
		$html = new \OC_Template("encryption", "mail", "");
		$html->assign('password', $password);
		$htmlMail = $html->fetchPage();

		$plainText = new \OC_Template("encryption", "altmail", "");
		$plainText->assign('password', $password);
		$plainTextMail = $plainText->fetchPage();

		return [$htmlMail, $plainTextMail];
	}
}
