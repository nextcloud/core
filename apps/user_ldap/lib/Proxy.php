<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
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

namespace OCA\User_LDAP;

use OCA\User_LDAP\Mapping\GroupMapping;
use OCA\User_LDAP\Mapping\UserMapping;
use OCA\User_LDAP\User\Manager;
use OCP\Share\IManager;

abstract class Proxy {
	private static $accesses = [];
	private $ldap = null;
	/** @var bool */
	private $isSingleBackend;

	/** @var \OCP\ICache|null */
	private $cache;

	/**
	 * @param ILDAPWrapper $ldap
	 */
	public function __construct(ILDAPWrapper $ldap) {
		$this->ldap = $ldap;
		$memcache = \OC::$server->getMemCacheFactory();
		if ($memcache->isAvailable()) {
			$this->cache = $memcache->createDistributed();
		}
	}

	/**
	 * @param string $configPrefix
	 */
	private function addAccess($configPrefix) {
		static $ocConfig;
		static $fs;
		static $log;
		static $avatarM;
		static $userMap;
		static $groupMap;
		static $shareManager;
		static $coreUserManager;
		static $coreNotificationManager;
		if ($fs === null) {
			$ocConfig = \OC::$server->getConfig();
			$fs = new FilesystemHelper();
			$log = new LogWrapper();
			$avatarM = \OC::$server->getAvatarManager();
			$db = \OC::$server->getDatabaseConnection();
			$userMap = new UserMapping($db);
			$groupMap = new GroupMapping($db);
			$coreUserManager = \OC::$server->getUserManager();
			$coreNotificationManager = \OC::$server->getNotificationManager();
			$shareManager = \OC::$server->get(IManager::class);
		}
		$userManager =
			new Manager($ocConfig, $fs, $log, $avatarM, new \OCP\Image(),
				$coreUserManager, $coreNotificationManager, $shareManager);
		$connector = new Connection($this->ldap, $configPrefix);
		$access = new Access($connector, $this->ldap, $userManager, new Helper($ocConfig, \OC::$server->getDatabaseConnection()), $ocConfig, $coreUserManager);
		$access->setUserMapper($userMap);
		$access->setGroupMapper($groupMap);
		self::$accesses[$configPrefix] = $access;
	}

	/**
	 * @param string $configPrefix
	 * @return mixed
	 */
	protected function getAccess($configPrefix) {
		if (!isset(self::$accesses[$configPrefix])) {
			$this->addAccess($configPrefix);
		}
		return self::$accesses[$configPrefix];
	}

	/**
	 * @param string $uid
	 * @return string
	 */
	protected function getUserCacheKey($uid) {
		return 'user-' . $uid . '-lastSeenOn';
	}

	/**
	 * @param string $gid
	 * @return string
	 */
	protected function getGroupCacheKey($gid) {
		return 'group-' . $gid . '-lastSeenOn';
	}

	/**
	 * @param string $id
	 * @param string $method
	 * @param array $parameters
	 * @param bool $passOnWhen
	 * @return mixed
	 */
	abstract protected function callOnLastSeenOn($id, $method, $parameters, $passOnWhen);

	/**
	 * @param string $id
	 * @param string $method
	 * @param array $parameters
	 * @return mixed
	 */
	abstract protected function walkBackends($id, $method, $parameters);

	/**
	 * @param string $id
	 * @return Access
	 */
	abstract public function getLDAPAccess($id);

	abstract protected function activeBackends(): int;

	protected function isSingleBackend(): bool {
		if ($this->isSingleBackend === null) {
			$this->isSingleBackend = $this->activeBackends() === 1;
		}
		return $this->isSingleBackend;
	}

	/**
	 * Takes care of the request to the User backend
	 *
	 * @param string $id
	 * @param string $method string, the method of the user backend that shall be called
	 * @param array $parameters an array of parameters to be passed
	 * @param bool $passOnWhen
	 * @return mixed, the result of the specified method
	 */
	protected function handleRequest($id, $method, $parameters, $passOnWhen = false) {
		if (!$this->isSingleBackend()) {
			$result = $this->callOnLastSeenOn($id, $method, $parameters, $passOnWhen);
		}
		if (!isset($result) || $result === $passOnWhen) {
			$result = $this->walkBackends($id, $method, $parameters);
		}
		return $result;
	}

	/**
	 * @param string|null $key
	 * @return string
	 */
	private function getCacheKey($key) {
		$prefix = 'LDAP-Proxy-';
		if ($key === null) {
			return $prefix;
		}
		return $prefix . hash('sha256', $key);
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function getFromCache($key) {
		if ($this->cache === null) {
			return null;
		}

		$key = $this->getCacheKey($key);
		$value = $this->cache->get($key);
		if ($value === null) {
			return null;
		}

		return json_decode(base64_decode($value));
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function writeToCache($key, $value) {
		if ($this->cache === null) {
			return;
		}
		$key = $this->getCacheKey($key);
		$value = base64_encode(json_encode($value));
		$this->cache->set($key, $value, 2592000);
	}

	public function clearCache() {
		if ($this->cache === null) {
			return;
		}
		$this->cache->clear($this->getCacheKey(null));
	}
}
