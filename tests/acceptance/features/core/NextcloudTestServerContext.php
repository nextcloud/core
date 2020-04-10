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

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Behat context to run each scenario against a clean Nextcloud server.
 *
 * Before each scenario is run, this context sets up a fresh Nextcloud server
 * with predefined data and configuration. Thanks to this every scenario is
 * independent from the others and they all know the initial state of the
 * server.
 *
 * This context is expected to be used along with RawMinkContext contexts (or
 * subclasses). As the server address can be different for each scenario, this
 * context automatically sets the "base_url" parameter of all its sibling
 * RawMinkContexts; just add NextcloudTestServerContext to the context list of a
 * suite in "behat.yml".
 *
 * The Nextcloud server is provided by an instance of NextcloudTestServerHelper;
 * its class must be specified when this context is created. By default,
 * "NextcloudTestServerLocalBuiltInHelper" is used, although that can be
 * customized using the "nextcloudTestServerHelper" parameter in "behat.yml". In
 * the same way, the parameters to be passed to the helper when it is created
 * can be customized using the "nextcloudTestServerHelperParameters" parameter,
 * which is an array (without keys) with the value of the parameters in the same
 * order as in the constructor of the helper class (by default, [ ]).
 *
 * Example of custom parameters in "behat.yml":
 * default:
 *   suites:
 *     default:
 *       contexts:
 *         - NextcloudTestServerContext:
 *             nextcloudTestServerHelper: NextcloudTestServerCustomHelper
 *             nextcloudTestServerHelperParameters:
 *               - first-parameter-value
 *               - second-parameter-value
 */
class NextcloudTestServerContext implements Context {

	/**
	 * @var NextcloudTestServerHelper
	 */
	private $nextcloudTestServerHelper;

	/**
	 * Creates a new NextcloudTestServerContext.
	 *
	 * @param string $nextcloudTestServerHelper the name of the
	 *        NextcloudTestServerHelper implementing class to use.
	 * @param array $nextcloudTestServerHelperParameters the parameters for the
	 *        constructor of the $nextcloudTestServerHelper class.
	 */
	public function __construct($nextcloudTestServerHelper = "NextcloudTestServerLocalBuiltInHelper",
								$nextcloudTestServerHelperParameters = [ ]) {
		$nextcloudTestServerHelperClass = new ReflectionClass($nextcloudTestServerHelper);

		if ($nextcloudTestServerHelperParameters === null) {
			$nextcloudTestServerHelperParameters = [];
		}

		$this->nextcloudTestServerHelper = $nextcloudTestServerHelperClass->newInstanceArgs($nextcloudTestServerHelperParameters);
	}

	/**
	 * @BeforeScenario
	 *
	 * Sets up the Nextcloud test server before each scenario.
	 *
	 * Once the Nextcloud test server is set up, the "base_url" parameter of the
	 * sibling RawMinkContexts is set to the base URL of the Nextcloud test
	 * server.
	 *
	 * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope the
	 *        BeforeScenario hook scope.
	 * @throws \Exception if the Nextcloud test server can not be set up or its
	 *         base URL got.
	 */
	public function setUpNextcloudTestServer(BeforeScenarioScope $scope) {
		$this->nextcloudTestServerHelper->setUp();

		$this->setBaseUrlInSiblingRawMinkContexts($scope, $this->nextcloudTestServerHelper->getBaseUrl());
	}

	/**
	 * @AfterScenario
	 *
	 * Cleans up the Nextcloud test server after each scenario.
	 *
	 * @throws \Exception if the Nextcloud test server can not be cleaned up.
	 */
	public function cleanUpNextcloudTestServer() {
		$this->nextcloudTestServerHelper->cleanUp();
	}

	private function setBaseUrlInSiblingRawMinkContexts(BeforeScenarioScope $scope, $baseUrl) {
		$environment = $scope->getEnvironment();

		foreach ($environment->getContexts() as $context) {
			if ($context instanceof Behat\MinkExtension\Context\RawMinkContext) {
				$context->setMinkParameter("base_url", $baseUrl);
			}
		}
	}

}
