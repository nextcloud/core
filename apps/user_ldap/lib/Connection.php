<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jarkko Lehtoranta <devel@jlranta.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
 * @author root <root@localhost.localdomain>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Xuanwo <xuanwo@yunify.com>
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

use OC\ServerNotAvailableException;
use OCP\ILogger;

/**
 * magic properties (incomplete)
 * responsible for LDAP connections in context with the provided configuration
 *
 * @property string ldapHost
 * @property string ldapPort holds the port number
 * @property string ldapUserFilter
 * @property string ldapUserDisplayName
 * @property string ldapUserDisplayName2
 * @property string ldapUserAvatarRule
 * @property boolean turnOnPasswordChange
 * @property string[] ldapBaseUsers
 * @property int|null ldapPagingSize holds an integer
 * @property bool|mixed|void ldapGroupMemberAssocAttr
 * @property string ldapUuidUserAttribute
 * @property string ldapUuidGroupAttribute
 * @property string ldapExpertUUIDUserAttr
 * @property string ldapExpertUUIDGroupAttr
 * @property string ldapQuotaAttribute
 * @property string ldapQuotaDefault
 * @property string ldapEmailAttribute
 * @property string ldapExtStorageHomeAttribute
 * @property string homeFolderNamingRule
 * @property bool|string ldapNestedGroups
 * @property string[] ldapBaseGroups
 * @property string ldapGroupFilter
 * @property string ldapGroupDisplayName
 * @property string ldapLoginFilter
 * @property string ldapDynamicGroupMemberURL
 * @property string ldapGidNumber
 * @property int hasMemberOfFilterSupport
 * @property int useMemberOfToDetectMembership
 * @property string ldapMatchingRuleInChainState
 */
class Connection extends LDAPUtility {
	private $ldapConnectionRes = null;
	private $configPrefix;
	private $configID;
	private $configured = false;
	//whether connection should be kept on __destruct
	private $dontDestruct = false;

	/**
	 * @var bool runtime flag that indicates whether supported primary groups are available
	 */
	public $hasPrimaryGroups = true;

	/**
	 * @var bool runtime flag that indicates whether supported POSIX gidNumber are available
	 */
	public $hasGidNumber = true;

	//cache handler
	protected $cache;

	/** @var Configuration settings handler **/
	protected $configuration;

	protected $doNotValidate = false;

	protected $ignoreValidation = false;

	protected $bindResult = [];

	/**
	 * Constructor
	 * @param ILDAPWrapper $ldap
	 * @param string $configPrefix a string with the prefix for the configkey column (appconfig table)
	 * @param string|null $configID a string with the value for the appid column (appconfig table) or null for on-the-fly connections
	 */
	public function __construct(ILDAPWrapper $ldap, $configPrefix = '', $configID = 'user_ldap') {
		parent::__construct($ldap);
		$this->configPrefix = $configPrefix;
		$this->configID = $configID;
		$this->configuration = new Configuration($configPrefix,
												 !is_null($configID));
		$memcache = \OC::$server->getMemCacheFactory();
		if ($memcache->isAvailable()) {
			$this->cache = $memcache->createDistributed();
		}
		$helper = new Helper(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection());
		$this->doNotValidate = !in_array($this->configPrefix,
			$helper->getServerConfigurationPrefixes());
	}

	public function __destruct() {
		if (!$this->dontDestruct && $this->ldap->isResource($this->ldapConnectionRes)) {
			@$this->ldap->unbind($this->ldapConnectionRes);
			$this->bindResult = [];
		}
	}

	/**
	 * defines behaviour when the instance is cloned
	 */
	public function __clone() {
		$this->configuration = new Configuration($this->configPrefix,
												 !is_null($this->configID));
		if (count($this->bindResult) !== 0 && $this->bindResult['result'] === true) {
			$this->bindResult = [];
		}
		$this->ldapConnectionRes = null;
		$this->dontDestruct = true;
	}

