<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
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

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Remote context.
 */
class RemoteContext implements Context {
	/** @var \OC\Remote\Instance */
	protected $remoteInstance;

	/** @var \OC\Remote\Credentials */
	protected $credentails;

	/** @var \OC\Remote\User */
	protected $userResult;

	protected $remoteUrl;

	protected $lastException;

	public function __construct($remote) {
		require_once __DIR__ . '/../../../../lib/base.php';
		$this->remoteUrl = $remote;
	}

	protected function getApiClient() {
		return new \OC\Remote\Api\OCS($this->remoteInstance, $this->credentails, \OC::$server->getHTTPClientService());
	}

	/**
	 * @Given /^using remote server "(REMOTE|NON_EXISTING)"$/
	 *
	 * @param string $remoteServer "NON_EXISTING" or "REMOTE"
	 */
	public function selectRemoteInstance($remoteServer) {
		if ($remoteServer == "REMOTE") {
			$baseUri = $this->remoteUrl;
		} else {
			$baseUri = 'nonexistingnextcloudserver.local';
		}
		$this->lastException = null;
		try {
			$this->remoteInstance = new \OC\Remote\Instance($baseUri, \OC::$server->getMemCacheFactory()->createLocal(), \OC::$server->getHTTPClientService());
			// trigger the status request
			$this->remoteInstance->getProtocol();
		} catch (\Exception $e) {
			$this->lastException = $e;
		}
	}

	/**
	 * @Then /^the remote version should be "([^"]*)"$/
	 * @param string $version
	 */
	public function theRemoteVersionShouldBe($version) {
		if ($version === '__current_version__') {
			$version = \OC::$server->getConfig()->getSystemValue('version', '0.0.0.0');
		}

		Assert::assertEquals($version, $this->remoteInstance->getVersion());
	}

	/**
	 * @Then /^the remote protocol should be "([^"]*)"$/
	 * @param string $protocol
	 */
	public function theRemoteProtocolShouldBe($protocol) {
		Assert::assertEquals($protocol, $this->remoteInstance->getProtocol());
	}

	/**
	 * @Given /^using credentials "([^"]*)", "([^"]*)"/
	 * @param string $user
	 * @param string $password
	 */
	public function usingCredentials($user, $password) {
		$this->credentails = new \OC\Remote\Credentials($user, $password);
	}

	/**
	 * @When /^getting the remote user info for "([^"]*)"$/
	 * @param string $user
	 */
	public function remoteUserInfo($user) {
		$this->lastException = null;
		try {
			$this->userResult = $this->getApiClient()->getUser($user);
		} catch (\Exception $e) {
			$this->lastException = $e;
		}
	}

	/**
	 * @Then /^the remote user should have userid "([^"]*)"$/
	 * @param string $user
	 */
	public function remoteUserId($user) {
		Assert::assertEquals($user, $this->userResult->getUserId());
	}

	/**
	 * @Then /^the request should throw a "([^"]*)"$/
	 * @param string $class
	 */
	public function lastError($class) {
		Assert::assertEquals($class, get_class($this->lastException));
	}

	/**
	 * @Then /^the capability "([^"]*)" is "([^"]*)"$/
	 * @param string $key
	 * @param string $value
	 */
	public function hasCapability($key, $value) {
		$capabilities = $this->getApiClient()->getCapabilities();
		$current = $capabilities;
		$parts = explode('.', $key);
		foreach ($parts as $part) {
			if ($current !== null) {
				$current = isset($current[$part]) ? $current[$part] : null;
			}
		}
		Assert::assertEquals($value, $current);
	}
}
