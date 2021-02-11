<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\User;

use OCA\User_LDAP\Mapping\UserMapping;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Share\IManager;
use OCP\Share\IShare;

class OfflineUser {
	/**
	 * @var string $ocName
	 */
	protected $ocName;
	/**
	 * @var string $dn
	 */
	protected $dn;
	/**
	 * @var string $uid the UID as provided by LDAP
	 */
	protected $uid;
	/**
	 * @var string $displayName
	 */
	protected $displayName;
	/**
	 * @var string $homePath
	 */
	protected $homePath;
	/**
	 * @var string $lastLogin the timestamp of the last login
	 */
	protected $lastLogin;
	/**
	 * @var string $foundDeleted the timestamp when the user was detected as unavailable
	 */
	protected $foundDeleted;
	/**
	 * @var string $email
	 */
	protected $email;
	/**
	 * @var bool $hasActiveShares
	 */
	protected $hasActiveShares;
	/**
	 * @var IConfig $config
	 */
	protected $config;
	/**
	 * @var IDBConnection $db
	 */
	protected $db;
	/**
	 * @var \OCA\User_LDAP\Mapping\UserMapping
	 */
	protected $mapping;
	/** @var IManager */
	private $shareManager;

	public function __construct(
		$ocName,
		IConfig $config,
		UserMapping $mapping,
		IManager $shareManager
	) {
		$this->ocName = $ocName;
		$this->config = $config;
		$this->mapping = $mapping;
		$this->shareManager = $shareManager;
	}

	/**
	 * remove the Delete-flag from the user.
	 */
	public function unmark() {
		$this->config->deleteUserValue($this->ocName, 'user_ldap', 'isDeleted');
		$this->config->deleteUserValue($this->ocName, 'user_ldap', 'foundDeleted');
	}

	/**
	 * exports the user details in an assoc array
	 * @return array
	 */
	public function export() {
		$data = [];
		$data['ocName'] = $this->getOCName();
		$data['dn'] = $this->getDN();
		$data['uid'] = $this->getUID();
		$data['displayName'] = $this->getDisplayName();
		$data['homePath'] = $this->getHomePath();
		$data['lastLogin'] = $this->getLastLogin();
		$data['email'] = $this->getEmail();
		$data['hasActiveShares'] = $this->getHasActiveShares();

		return $data;
	}

	/**
	 * getter for Nextcloud internal name
	 * @return string
	 */
	public function getOCName() {
		return $this->ocName;
	}

	/**
	 * getter for LDAP uid
	 * @return string
	 */
	public function getUID() {
		if (!isset($this->uid)) {
			$this->fetchDetails();
		}
		return $this->uid;
	}

	/**
	 * getter for LDAP DN
	 * @return string
	 */
	public function getDN() {
		if (!isset($this->dn)) {
			$this->fetchDetails();
		}
		return $this->dn;
	}

	/**
	 * getter for display name
	 * @return string
	 */
	public function getDisplayName() {
		if (!isset($this->displayName)) {
			$this->fetchDetails();
		}
		return $this->displayName;
	}

	/**
	 * getter for email
	 * @return string
	 */
	public function getEmail() {
		if (!isset($this->email)) {
			$this->fetchDetails();
		}
		return $this->email;
	}

	/**
	 * getter for home directory path
	 * @return string
	 */
	public function getHomePath() {
		if (!isset($this->homePath)) {
			$this->fetchDetails();
		}
		return $this->homePath;
	}

	/**
	 * getter for the last login timestamp
	 * @return int
	 */
	public function getLastLogin() {
		if (!isset($this->lastLogin)) {
			$this->fetchDetails();
		}
		return (int)$this->lastLogin;
	}

	/**
	 * getter for the detection timestamp
	 * @return int
	 */
	public function getDetectedOn() {
		if (!isset($this->foundDeleted)) {
			$this->fetchDetails();
		}
		return (int)$this->foundDeleted;
	}

	/**
	 * getter for having active shares
	 * @return bool
	 */
	public function getHasActiveShares() {
		if (!isset($this->hasActiveShares)) {
			$this->fetchDetails();
		}
		return $this->hasActiveShares;
	}

	/**
	 * reads the user details
	 */
	protected function fetchDetails() {
		$properties = [
			'displayName' => 'user_ldap',
			'uid' => 'user_ldap',
			'homePath' => 'user_ldap',
			'foundDeleted' => 'user_ldap',
			'email' => 'settings',
			'lastLogin' => 'login',
		];
		foreach ($properties as $property => $app) {
			$this->$property = $this->config->getUserValue($this->ocName, $app, $property, '');
		}

		$dn = $this->mapping->getDNByName($this->ocName);
		$this->dn = ($dn !== false) ? $dn : '';

		$this->determineShares();
	}

	/**
	 * finds out whether the user has active shares. The result is stored in
	 * $this->hasActiveShares
	 */
	protected function determineShares() {
		$shareInterface = new \ReflectionClass(IShare::class);
		$shareConstants = $shareInterface->getConstants();

		foreach ($shareConstants as $constantName => $constantValue) {
			if (strpos($constantName, 'TYPE_') !== 0
				|| $constantValue === IShare::TYPE_USERGROUP
			) {
				continue;
			}
			$shares = $this->shareManager->getSharesBy(
				$this->ocName,
				$constantValue,
				null,
				false,
				1
			);
			if (!empty($shares)) {
				$this->hasActiveShares = true;
				return;
			}
		}

		$this->hasActiveShares = false;
	}
}
