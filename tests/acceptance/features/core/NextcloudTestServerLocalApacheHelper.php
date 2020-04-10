<?php

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * tests themselves using the Apache web server.
 *
 * The Nextcloud test server is executed using the Apache web server; the
 * default Apache directory is expected to have been set to the root directory
 * of the Nextcloud server (for example, by linking "var/www/html" to it); in
 * any case, note that the acceptance tests must be run from the acceptance
 * tests directory. The "setUp" method resets the Nextcloud server to its
 * initial state and starts it, while the "cleanUp" method stops it. To be able
 * to reset the Nextcloud server to its initial state a Git repository must be
 * provided in the root directory of the Nextcloud server; the last commit in
 * that Git repository must provide the initial state for the Nextcloud server
 * expected by the acceptance tests. When the Nextcloud server is reset the
 * owner of "apps", "config" and "data" must be set to the user that Apache
 * server is run as; it is assumed that Apache is run as "www-data".
 *
 * The Nextcloud server is available at "$nextcloudServerDomain", which can be
 * optionally specified when the NextcloudTestServerLocalApacheHelper is
 * created; if no value is given "127.0.0.1" is used by default. In any case,
 * the value of "$nextcloudServerDomain" must be seen as a trusted domain by the
 * Nextcloud server (which would be the case for "127.0.0.1" if it was installed
 * by running "occ maintenance:install"). The base URL to access the Nextcloud
 * server can be got from "getBaseUrl".
 */
class NextcloudTestServerLocalApacheHelper implements NextcloudTestServerHelper {

	/**
	 * @var string
	 */
	private $nextcloudServerDomain;

	/**
	 * Creates a new NextcloudTestServerLocalApacheHelper.
	 */
	public function __construct($nextcloudServerDomain = "127.0.0.1") {
		$this->nextcloudServerDomain = $nextcloudServerDomain;
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
		// Ensure that previous Apache server is not running (as cleanUp may not
		// have been called).
		$this->stopApacheServer();

		$this->execOrException("cd ../../ && git reset --hard HEAD");
		$this->execOrException("cd ../../ && git clean -d --force");
		$this->execOrException("cd ../../ && chown -R www-data:www-data apps config data");

		$this->execOrException("service apache2 start");

		$timeout = 60;
		if (!Utils::waitForServer($this->getBaseUrl(), $timeout)) {
			throw new Exception("Nextcloud test server could not be started");
		}
	}

	/**
	 * Cleans up the Nextcloud test server.
	 *
	 * It stops the running Nextcloud test server, if any.
	 */
	public function cleanUp() {
		$this->stopApacheServer();
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
	 * Stops the Apache server started in setUp, if any.
	 */
	private function stopApacheServer() {
		$this->execOrException("service apache2 stop");
	}

}
