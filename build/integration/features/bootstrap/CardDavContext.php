<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Phil Davis <phil.davis@inf.org>
 * @author Robin Appelman <robin@icewind.nl>
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

use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;

class CardDavContext implements \Behat\Behat\Context\Context {
	/** @var string  */
	private $baseUrl;
	/** @var Client */
	private $client;
	/** @var ResponseInterface */
	private $response;
	/** @var string */
	private $responseXml = '';

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
		$this->responseXml = '';
	}


	/** @AfterScenario */
	public function afterScenario() {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/admin/MyAddressbook';
		try {
			$this->client->delete(
				$davUrl,
				[
					'auth' => [
						'admin',
						'admin',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {}
	}

	/**
	 * @When :user requests addressbook :addressBook with statuscode :statusCode on the endpoint :endpoint
	 * @param string $user
	 * @param string $addressBook
	 * @param int $statusCode
	 * @param string $endpoint
	 * @throws \Exception
	 */
	public function requestsAddressbookWithStatuscodeOnTheEndpoint($user, $addressBook, $statusCode, $endpoint) {
		$davUrl = $this->baseUrl . $endpoint . $addressBook;

		$password = ($user === 'admin') ? 'admin' : '123456';
		try {
			$this->response = $this->client->request(
				'PROPFIND',
				$davUrl,
				[
					'auth' => [
						$user,
						$password,
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}

		if((int)$statusCode !== $this->response->getStatusCode()) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					(int)$statusCode,
					$this->response->getStatusCode()
				)
			);
		}

		$body = $this->response->getBody()->getContents();
		if(substr($body, 0, 1) === '<') {
			$reader = new Sabre\Xml\Reader();
			$reader->xml($body);
			$this->responseXml = $reader->parse();
		}
	}

	/**
	 * @Given :user creates an addressbook named :addressBook with statuscode :statusCode
	 * @param string $user
	 * @param string $addressBook
	 * @param int $statusCode
	 * @throws \Exception
	 */
	public function createsAnAddressbookNamedWithStatuscode($user, $addressBook, $statusCode) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$user.'/'.$addressBook;
		$password = ($user === 'admin') ? 'admin' : '123456';

		$this->response = $this->client->request(
			'MKCOL',
			$davUrl,
			[
				'body' => '<d:mkcol xmlns:card="urn:ietf:params:xml:ns:carddav"
              xmlns:d="DAV:">
    <d:set>
      <d:prop>
        <d:resourcetype>
            <d:collection />,<card:addressbook />
          </d:resourcetype>,<d:displayname>'.$addressBook.'</d:displayname>
      </d:prop>
    </d:set>
  </d:mkcol>',
				'auth' => [
					$user,
					$password,
				],
				'headers' => [
					'Content-Type' => 'application/xml;charset=UTF-8',
				],
			]
		);

		if($this->response->getStatusCode() !== (int)$statusCode) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					(int)$statusCode,
					$this->response->getStatusCode()
				)
			);
		}
	}

	/**
	 * @When The CardDAV exception is :message
	 * @param string $message
	 * @throws \Exception
	 */
	public function theCarddavExceptionIs($message) {
		$result = $this->responseXml['value'][0]['value'];

		if($message !== $result) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					$message,
					$result
				)
			);
		}
	}

	/**
	 * @When The CardDAV error message is :arg1
	 * @param string $message
	 * @throws \Exception
	 */
	public function theCarddavErrorMessageIs($message) {
		$result = $this->responseXml['value'][1]['value'];

		if($message !== $result) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					$message,
					$result
				)
			);
		}
	}

	/**
	 * @Given :user uploads the contact :fileName to the addressbook :addressbook
	 */
	public function uploadsTheContactToTheAddressbook($user, $fileName, $addressBook) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$user.'/'.$addressBook . '/' . $fileName;
		$password = ($user === 'admin') ? 'admin' : '123456';

		$this->response = $this->client->request(
			'PUT',
			$davUrl,
			[
				'body' => file_get_contents(__DIR__ . '/../../data/' . $fileName),
				'auth' => [
					$user,
					$password,
				],
				'headers' => [
					'Content-Type' => 'application/xml;charset=UTF-8',
				],
			]
		);

		if($this->response->getStatusCode() !== 201) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					201,
					$this->response->getStatusCode()
				)
			);
		}
	}

	/**
	 * @When Exporting the picture of contact :fileName from addressbook :addressBook as user :user
	 */
	public function whenExportingThePictureOfContactFromAddressbookAsUser($fileName, $addressBook, $user) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$user.'/'.$addressBook . '/' . $fileName . '?photo=true';
		$password = ($user === 'admin') ? 'admin' : '123456';

		try {
			$this->response = $this->client->request(
				'GET',
				$davUrl,
				[
					'auth' => [
						$user,
						$password,
					],
					'headers' => [
						'Content-Type' => 'application/xml;charset=UTF-8',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When Downloading the contact :fileName from addressbook :addressBook as user :user
	 */
	public function whenDownloadingTheContactFromAddressbookAsUser($fileName, $addressBook, $user) {
		$davUrl = $this->baseUrl . '/remote.php/dav/addressbooks/users/'.$user.'/'.$addressBook . '/' . $fileName;
		$password = ($user === 'admin') ? 'admin' : '123456';

		try {
			$this->response = $this->client->request(
				'GET',
				$davUrl,
				[
					'auth' => [
						$user,
						$password,
					],
					'headers' => [
						'Content-Type' => 'application/xml;charset=UTF-8',
					],
				]
			);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
				$this->response = $e->getResponse();
		}
	}

	/**
	 * @Then The following HTTP headers should be set
	 * @param \Behat\Gherkin\Node\TableNode $table
	 * @throws \Exception
	 */
	public function theFollowingHttpHeadersShouldBeSet(\Behat\Gherkin\Node\TableNode $table) {
		foreach($table->getTable() as $header) {
			$headerName = $header[0];
			$expectedHeaderValue = $header[1];
			$returnedHeader = $this->response->getHeader($headerName)[0];
			if($returnedHeader !== $expectedHeaderValue) {
				throw new \Exception(
					sprintf(
						"Expected value '%s' for header '%s', got '%s'",
						$expectedHeaderValue,
						$headerName,
						$returnedHeader
					)
				);
			}
		}
	}
}
