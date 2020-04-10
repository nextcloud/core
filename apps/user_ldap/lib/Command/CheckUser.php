<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP\Command;

use OCA\User_LDAP\Helper;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\DeletedUsersIndex;
use OCA\User_LDAP\User_Proxy;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckUser extends Command {
	/** @var User_Proxy */
	protected $backend;

	/** @var Helper */
	protected $helper;

	/** @var DeletedUsersIndex */
	protected $dui;

	/** @var UserMapping */
	protected $mapping;

	/**
	 * @param User_Proxy $uBackend
	 * @param Helper $helper
	 * @param DeletedUsersIndex $dui
	 * @param UserMapping $mapping
	 */
	public function __construct(User_Proxy $uBackend, Helper $helper, DeletedUsersIndex $dui, UserMapping $mapping) {
		$this->backend = $uBackend;
		$this->helper = $helper;
		$this->dui = $dui;
		$this->mapping = $mapping;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('ldap:check-user')
			->setDescription('checks whether a user exists on LDAP.')
			->addArgument(
					'ocName',
					InputArgument::REQUIRED,
					'the user name as used in Nextcloud'
					 )
			->addOption(
					'force',
					null,
					InputOption::VALUE_NONE,
					'ignores disabled LDAP configuration'
					 )
			->addOption(
				'update',
				null,
				InputOption::VALUE_NONE,
				'syncs values from LDAP'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		try {
			$uid = $input->getArgument('ocName');
			$this->isAllowed($input->getOption('force'));
			$this->confirmUserIsMapped($uid);
			$exists = $this->backend->userExistsOnLDAP($uid);
			if($exists === true) {
				$output->writeln('The user is still available on LDAP.');
				if($input->getOption('update')) {
					$this->updateUser($uid, $output);
				}
				return;
			}

			$this->dui->markUser($uid);
			$output->writeln('The user does not exists on LDAP anymore.');
			$output->writeln('Clean up the user\'s remnants by: ./occ user:delete "'
				. $uid . '"');
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage(). '</error>');
		}
	}

	/**
	 * checks whether a user is actually mapped
	 * @param string $ocName the username as used in Nextcloud
	 * @throws \Exception
	 * @return true
	 */
	protected function confirmUserIsMapped($ocName) {
		$dn = $this->mapping->getDNByName($ocName);
		if ($dn === false) {
			throw new \Exception('The given user is not a recognized LDAP user.');
		}

		return true;
	}

	/**
	 * checks whether the setup allows reliable checking of LDAP user existence
	 * @throws \Exception
	 * @return true
	 */
	protected function isAllowed($force) {
		if($this->helper->haveDisabledConfigurations() && !$force) {
			throw new \Exception('Cannot check user existence, because '
				. 'disabled LDAP configurations are present.');
		}

		// we don't check ldapUserCleanupInterval from config.php because this
		// action is triggered manually, while the setting only controls the
		// background job.

		return true;
	}

	private function updateUser(string $uid, OutputInterface $output): void {
		try {
			$access = $this->backend->getLDAPAccess($uid);
			$attrs = $access->userManager->getAttributes();
			$user = $access->userManager->get($uid);
			$avatarAttributes = $access->getConnection()->resolveRule('avatar');
			$result = $access->search('objectclass=*', [$user->getDN()], $attrs, 1, 0);
			foreach ($result[0] as $attribute => $valueSet) {
				$output->writeln('  ' . $attribute . ': ');
				foreach ($valueSet as $value) {
					if (in_array($attribute, $avatarAttributes)) {
						$value = '{ImageData}';
					}
					$output->writeln('    ' . $value);
				}
			}
			$access->batchApplyUserAttributes($result);
		} catch (\Exception $e) {
			$output->writeln('<error>Error while trying to lookup and update attributes from LDAP</error>');
		}
	}

}
