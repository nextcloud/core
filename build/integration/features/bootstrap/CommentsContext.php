<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Robin Appelman <robin@icewind.nl>
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

require __DIR__ . '/../../vendor/autoload.php';

class CommentsContext implements \Behat\Behat\Context\Context {
	/** @var string */
	private $baseUrl;
	/** @var array */
	private $response;
	/** @var int */
	private $commentId;
	/** @var int */
	private $fileId;

	/**
	 * @param string $baseUrl
	 */
	public function __construct($baseUrl) {
		$this->baseUrl = $baseUrl;

		// in case of ci deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = substr($testServerUrl, 0, -5);
		}
	}

	/** @AfterScenario */
	public function teardownScenario() {
		$client = new \GuzzleHttp\Client();
		try {
			$client->delete(
				$this->baseUrl . '/remote.php/webdav/myFileToComment.txt',
				[
					'auth' => [
						'user0',
						'123456',
					],
					'headers' => [
						'Content-Type' => 'application/json',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$e->getResponse();
		}
	}

	/**
	 * @param string $path
	 * @return int
	 */
	private function getFileIdForPath($path) {
		$url = $this->baseUrl . '/remote.php/webdav/' . $path;
		$context = stream_context_create([
			'http' => [
				'method' => 'PROPFIND',
				'header' => "Authorization: Basic dXNlcjA6MTIzNDU2\r\nContent-Type: application/x-www-form-urlencoded",
				'content' => '<?xml version="1.0"?>
<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:prop>
    <oc:fileid />
  </d:prop>
</d:propfind>'
			]
		]);

		$response = file_get_contents($url, false, $context);
		preg_match_all('/\<oc:fileid\>(.*)\<\/oc:fileid\>/', $response, $matches);
		return (int)$matches[1][0];
	}

	/**
	 * @When :user posts a comment with content :content on the file named :fileName it should return :statusCode
	 * @param string $user
	 * @param string $content
	 * @param string $fileName
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function postsACommentWithContentOnTheFileNamedItShouldReturn($user, $content, $fileName, $statusCode) {
		$fileId = $this->getFileIdForPath($fileName);
		$this->fileId = (int)$fileId;
		$url = $this->baseUrl . '/remote.php/dav/comments/files/' . $fileId . '/';

		$client = new \GuzzleHttp\Client();
		try {
			$res = $client->post(
				$url,
				[
					'body' => '{"actorId":"user0","actorDisplayName":"user0","actorType":"users","verb":"comment","message":"' . $content . '","creationDateTime":"Thu, 18 Feb 2016 17:04:18 GMT","objectType":"files"}',
					'auth' => [
						$user,
						'123456',
					],
					'headers' => [
						'Content-Type' => 'application/json',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$res = $e->getResponse();
		}

		if ($res->getStatusCode() !== (int)$statusCode) {
			throw new \Exception("Response status code was not $statusCode (" . $res->getStatusCode() . ")");
		}
	}


	/**
	 * @Then As :user load all the comments of the file named :fileName it should return :statusCode
	 * @param string $user
	 * @param string $fileName
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function asLoadloadAllTheCommentsOfTheFileNamedItShouldReturn($user, $fileName, $statusCode) {
		$fileId = $this->getFileIdForPath($fileName);
		$url = $this->baseUrl . '/remote.php/dav/comments/files/' . $fileId . '/';

		try {
			$client = new \GuzzleHttp\Client();
			$res = $client->request(
				'REPORT',
				$url,
				[
					'body' => '<?xml version="1.0" encoding="utf-8" ?>
<oc:filter-comments xmlns:oc="http://owncloud.org/ns">
    <oc:limit>200</oc:limit>
    <oc:offset>0</oc:offset>
</oc:filter-comments>
',
					'auth' => [
						$user,
						'123456',
					],
					'headers' => [
						'Content-Type' => 'application/json',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$res = $e->getResponse();
		}

		if ($res->getStatusCode() !== (int)$statusCode) {
			throw new \Exception("Response status code was not $statusCode (" . $res->getStatusCode() . ")");
		}

		if ($res->getStatusCode() === 207) {
			$service = new Sabre\Xml\Service();
			$this->response = $service->parse($res->getBody()->getContents());
			$this->commentId = (int)$this->response[0]['value'][2]['value'][0]['value'][0]['value'];
		}
	}

	/**
	 * @Given As :user sending :verb to :url with
	 * @param string $user
	 * @param string $verb
	 * @param string $url
	 * @param \Behat\Gherkin\Node\TableNode $body
	 * @throws \Exception
	 */
	public function asUserSendingToWith($user, $verb, $url, \Behat\Gherkin\Node\TableNode $body) {
		$client = new \GuzzleHttp\Client();
		$options = [];
		$options['auth'] = [$user, '123456'];
		$fd = $body->getRowsHash();
		$options['form_params'] = $fd;
		$options['headers'] = [
			'OCS-APIREQUEST' => 'true',
		];
		$client->request($verb, $this->baseUrl . '/ocs/v1.php/' . $url, $options);
	}

	/**
	 * @Then As :user delete the created comment it should return :statusCode
	 * @param string $user
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function asDeleteTheCreatedCommentItShouldReturn($user, $statusCode) {
		$url = $this->baseUrl . '/remote.php/dav/comments/files/' . $this->fileId . '/' . $this->commentId;

		$client = new \GuzzleHttp\Client();
		try {
			$res = $client->delete(
				$url,
				[
					'auth' => [
						$user,
						'123456',
					],
					'headers' => [
						'Content-Type' => 'application/json',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$res = $e->getResponse();
		}

		if ($res->getStatusCode() !== (int)$statusCode) {
			throw new \Exception("Response status code was not $statusCode (" . $res->getStatusCode() . ")");
		}
	}

	/**
	 * @Then the response should contain a property :key with value :value
	 * @param string $key
	 * @param string $value
	 * @throws \Exception
	 */
	public function theResponseShouldContainAPropertyWithValue($key, $value) {
		$keys = $this->response[0]['value'][2]['value'][0]['value'];
		$found = false;
		foreach ($keys as $singleKey) {
			if ($singleKey['name'] === '{http://owncloud.org/ns}' . substr($key, 3)) {
				if ($singleKey['value'] === $value) {
					$found = true;
				}
			}
		}
		if ($found === false) {
			throw new \Exception("Cannot find property $key with $value");
		}
	}

	/**
	 * @Then the response should contain only :number comments
	 * @param int $number
	 * @throws \Exception
	 */
	public function theResponseShouldContainOnlyComments($number) {
		$count = 0;
		if ($this->response !== null) {
			$count = count($this->response);
		}
		if ($count !== (int)$number) {
			throw new \Exception("Found more comments than $number (" . $count . ")");
		}
	}

	/**
	 * @Then As :user edit the last created comment and set text to :text it should return :statusCode
	 * @param string $user
	 * @param string $text
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function asEditTheLastCreatedCommentAndSetTextToItShouldReturn($user, $text, $statusCode) {
		$client = new \GuzzleHttp\Client();
		$options = [];
		$options['auth'] = [$user, '123456'];
		$options['body'] = '<?xml version="1.0"?>
<d:propertyupdate  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:set>
   <d:prop>
      <oc:message>' . $text . '</oc:message>
    </d:prop>
  </d:set>
</d:propertyupdate>';
		try {
			$res = $client->request('PROPPATCH', $this->baseUrl . '/remote.php/dav/comments/files/' . $this->fileId . '/' . $this->commentId, $options);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$res = $e->getResponse();
		}

		if ($res->getStatusCode() !== (int)$statusCode) {
			throw new \Exception("Response status code was not $statusCode (" . $res->getStatusCode() . ")");
		}
	}


}
