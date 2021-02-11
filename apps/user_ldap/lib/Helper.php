<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
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

use OC\Cache\CappedMemoryCache;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

class Helper {

	/** @var IConfig */
	private $config;

	/** @var IDBConnection */
	private $connection;

	/** @var CappedMemoryCache */
	protected $sanitizeDnCache;

	public function __construct(IConfig $config,
								IDBConnection $connection) {
		$this->config = $config;
		$this->connection = $connection;
		$this->sanitizeDnCache = new CappedMemoryCache(10000);
	}

	/**
	 * returns prefixes for each saved LDAP/AD server configuration.
	 *
	 * @param bool $activeConfigurations optional, whether only active configuration shall be
	 * retrieved, defaults to false
	 * @return array with a list of the available prefixes
	 *
	 * Configuration prefixes are used to set up configurations for n LDAP or
	 * AD servers. Since configuration is stored in the database, table
	 * appconfig under appid user_ldap, the common identifiers in column
	 * 'configkey' have a prefix. The prefix for the very first server
	 * configuration is empty.
	 * Configkey Examples:
	 * Server 1: ldap_login_filter
	 * Server 2: s1_ldap_login_filter
	 * Server 3: s2_ldap_login_filter
	 *
	 * The prefix needs to be passed to the constructor of Connection class,
	 * except the default (first) server shall be connected to.
	 *
	 */
	public function getServerConfigurationPrefixes($activeConfigurations = false): array {
		$referenceConfigkey = 'ldap_configuration_active';

		$keys = $this->getServersConfig($referenceConfigkey);

		$prefixes = [];
		foreach ($keys as $key) {
			if ($activeConfigurations && $this->config->getAppValue('user_ldap', $key, '0') !== '1') {
				continue;
			}

			$len = strlen($key) - strlen($referenceConfigkey);
			$prefixes[] = substr($key, 0, $len);
		}
		asort($prefixes);

		return $prefixes;
	}

	/**
	 *
	 * determines the host for every configured connection
	 *
	 * @return array an array with configprefix as keys
	 *
	 */
	public function getServerConfigurationHosts() {
		$referenceConfigkey = 'ldap_host';

		$keys = $this->getServersConfig($referenceConfigkey);

		$result = [];
		foreach ($keys as $key) {
			$len = strlen($key) - strlen($referenceConfigkey);
			$prefix = substr($key, 0, $len);
			$result[$prefix] = $this->config->getAppValue('user_ldap', $key);
		}

		return $result;
	}

	/**
	 * return the next available configuration prefix
	 *
	 * @return string
	 */
	public function getNextServerConfigurationPrefix() {
		$serverConnections = $this->getServerConfigurationPrefixes();

		if (count($serverConnections) === 0) {
			return 's01';
		}

		sort($serverConnections);
		$lastKey = array_pop($serverConnections);
		$lastNumber = (int)str_replace('s', '', $lastKey);
		return 's' . str_pad($lastNumber + 1, 2, '0', STR_PAD_LEFT);
	}

	private function getServersConfig($value) {
		$regex = '/' . $value . '$/S';

		$keys = $this->config->getAppKeys('user_ldap');
		$result = [];
		foreach ($keys as $key) {
			if (preg_match($regex, $key) === 1) {
				$result[] = $key;
			}
		}

		return $result;
	}

	/**
	 * deletes a given saved LDAP/AD server configuration.
	 *
	 * @param string $prefix the configuration prefix of the config to delete
	 * @return bool true on success, false otherwise
	 */
	public function deleteServerConfiguration($prefix) {
		if (!in_array($prefix, self::getServerConfigurationPrefixes())) {
			return false;
		}

		$query = $this->connection->getQueryBuilder();
		$query->delete('appconfig')
			->where($query->expr()->eq('appid', $query->createNamedParameter('user_ldap')))
			->andWhere($query->expr()->like('configkey', $query->createNamedParameter((string)$prefix . '%')))
			->andWhere($query->expr()->notIn('configkey', $query->createNamedParameter([
				'enabled',
				'installed_version',
				'types',
				'bgjUpdateGroupsLastRun',
			], IQueryBuilder::PARAM_STR_ARRAY)));

		if (empty($prefix)) {
			$query->andWhere($query->expr()->notLike('configkey', $query->createNamedParameter('s%')));
		}

		$deletedRows = $query->execute();
		return $deletedRows !== 0;
	}

