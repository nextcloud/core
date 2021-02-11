<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Thomas Citharel <nextcloud@tcit.fr>
 * @copyright Copyright (c) 2020, leith abdulla (<online-nextcloud@eleith.com>)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author eleith <online+github@eleith.com>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CalDAV\WebcalCaching;

use Exception;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OCA\DAV\CalDAV\CalDavBackend;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\LocalServerException;
use OCP\IConfig;
use OCP\ILogger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Href;
use Sabre\VObject\Component;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Recur\NoInstancesException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;
use Sabre\VObject\Splitter\ICalendar;
use Sabre\VObject\UUIDUtil;
use function count;

class RefreshWebcalService {

	/** @var CalDavBackend */
	private $calDavBackend;

	/** @var IClientService */
	private $clientService;

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	public const REFRESH_RATE = '{http://apple.com/ns/ical/}refreshrate';
	public const STRIP_ALARMS = '{http://calendarserver.org/ns/}subscribed-strip-alarms';
	public const STRIP_ATTACHMENTS = '{http://calendarserver.org/ns/}subscribed-strip-attachments';
	public const STRIP_TODOS = '{http://calendarserver.org/ns/}subscribed-strip-todos';

	/**
	 * RefreshWebcalJob constructor.
	 *
	 * @param CalDavBackend $calDavBackend
	 * @param IClientService $clientService
	 * @param IConfig $config
	 * @param ILogger $logger
	 */
	public function __construct(CalDavBackend $calDavBackend, IClientService $clientService, IConfig $config, ILogger $logger) {
		$this->calDavBackend = $calDavBackend;
		$this->clientService = $clientService;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * @param string $principalUri
	 * @param string $uri
	 */
	public function refreshSubscription(string $principalUri, string $uri) {
		$subscription = $this->getSubscription($principalUri, $uri);
		$mutations = [];
		if (!$subscription) {
			return;
		}

		$webcalData = $this->queryWebcalFeed($subscription, $mutations);
		if (!$webcalData) {
			return;
		}

		$stripTodos = ($subscription[self::STRIP_TODOS] ?? 1) === 1;
		$stripAlarms = ($subscription[self::STRIP_ALARMS] ?? 1) === 1;
		$stripAttachments = ($subscription[self::STRIP_ATTACHMENTS] ?? 1) === 1;

		try {
			$splitter = new ICalendar($webcalData, Reader::OPTION_FORGIVING);

			// we wait with deleting all outdated events till we parsed the new ones
			// in case the new calendar is broken and `new ICalendar` throws a ParseException
			// the user will still see the old data
			$this->calDavBackend->purgeAllCachedEventsForSubscription($subscription['id']);

			while ($vObject = $splitter->getNext()) {
				/** @var Component $vObject */
				$compName = null;

				foreach ($vObject->getComponents() as $component) {
					if ($component->name === 'VTIMEZONE') {
						continue;
					}

					$compName = $component->name;

					if ($stripAlarms) {
						unset($component->{'VALARM'});
					}
					if ($stripAttachments) {
						unset($component->{'ATTACH'});
					}
				}

				if ($stripTodos && $compName === 'VTODO') {
					continue;
				}

				$uri = $this->getRandomCalendarObjectUri();
				$calendarData = $vObject->serialize();
				try {
					$this->calDavBackend->createCalendarObject($subscription['id'], $uri, $calendarData, CalDavBackend::CALENDAR_TYPE_SUBSCRIPTION);
				} catch (NoInstancesException | BadRequest $ex) {
					$this->logger->logException($ex);
				}
			}

			$newRefreshRate = $this->checkWebcalDataForRefreshRate($subscription, $webcalData);
			if ($newRefreshRate) {
				$mutations[self::REFRESH_RATE] = $newRefreshRate;
			}

			$this->updateSubscription($subscription, $mutations);
		} catch (ParseException $ex) {
			$subscriptionId = $subscription['id'];

			$this->logger->logException($ex);
			$this->logger->warning("Subscription $subscriptionId could not be refreshed due to a parsing error");
		}
	}

	/**
	 * loads subscription from backend
	 *
	 * @param string $principalUri
	 * @param string $uri
	 * @return array|null
	 */
	public function getSubscription(string $principalUri, string $uri) {
		$subscriptions = array_values(array_filter(
			$this->calDavBackend->getSubscriptionsForUser($principalUri),
			function ($sub) use ($uri) {
				return $sub['uri'] === $uri;
			}
		));

		if (count($subscriptions) === 0) {
			return null;
		}

		return $subscriptions[0];
	}

	/**
	 * gets webcal feed from remote server
	 *
	 * @param array $subscription
	 * @param array &$mutations
	 * @return null|string
	 */
	private function queryWebcalFeed(array $subscription, array &$mutations) {
		$client = $this->clientService->newClient();

		$didBreak301Chain = false;
		$latestLocation = null;

		$handlerStack = HandlerStack::create();
		$handlerStack->push(Middleware::mapRequest(function (RequestInterface $request) {
			return $request
				->withHeader('Accept', 'text/calendar, application/calendar+json, application/calendar+xml')
				->withHeader('User-Agent', 'Nextcloud Webcal Crawler');
		}));
		$handlerStack->push(Middleware::mapResponse(function (ResponseInterface $response) use (&$didBreak301Chain, &$latestLocation) {
			if (!$didBreak301Chain) {
				if ($response->getStatusCode() !== 301) {
					$didBreak301Chain = true;
				} else {
					$latestLocation = $response->getHeader('Location');
				}
			}
			return $response;
		}));

		$allowLocalAccess = $this->config->getAppValue('dav', 'webcalAllowLocalAccess', 'no');
		$subscriptionId = $subscription['id'];
		$url = $this->cleanURL($subscription['source']);
		if ($url === null) {
			return null;
		}

		try {
			$params = [
				'allow_redirects' => [
					'redirects' => 10
				],
				'handler' => $handlerStack,
				'nextcloud' => [
					'allow_local_address' => $allowLocalAccess === 'yes',
				]
			];

			$user = parse_url($subscription['source'], PHP_URL_USER);
			$pass = parse_url($subscription['source'], PHP_URL_PASS);
			if ($user !== null && $pass !== null) {
				$params['auth'] = [$user, $pass];
			}

			$response = $client->get($url, $params);
			$body = $response->getBody();

			if ($latestLocation) {
				$mutations['{http://calendarserver.org/ns/}source'] = new Href($latestLocation);
			}

			$contentType = $response->getHeader('Content-Type');
			$contentType = explode(';', $contentType, 2)[0];
			switch ($contentType) {
				case 'application/calendar+json':
					try {
						$jCalendar = Reader::readJson($body, Reader::OPTION_FORGIVING);
					} catch (Exception $ex) {
						// In case of a parsing error return null
						$this->logger->debug("Subscription $subscriptionId could not be parsed");
						return null;
					}
					return $jCalendar->serialize();

				case 'application/calendar+xml':
					try {
						$xCalendar = Reader::readXML($body);
					} catch (Exception $ex) {
						// In case of a parsing error return null
						$this->logger->debug("Subscription $subscriptionId could not be parsed");
						return null;
					}
					return $xCalendar->serialize();

				case 'text/calendar':
				default:
					try {
						$vCalendar = Reader::read($body);
					} catch (Exception $ex) {
						// In case of a parsing error return null
						$this->logger->debug("Subscription $subscriptionId could not be parsed");
						return null;
					}
					return $vCalendar->serialize();
			}
		} catch (LocalServerException $ex) {
			$this->logger->logException($ex, [
				'message' => "Subscription $subscriptionId was not refreshed because it violates local access rules",
				'level' => ILogger::WARN,
			]);

			return null;
		} catch (Exception $ex) {
			$this->logger->logException($ex, [
				'message' => "Subscription $subscriptionId could not be refreshed due to a network error",
				'level' => ILogger::WARN,
			]);

			return null;
		}
	}

	/**
	 * check if:
	 *  - current subscription stores a refreshrate
	 *  - the webcal feed suggests a refreshrate
	 *  - return suggested refreshrate if user didn't set a custom one
	 *
	 * @param array $subscription
	 * @param string $webcalData
	 * @return string|null
	 */
	private function checkWebcalDataForRefreshRate($subscription, $webcalData) {
		// if there is no refreshrate stored in the database, check the webcal feed
		// whether it suggests any refresh rate and store that in the database
		if (isset($subscription[self::REFRESH_RATE]) && $subscription[self::REFRESH_RATE] !== null) {
			return null;
		}

		/** @var Component\VCalendar $vCalendar */
		$vCalendar = Reader::read($webcalData);

		$newRefreshRate = null;
		if (isset($vCalendar->{'X-PUBLISHED-TTL'})) {
			$newRefreshRate = $vCalendar->{'X-PUBLISHED-TTL'}->getValue();
		}
		if (isset($vCalendar->{'REFRESH-INTERVAL'})) {
			$newRefreshRate = $vCalendar->{'REFRESH-INTERVAL'}->getValue();
		}

		if (!$newRefreshRate) {
			return null;
		}

		// check if new refresh rate is even valid
		try {
			DateTimeParser::parseDuration($newRefreshRate);
		} catch (InvalidDataException $ex) {
			return null;
		}

		return $newRefreshRate;
	}

	/**
	 * update subscription stored in database
	 * used to set:
	 *  - refreshrate
	 *  - source
	 *
	 * @param array $subscription
	 * @param array $mutations
	 */
	private function updateSubscription(array $subscription, array $mutations) {
		if (empty($mutations)) {
			return;
		}

		$propPatch = new PropPatch($mutations);
		$this->calDavBackend->updateSubscription($subscription['id'], $propPatch);
		$propPatch->commit();
	}

	/**
	 * This method will strip authentication information and replace the
	 * 'webcal' or 'webcals' protocol scheme
	 *
	 * @param string $url
	 * @return string|null
	 */
	private function cleanURL(string $url) {
		$parsed = parse_url($url);
		if ($parsed === false) {
			return null;
		}

		if (isset($parsed['scheme']) && $parsed['scheme'] === 'http') {
			$scheme = 'http';
		} else {
			$scheme = 'https';
		}

		$host = $parsed['host'] ?? '';
		$port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
		$path = $parsed['path'] ?? '';
		$query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
		$fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

		$cleanURL = "$scheme://$host$port$path$query$fragment";
		// parse_url is giving some weird results if no url and no :// is given,
		// so let's test the url again
		$parsedClean = parse_url($cleanURL);
		if ($parsedClean === false || !isset($parsedClean['host'])) {
			return null;
		}

		return $cleanURL;
	}

	/**
	 * Returns a random uri for a calendar-object
	 *
	 * @return string
	 */
	public function getRandomCalendarObjectUri():string {
		return UUIDUtil::getUUID() . '.ics';
	}
}
