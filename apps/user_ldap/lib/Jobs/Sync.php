<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\User_LDAP\Jobs;

use OC\BackgroundJob\TimedJob;
use OC\ServerNotAvailableException;
use OCA\User_LDAP\AccessFactory;
use OCA\User_LDAP\Configuration;
use OCA\User_LDAP\ConnectionFactory;
use OCA\User_LDAP\Helper;
use OCA\User_LDAP\LDAP;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\Manager;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Notification\IManager;

class Sync extends TimedJob {
	public const MAX_INTERVAL = 12 * 60 * 60; // 12h
	public const MIN_INTERVAL = 30 * 60; // 30min
	/** @var  Helper */
	protected $ldapHelper;
	/** @var  LDAP */
	protected $ldap;
	/** @var  Manager */
	protected $userManager;
	/** @var UserMapping */
	protected $mapper;
	/** @var  IConfig */
	protected $config;
	/** @var  IAvatarManager */
	protected $avatarManager;
	/** @var  IDBConnection */
	protected $dbc;
	/** @var  IUserManager */
	protected $ncUserManager;
	/** @var  IManager */
	protected $notificationManager;
	/** @var ConnectionFactory */
	protected $connectionFactory;
	/** @var AccessFactory */
	protected $accessFactory;

	public function __construct(Manager  $userManager) {
		$this->userManager = $userManager;
		$this->setInterval(
			\OC::$server->getConfig()->getAppValue(
				'user_ldap',
				'background_sync_interval',
				self::MIN_INTERVAL
			)
		);
	}

	/**
	 * updates the interval
	 *
	 * the idea is to adjust the interval depending on the amount of known users
	 * and the attempt to update each user one day. At most it would run every
	 * 30 minutes, and at least every 12 hours.
	 */
	public function updateInterval() {
		$minPagingSize = $this->getMinPagingSize();
		$mappedUsers = $this->mapper->count();

		$runsPerDay = ($minPagingSize === 0 || $mappedUsers === 0) ? self::MAX_INTERVAL
			: $mappedUsers / $minPagingSize;
		$interval = floor(24 * 60 * 60 / $runsPerDay);
		$interval = min(max($interval, self::MIN_INTERVAL), self::MAX_INTERVAL);

		$this->config->setAppValue('user_ldap', 'background_sync_interval', $interval);
	}

	/**
	 * returns the smallest configured paging size
	 * @return int
	 */
	protected function getMinPagingSize() {
		$configKeys = $this->config->getAppKeys('user_ldap');
		$configKeys = array_filter($configKeys, function ($key) {
			return strpos($key, 'ldap_paging_size') !== false;
		});
		$minPagingSize = null;
		foreach ($configKeys as $configKey) {
			$pagingSize = $this->config->getAppValue('user_ldap', $configKey, $minPagingSize);
			$minPagingSize = $minPagingSize === null ? $pagingSize : min($minPagingSize, $pagingSize);
		}
		return (int)$minPagingSize;
	}

	/**
	 * @param array $argument
	 */
	public function run($argument) {
		$this->setArgument($argument);

		$isBackgroundJobModeAjax = $this->config
				->getAppValue('core', 'backgroundjobs_mode', 'ajax') === 'ajax';
		if ($isBackgroundJobModeAjax) {
			return;
		}

		$cycleData = $this->getCycle();
		if ($cycleData === null) {
			$cycleData = $this->determineNextCycle();
			if ($cycleData === null) {
				$this->updateInterval();
				return;
			}
		}

		if (!$this->qualifiesToRun($cycleData)) {
			$this->updateInterval();
			return;
		}

		try {
			$expectMoreResults = $this->runCycle($cycleData);
			if ($expectMoreResults) {
				$this->increaseOffset($cycleData);
			} else {
				$this->determineNextCycle($cycleData);
			}
			$this->updateInterval();
		} catch (ServerNotAvailableException $e) {
			$this->determineNextCycle($cycleData);
		}
	}

	/**
	 * @param array $cycleData
	 * @return bool whether more results are expected from the same configuration
	 */
	public function runCycle($cycleData) {
		$connection = $this->connectionFactory->get($cycleData['prefix']);
		$access = $this->accessFactory->get($connection);
		$access->setUserMapper($this->mapper);

		$filter = $access->combineFilterWithAnd([
			$access->connection->ldapUserFilter,
			$access->connection->ldapUserDisplayName . '=*',
			$access->getFilterPartForUserSearch('')
		]);
		$results = $access->fetchListOfUsers(
			$filter,
			$access->userManager->getAttributes(),
			$connection->ldapPagingSize,
			$cycleData['offset'],
			true
		);

		if ((int)$connection->ldapPagingSize === 0) {
			return false;
		}
		return count($results) >= (int)$connection->ldapPagingSize;
	}

	/**
	 * returns the info about the current cycle that should be run, if any,
	 * otherwise null
	 *
	 * @return array|null
	 */
	public function getCycle() {
		$prefixes = $this->ldapHelper->getServerConfigurationPrefixes(true);
		if (count($prefixes) === 0) {
			return null;
		}

		$cycleData = [
			'prefix' => $this->config->getAppValue('user_ldap', 'background_sync_prefix', null),
			'offset' => (int)$this->config->getAppValue('user_ldap', 'background_sync_offset', 0),
		];

		if (
			$cycleData['prefix'] !== null
			&& in_array($cycleData['prefix'], $prefixes)
		) {
			return $cycleData;
		}

		return null;
	}

