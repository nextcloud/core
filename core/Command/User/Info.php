<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Core\Command\User;

use OC\Core\Command\Base;
use OCP\IGroupManager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Info extends Base {
	/** @var IUserManager */
	protected $userManager;
	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IUserManager $userManager, IGroupManager $groupManager) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('user:info')
			->setDescription('show user info')
			->addArgument(
				'user',
				InputArgument::REQUIRED,
				'user to show'
			)->addOption(
				'output',
				null,
				InputOption::VALUE_OPTIONAL,
				'Output format (plain, json or json_pretty, default is plain)',
				$this->defaultOutputFormat
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$user = $this->userManager->get($input->getArgument('user'));
		if (is_null($user)) {
			$output->writeln('<error>user not found</error>');
			return 1;
		}
		$groups = $this->groupManager->getUserGroupIds($user);
		$data = [
			'user_id' => $user->getUID(),
			'display_name' => $user->getDisplayName(),
			'email' => $user->getEMailAddress() ? $user->getEMailAddress() : '',
			'cloud_id' => $user->getCloudId(),
			'enabled' => $user->isEnabled(),
			'groups' => $groups,
			'quota' => $user->getQuota(),
			'last_seen' => date(\DateTime::ATOM, $user->getLastLogin()), // ISO-8601
			'user_directory' => $user->getHome(),
			'backend' => $user->getBackendClassName()
		];
		$this->writeArrayInOutputFormat($input, $output, $data);
	}
}
