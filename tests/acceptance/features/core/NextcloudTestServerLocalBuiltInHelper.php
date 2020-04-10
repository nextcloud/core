<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Helper to manage a Nextcloud test server started directly by the acceptance
 * tests themselves using the PHP built-in web server.
 *
 * The Nextcloud test server is executed using the PHP built-in web server
 * directly from the grandparent directory of the acceptance tests directory
 * (that is, the root directory of the Nextcloud server); note that the
 * acceptance tests must be run from the acceptance tests directory. The "setUp"
 * method resets the Nextcloud server to its initial state and starts it, while
 * the "cleanUp" method stops it. To be able to reset the Nextcloud server to
 * its initial state a Git repository must be provided in the root directory of
 * the Nextcloud server; the last commit in that Git repository must provide the
 * initial state for the Nextcloud server expected by the acceptance tests.
 *
 * The Nextcloud server is available at "$nextcloudServerDomain", which can be
 * optionally specified when the NextcloudTestServerLocalBuiltInHelper is
 * created; if no value is given "127.0.0.1" is used by default. In any case,
 * the value of "$nextcloudServerDomain" must be seen as a trusted domain by the
 * Nextcloud server (which would be the case for "127.0.0.1" if it was installed
 * by running "occ maintenance:install"). The base URL to access the Nextcloud
 * server can be got from "getBaseUrl".
 */
class NextcloudTestServerLocalBuiltInHelper implements NextcloudTestServerHelper {

	/**
	 * @var string
	 */
	private $nextcloudServerDomain;

	/**
	 * @var string
	 */
	private $phpServerPid;

	/**
	 * Creates a new NextcloudTestServerLocalBuiltInHelper.
	 */
	public function __construct($nextcloudServerDomain = "127.0.0.1") {
		$this->nextcloudServerDomain = $nextcloudServerDomain;

		$this->phpServerPid = "";
	}

	/**
	 * Sets up the Nextcloud test server.
	 *
	 * It resets the Nextcloud test server restoring its last saved Git state
	 * and then waits for the Nextcloud test server to start again; if the
	 * server can not be reset or if it does not start again after some time an
	 * exception is thrown (as it is just a warning for the test runner and
	 * nothing to be explicitly catched a plain base Exception is used).
	 *
	 * @throws \Exception if the Nextcloud test server can not be reset or
	 *         started again.
	 */
	public function setUp(): void {
		// Ensure that previous PHP server is not running (as cleanUp may not
		// have been called).
		$this->killPhpServer();

		$this->execOrException("cd ../../ && git reset --hard HEAD");
		$this->execOrException("cd ../../ && git clean -d --force");

		// execOrException is not used because the server is started in the
		// background, so the command will always succeed even if the server
		// itself fails.
		$this->phpServerPid = exec("php -S " . $this->nextcloudServerDomain . ":80 -t ../../ >/dev/null 2>&1 & echo $!");

		$timeout = 60;
		if (!Utils::waitForServer($this->getBaseUrl(), $timeout)) {
			throw new Exception("Nextcloud test server could not be started");
		}
	}

	/**
	 * Cleans up the Nextcloud test server.
	 *
	 * It kills the running Nextcloud test server, if any.
	 */
	public function cleanUp() {
		$this->killPhpServer();
	}

	/**
	 * Returns the base URL of the Nextcloud test server.
	 *
	 * @return string the base URL of the Nextcloud test server.
	 */
	public function getBaseUrl() {
		return "http://" . $this->nextcloudServerDomain . "/index.php";
	}

	/**
	 * Executes the given command, throwing an Exception if it fails.
	 *
	 * @param string $command the command to execute.
	 * @throws \Exception if the command fails to execute.
	 */
	private function execOrException($command) {
		exec($command . " 2>&1", $output, $returnValue);
		if ($returnValue != 0) {
			throw new Exception("'$command' could not be executed: " . implode("\n", $output));
		}
	}

	/**
	 * Kills the PHP built-in web server started in setUp, if any.
	 */
	private function killPhpServer() {
		if ($this->phpServerPid == "") {
			return;
		}

		// execOrException is not used because the PID may no longer exist when
		// trying to kill it.
		exec("kill " . $this->phpServerPid);

		$this->phpServerPid = "";
	}

}
