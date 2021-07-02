<?php
/**
 * @copyright Copyright (c) 2016 Sergio Bertolin <sbertolin@solidgear.es>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Sergio Bertolin <sbertolin@solidgear.es>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\TableNode;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Federation context.
 */
class FederationContext implements Context, SnippetAcceptingContext {
	use WebDav;
	use AppConfiguration;
	use CommandLine;

	/** @var string */
	private static $phpFederatedServerPid = '';

	/** @var string */
	private $lastAcceptedRemoteShareId;

	/**
	 * @BeforeScenario
	 * @AfterScenario
	 *
	 * The server is started also after the scenarios to ensure that it is
	 * properly cleaned up if stopped.
	 */
	public function startFederatedServer() {
		if (self::$phpFederatedServerPid !== '') {
			return;
		}

		$port = getenv('PORT_FED');

		self::$phpFederatedServerPid = exec('php -S localhost:' . $port . ' -t ../../ >/dev/null & echo $!');
	}

	/**
	 * @BeforeScenario
	 */
	public function cleanupRemoteStorages() {
		// Ensure that dangling remote storages from previous tests will not
		// interfere with the current scenario.
		// The storages must be cleaned before each scenario; they can not be
		// cleaned after each scenario, as this hook is executed before the hook
		// that removes the users, so the shares would be still valid and thus
		// the storages would not be dangling yet.
		$this->runOcc(['sharing:cleanup-remote-storages']);
	}

	/**
	 * @Given /^User "([^"]*)" from server "(LOCAL|REMOTE)" shares "([^"]*)" with user "([^"]*)" from server "(LOCAL|REMOTE)"$/
	 *
	 * @param string $sharerUser
	 * @param string $sharerServer "LOCAL" or "REMOTE"
	 * @param string $sharerPath
	 * @param string $shareeUser
	 * @param string $shareeServer "LOCAL" or "REMOTE"
	 */
	public function federateSharing($sharerUser, $sharerServer, $sharerPath, $shareeUser, $shareeServer) {
		if ($shareeServer == "REMOTE") {
			$shareWith = "$shareeUser@" . substr($this->remoteBaseUrl, 0, -4);
		} else {
			$shareWith = "$shareeUser@" . substr($this->localBaseUrl, 0, -4);
		}
		$previous = $this->usingServer($sharerServer);
		$this->createShare($sharerUser, $sharerPath, 6, $shareWith, null, null, null);
		$this->usingServer($previous);
	}


	/**
	 * @Given /^User "([^"]*)" from server "(LOCAL|REMOTE)" shares "([^"]*)" with group "([^"]*)" from server "(LOCAL|REMOTE)"$/
	 *
	 * @param string $sharerUser
	 * @param string $sharerServer "LOCAL" or "REMOTE"
	 * @param string $sharerPath
	 * @param string $shareeUser
	 * @param string $shareeServer "LOCAL" or "REMOTE"
	 */
	public function federateGroupSharing($sharerUser, $sharerServer, $sharerPath, $shareeGroup, $shareeServer) {
		if ($shareeServer == "REMOTE") {
			$shareWith = "$shareeGroup@" . substr($this->remoteBaseUrl, 0, -4);
		} else {
			$shareWith = "$shareeGroup@" . substr($this->localBaseUrl, 0, -4);
		}
		$previous = $this->usingServer($sharerServer);
		$this->createShare($sharerUser, $sharerPath, 9, $shareWith, null, null, null);
		$this->usingServer($previous);
	}

	/**
	 * @Then remote share :count is returned with
	 *
	 * @param int $number
	 * @param TableNode $body
	 */
	public function remoteShareXIsReturnedWith(int $number, TableNode $body) {
		$this->theHTTPStatusCodeShouldBe('200');
		$this->theOCSStatusCodeShouldBe('100');

		if (!($body instanceof TableNode)) {
			return;
		}

		$returnedShare = $this->getXmlResponse()->data[0];
		if ($returnedShare->element) {
			$returnedShare = $returnedShare->element[$number];
		}

		$defaultExpectedFields = [
			'id' => 'A_NUMBER',
			'remote_id' => 'A_NUMBER',
			'accepted' => '1',
		];
		$expectedFields = array_merge($defaultExpectedFields, $body->getRowsHash());

		foreach ($expectedFields as $field => $value) {
			$this->assertFieldIsInReturnedShare($field, $value, $returnedShare);
		}
	}

	/**
	 * @When /^User "([^"]*)" from server "(LOCAL|REMOTE)" accepts last pending share$/
	 * @param string $user
	 * @param string $server
	 */
	public function acceptLastPendingShare($user, $server) {
		$previous = $this->usingServer($server);
		$this->asAn($user);
		$this->sendingToWith('GET', "/apps/files_sharing/api/v1/remote_shares/pending", null);
		$this->theHTTPStatusCodeShouldBe('200');
		$this->theOCSStatusCodeShouldBe('100');
		$share_id = simplexml_load_string($this->response->getBody())->data[0]->element[0]->id;
		$this->sendingToWith('POST', "/apps/files_sharing/api/v1/remote_shares/pending/{$share_id}", null);
		$this->theHTTPStatusCodeShouldBe('200');
		$this->theOCSStatusCodeShouldBe('100');
		$this->usingServer($previous);

		$this->lastAcceptedRemoteShareId = $share_id;
	}

	/**
	 * @When /^user "([^"]*)" deletes last accepted remote share$/
	 * @param string $user
	 */
	public function deleteLastAcceptedRemoteShare($user) {
		$this->asAn($user);
		$this->sendingToWith('DELETE', "/apps/files_sharing/api/v1/remote_shares/" . $this->lastAcceptedRemoteShareId, null);
	}

	/**
	 * @When /^remote server is stopped$/
	 */
	public function remoteServerIsStopped() {
		if (self::$phpFederatedServerPid === '') {
			return;
		}

		exec('kill ' . self::$phpFederatedServerPid);

		self::$phpFederatedServerPid = '';
	}

	protected function resetAppConfigs() {
		$this->deleteServerConfig('files_sharing', 'incoming_server2server_group_share_enabled');
		$this->deleteServerConfig('files_sharing', 'outgoing_server2server_group_share_enabled');
	}
}
