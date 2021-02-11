<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @author Adrian Brzezinski <adrian.brzezinski@eo.pl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julien Lutran <julien.lutran@corp.ovh.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Volker <skydiablo@gmx.net>
 * @author William Pain <pain.william@gmail.com>
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

namespace OC\Files\ObjectStore;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use OCP\Files\StorageAuthException;
use OCP\Files\StorageNotAvailableException;
use OCP\ICache;
use OCP\ILogger;
use OpenStack\Common\Auth\Token;
use OpenStack\Common\Error\BadResponseError;
use OpenStack\Common\Transport\Utils as TransportUtils;
use OpenStack\Identity\v2\Models\Catalog;
use OpenStack\Identity\v2\Service as IdentityV2Service;
use OpenStack\Identity\v3\Service as IdentityV3Service;
use OpenStack\ObjectStore\v1\Models\Container;
use OpenStack\OpenStack;
use Psr\Http\Message\RequestInterface;

class SwiftFactory {
	private $cache;
	private $params;
	/** @var Container|null */
	private $container = null;
	private $logger;

	public const DEFAULT_OPTIONS = [
		'autocreate' => false,
		'urlType' => 'publicURL',
		'catalogName' => 'swift',
		'catalogType' => 'object-store'
	];

	public function __construct(ICache $cache, array $params, ILogger $logger) {
		$this->cache = $cache;
		$this->params = $params;
		$this->logger = $logger;
	}

	/**
	 * Gets currently cached token id
	 *
	 * @return string
	 * @throws StorageAuthException
	 */
	public function getCachedTokenId() {
		if (!isset($this->params['cachedToken'])) {
			throw new StorageAuthException('Unauthenticated ObjectStore connection');
		}

		// Is it V2 token?
		if (isset($this->params['cachedToken']['token'])) {
			return $this->params['cachedToken']['token']['id'];
		}

		return $this->params['cachedToken']['id'];
	}

	private function getCachedToken(string $cacheKey) {
		$cachedTokenString = $this->cache->get($cacheKey . '/token');
		if ($cachedTokenString) {
			return json_decode($cachedTokenString, true);
		} else {
			return null;
		}
	}

	private function cacheToken(Token $token, string $serviceUrl, string $cacheKey) {
		if ($token instanceof \OpenStack\Identity\v3\Models\Token) {
			// for v3 the catalog is cached as part of the token, so no need to cache $serviceUrl separately
			$value = $token->export();
		} else {
			/** @var \OpenStack\Identity\v2\Models\Token $token */
			$value = [
				'serviceUrl' => $serviceUrl,
				'token' => [
					'issued_at' => $token->issuedAt->format('c'),
					'expires' => $token->expires->format('c'),
					'id' => $token->id,
					'tenant' => $token->tenant
				]
			];
		}

		$this->params['cachedToken'] = $value;
		$this->cache->set($cacheKey . '/token', json_encode($value));
	}

	/**
	 * @return OpenStack
	 * @throws StorageAuthException
	 */
	private function getClient() {
		if (isset($this->params['bucket'])) {
			$this->params['container'] = $this->params['bucket'];
		}
		if (!isset($this->params['container'])) {
			$this->params['container'] = 'nextcloud';
		}
		if (isset($this->params['user']) && is_array($this->params['user'])) {
			$userName = $this->params['user']['name'];
		} else {
			if (!isset($this->params['username']) && isset($this->params['user'])) {
				$this->params['username'] = $this->params['user'];
			}
			$userName = $this->params['username'];
		}
		if (!isset($this->params['tenantName']) && isset($this->params['tenant'])) {
			$this->params['tenantName'] = $this->params['tenant'];
		}
		if (isset($this->params['domain'])) {
			$this->params['scope']['project']['name'] = $this->params['tenant'];
			$this->params['scope']['project']['domain']['name'] = $this->params['domain'];
		}
		$this->params = array_merge(self::DEFAULT_OPTIONS, $this->params);

		$cacheKey = $userName . '@' . $this->params['url'] . '/' . $this->params['container'];
		$token = $this->getCachedToken($cacheKey);
		$this->params['cachedToken'] = $token;

		$httpClient = new Client([
			'base_uri' => TransportUtils::normalizeUrl($this->params['url']),
			'handler' => HandlerStack::create()
		]);

		if (isset($this->params['user']) && is_array($this->params['user']) && isset($this->params['user']['name'])) {
			if (!isset($this->params['scope'])) {
				throw new StorageAuthException('Scope has to be defined for V3 requests');
			}

			return $this->auth(IdentityV3Service::factory($httpClient), $cacheKey);
		} else {
			return $this->auth(SwiftV2CachingAuthService::factory($httpClient), $cacheKey);
		}
	}