	public function __get(string $name) {
		if (!$this->configured) {
			$this->readConfiguration();
		}

		return $this->configuration->$name;
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {
		$this->doNotValidate = false;
		$before = $this->configuration->$name;
		$this->configuration->$name = $value;
		$after = $this->configuration->$name;
		if ($before !== $after) {
			if ($this->configID !== '' && $this->configID !== null) {
				$this->configuration->saveConfiguration();
			}
			$this->validateConfiguration();
		}
	}

	/**
	 * @param string $rule
	 * @return array
	 * @throws \RuntimeException
	 */
	public function resolveRule($rule) {
		return $this->configuration->resolveRule($rule);
	}

	/**
	 * sets whether the result of the configuration validation shall
	 * be ignored when establishing the connection. Used by the Wizard
	 * in early configuration state.
	 * @param bool $state
	 */
	public function setIgnoreValidation($state) {
		$this->ignoreValidation = (bool)$state;
	}

	/**
	 * initializes the LDAP backend
	 * @param bool $force read the config settings no matter what
	 */
	public function init($force = false) {
		$this->readConfiguration($force);
		$this->establishConnection();
	}

	/**
	 * Returns the LDAP handler
	 */
	public function getConnectionResource() {
		if (!$this->ldapConnectionRes) {
			$this->init();
		} elseif (!$this->ldap->isResource($this->ldapConnectionRes)) {
			$this->ldapConnectionRes = null;
			$this->establishConnection();
		}
		if (is_null($this->ldapConnectionRes)) {
			\OCP\Util::writeLog('user_ldap', 'No LDAP Connection to server ' . $this->configuration->ldapHost, ILogger::ERROR);
			throw new ServerNotAvailableException('Connection to LDAP server could not be established');
		}
		return $this->ldapConnectionRes;
	}

	/**
	 * resets the connection resource
	 */
	public function resetConnectionResource() {
		if (!is_null($this->ldapConnectionRes)) {
			@$this->ldap->unbind($this->ldapConnectionRes);
			$this->ldapConnectionRes = null;
			$this->bindResult = [];
		}
	}

	/**
	 * @param string|null $key
	 * @return string
	 */
	private function getCacheKey($key) {
		$prefix = 'LDAP-'.$this->configID.'-'.$this->configPrefix.'-';
		if (is_null($key)) {
			return $prefix;
		}
		return $prefix.hash('sha256', $key);
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 */
	public function getFromCache($key) {
		if (!$this->configured) {
			$this->readConfiguration();
		}
		if (is_null($this->cache) || !$this->configuration->ldapCacheTTL) {
			return null;
		}
		$key = $this->getCacheKey($key);

		return json_decode(base64_decode($this->cache->get($key)), true);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function writeToCache($key, $value) {
		if (!$this->configured) {
			$this->readConfiguration();
		}
		if (is_null($this->cache)
			|| !$this->configuration->ldapCacheTTL
			|| !$this->configuration->ldapConfigurationActive) {
			return null;
		}
		$key = $this->getCacheKey($key);
		$value = base64_encode(json_encode($value));
		$this->cache->set($key, $value, $this->configuration->ldapCacheTTL);
	}

	public function clearCache() {
		if (!is_null($this->cache)) {
			$this->cache->clear($this->getCacheKey(null));
		}
	}

	/**
	 * Caches the general LDAP configuration.
	 * @param bool $force optional. true, if the re-read should be forced. defaults
	 * to false.
	 * @return null
	 */
	private function readConfiguration($force = false) {
		if ((!$this->configured || $force) && !is_null($this->configID)) {
			$this->configuration->readConfiguration();
			$this->configured = $this->validateConfiguration();
		}
	}

	/**
	 * set LDAP configuration with values delivered by an array, not read from configuration
	 * @param array $config array that holds the config parameters in an associated array
	 * @param array &$setParameters optional; array where the set fields will be given to
	 * @return boolean true if config validates, false otherwise. Check with $setParameters for detailed success on single parameters
	 */
	public function setConfiguration($config, &$setParameters = null) {
		if (is_null($setParameters)) {
			$setParameters = [];
		}
		$this->doNotValidate = false;
		$this->configuration->setConfiguration($config, $setParameters);
		if (count($setParameters) > 0) {
			$this->configured = $this->validateConfiguration();
		}


		return $this->configured;
	}

	/**
	 * saves the current Configuration in the database and empties the
	 * cache
	 * @return null
	 */
	public function saveConfiguration() {
		$this->configuration->saveConfiguration();
		$this->clearCache();
	}

	/**
	 * get the current LDAP configuration
	 * @return array
	 */
	public function getConfiguration() {
		$this->readConfiguration();
		$config = $this->configuration->getConfiguration();
		$cta = $this->configuration->getConfigTranslationArray();
		$result = [];
		foreach ($cta as $dbkey => $configkey) {
			switch ($configkey) {
				case 'homeFolderNamingRule':
					if (strpos($config[$configkey], 'attr:') === 0) {
						$result[$dbkey] = substr($config[$configkey], 5);
					} else {
						$result[$dbkey] = '';
					}
					break;
				case 'ldapBase':
				case 'ldapBaseUsers':
				case 'ldapBaseGroups':
				case 'ldapAttributesForUserSearch':
				case 'ldapAttributesForGroupSearch':
					if (is_array($config[$configkey])) {
						$result[$dbkey] = implode("\n", $config[$configkey]);
						break;
					} //else follows default
					// no break
				default:
					$result[$dbkey] = $config[$configkey];
			}
		}
		return $result;
	}

	private function doSoftValidation() {
		//if User or Group Base are not set, take over Base DN setting
		foreach (['ldapBaseUsers', 'ldapBaseGroups'] as $keyBase) {
			$val = $this->configuration->$keyBase;
			if (empty($val)) {
				$this->configuration->$keyBase = $this->configuration->ldapBase;
			}
		}

		foreach (['ldapExpertUUIDUserAttr' => 'ldapUuidUserAttribute',
			'ldapExpertUUIDGroupAttr' => 'ldapUuidGroupAttribute']
				as $expertSetting => $effectiveSetting) {
			$uuidOverride = $this->configuration->$expertSetting;
			if (!empty($uuidOverride)) {
				$this->configuration->$effectiveSetting = $uuidOverride;
			} else {
				$uuidAttributes = Access::UUID_ATTRIBUTES;
				array_unshift($uuidAttributes, 'auto');
				if (!in_array($this->configuration->$effectiveSetting,
							$uuidAttributes)
					&& (!is_null($this->configID))) {
					$this->configuration->$effectiveSetting = 'auto';
					$this->configuration->saveConfiguration();
					\OCP\Util::writeLog('user_ldap',
										'Illegal value for the '.
										$effectiveSetting.', '.'reset to '.
										'autodetect.', ILogger::INFO);
				}
			}
		}

		$backupPort = (int)$this->configuration->ldapBackupPort;
		if ($backupPort <= 0) {
			$this->configuration->backupPort = $this->configuration->ldapPort;
		}

		//make sure empty search attributes are saved as simple, empty array
		$saKeys = ['ldapAttributesForUserSearch',
			'ldapAttributesForGroupSearch'];
		foreach ($saKeys as $key) {
			$val = $this->configuration->$key;
			if (is_array($val) && count($val) === 1 && empty($val[0])) {
				$this->configuration->$key = [];
			}
		}

		if ((stripos($this->configuration->ldapHost, 'ldaps://') === 0)
			&& $this->configuration->ldapTLS) {
			$this->configuration->ldapTLS = false;
			\OCP\Util::writeLog(
				'user_ldap',
				'LDAPS (already using secure connection) and TLS do not work together. Switched off TLS.',
				ILogger::INFO
			);
		}
	}

	/**
	 * @return bool
	 */
	private function doCriticalValidation() {
		$configurationOK = true;
		$errorStr = 'Configuration Error (prefix '.
			(string)$this->configPrefix .'): ';

		//options that shall not be empty
		$options = ['ldapHost', 'ldapPort', 'ldapUserDisplayName',
			'ldapGroupDisplayName', 'ldapLoginFilter'];
		foreach ($options as $key) {
			$val = $this->configuration->$key;
			if (empty($val)) {
				switch ($key) {
					case 'ldapHost':
						$subj = 'LDAP Host';
						break;
					case 'ldapPort':
						$subj = 'LDAP Port';
						break;
					case 'ldapUserDisplayName':
						$subj = 'LDAP User Display Name';
						break;
					case 'ldapGroupDisplayName':
						$subj = 'LDAP Group Display Name';
						break;
					case 'ldapLoginFilter':
						$subj = 'LDAP Login Filter';
						break;
					default:
						$subj = $key;
						break;
				}
				$configurationOK = false;
				\OCP\Util::writeLog(
					'user_ldap',
					$errorStr.'No '.$subj.' given!',
					ILogger::WARN
				);
			}
		}

		//combinations
		$agent = $this->configuration->ldapAgentName;
		$pwd = $this->configuration->ldapAgentPassword;
		if (
			($agent === '' && $pwd !== '')
			|| ($agent !== '' && $pwd === '')
		) {
			\OCP\Util::writeLog(
				'user_ldap',
				$errorStr.'either no password is given for the user ' .
					'agent or a password is given, but not an LDAP agent.',
				ILogger::WARN);
			$configurationOK = false;
		}

		$base = $this->configuration->ldapBase;
		$baseUsers = $this->configuration->ldapBaseUsers;
		$baseGroups = $this->configuration->ldapBaseGroups;

		if (empty($base) && empty($baseUsers) && empty($baseGroups)) {
			\OCP\Util::writeLog(
				'user_ldap',
				$errorStr.'Not a single Base DN given.',
				ILogger::WARN
			);
			$configurationOK = false;
		}

		if (mb_strpos($this->configuration->ldapLoginFilter, '%uid', 0, 'UTF-8')
		   === false) {
			\OCP\Util::writeLog(
				'user_ldap',
				$errorStr.'login filter does not contain %uid place holder.',
				ILogger::WARN
			);
			$configurationOK = false;
		}

		return $configurationOK;
	}

	/**
	 * Validates the user specified configuration
	 * @return bool true if configuration seems OK, false otherwise
	 */
	private function validateConfiguration() {
		if ($this->doNotValidate) {
			//don't do a validation if it is a new configuration with pure
			//default values. Will be allowed on changes via __set or
			//setConfiguration
			return false;
		}

		// first step: "soft" checks: settings that are not really
		// necessary, but advisable. If left empty, give an info message
		$this->doSoftValidation();

		//second step: critical checks. If left empty or filled wrong, mark as
		//not configured and give a warning.
		return $this->doCriticalValidation();
	}


	/**
	 * Connects and Binds to LDAP
	 *
	 * @throws ServerNotAvailableException
	 */
	private function establishConnection() {
		if (!$this->configuration->ldapConfigurationActive) {
			return null;
		}
		static $phpLDAPinstalled = true;
		if (!$phpLDAPinstalled) {
			return false;
		}
		if (!$this->ignoreValidation && !$this->configured) {
			\OCP\Util::writeLog(
				'user_ldap',
				'Configuration is invalid, cannot connect',
				ILogger::WARN
			);
			return false;
		}
		if (!$this->ldapConnectionRes) {
			if (!$this->ldap->areLDAPFunctionsAvailable()) {
				$phpLDAPinstalled = false;
				\OCP\Util::writeLog(
					'user_ldap',
					'function ldap_connect is not available. Make sure that the PHP ldap module is installed.',
					ILogger::ERROR
				);

				return false;
			}
			if ($this->configuration->turnOffCertCheck) {
				if (putenv('LDAPTLS_REQCERT=never')) {
					\OCP\Util::writeLog('user_ldap',
						'Turned off SSL certificate validation successfully.',
						ILogger::DEBUG);
				} else {
					\OCP\Util::writeLog(
						'user_ldap',
						'Could not turn off SSL certificate validation.',
						ILogger::WARN
					);
				}
			}

			$isOverrideMainServer = ($this->configuration->ldapOverrideMainServer
				|| $this->getFromCache('overrideMainServer'));
			$isBackupHost = (trim($this->configuration->ldapBackupHost) !== "");
			$bindStatus = false;
			try {
				if (!$isOverrideMainServer) {
					$this->doConnect($this->configuration->ldapHost,
						$this->configuration->ldapPort);
					return $this->bind();
				}
			} catch (ServerNotAvailableException $e) {
				if (!$isBackupHost) {
					throw $e;
				}
			}

			//if LDAP server is not reachable, try the Backup (Replica!) Server
			if ($isBackupHost || $isOverrideMainServer) {
				$this->doConnect($this->configuration->ldapBackupHost,
								 $this->configuration->ldapBackupPort);
				$this->bindResult = [];
				$bindStatus = $this->bind();
				$error = $this->ldap->isResource($this->ldapConnectionRes) ?
					$this->ldap->errno($this->ldapConnectionRes) : -1;
				if ($bindStatus && $error === 0 && !$this->getFromCache('overrideMainServer')) {
					//when bind to backup server succeeded and failed to main server,
					//skip contacting him until next cache refresh
					$this->writeToCache('overrideMainServer', true);
				}
			}

			return $bindStatus;
		}
		return null;
	}

	/**
	 * @param string $host
	 * @param string $port
	 * @return bool
	 * @throws \OC\ServerNotAvailableException
	 */
	private function doConnect($host, $port) {
		if ($host === '') {
			return false;
		}

		$this->ldapConnectionRes = $this->ldap->connect($host, $port);

		if (!$this->ldap->setOption($this->ldapConnectionRes, LDAP_OPT_PROTOCOL_VERSION, 3)) {
			throw new ServerNotAvailableException('Could not set required LDAP Protocol version.');
		}

		if (!$this->ldap->setOption($this->ldapConnectionRes, LDAP_OPT_REFERRALS, 0)) {
			throw new ServerNotAvailableException('Could not disable LDAP referrals.');
		}

		if ($this->configuration->ldapTLS) {
			if (!$this->ldap->startTls($this->ldapConnectionRes)) {
				throw new ServerNotAvailableException('Start TLS failed, when connecting to LDAP host ' . $host . '.');
			}
		}

		return true;
	}

	/**
	 * Binds to LDAP
	 */
	public function bind() {
		if (!$this->configuration->ldapConfigurationActive) {
			return false;
		}
		$cr = $this->ldapConnectionRes;
		if (!$this->ldap->isResource($cr)) {
			$cr = $this->getConnectionResource();
		}

		if (
			count($this->bindResult) !== 0
			&& $this->bindResult['dn'] === $this->configuration->ldapAgentName
			&& \OC::$server->getHasher()->verify(
				$this->configPrefix . $this->configuration->ldapAgentPassword,
				$this->bindResult['hash']
			)
		) {
			// don't attempt to bind again with the same data as before
			// bind might have been invoked via getConnectionResource(),
			// but we need results specifically for e.g. user login
			return $this->bindResult['result'];
		}

		$ldapLogin = @$this->ldap->bind($cr,
										$this->configuration->ldapAgentName,
										$this->configuration->ldapAgentPassword);

		$this->bindResult = [
			'dn' => $this->configuration->ldapAgentName,
			'hash' => \OC::$server->getHasher()->hash($this->configPrefix . $this->configuration->ldapAgentPassword),
			'result' => $ldapLogin,
		];

		if (!$ldapLogin) {
			$errno = $this->ldap->errno($cr);

			\OCP\Util::writeLog('user_ldap',
				'Bind failed: ' . $errno . ': ' . $this->ldap->error($cr),
				ILogger::WARN);

			// Set to failure mode, if LDAP error code is not LDAP_SUCCESS or LDAP_INVALID_CREDENTIALS
			// or (needed for Apple Open Directory:) LDAP_INSUFFICIENT_ACCESS
			if ($errno !== 0 && $errno !== 49 && $errno !== 50) {
				$this->ldapConnectionRes = null;
			}

			return false;
		}
		return true;
	}
}
