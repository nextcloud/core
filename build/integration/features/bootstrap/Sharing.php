<?php
/**
 *
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergio Bertolin <sbertolin@solidgear.es>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
 * @author Vincent Petry <vincent@nextcloud.com>
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
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../../vendor/autoload.php';



trait Sharing {
	use Provisioning;

	/** @var int */
	private $sharingApiVersion = 1;

	/** @var SimpleXMLElement */
	private $lastShareData = null;

	/** @var SimpleXMLElement[] */
	private $storedShareData = [];

	/** @var int */
	private $savedShareId = null;

	/** @var ResponseInterface */
	private $response;

	/**
	 * @Given /^as "([^"]*)" creating a share with$/
	 * @param string $user
	 * @param TableNode|null $body
	 */
	public function asCreatingAShareWith($user, $body) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares";
		$client = new Client();
		$options = [
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		if ($user === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$user, $this->regularUser];
		}

		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			if (array_key_exists('expireDate', $fd)) {
				$dateModification = $fd['expireDate'];
				$fd['expireDate'] = date('Y-m-d', strtotime($dateModification));
			}
			$options['form_params'] = $fd;
		}

		try {
			$this->response = $client->request("POST", $fullUrl, $options);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}

		$this->lastShareData = simplexml_load_string($this->response->getBody());
	}

	/**
	 * @When /^save the last share data as "([^"]*)"$/
	 */
	public function saveLastShareData($name) {
		$this->storedShareData[$name] = $this->lastShareData;
	}

	/**
	 * @When /^restore the last share data from "([^"]*)"$/
	 */
	public function restoreLastShareData($name) {
		$this->lastShareData = $this->storedShareData[$name];
	}

	/**
	 * @When /^creating a share with$/
	 * @param TableNode|null $body
	 */
	public function creatingShare($body) {
		$this->asCreatingAShareWith($this->currentUser, $body);
	}

	/**
	 * @When /^accepting last share$/
	 */
	public function acceptingLastShare() {
		$share_id = $this->lastShareData->data[0]->id;
		$url = "/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/pending/$share_id";
		$this->sendingToWith("POST", $url, null);

		$this->theHTTPStatusCodeShouldBe('200');
	}

	/**
	 * @When /^user "([^"]*)" accepts last share$/
	 *
	 * @param string $user
	 */
	public function userAcceptsLastShare(string $user) {
		// "As userXXX" and "user userXXX accepts last share" steps are not
		// expected to be used in the same scenario, but restore the user just
		// in case.
		$previousUser = $this->currentUser;

		$this->currentUser = $user;

		$share_id = $this->lastShareData->data[0]->id;
		$url = "/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/pending/$share_id";
		$this->sendingToWith("POST", $url, null);

		$this->currentUser = $previousUser;

		$this->theHTTPStatusCodeShouldBe('200');
	}

	/**
	 * @Then /^last link share can be downloaded$/
	 */
	public function lastLinkShareCanBeDownloaded() {
		if (count($this->lastShareData->data->element) > 0) {
			$url = $this->lastShareData->data[0]->url;
		} else {
			$url = $this->lastShareData->data->url;
		}
		$fullUrl = $url . "/download";
		$this->checkDownload($fullUrl, null, 'text/plain');
	}

	/**
	 * @Then /^last share can be downloaded$/
	 */
	public function lastShareCanBeDownloaded() {
		if (count($this->lastShareData->data->element) > 0) {
			$token = $this->lastShareData->data[0]->token;
		} else {
			$token = $this->lastShareData->data->token;
		}

		$fullUrl = substr($this->baseUrl, 0, -4) . "index.php/s/" . $token . "/download";
		$this->checkDownload($fullUrl, null, 'text/plain');
	}

	/**
	 * @Then /^last share with password "([^"]*)" can be downloaded$/
	 */
	public function lastShareWithPasswordCanBeDownloaded($password) {
		if (count($this->lastShareData->data->element) > 0) {
			$token = $this->lastShareData->data[0]->token;
		} else {
			$token = $this->lastShareData->data->token;
		}

		$fullUrl = substr($this->baseUrl, 0, -4) . "public.php/webdav";
		$this->checkDownload($fullUrl, [$token, $password], 'text/plain');
	}

	private function checkDownload($url, $auth = null, $mimeType = null) {
		if ($auth !== null) {
			$options['auth'] = $auth;
		}
		$options['stream'] = true;

		$client = new Client();
		$this->response = $client->get($url, $options);
		Assert::assertEquals(200, $this->response->getStatusCode());

		$buf = '';
		$body = $this->response->getBody();
		while (!$body->eof()) {
			// read everything
			$buf .= $body->read(8192);
		}
		$body->close();

		if ($mimeType !== null) {
			$finfo = new finfo;
			Assert::assertEquals($mimeType, $finfo->buffer($buf, FILEINFO_MIME_TYPE));
		}
	}

	/**
	 * @When /^Adding expiration date to last share$/
	 */
	public function addingExpirationDate() {
		$share_id = (string) $this->lastShareData->data[0]->id;
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/$share_id";
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUser];
		}
		$date = date('Y-m-d', strtotime("+3 days"));
		$options['form_params'] = ['expireDate' => $date];
		$this->response = $this->response = $client->request("PUT", $fullUrl, $options);
		Assert::assertEquals(200, $this->response->getStatusCode());
	}

	/**
	 * @When /^Updating last share with$/
	 * @param TableNode|null $body
	 */
	public function updatingLastShare($body) {
		$share_id = (string) $this->lastShareData->data[0]->id;
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/$share_id";
		$client = new Client();
		$options = [
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUser];
		}

		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			if (array_key_exists('expireDate', $fd)) {
				$dateModification = $fd['expireDate'];
				$fd['expireDate'] = date('Y-m-d', strtotime($dateModification));
			}
			$options['form_params'] = $fd;
		}

		try {
			$this->response = $client->request("PUT", $fullUrl, $options);
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	public function createShare($user,
								$path = null,
								$shareType = null,
								$shareWith = null,
								$publicUpload = null,
								$password = null,
								$permissions = null) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares";
		$client = new Client();
		$options = [
			'headers' => [
				'OCS-APIREQUEST' => 'true',
			],
		];

		if ($user === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$user, $this->regularUser];
		}
		$body = [];
		if (!is_null($path)) {
			$body['path'] = $path;
		}
		if (!is_null($shareType)) {
			$body['shareType'] = $shareType;
		}
		if (!is_null($shareWith)) {
			$body['shareWith'] = $shareWith;
		}
		if (!is_null($publicUpload)) {
			$body['publicUpload'] = $publicUpload;
		}
		if (!is_null($password)) {
			$body['password'] = $password;
		}
		if (!is_null($permissions)) {
			$body['permissions'] = $permissions;
		}

		$options['form_params'] = $body;

		try {
			$this->response = $client->request("POST", $fullUrl, $options);
			$this->lastShareData = simplexml_load_string($this->response->getBody());
		} catch (\GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			throw new \Exception($this->response->getBody());
		}
	}

	public function isFieldInResponse($field, $contentExpected) {
		$data = simplexml_load_string($this->response->getBody())->data[0];
		if ((string)$field == 'expiration') {
			$contentExpected = date('Y-m-d', strtotime($contentExpected)) . " 00:00:00";
		}
		if (count($data->element) > 0) {
			foreach ($data as $element) {
				if ($contentExpected == "A_TOKEN") {
					return (strlen((string)$element->$field) == 15);
				} elseif ($contentExpected == "A_NUMBER") {
					return is_numeric((string)$element->$field);
				} elseif ($contentExpected == "AN_URL") {
					return $this->isExpectedUrl((string)$element->$field, "index.php/s/");
				} elseif ((string)$element->$field == $contentExpected) {
					return true;
				} else {
					print($element->$field);
				}
			}

			return false;
		} else {
			if ($contentExpected == "A_TOKEN") {
				return (strlen((string)$data->$field) == 15);
			} elseif ($contentExpected == "A_NUMBER") {
				return is_numeric((string)$data->$field);
			} elseif ($contentExpected == "AN_URL") {
				return $this->isExpectedUrl((string)$data->$field, "index.php/s/");
			} elseif ($data->$field == $contentExpected) {
				return true;
			}
			return false;
		}
	}

	/**
	 * @Then /^File "([^"]*)" should be included in the response$/
	 *
	 * @param string $filename
	 */
	public function checkSharedFileInResponse($filename) {
		Assert::assertEquals(true, $this->isFieldInResponse('file_target', "/$filename"));
	}

	/**
	 * @Then /^File "([^"]*)" should not be included in the response$/
	 *
	 * @param string $filename
	 */
	public function checkSharedFileNotInResponse($filename) {
		Assert::assertEquals(false, $this->isFieldInResponse('file_target', "/$filename"));
	}

	/**
	 * @Then /^User "([^"]*)" should be included in the response$/
	 *
	 * @param string $user
	 */
	public function checkSharedUserInResponse($user) {
		Assert::assertEquals(true, $this->isFieldInResponse('share_with', "$user"));
	}

	/**
	 * @Then /^User "([^"]*)" should not be included in the response$/
	 *
	 * @param string $user
	 */
	public function checkSharedUserNotInResponse($user) {
		Assert::assertEquals(false, $this->isFieldInResponse('share_with', "$user"));
	}

	public function isUserOrGroupInSharedData($userOrGroup, $permissions = null) {
		$data = simplexml_load_string($this->response->getBody())->data[0];
		foreach ($data as $element) {
			if ($element->share_with == $userOrGroup && ($permissions === null || $permissions == $element->permissions)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @Given /^(file|folder|entry) "([^"]*)" of user "([^"]*)" is shared with user "([^"]*)"( with permissions ([\d]*))?$/
	 *
	 * @param string $filepath
	 * @param string $user1
	 * @param string $user2
	 */
	public function assureFileIsShared($entry, $filepath, $user1, $user2, $withPerms = null, $permissions = null) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares" . "?path=$filepath";
		$client = new Client();
		$options = [];
		if ($user1 === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$user1, $this->regularUser];
		}
		$options['headers'] = [
			'OCS-APIREQUEST' => 'true',
		];
		$this->response = $client->get($fullUrl, $options);
		if ($this->isUserOrGroupInSharedData($user2, $permissions)) {
			return;
		} else {
			$this->createShare($user1, $filepath, 0, $user2, null, null, $permissions);
		}
		$this->response = $client->get($fullUrl, $options);
		Assert::assertEquals(true, $this->isUserOrGroupInSharedData($user2, $permissions));
	}

	/**
	 * @Given /^(file|folder|entry) "([^"]*)" of user "([^"]*)" is shared with group "([^"]*)"( with permissions ([\d]*))?$/
	 *
	 * @param string $filepath
	 * @param string $user
	 * @param string $group
	 */
	public function assureFileIsSharedWithGroup($entry, $filepath, $user, $group, $withPerms = null, $permissions = null) {
		$fullUrl = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares" . "?path=$filepath";
		$client = new Client();
		$options = [];
		if ($user === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$user, $this->regularUser];
		}
		$options['headers'] = [
			'OCS-APIREQUEST' => 'true',
		];
		$this->response = $client->get($fullUrl, $options);
		if ($this->isUserOrGroupInSharedData($group, $permissions)) {
			return;
		} else {
			$this->createShare($user, $filepath, 1, $group, null, null, $permissions);
		}
		$this->response = $client->get($fullUrl, $options);
		Assert::assertEquals(true, $this->isUserOrGroupInSharedData($group, $permissions));
	}

	/**
	 * @When /^Deleting last share$/
	 */
	public function deletingLastShare() {
		$share_id = $this->lastShareData->data[0]->id;
		$url = "/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/$share_id";
		$this->sendingToWith("DELETE", $url, null);
	}

	/**
	 * @When /^Getting info of last share$/
	 */
	public function gettingInfoOfLastShare() {
		$share_id = $this->lastShareData->data[0]->id;
		$url = "/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/$share_id";
		$this->sendingToWith("GET", $url, null);
	}

	/**
	 * @Then /^last share_id is included in the answer$/
	 */
	public function checkingLastShareIDIsIncluded() {
		$share_id = $this->lastShareData->data[0]->id;
		if (!$this->isFieldInResponse('id', $share_id)) {
			Assert::fail("Share id $share_id not found in response");
		}
	}

	/**
	 * @Then /^last share_id is not included in the answer$/
	 */
	public function checkingLastShareIDIsNotIncluded() {
		$share_id = $this->lastShareData->data[0]->id;
		if ($this->isFieldInResponse('id', $share_id)) {
			Assert::fail("Share id $share_id has been found in response");
		}
	}

	/**
	 * @Then /^Share fields of last share match with$/
	 * @param TableNode|null $body
	 */
	public function checkShareFields($body) {
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();

			foreach ($fd as $field => $value) {
				if (substr($field, 0, 10) === "share_with") {
					$value = str_replace("REMOTE", substr($this->remoteBaseUrl, 0, -5), $value);
					$value = str_replace("LOCAL", substr($this->localBaseUrl, 0, -5), $value);
				}
				if (substr($field, 0, 6) === "remote") {
					$value = str_replace("REMOTE", substr($this->remoteBaseUrl, 0, -4), $value);
					$value = str_replace("LOCAL", substr($this->localBaseUrl, 0, -4), $value);
				}
				if (!$this->isFieldInResponse($field, $value)) {
					Assert::fail("$field" . " doesn't have value " . "$value");
				}
			}
		}
	}

	/**
	 * @Then the list of returned shares has :count shares
	 */
	public function theListOfReturnedSharesHasShares(int $count) {
		$this->theHTTPStatusCodeShouldBe('200');
		$this->theOCSStatusCodeShouldBe('100');

		$returnedShares = $this->getXmlResponse()->data[0];

		Assert::assertEquals($count, count($returnedShares->element));
	}

	/**
	 * @Then share :count is returned with
	 *
	 * @param int $number
	 * @param TableNode $body
	 */
	public function shareXIsReturnedWith(int $number, TableNode $body) {
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
			'permissions' => '19',
			'stime' => 'A_NUMBER',
			'parent' => '',
			'expiration' => '',
			'token' => '',
			'storage' => 'A_NUMBER',
			'item_source' => 'A_NUMBER',
			'file_source' => 'A_NUMBER',
			'file_parent' => 'A_NUMBER',
			'mail_send' => '0'
		];
		$expectedFields = array_merge($defaultExpectedFields, $body->getRowsHash());

		if (!array_key_exists('uid_file_owner', $expectedFields) &&
				array_key_exists('uid_owner', $expectedFields)) {
			$expectedFields['uid_file_owner'] = $expectedFields['uid_owner'];
		}
		if (!array_key_exists('displayname_file_owner', $expectedFields) &&
				array_key_exists('displayname_owner', $expectedFields)) {
			$expectedFields['displayname_file_owner'] = $expectedFields['displayname_owner'];
		}

		if (array_key_exists('share_type', $expectedFields) &&
				$expectedFields['share_type'] == 10 /* IShare::TYPE_ROOM */ &&
				array_key_exists('share_with', $expectedFields)) {
			if ($expectedFields['share_with'] === 'private_conversation') {
				$expectedFields['share_with'] = 'REGEXP /^private_conversation_[0-9a-f]{6}$/';
			} else {
				$expectedFields['share_with'] = FeatureContext::getTokenForIdentifier($expectedFields['share_with']);
			}
		}

		foreach ($expectedFields as $field => $value) {
			$this->assertFieldIsInReturnedShare($field, $value, $returnedShare);
		}
	}

	/**
	 * @return SimpleXMLElement
	 */
	private function getXmlResponse(): \SimpleXMLElement {
		return simplexml_load_string($this->response->getBody());
	}

	/**
	 * @param string $field
	 * @param string $contentExpected
	 * @param \SimpleXMLElement $returnedShare
	 */
	private function assertFieldIsInReturnedShare(string $field, string $contentExpected, \SimpleXMLElement $returnedShare) {
		if ($contentExpected === 'IGNORE') {
			return;
		}

		if (!array_key_exists($field, $returnedShare)) {
			Assert::fail("$field was not found in response");
		}

		if ($field === 'expiration' && !empty($contentExpected)) {
			$contentExpected = date('Y-m-d', strtotime($contentExpected)) . " 00:00:00";
		}

		if ($contentExpected === 'A_NUMBER') {
			Assert::assertTrue(is_numeric((string)$returnedShare->$field), "Field '$field' is not a number: " . $returnedShare->$field);
		} elseif ($contentExpected === 'A_TOKEN') {
			// A token is composed by 15 characters from
			// ISecureRandom::CHAR_HUMAN_READABLE.
			Assert::assertRegExp('/^[abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789]{15}$/', (string)$returnedShare->$field, "Field '$field' is not a token");
		} elseif (strpos($contentExpected, 'REGEXP ') === 0) {
			Assert::assertRegExp(substr($contentExpected, strlen('REGEXP ')), (string)$returnedShare->$field, "Field '$field' does not match");
		} else {
			Assert::assertEquals($contentExpected, (string)$returnedShare->$field, "Field '$field' does not match");
		}
	}

	/**
	 * @Then As :user remove all shares from the file named :fileName
	 */
	public function asRemoveAllSharesFromTheFileNamed($user, $fileName) {
		$url = $this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares?format=json";
		$client = new \GuzzleHttp\Client();
		$res = $client->get(
			$url,
			[
				'auth' => [
					$user,
					'123456',
				],
				'headers' => [
					'Content-Type' => 'application/json',
					'OCS-APIREQUEST' => 'true',
				],
			]
		);
		$json = json_decode($res->getBody()->getContents(), true);
		$deleted = false;
		foreach ($json['ocs']['data'] as $data) {
			if (stripslashes($data['path']) === $fileName) {
				$id = $data['id'];
				$client->delete(
					$this->baseUrl . "v{$this->apiVersion}.php/apps/files_sharing/api/v{$this->sharingApiVersion}/shares/{$id}",
					[
						'auth' => [
							$user,
							'123456',
						],
						'headers' => [
							'Content-Type' => 'application/json',
							'OCS-APIREQUEST' => 'true',
						],
					]
				);
				$deleted = true;
			}
		}

		if ($deleted === false) {
			throw new \Exception("Could not delete file $fileName");
		}
	}

	/**
	 * @When save last share id
	 */
	public function saveLastShareId() {
		$this->savedShareId = $this->lastShareData['data']['id'];
	}

	/**
	 * @Then share ids should match
	 */
	public function shareIdsShouldMatch() {
		if ($this->savedShareId !== $this->lastShareData['data']['id']) {
			throw new \Exception('Expected the same link share to be returned');
		}
	}

	/**
	 * @When /^getting sharees for$/
	 * @param TableNode $body
	 */
	public function whenGettingShareesFor($body) {
		$url = '/apps/files_sharing/api/v1/sharees';
		if ($body instanceof TableNode) {
			$parameters = [];
			foreach ($body->getRowsHash() as $key => $value) {
				$parameters[] = $key . '=' . $value;
			}
			if (!empty($parameters)) {
				$url .= '?' . implode('&', $parameters);
			}
		}

		$this->sendingTo('GET', $url);
	}

	/**
	 * @Then /^"([^"]*)" sharees returned (are|is empty)$/
	 * @param string $shareeType
	 * @param string $isEmpty
	 * @param TableNode|null $shareesList
	 */
	public function thenListOfSharees($shareeType, $isEmpty, $shareesList = null) {
		if ($isEmpty !== 'is empty') {
			$sharees = $shareesList->getRows();
			$respondedArray = $this->getArrayOfShareesResponded($this->response, $shareeType);
			Assert::assertEquals($sharees, $respondedArray);
		} else {
			$respondedArray = $this->getArrayOfShareesResponded($this->response, $shareeType);
			Assert::assertEmpty($respondedArray);
		}
	}

	public function getArrayOfShareesResponded(ResponseInterface $response, $shareeType) {
		$elements = simplexml_load_string($response->getBody())->data;
		$elements = json_decode(json_encode($elements), 1);
		if (strpos($shareeType, 'exact ') === 0) {
			$elements = $elements['exact'];
			$shareeType = substr($shareeType, 6);
		}

		$sharees = [];
		foreach ($elements[$shareeType] as $element) {
			$sharees[] = [$element['label'], $element['value']['shareType'], $element['value']['shareWith']];
		}
		return $sharees;
	}
}
