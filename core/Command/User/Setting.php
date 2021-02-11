<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Leuker <j.leuker@hosting.de>
 * @author Kim Brose <kim.brose@rwth-aachen.de>
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

namespace OC\Core\Command\User;

use OC\Core\Command\Base;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Setting extends Base {
	/** @var IUserManager */
	protected $userManager;

	/** @var IConfig */
	protected $config;

	/** @var IDBConnection */
	protected $connection;

	/**
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param IDBConnection $connection
	 */
	public function __construct(IUserManager $userManager, IConfig $config, IDBConnection $connection) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->config = $config;
		$this->connection = $connection;
	}

	protected function configure() {
		parent::configure();
		$this
			->setName('user:setting')
			->setDescription('Read and modify user settings')
			->addArgument(
				'uid',
				InputArgument::REQUIRED,
				'User ID used to login'
			)
			->addArgument(
				'app',
				InputArgument::OPTIONAL,
				'Restrict the settings to a given app',
				''
			)
			->addArgument(
				'key',
				InputArgument::OPTIONAL,
				'Setting key to set, get or delete',
				''
			)
			->addOption(
				'ignore-missing-user',
				null,
				InputOption::VALUE_NONE,
				'Use this option to ignore errors when the user does not exist'
			)

			// Get
			->addOption(
				'default-value',
				null,
				InputOption::VALUE_REQUIRED,
				'(Only applicable on get) If no default value is set and the config does not exist, the command will exit with 1'
			)

			// Set
			->addArgument(
				'value',
				InputArgument::OPTIONAL,
				'The new value of the setting',
				null
			)
			->addOption(
				'update-only',
				null,
				InputOption::VALUE_NONE,
				'Only updates the value, if it is not set before, it is not being added'
			)

			// Delete
			->addOption(
				'delete',
				null,
				InputOption::VALUE_NONE,
				'Specify this option to delete the config'
			)
			->addOption(
				'error-if-not-exists',
				null,
				InputOption::VALUE_NONE,
				'Checks whether the setting exists before deleting it'
			)
		;
	}

	protected function checkInput(InputInterface $input) {
		$uid = $input->getArgument('uid');
		if (!$input->getOption('ignore-missing-user') && !$this->userManager->userExists($uid)) {
			throw new \InvalidArgumentException('The user "' . $uid . '" does not exist.');
		}

		if ($input->getArgument('key') === '' && $input->hasParameterOption('--default-value')) {
			throw new \InvalidArgumentException('The "default-value" option can only be used when specifying a key.');
		}

		if ($input->getArgument('key') === '' && $input->getArgument('value') !== null) {
			throw new \InvalidArgumentException('The value argument can only be used when specifying a key.');
		}
		if ($input->getArgument('value') !== null && $input->hasParameterOption('--default-value')) {
			throw new \InvalidArgumentException('The value argument can not be used together with "default-value".');
		}
		if ($input->getOption('update-only') && $input->getArgument('value') === null) {
			throw new \InvalidArgumentException('The "update-only" option can only be used together with "value".');
		}

		if ($input->getArgument('key') === '' && $input->getOption('delete')) {
			throw new \InvalidArgumentException('The "delete" option can only be used when specifying a key.');
		}
		if ($input->getOption('delete') && $input->hasParameterOption('--default-value')) {
			throw new \InvalidArgumentException('The "delete" option can not be used together with "default-value".');
		}
		if ($input->getOption('delete') && $input->getArgument('value') !== null) {
			throw new \InvalidArgumentException('The "delete" option can not be used together with "value".');
		}
		if ($input->getOption('error-if-not-exists') && !$input->getOption('delete')) {
			throw new \InvalidArgumentException('The "error-if-not-exists" option can only be used together with "delete".');
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		try {
			$this->checkInput($input);
		} catch (\InvalidArgumentException $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return 1;
		}

		$uid = $input->getArgument('uid');
		$app = $input->getArgument('app');
		$key = $input->getArgument('key');

		if ($key !== '') {
			$value = $this->config->getUserValue($uid, $app, $key, null);
			if ($input->getArgument('value') !== null) {
				if ($input->hasParameterOption('--update-only') && $value === null) {
					$output->writeln('<error>The setting does not exist for user "' . $uid . '".</error>');
					return 1;
				}

				if ($app === 'settings' && in_array($key , ['email', 'display_name'])) {
					$user = $this->userManager->get($uid);
					if ($user instanceof IUser) {
						if ($key === 'email') {
							$user->setEMailAddress($input->getArgument('value'));
						} elseif ($key === 'display_name') {
							if (!$user->setDisplayName($input->getArgument('value'))) {
								if ($user->getDisplayName() === $input->getArgument('value')) {
									$output->writeln('<error>New and old display name are the same</error>');
								} elseif ($input->getArgument('value') === '') {
									$output->writeln('<error>New display name can\'t be empty</error>');
								} else {
									$output->writeln('<error>Could not set display name</error>');
								}
								return 1;
							}
						}
						// setEmailAddress and setDisplayName both internally set the value
						return 0;
					}
				}

				$this->config->setUserValue($uid, $app, $key, $input->getArgument('value'));
				return 0;
			} elseif ($input->hasParameterOption('--delete')) {
				if ($input->hasParameterOption('--error-if-not-exists') && $value === null) {
					$output->writeln('<error>The setting does not exist for user "' . $uid . '".</error>');
					return 1;
				}

				if ($app === 'settings' && in_array($key , ['email', 'display_name'])) {
					$user = $this->userManager->get($uid);
					if ($user instanceof IUser) {
						if ($key === 'email') {
							$user->setEMailAddress('');
							// setEmailAddress already deletes the value
							return 0;
						} elseif ($key === 'display_name') {
							$output->writeln('<error>Display name can\'t be deleted.</error>');
							return 1;
						}
					}
				}

				$this->config->deleteUserValue($uid, $app, $key);
				return 0;
			} elseif ($value !== null) {
				$output->writeln($value);
				return 0;
			} elseif ($input->hasParameterOption('--default-value')) {
				$output->writeln($input->getOption('default-value'));
				return 0;
			} else {
				if ($app === 'settings' && $key === 'display_name') {
					$user = $this->userManager->get($uid);
					$output->writeln($user->getDisplayName());
					return 0;
				}
				$output->writeln('<error>The setting does not exist for user "' . $uid . '".</error>');
				return 1;
			}
		} else {
			$settings = $this->getUserSettings($uid, $app);
			$this->writeArrayInOutputFormat($input, $output, $settings);
			return 0;
		}
	}

	protected function getUserSettings($uid, $app) {
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('preferences')
			->where($query->expr()->eq('userid', $query->createNamedParameter($uid)));

		if ($app !== '') {
			$query->andWhere($query->expr()->eq('appid', $query->createNamedParameter($app)));
		}

		$result = $query->execute();
		$settings = [];
		while ($row = $result->fetch()) {
			$settings[$row['appid']][$row['configkey']] = $row['configvalue'];
		}

		$user = $this->userManager->get($uid);
		$settings['settings']['display_name'] = $user->getDisplayName();

		$result->closeCursor();

		return $settings;
	}
}
