<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
use Psr\Http\Message\ResponseInterface;

class CalDavContext implements \Behat\Behat\Context\Context {
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
		$davUrl = $this->baseUrl. '/remote.php/dav/calendars/admin/MyCalendar';
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
	 * @When :user requests calendar :calendar on the endpoint :endpoint
	 * @param string $user
	 * @param string $calendar
	 * @param string $endpoint
	 */
	public function requestsCalendar($user, $calendar, $endpoint) {
		$davUrl = $this->baseUrl . $endpoint . $calendar;

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
	}

	/**
	 * @Then The CalDAV HTTP status code should be :code
	 * @param int $code
	 * @throws \Exception
	 */
	public function theCaldavHttpStatusCodeShouldBe($code) {
		if((int)$code !== $this->response->getStatusCode()) {
			throw new \Exception(
				sprintf(
					'Expected %s got %s',
					(int)$code,
					$this->response->getStatusCode()
				)
			);
		}

		$body = $this->response->getBody()->getContents();
		if($body && substr($body, 0, 1) === '<') {
			$reader = new Sabre\Xml\Reader();
			$reader->xml($body);
			$this->responseXml = $reader->parse();
		}
	}

	/**
	 * @Then The exception is :message
	 * @param string $message
	 * @throws \Exception
	 */
	public function theExceptionIs($message) {
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
	 * @Then The error message is :message
	 * @param string $message
	 * @throws \Exception
	 */
	public function theErrorMessageIs($message) {
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
	 * @Given :user creates a calendar named :name
	 * @param string $user
	 * @param string $name
	 */
	public function createsACalendarNamed($user, $name) {
		$davUrl = $this->baseUrl . '/remote.php/dav/calendars/'.$user.'/'.$name;
		$password = ($user === 'admin') ? 'admin' : '123456';

		$this->response = $this->client->request(
			'MKCALENDAR',
			$davUrl,
			[
				'body' => '<c:mkcalendar xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:d="DAV:" xmlns:a="http://apple.com/ns/ical/" xmlns:o="http://owncloud.org/ns"><d:set><d:prop><d:displayname>test</d:displayname><o:calendar-enabled>1</o:calendar-enabled><a:calendar-color>#21213D</a:calendar-color><c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set></d:prop></d:set></c:mkcalendar>',
				'auth' => [
					$user,
					$password,
				],
			]
		);
	}

	/**
	 * @Then :user publicly shares the calendar named :name
	 *
	 * @param string $user
	 * @param string $name
	 */
	public function publiclySharesTheCalendarNamed($user, $name) {
		$davUrl = $this->baseUrl . '/remote.php/dav/calendars/'.$user.'/'.$name;
		$password = ($user === 'admin') ? 'admin' : '123456';

		$this->response = $this->client->request(
			'POST',
			$davUrl,
			[
				'body' => '<cs:publish-calendar xmlns:cs="http://calendarserver.org/ns/"/>',
				'auth' => [
					$user,
					$password,
				],
				'headers' => [
					'Content-Type' => 'application/xml; charset=UTF-8',
				],
			]
		);
	}

	/**
	 * @Then There should be :amount calendars in the response body
	 *
	 * @param string $amount
	 */
	public function t($amount) {
		$jsonEncoded = json_encode($this->responseXml);
		$arrayElement = json_decode($jsonEncoded, true);
		$actual = count($arrayElement['value']) - 1;
		if($actual !== (int)$amount) {
			throw new InvalidArgumentException(
				sprintf(
					'Expected %s got %s',
					$amount,
					$actual
				)
			);
		}
	}
}
