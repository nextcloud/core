<?php
/**
 *
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Phil Davis <phil.davis@inf.org>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
require __DIR__ . '/../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

class ChecksumsContext implements \Behat\Behat\Context\Context {
	/** @var string  */
	private $baseUrl;
	/** @var Client */
	private $client;
	/** @var ResponseInterface */
	private $response;

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

	/** @BeforeScenario */
	public function setUpScenario() {
		$this->client = new Client();
	}

	/** @AfterScenario */
	public function tearDownScenario() {
	}


	/**
	 * @param string $userName
	 * @return string
	 */
	private function getPasswordForUser($userName) {
		if($userName === 'admin') {
			return 'admin';
		}
		return '123456';
	}

	/**
	 * @When user :user uploads file :source to :destination with checksum :checksum
	 * @param string $user
	 * @param string $source
	 * @param string $destination
	 * @param string $checksum
	 */
	public function userUploadsFileToWithChecksum($user, $source, $destination, $checksum)
	{
		$file = \GuzzleHttp\Psr7\stream_for(fopen($source, 'r'));
		try {
			$this->response = $this->client->put(
				$this->baseUrl . '/remote.php/webdav' . $destination,
				[
					'auth' => [
						$user,
						$this->getPasswordForUser($user)
					],
					'body' => $file,
					'headers' => [
						'OC-Checksum' => $checksum
					]
				]
			);
		} catch (\GuzzleHttp\Exception\ServerException $e) {
			// 4xx and 5xx responses cause an exception
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @Then The webdav response should have a status code :statusCode
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function theWebdavResponseShouldHaveAStatusCode($statusCode) {
		if((int)$statusCode !== $this->response->getStatusCode()) {
			throw new \Exception("Expected $statusCode, got ".$this->response->getStatusCode());
		}
	}

	/**
	 * @When user :user request the checksum of :path via propfind
	 * @param string $user
	 * @param string $path
	 */
	public function userRequestTheChecksumOfViaPropfind($user, $path)
	{
		$this->response = $this->client->request(
			'PROPFIND',
			$this->baseUrl . '/remote.php/webdav' . $path,
			[
				'body' => '<?xml version="1.0"?>
<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:prop>
    <oc:checksums />
  </d:prop>
</d:propfind>',
				'auth' => [
					$user,
					$this->getPasswordForUser($user),
				]
			]
		);
	}

	/**
	 * @Then The webdav checksum should match :checksum
	 * @param string $checksum
	 * @throws \Exception
	 */
	public function theWebdavChecksumShouldMatch($checksum)
	{
		$service = new Sabre\Xml\Service();
		$parsed = $service->parse($this->response->getBody()->getContents());

		/*
		 * Fetch the checksum array
		 * Maybe we want to do this a bit cleaner ;)
		 */
		$checksums = $parsed[0]['value'][1]['value'][0]['value'][0];

		if ($checksums['value'][0]['value'] !== $checksum) {
			throw new \Exception("Expected $checksum, got ".$checksums['value'][0]['value']);
		}
	}

	/**
	 * @When user :user downloads the file :path
	 * @param string $user
	 * @param string $path
	 */
	public function userDownloadsTheFile($user, $path)
	{
		$this->response = $this->client->get(
			$this->baseUrl . '/remote.php/webdav' . $path,
			[
				'auth' => [
					$user,
					$this->getPasswordForUser($user),
				]
			]
		);
	}

	/**
	 * @Then The header checksum should match :checksum
	 * @param string $checksum
	 * @throws \Exception
	 */
	public function theHeaderChecksumShouldMatch($checksum)
	{
		if ($this->response->getHeader('OC-Checksum')[0] !== $checksum) {
			throw new \Exception("Expected $checksum, got ".$this->response->getHeader('OC-Checksum')[0]);
		}
	}

	/**
	 * @Given User :user copied file :source to :destination
	 * @param string $user
	 * @param string $source
	 * @param string $destination
	 */
	public function userCopiedFileTo($user, $source, $destination)
	{
		$this->response = $this->client->request(
			'MOVE',
			$this->baseUrl . '/remote.php/webdav' . $source,
			[
				'auth' => [
					$user,
					$this->getPasswordForUser($user),
				],
				'headers' => [
					'Destination' => $this->baseUrl . '/remote.php/webdav' . $destination,
				],
			]
		);
	}

	/**
	 * @Then The webdav checksum should be empty
	 */
	public function theWebdavChecksumShouldBeEmpty()
	{
		$service = new Sabre\Xml\Service();
		$parsed = $service->parse($this->response->getBody()->getContents());

		/*
		 * Fetch the checksum array
		 * Maybe we want to do this a bit cleaner ;)
		 */
		$status = $parsed[0]['value'][1]['value'][1]['value'];

		if ($status !== 'HTTP/1.1 404 Not Found') {
			throw new \Exception("Expected 'HTTP/1.1 404 Not Found', got ".$status);
		}
	}

	/**
	 * @Then The OC-Checksum header should not be there
	 */
	public function theOcChecksumHeaderShouldNotBeThere()
	{
		if ($this->response->hasHeader('OC-Checksum')) {
			throw new \Exception("Expected no checksum header but got ".$this->response->getHeader('OC-Checksum')[0]);
		}
	}

	/**
	 * @Given user :user uploads chunk file :num of :total with :data to :destination with checksum :checksum
	 * @param string $user
	 * @param int $num
	 * @param int $total
	 * @param string $data
	 * @param string $destination
	 * @param string $checksum
	 */
	public function userUploadsChunkFileOfWithToWithChecksum($user, $num, $total, $data, $destination, $checksum)
	{
		$num -= 1;
		$this->response = $this->client->put(
			$this->baseUrl . '/remote.php/webdav' . $destination . '-chunking-42-'.$total.'-'.$num,
			[
				'auth' => [
					$user,
					$this->getPasswordForUser($user)
				],
				'body' => $data,
				'headers' => [
					'OC-Checksum' => $checksum,
					'OC-Chunked' => '1',
				]
			]
		);

	}
}
