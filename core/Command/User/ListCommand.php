<?php
/**
 * @copyright Copyright (c) 2016 Robin Appelman <robin@icewind.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Base {

	/** @var IUserManager */
	protected $userManager;

	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IUserManager $userManager,
								IGroupManager $groupManager) {
		$this->userManager = $userManager;
		$this->groupManager = $groupManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('user:list')
			->setDescription('list configured users')
			->addOption(
				'limit',
				'l',
				InputOption::VALUE_OPTIONAL,
				'Number of users to retrieve',
				500
			)->addOption(
				'offset',
				'o',
				InputOption::VALUE_OPTIONAL,
				'Offset for retrieving users',
				0
			)->addOption(
				'output',
				null,
				InputOption::VALUE_OPTIONAL,
				'Output format (plain, json or json_pretty, default is plain)',
				$this->defaultOutputFormat
			)->addOption(
				'info',
				'i',
				InputOption::VALUE_NONE,
				'Show detailed info'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$users = $this->userManager->search('', (int) $input->getOption('limit'), (int) $input->getOption('offset'));

		$this->writeArrayInOutputFormat($input, $output, $this->formatUsers($users, (bool)$input->getOption('info')));
		return 0;
	}

	/**
	 * @param IUser[] $users
	 * @param bool [$detailed=false]
	 * @return array
	 */
	private function formatUsers(array $users, bool $detailed = false) {
		$keys = array_map(function (IUser $user) {
			return $user->getUID();
		}, $users);

		$values = array_map(function (IUser $user) use ($detailed) {
			if ($detailed) {
				$groups = $this->groupManager->getUserGroupIds($user);
				return [
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
			}
			return $user->getDisplayName();
		}, $users);
		return array_combine($keys, $values);
	}
}
