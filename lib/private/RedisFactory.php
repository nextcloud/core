<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Alejandro Varela <epma01@gmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

class RedisFactory {
	/** @var  \Redis */
	private $instance;

	/** @var  SystemConfig */
	private $config;

	/**
	 * RedisFactory constructor.
	 *
	 * @param SystemConfig $config
	 */
	public function __construct(SystemConfig $config) {
		$this->config = $config;
	}

	private function create() {
		if ($config = $this->config->getValue('redis.cluster', [])) {
			if (!class_exists('RedisCluster')) {
				throw new \Exception('Redis Cluster support is not available');
			}
			// cluster config
			if (isset($config['timeout'])) {
				$timeout = $config['timeout'];
			} else {
				$timeout = null;
			}
			if (isset($config['read_timeout'])) {
				$readTimeout = $config['read_timeout'];
			} else {
				$readTimeout = null;
			}
			if (isset($config['password']) && $config['password'] !== '') {
				$this->instance = new \RedisCluster(null, $config['seeds'], $timeout, $readTimeout, false, $config['password']);
			} else {
				$this->instance = new \RedisCluster(null, $config['seeds'], $timeout, $readTimeout);
			}

			if (isset($config['failover_mode'])) {
				$this->instance->setOption(\RedisCluster::OPT_SLAVE_FAILOVER, $config['failover_mode']);
			}
		} else {

			$this->instance = new \Redis();
			$config = $this->config->getValue('redis', []);
			if (isset($config['host'])) {
				$host = $config['host'];
			} else {
				$host = '127.0.0.1';
			}
			if (isset($config['port'])) {
				$port = $config['port'];
			} else if ($host[0] !== '/') {
				$port = 6379;
			} else {
				$port = null;
			}
			if (isset($config['timeout'])) {
				$timeout = $config['timeout'];
			} else {
				$timeout = 0.0; // unlimited
			}

			$this->instance->connect($host, $port, $timeout);
			if (isset($config['password']) && $config['password'] !== '') {
				$this->instance->auth($config['password']);
			}

			if (isset($config['dbindex'])) {
				$this->instance->select($config['dbindex']);
			}
		}
	}

	public function getInstance() {
		if (!$this->isAvailable()) {
			throw new \Exception('Redis support is not available');
		}
		if (!$this->instance instanceof \Redis) {
			$this->create();
		}

		return $this->instance;
	}

	public function isAvailable() {
		return extension_loaded('redis')
		&& version_compare(phpversion('redis'), '2.2.5', '>=');
	}
}
