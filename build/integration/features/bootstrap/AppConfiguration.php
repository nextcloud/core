<?php
/**
 *
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Sergio Bertolin <sbertolin@solidgear.es>
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
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../../vendor/autoload.php';

trait AppConfiguration {
	/** @var string */
	private $currentUser = '';

	/** @var ResponseInterface */
	private $response = null;

	abstract public function sendingTo($verb, $url);
	abstract public function sendingToWith($verb, $url, $body);
	abstract public function theOCSStatusCodeShouldBe($statusCode);
	abstract public function theHTTPStatusCodeShouldBe($statusCode);

	/**
	 * @Given /^parameter "([^"]*)" of app "([^"]*)" is set to "([^"]*)"$/
	 * @param string $parameter
	 * @param string $app
	 * @param string $value
	 */
	public function serverParameterIsSetTo($parameter, $app, $value) {
		$user = $this->currentUser;
		$this->currentUser = 'admin';

		$this->modifyServerConfig($app, $parameter, $value);

		$this->currentUser = $user;
	}

	/**
	 * @param string $app
	 * @param string $parameter
	 * @param string $value
	 */
	protected function modifyServerConfig($app, $parameter, $value) {
		$body = new \Behat\Gherkin\Node\TableNode([['value', $value]]);
		$this->sendingToWith('post', "/apps/testing/api/v1/app/{$app}/{$parameter}", $body);
		$this->theHTTPStatusCodeShouldBe('200');
		if ($this->apiVersion === 1) {
			$this->theOCSStatusCodeShouldBe('100');
		}
	}

	protected function setStatusTestingApp($enabled) {
		$this->sendingTo(($enabled ? 'post' : 'delete'), '/cloud/apps/testing');
		$this->theHTTPStatusCodeShouldBe('200');
		if ($this->apiVersion === 1) {
			$this->theOCSStatusCodeShouldBe('100');
		}

		$this->sendingTo('get', '/cloud/apps?filter=enabled');
		$this->theHTTPStatusCodeShouldBe('200');
		if ($enabled) {
			Assert::assertContains('testing', $this->response->getBody()->getContents());
		} else {
			Assert::assertNotContains('testing', $this->response->getBody()->getContents());
		}
	}

	abstract protected function resetAppConfigs();

	/**
	 * @BeforeScenario
	 *
	 * Enable the testing app before the first scenario of the feature and
	 * reset the configs before each scenario
	 * @param BeforeScenarioScope $event
	 */
	public function prepareParameters(BeforeScenarioScope $event) {
		$user = $this->currentUser;
		$this->currentUser = 'admin';

		$scenarios = $event->getFeature()->getScenarios();
		if ($event->getScenario() === reset($scenarios)) {
			$this->setStatusTestingApp(true);
		}

		$this->resetAppConfigs();

		$this->currentUser = $user;
	}

	/**
	 * @AfterScenario
	 *
	 * Reset the values after the last scenario of the feature and disable the testing app
	 * @param AfterScenarioScope $event
	 */
	public function undoChangingParameters(AfterScenarioScope $event) {
		$scenarios = $event->getFeature()->getScenarios();
		if ($event->getScenario() === end($scenarios)) {
			$user = $this->currentUser;
			$this->currentUser = 'admin';

			$this->resetAppConfigs();

			$this->setStatusTestingApp(false);
			$this->currentUser = $user;
		}
	}
}
