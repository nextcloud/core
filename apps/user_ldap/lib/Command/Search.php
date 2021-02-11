<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
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

use OCA\User_LDAP\Group_Proxy;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\User_Proxy;
use OCP\IConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Search extends Command {
	/** @var \OCP\IConfig */
	protected $ocConfig;
	/** @var User_Proxy */
	private $userProxy;
	/** @var Group_Proxy */
	private $groupProxy;

	public function __construct(IConfig $ocConfig, User_Proxy $userProxy, Group_Proxy $groupProxy) {
		parent::__construct();
		$this->ocConfig = $ocConfig;
		$this->userProxy = $userProxy;
		$this->groupProxy = $groupProxy;
	}

	protected function configure() {
		$this
			->setName('ldap:search')
			->setDescription('executes a user or group search')
			->addArgument(
					'search',
					InputArgument::REQUIRED,
					'the search string (can be empty)'
					 )
			->addOption(
					'group',
					null,
					InputOption::VALUE_NONE,
					'searches groups instead of users'
					 )
			->addOption(
					'offset',
					null,
					InputOption::VALUE_REQUIRED,
					'The offset of the result set. Needs to be a multiple of limit. defaults to 0.',
					0
					 )
			->addOption(
					'limit',
					null,
					InputOption::VALUE_REQUIRED,
					'limit the results. 0 means no limit, defaults to 15',
					15
					 )
		;
	}

	/**
	 * Tests whether the offset and limit options are valid
	 * @param int $offset
	 * @param int $limit
	 * @throws \InvalidArgumentException
	 */
	protected function validateOffsetAndLimit($offset, $limit) {
		if ($limit < 0) {
			throw new \InvalidArgumentException('limit must be  0 or greater');
		}
		if ($offset < 0) {
			throw new \InvalidArgumentException('offset must be 0 or greater');
		}
		if ($limit === 0 && $offset !== 0) {
			throw new \InvalidArgumentException('offset must be 0 if limit is also set to 0');
		}
		if ($offset > 0 && ($offset % $limit !== 0)) {
			throw new \InvalidArgumentException('offset must be a multiple of limit');
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$helper = new Helper($this->ocConfig, \OC::$server->getDatabaseConnection());
		$configPrefixes = $helper->getServerConfigurationPrefixes(true);
		$ldapWrapper = new LDAP();

		$offset = (int)$input->getOption('offset');
		$limit = (int)$input->getOption('limit');
		$this->validateOffsetAndLimit($offset, $limit);

		if ($input->getOption('group')) {
			$proxy = $this->groupProxy;
			$getMethod = 'getGroups';
			$printID = false;
			// convert the limit of groups to null. This will show all the groups available instead of
			// nothing, and will match the same behaviour the search for users has.
			if ($limit === 0) {
				$limit = null;
			}
		} else {
			$proxy = $this->userProxy;
			$getMethod = 'getDisplayNames';
			$printID = true;
		}

		$result = $proxy->$getMethod($input->getArgument('search'), $limit, $offset);
		foreach ($result as $id => $name) {
			$line = $name . ($printID ? ' ('.$id.')' : '');
			$output->writeln($line);
		}
		return 0;
	}
}
