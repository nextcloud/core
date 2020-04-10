<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Schlichenmaier <johannes@schlichenmaier.info>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OC;

use OCP\IConfig;

/**
 * Class which provides access to the system config values stored in config.php
 * Internal class for bootstrap only.
 * fixes cyclic DI: AllConfig needs AppConfig needs Database needs AllConfig
 */
class SystemConfig {

	/** @var array */
	protected $sensitiveValues = [
		'instanceid' => true,
		'datadirectory' => true,
		'dbname' => true,
		'dbhost' => true,
		'dbpassword' => true,
		'dbuser' => true,
		'mail_from_address' => true,
		'mail_domain' => true,
		'mail_smtphost' => true,
		'mail_smtpname' => true,
		'mail_smtppassword' => true,
		'passwordsalt' => true,
		'secret' => true,
		'updater.secret' => true,
		'trusted_proxies' => true,
		'proxyuserpwd' => true,
		'log.condition' => [
			'shared_secret' => true,
		],
		'license-key' => true,
		'redis' => [
			'host' => true,
			'password' => true,
		],
		'objectstore' => [
			'arguments' => [
				// Legacy Swift (https://github.com/nextcloud/server/pull/17696#discussion_r341302207)
				'options' => [
					'credentials' => [
						'key' => true,
						'secret' => true,
					]
				],
				// S3
				'key' => true,
				'secret' => true,
				// Swift v2
				'username' => true,
				'password' => true,
				// Swift v3
				'user' => [
					'name' => true,
					'password' => true,
				],
			],
		],
	];

	/** @var Config */
	private $config;

	public function __construct(Config $config) {
		$this->config = $config;
	}

	/**
	 * Lists all available config keys
	 * @return array an array of key names
	 */
	public function getKeys() {
		return $this->config->getKeys();
	}

	/**
	 * Sets a new system wide value
	 *
	 * @param string $key the key of the value, under which will be saved
	 * @param mixed $value the value that should be stored
	 */
	public function setValue($key, $value) {
		$this->config->setValue($key, $value);
	}

	/**
	 * Sets and deletes values and writes the config.php
	 *
	 * @param array $configs Associative array with `key => value` pairs
	 *                       If value is null, the config key will be deleted
	 */
	public function setValues(array $configs) {
		$this->config->setValues($configs);
	}

	/**
	 * Looks up a system wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed the value or $default
	 */
	public function getValue($key, $default = '') {
		return $this->config->getValue($key, $default);
	}

	/**
	 * Looks up a system wide defined value and filters out sensitive data
	 *
	 * @param string $key the key of the value, under which it was saved
	 * @param mixed $default the default value to be returned if the value isn't set
	 * @return mixed the value or $default
	 */
	public function getFilteredValue($key, $default = '') {
		$value = $this->getValue($key, $default);

		if (isset($this->sensitiveValues[$key])) {
			$value = $this->removeSensitiveValue($this->sensitiveValues[$key], $value);
		}

		return $value;
	}

	/**
	 * Delete a system wide defined value
	 *
	 * @param string $key the key of the value, under which it was saved
	 */
	public function deleteValue($key) {
		$this->config->deleteKey($key);
	}

	/**
	 * @param bool|array $keysToRemove
	 * @param mixed $value
	 * @return mixed
	 */
	protected function removeSensitiveValue($keysToRemove, $value) {
		if ($keysToRemove === true) {
			return IConfig::SENSITIVE_VALUE;
		}

		if (is_array($value)) {
			foreach ($keysToRemove as $keyToRemove => $valueToRemove) {
				if (isset($value[$keyToRemove])) {
					$value[$keyToRemove] = $this->removeSensitiveValue($valueToRemove, $value[$keyToRemove]);
				}
			}
		}

		return $value;
	}
}