	/**
	 * Save the provided cycle information in the DB
	 *
	 * @param array $cycleData
	 */
	public function setCycle(array $cycleData) {
		$this->config->setAppValue('user_ldap', 'background_sync_prefix', $cycleData['prefix']);
		$this->config->setAppValue('user_ldap', 'background_sync_offset', $cycleData['offset']);
	}

	/**
	 * returns data about the next cycle that should run, if any, otherwise
	 * null. It also always goes for the next LDAP configuration!
	 *
	 * @param array|null $cycleData the old cycle
	 * @return array|null
	 */
	public function determineNextCycle(array $cycleData = null) {
		$prefixes = $this->ldapHelper->getServerConfigurationPrefixes(true);
		if (count($prefixes) === 0) {
			return null;
		}

		// get the next prefix in line and remember it
		$oldPrefix = $cycleData === null ? null : $cycleData['prefix'];
		$prefix = $this->getNextPrefix($oldPrefix);
		if ($prefix === null) {
			return null;
		}
		$cycleData['prefix'] = $prefix;
		$cycleData['offset'] = 0;
		$this->setCycle(['prefix' => $prefix, 'offset' => 0]);

		return $cycleData;
	}

	/**
	 * Checks whether the provided cycle should be run. Currently only the
	 * last configuration change goes into account (at least one hour).
	 *
	 * @param $cycleData
	 * @return bool
	 */
	public function qualifiesToRun($cycleData) {
		$lastChange = $this->config->getAppValue('user_ldap', $cycleData['prefix'] . '_lastChange', 0);
		if ((time() - $lastChange) > 60 * 30) {
			return true;
		}
		return false;
	}

	/**
	 * increases the offset of the current cycle for the next run
	 *
	 * @param $cycleData
	 */
	protected function increaseOffset($cycleData) {
		$ldapConfig = new Configuration($cycleData['prefix']);
		$cycleData['offset'] += (int)$ldapConfig->ldapPagingSize;
		$this->setCycle($cycleData);
	}

	/**
	 * determines the next configuration prefix based on the last one (if any)
	 *
	 * @param string|null $lastPrefix
	 * @return string|null
	 */
	protected function getNextPrefix($lastPrefix) {
		$prefixes = $this->ldapHelper->getServerConfigurationPrefixes(true);
		$noOfPrefixes = count($prefixes);
		if ($noOfPrefixes === 0) {
			return null;
		}
		$i = $lastPrefix === null ? false : array_search($lastPrefix, $prefixes, true);
		if ($i === false) {
			$i = -1;
		} else {
			$i++;
		}

		if (!isset($prefixes[$i])) {
			$i = 0;
		}
		return $prefixes[$i];
	}

	/**
	 * "fixes" DI
	 *
	 * @param array $argument
	 */
	public function setArgument($argument) {
		if (isset($argument['config'])) {
			$this->config = $argument['config'];
		} else {
			$this->config = \OC::$server->getConfig();
		}

		if (isset($argument['helper'])) {
			$this->ldapHelper = $argument['helper'];
		} else {
			$this->ldapHelper = new Helper($this->config, \OC::$server->getDatabaseConnection());
		}

		if (isset($argument['ldapWrapper'])) {
			$this->ldap = $argument['ldapWrapper'];
		} else {
			$this->ldap = new LDAP();
		}

		if (isset($argument['avatarManager'])) {
			$this->avatarManager = $argument['avatarManager'];
		} else {
			$this->avatarManager = \OC::$server->getAvatarManager();
		}

		if (isset($argument['dbc'])) {
			$this->dbc = $argument['dbc'];
		} else {
			$this->dbc = \OC::$server->getDatabaseConnection();
		}

		if (isset($argument['ncUserManager'])) {
			$this->ncUserManager = $argument['ncUserManager'];
		} else {
			$this->ncUserManager = \OC::$server->getUserManager();
		}

		if (isset($argument['notificationManager'])) {
			$this->notificationManager = $argument['notificationManager'];
		} else {
			$this->notificationManager = \OC::$server->getNotificationManager();
		}

		if (isset($argument['userManager'])) {
			$this->userManager = $argument['userManager'];
		}

		if (isset($argument['mapper'])) {
			$this->mapper = $argument['mapper'];
		} else {
			$this->mapper = new UserMapping($this->dbc);
		}

		if (isset($argument['connectionFactory'])) {
			$this->connectionFactory = $argument['connectionFactory'];
		} else {
			$this->connectionFactory = new ConnectionFactory($this->ldap);
		}

		if (isset($argument['accessFactory'])) {
			$this->accessFactory = $argument['accessFactory'];
		} else {
			$this->accessFactory = new AccessFactory(
				$this->ldap,
				$this->userManager,
				$this->ldapHelper,
				$this->config,
				$this->ncUserManager
			);
		}
	}
}