	/**
	 * checks whether there is one or more disabled LDAP configurations
	 */
	public function haveDisabledConfigurations(): bool {
		$all = $this->getServerConfigurationPrefixes(false);
		$active = $this->getServerConfigurationPrefixes(true);

		return count($all) !== count($active) || count($all) === 0;
	}

	/**
	 * extracts the domain from a given URL
	 *
	 * @param string $url the URL
	 * @return string|false domain as string on success, false otherwise
	 */
	public function getDomainFromURL($url) {
		$uinfo = parse_url($url);
		if (!is_array($uinfo)) {
			return false;
		}

		$domain = false;
		if (isset($uinfo['host'])) {
			$domain = $uinfo['host'];
		} elseif (isset($uinfo['path'])) {
			$domain = $uinfo['path'];
		}

		return $domain;
	}

	/**
	 * sanitizes a DN received from the LDAP server
	 *
	 * @param array $dn the DN in question
	 * @return array|string the sanitized DN
	 */
	public function sanitizeDN($dn) {
		//treating multiple base DNs
		if (is_array($dn)) {
			$result = [];
			foreach ($dn as $singleDN) {
				$result[] = $this->sanitizeDN($singleDN);
			}
			return $result;
		}

		if (!is_string($dn)) {
			throw new \LogicException('String expected ' . \gettype($dn) . ' given');
		}

		if (($sanitizedDn = $this->sanitizeDnCache->get($dn)) !== null) {
			return $sanitizedDn;
		}

		//OID sometimes gives back DNs with whitespace after the comma
		// a la "uid=foo, cn=bar, dn=..." We need to tackle this!
		$sanitizedDn = preg_replace('/([^\\\]),(\s+)/u', '\1,', $dn);

		//make comparisons and everything work
		$sanitizedDn = mb_strtolower($sanitizedDn, 'UTF-8');

		//escape DN values according to RFC 2253 – this is already done by ldap_explode_dn
		//to use the DN in search filters, \ needs to be escaped to \5c additionally
		//to use them in bases, we convert them back to simple backslashes in readAttribute()
		$replacements = [
			'\,' => '\5c2C',
			'\=' => '\5c3D',
			'\+' => '\5c2B',
			'\<' => '\5c3C',
			'\>' => '\5c3E',
			'\;' => '\5c3B',
			'\"' => '\5c22',
			'\#' => '\5c23',
			'(' => '\28',
			')' => '\29',
			'*' => '\2A',
		];
		$sanitizedDn = str_replace(array_keys($replacements), array_values($replacements), $sanitizedDn);
		$this->sanitizeDnCache->set($dn, $sanitizedDn);

		return $sanitizedDn;
	}

	/**
	 * converts a stored DN so it can be used as base parameter for LDAP queries, internally we store them for usage in LDAP filters
	 *
	 * @param string $dn the DN
	 * @return string
	 */
	public function DNasBaseParameter($dn) {
		return str_ireplace('\\5c', '\\', $dn);
	}

	/**
	 * listens to a hook thrown by server2server sharing and replaces the given
	 * login name by a username, if it matches an LDAP user.
	 *
	 * @param array $param
	 * @throws \Exception
	 */
	public static function loginName2UserName($param) {
		if (!isset($param['uid'])) {
			throw new \Exception('key uid is expected to be set in $param');
		}

		$userBackend = \OC::$server->get(User_Proxy::class);
		$uid = $userBackend->loginName2UserName($param['uid']);
		if ($uid !== false) {
			$param['uid'] = $uid;
		}
	}
}