	/**
	 * @param IdentityV2Service|IdentityV3Service $authService
	 * @param string $cacheKey
	 * @return OpenStack
	 * @throws StorageAuthException
	 */
	private function auth($authService, string $cacheKey) {
		$this->params['identityService'] = $authService;
		$this->params['authUrl'] = $this->params['url'];

		$cachedToken = $this->params['cachedToken'];
		$hasValidCachedToken = false;
		if (\is_array($cachedToken)) {
			if ($authService instanceof IdentityV3Service) {
				$token = $authService->generateTokenFromCache($cachedToken);
				if (\is_null($token->catalog)) {
					$this->logger->warning('Invalid cached token for swift, no catalog set: ' . json_encode($cachedToken));
				} elseif ($token->hasExpired()) {
					$this->logger->debug('Cached token for swift expired');
				} else {
					$hasValidCachedToken = true;
				}
			} else {
				try {
					/** @var \OpenStack\Identity\v2\Models\Token $token */
					$token = $authService->model(\OpenStack\Identity\v2\Models\Token::class, $cachedToken['token']);
					$now = new \DateTimeImmutable("now");
					if ($token->expires > $now) {
						$hasValidCachedToken = true;
						$this->params['v2cachedToken'] = $token;
						$this->params['v2serviceUrl'] = $cachedToken['serviceUrl'];
					} else {
						$this->logger->debug('Cached token for swift expired');
					}
				} catch (\Exception $e) {
					$this->logger->logException($e);
				}
			}
		}

		if (!$hasValidCachedToken) {
			unset($this->params['cachedToken']);
			try {
				list($token, $serviceUrl) = $authService->authenticate($this->params);
				$this->cacheToken($token, $serviceUrl, $cacheKey);
			} catch (ConnectException $e) {
				throw new StorageAuthException('Failed to connect to keystone, verify the keystone url', $e);
			} catch (ClientException $e) {
				$statusCode = $e->getResponse()->getStatusCode();
				if ($statusCode === 404) {
					throw new StorageAuthException('Keystone not found, verify the keystone url', $e);
				} elseif ($statusCode === 412) {
					throw new StorageAuthException('Precondition failed, verify the keystone url', $e);
				} elseif ($statusCode === 401) {
					throw new StorageAuthException('Authentication failed, verify the username, password and possibly tenant', $e);
				} else {
					throw new StorageAuthException('Unknown error', $e);
				}
			} catch (RequestException $e) {
				throw new StorageAuthException('Connection reset while connecting to keystone, verify the keystone url', $e);
			}
		}


		$client = new OpenStack($this->params);

		return $client;
	}

	/**
	 * @return \OpenStack\ObjectStore\v1\Models\Container
	 * @throws StorageAuthException
	 * @throws StorageNotAvailableException
	 */
	public function getContainer() {
		if (is_null($this->container)) {
			$this->container = $this->createContainer();
		}

		return $this->container;
	}

	/**
	 * @return \OpenStack\ObjectStore\v1\Models\Container
	 * @throws StorageAuthException
	 * @throws StorageNotAvailableException
	 */
	private function createContainer() {
		$client = $this->getClient();
		$objectStoreService = $client->objectStoreV1();

		$autoCreate = isset($this->params['autocreate']) && $this->params['autocreate'] === true;
		try {
			$container = $objectStoreService->getContainer($this->params['container']);
			if ($autoCreate) {
				$container->getMetadata();
			}
			return $container;
		} catch (BadResponseError $ex) {
			// if the container does not exist and autocreate is true try to create the container on the fly
			if ($ex->getResponse()->getStatusCode() === 404 && $autoCreate) {
				return $objectStoreService->createContainer([
					'name' => $this->params['container']
				]);
			} else {
				throw new StorageNotAvailableException('Invalid response while trying to get container info', StorageNotAvailableException::STATUS_ERROR, $ex);
			}
		} catch (ConnectException $e) {
			/** @var RequestInterface $request */
			$request = $e->getRequest();
			$host = $request->getUri()->getHost() . ':' . $request->getUri()->getPort();
			\OC::$server->getLogger()->error("Can't connect to object storage server at $host");
			throw new StorageNotAvailableException("Can't connect to object storage server at $host", StorageNotAvailableException::STATUS_ERROR, $e);
		}
	}
}
