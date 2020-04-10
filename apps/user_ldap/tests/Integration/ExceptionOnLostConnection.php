<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\User_LDAP\Tests\Integration;

use OC\ServerNotAvailableException;
use OCA\User_LDAP\LDAP;

/**
 * Class ExceptionOnLostConnection
 *
 * integration test, ensures that an exception is thrown, when the connection is lost.
 *
 * LDAP must be available via toxiproxy.
 *
 * This test must be run manually.
 *
 */
class ExceptionOnLostConnection {
	/** @var  string */
	private $toxiProxyHost;

	/** @var  string */
	private $toxiProxyName;

	/** @var  string */
	private $ldapBase;

	/** @var string|null  */
	private $ldapBindDN;

	/** @var string|null  */
	private $ldapBindPwd;

	/** @var  string */
	private $ldapHost;

	/** @var  \OCA\User_LDAP\LDAP */
	private $ldap;

	/** @var  bool */
	private $originalProxyState;

	/**
	 * @param string $proxyHost host of toxiproxy as url, like http://localhost:8474
	 * @param string $proxyName name of the LDAP proxy service as configured in toxiProxy
	 * @param string $ldapBase any valid LDAP base DN
	 * @param null $bindDN optional, bind DN if anonymous bind is not possible
	 * @param null $bindPwd optional
	 */
	public function __construct($proxyHost, $proxyName, $ldapBase, $bindDN = null, $bindPwd = null) {
		$this->toxiProxyHost = $proxyHost;
		$this->toxiProxyName = $proxyName;
		$this->ldapBase = $ldapBase;
		$this->ldapBindDN = $bindDN;
		$this->ldapBindPwd = $bindPwd;

		$this->setUp();
	}

	/**
	 * destructor
	 */
	public function __destruct() {
		$this->cleanUp();
	}

	/**
	 * prepares everything for the test run. Includes loading Nextcloud and
	 * the LDAP backend, as well as getting information about toxiproxy.
	 * Also creates an instance of the LDAP class, the testee
	 *
	 * @throws \Exception
	 */
	public function setUp(): void {
		require_once __DIR__  . '/../../../../lib/base.php';
		\OC_App::loadApps(['user_ldap']);

		$ch = $this->getCurl();
		$proxyInfoJson = curl_exec($ch);
		$this->checkCurlResult($ch, $proxyInfoJson);
		$proxyInfo = json_decode($proxyInfoJson, true);
		$this->originalProxyState = $proxyInfo['enabled'];
		$this->ldapHost = 'ldap://' . $proxyInfo['listen']; // contains port as well

		$this->ldap = new LDAP();
	}

	/**
	 * restores original state of the LDAP proxy, if necessary
	 */
	public function cleanUp() {
		if($this->originalProxyState === true) {
			$this->setProxyState(true);
		}
	}

	/**
	 * runs the test and prints the result. Exit code is 0 if successful, 1 on
	 * fail
	 */
	public function run() {
		if($this->originalProxyState === false) {
			$this->setProxyState(true);
		}
		//host contains port, 2nd parameter will be ignored
		$cr = $this->ldap->connect($this->ldapHost, 0);
		$this->ldap->bind($cr, $this->ldapBindDN, $this->ldapBindPwd);
		$this->ldap->search($cr, $this->ldapBase, 'objectClass=*', ['dn'], true, 5);

		// disable LDAP, will cause lost connection
		$this->setProxyState(false);
		try {
			$this->ldap->search($cr, $this->ldapBase, 'objectClass=*', ['dn'], true, 5);
		} catch (ServerNotAvailableException $e) {
			print("Test PASSED" . PHP_EOL);
			exit(0);
		}
		print("Test FAILED" . PHP_EOL);
		exit(1);
	}

	/**
	 * tests whether a curl operation ran successfully. If not, an exception
	 * is thrown
	 *
	 * @param resource $ch
	 * @param mixed $result
	 * @throws \Exception
	 */
	private function checkCurlResult($ch, $result) {
		if($result === false) {
			$error = curl_error($ch);
			curl_close($ch);
			throw new \Exception($error);
		}
	}

	/**
	 * enables or disabled the LDAP proxy service in toxiproxy
	 *
	 * @param bool $isEnabled whether is should be enabled or disables
	 * @throws \Exception
	 */
	private function setProxyState($isEnabled) {
		if(!is_bool($isEnabled)) {
			throw new \InvalidArgumentException('Bool expected');
		}
		$postData = json_encode(['enabled' => $isEnabled]);
		$ch = $this->getCurl();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($postData)]
		);
		$recvd = curl_exec($ch);
		$this->checkCurlResult($ch, $recvd);
	}

	/**
	 * initializes a curl handler towards the toxiproxy LDAP proxy service
	 * @return resource
	 */
	private function getCurl() {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->toxiProxyHost . '/proxies/' . $this->toxiProxyName);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		return $ch;
	}
}

$test = new ExceptionOnLostConnection('http://localhost:8474', 'ldap', 'dc=owncloud,dc=bzoc');
$test->run();
