<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author b108@volgograd "b108@volgograd"
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Juan Pablo Villafáñez <jvillafanez@solidgear.es>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Mitar <mitar.git@tnode.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Oliver Wegner <void1976@gmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
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
namespace OC\AppFramework\Http;

use OC\Security\CSRF\CsrfToken;
use OC\Security\CSRF\CsrfTokenManager;
use OC\Security\TrustedDomainHelper;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

/**
 * Class for accessing variables in the request.
 * This class provides an immutable object with request variables.
 *
 * @property mixed[] cookies
 * @property mixed[] env
 * @property mixed[] files
 * @property string method
 * @property mixed[] parameters
 * @property mixed[] server
 */
class Request implements \ArrayAccess, \Countable, IRequest {
	// Microsoft Edge User Agent from https://msdn.microsoft.com/en-us/library/hh869301(v=vs.85).aspx
	public const USER_AGENT_MS_EDGE = '/^Mozilla\/5\.0 \([^)]+\) AppleWebKit\/[0-9.]+ \(KHTML, like Gecko\) Chrome\/[0-9.]+ (Mobile Safari|Safari)\/[0-9.]+ Edge\/[0-9.]+$/';
	// Firefox User Agent from https://developer.mozilla.org/en-US/docs/Web/HTTP/Gecko_user_agent_string_reference
	public const USER_AGENT_FIREFOX = '/^Mozilla\/5\.0 \([^)]+\) Gecko\/[0-9.]+ Firefox\/[0-9.]+$/';
	// Chrome User Agent from https://developer.chrome.com/multidevice/user-agent
	public const USER_AGENT_CHROME = '/^Mozilla\/5\.0 \([^)]+\) AppleWebKit\/[0-9.]+ \(KHTML, like Gecko\)( Ubuntu Chromium\/[0-9.]+|) Chrome\/[0-9.]+ (Mobile Safari|Safari)\/[0-9.]+( (Vivaldi|Brave|OPR)\/[0-9.]+|)$/';
	// Safari User Agent from http://www.useragentstring.com/pages/Safari/
	public const USER_AGENT_SAFARI = '/^Mozilla\/5\.0 \([^)]+\) AppleWebKit\/[0-9.]+ \(KHTML, like Gecko\) Version\/[0-9.]+ Safari\/[0-9.A-Z]+$/';
	// Android Chrome user agent: https://developers.google.com/chrome/mobile/docs/user-agent
	public const USER_AGENT_ANDROID_MOBILE_CHROME = '#Android.*Chrome/[.0-9]*#';
	public const USER_AGENT_FREEBOX = '#^Mozilla/5\.0$#';
	public const REGEX_LOCALHOST = '/^(127\.0\.0\.1|localhost|\[::1\])$/';

	protected $inputStream;
	protected $content;
	protected $items = [];
	protected $allowedKeys = [
		'get',
		'post',
		'files',
		'server',
		'env',
		'cookies',
		'urlParams',
		'parameters',
		'method',
		'requesttoken',
	];
	/** @var ISecureRandom */
	protected $secureRandom;
	/** @var IConfig */
	protected $config;
	/** @var string */
	protected $requestId = '';
	/** @var ICrypto */
	protected $crypto;
	/** @var CsrfTokenManager|null */
	protected $csrfTokenManager;

	/** @var bool */
	protected $contentDecoded = false;

	/**
	 * @param array $vars An associative array with the following optional values:
	 *        - array 'urlParams' the parameters which were matched from the URL
	 *        - array 'get' the $_GET array
	 *        - array|string 'post' the $_POST array or JSON string
	 *        - array 'files' the $_FILES array
	 *        - array 'server' the $_SERVER array
	 *        - array 'env' the $_ENV array
	 *        - array 'cookies' the $_COOKIE array
	 *        - string 'method' the request method (GET, POST etc)
	 *        - string|false 'requesttoken' the requesttoken or false when not available
	 * @param ISecureRandom $secureRandom
	 * @param IConfig $config
	 * @param CsrfTokenManager|null $csrfTokenManager
	 * @param string $stream
	 * @see https://www.php.net/manual/en/reserved.variables.php
	 */
	public function __construct(array $vars,
								ISecureRandom $secureRandom,
								IConfig $config,
								CsrfTokenManager $csrfTokenManager = null,
								string $stream = 'php://input') {
		$this->inputStream = $stream;
		$this->items['params'] = [];
		$this->secureRandom = $secureRandom;
		$this->config = $config;
		$this->csrfTokenManager = $csrfTokenManager;

		if (!array_key_exists('method', $vars)) {
			$vars['method'] = 'GET';
		}

		foreach ($this->allowedKeys as $name) {
			$this->items[$name] = isset($vars[$name])
				? $vars[$name]
				: [];
		}

		$this->items['parameters'] = array_merge(
			$this->items['get'],
			$this->items['post'],
			$this->items['urlParams'],
			$this->items['params']
		);
	}
	/**
	 * @param array $parameters
	 */
	public function setUrlParameters(array $parameters) {
		$this->items['urlParams'] = $parameters;
		$this->items['parameters'] = array_merge(
			$this->items['parameters'],
			$this->items['urlParams']
		);
	}

	/**
	 * Countable method
	 * @return int
	 */
	public function count(): int {
		return \count($this->items['parameters']);
	}

	/**
	 * ArrayAccess methods
	 *
	 * Gives access to the combined GET, POST and urlParams arrays
	 *
	 * Examples:
	 *
	 * $var = $request['myvar'];
	 *
	 * or
	 *
	 * if(!isset($request['myvar']) {
	 * 	// Do something
	 * }
	 *
	 * $request['myvar'] = 'something'; // This throws an exception.
	 *
	 * @param string $offset The key to lookup
	 * @return boolean
	 */
	public function offsetExists($offset): bool {
		return isset($this->items['parameters'][$offset]);
	}

	/**
	 * @see offsetExists
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset) {
		return isset($this->items['parameters'][$offset])
			? $this->items['parameters'][$offset]
			: null;
	}

	/**
	 * @see offsetExists
	 * @param string $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value) {
		throw new \RuntimeException('You cannot change the contents of the request object');
	}

	/**
	 * @see offsetExists
	 * @param string $offset
	 */
	public function offsetUnset($offset) {
		throw new \RuntimeException('You cannot change the contents of the request object');
	}

	/**
	 * Magic property accessors
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value) {
		throw new \RuntimeException('You cannot change the contents of the request object');
	}

	/**
	 * Access request variables by method and name.
	 * Examples:
	 *
	 * $request->post['myvar']; // Only look for POST variables
	 * $request->myvar; or $request->{'myvar'}; or $request->{$myvar}
	 * Looks in the combined GET, POST and urlParams array.
	 *
	 * If you access e.g. ->post but the current HTTP request method
	 * is GET a \LogicException will be thrown.
	 *
	 * @param string $name The key to look for.
	 * @throws \LogicException
	 * @return mixed|null
	 */
	public function __get($name) {
		switch ($name) {
			case 'put':
			case 'patch':
			case 'get':
			case 'post':
				if ($this->method !== strtoupper($name)) {
					throw new \LogicException(sprintf('%s cannot be accessed in a %s request.', $name, $this->method));
				}
				return $this->getContent();
			case 'files':
			case 'server':
			case 'env':
			case 'cookies':
			case 'urlParams':
			case 'method':
				return isset($this->items[$name])
					? $this->items[$name]
					: null;
			case 'parameters':
			case 'params':
				return $this->getContent();
			default:
				return isset($this[$name])
					? $this[$name]
					: null;
		}
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name) {
		if (\in_array($name, $this->allowedKeys, true)) {
			return true;
		}
		return isset($this->items['parameters'][$name]);
	}

	/**
	 * @param string $id
	 */
	public function __unset($id) {
		throw new \RuntimeException('You cannot change the contents of the request object');
	}

	/**
	 * Returns the value for a specific http header.
	 *
	 * This method returns an empty string if the header did not exist.
	 *
	 * @param string $name
	 * @return string
	 */
	public function getHeader(string $name): string {
		$name = strtoupper(str_replace('-', '_',$name));
		if (isset($this->server['HTTP_' . $name])) {
			return $this->server['HTTP_' . $name];
		}

		// There's a few headers that seem to end up in the top-level
		// server array.
		switch ($name) {
			case 'CONTENT_TYPE':
			case 'CONTENT_LENGTH':
			case 'REMOTE_ADDR':
				if (isset($this->server[$name])) {
					return $this->server[$name];
				}
				break;
		}

		return '';
	}

	/**
	 * Lets you access post and get parameters by the index
	 * In case of json requests the encoded json body is accessed
	 *
	 * @param string $key the key which you want to access in the URL Parameter
	 *                     placeholder, $_POST or $_GET array.
	 *                     The priority how they're returned is the following:
	 *                     1. URL parameters
	 *                     2. POST parameters
	 *                     3. GET parameters
	 * @param mixed $default If the key is not found, this value will be returned
	 * @return mixed the content of the array
	 */
	public function getParam(string $key, $default = null) {
		return isset($this->parameters[$key])
			? $this->parameters[$key]
			: $default;
	}

	/**
	 * Returns all params that were received, be it from the request
	 * (as GET or POST) or throuh the URL by the route
	 * @return array the array with all parameters
	 */
	public function getParams(): array {
		return is_array($this->parameters) ? $this->parameters : [];
	}

	/**
	 * Returns the method of the request
	 * @return string the method of the request (POST, GET, etc)
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Shortcut for accessing an uploaded file through the $_FILES array
	 * @param string $key the key that will be taken from the $_FILES array
	 * @return array the file in the $_FILES element
	 */
	public function getUploadedFile(string $key) {
		return isset($this->files[$key]) ? $this->files[$key] : null;
	}

	/**
	 * Shortcut for getting env variables
	 * @param string $key the key that will be taken from the $_ENV array
	 * @return array the value in the $_ENV element
	 */
	public function getEnv(string $key) {
		return isset($this->env[$key]) ? $this->env[$key] : null;
	}

	/**
	 * Shortcut for getting cookie variables
	 * @param string $key the key that will be taken from the $_COOKIE array
	 * @return string the value in the $_COOKIE element
	 */
	public function getCookie(string $key) {
		return isset($this->cookies[$key]) ? $this->cookies[$key] : null;
	}

	/**
	 * Returns the request body content.
	 *
	 * If the HTTP request method is PUT and the body
	 * not application/x-www-form-urlencoded or application/json a stream
	 * resource is returned, otherwise an array.
	 *
	 * @return array|string|resource The request body content or a resource to read the body stream.
	 *
	 * @throws \LogicException
	 */
	protected function getContent() {
		// If the content can't be parsed into an array then return a stream resource.
		if ($this->method === 'PUT'
			&& $this->getHeader('Content-Length') !== '0'
			&& $this->getHeader('Content-Length') !== ''
			&& strpos($this->getHeader('Content-Type'), 'application/x-www-form-urlencoded') === false
			&& strpos($this->getHeader('Content-Type'), 'application/json') === false
		) {
			if ($this->content === false) {
				throw new \LogicException(
					'"put" can only be accessed once if not '
					. 'application/x-www-form-urlencoded or application/json.'
				);
			}
			$this->content = false;
			return fopen($this->inputStream, 'rb');
		} else {
			$this->decodeContent();
			return $this->items['parameters'];
		}
	}

	/**
	 * Attempt to decode the content and populate parameters
	 */
	protected function decodeContent() {
		if ($this->contentDecoded) {
			return;
		}
		$params = [];

		// 'application/json' must be decoded manually.
		if (strpos($this->getHeader('Content-Type'), 'application/json') !== false) {
			$params = json_decode(file_get_contents($this->inputStream), true);
			if ($params !== null && \count($params) > 0) {
				$this->items['params'] = $params;
				if ($this->method === 'POST') {
					$this->items['post'] = $params;
				}
			}

			// Handle application/x-www-form-urlencoded for methods other than GET
		// or post correctly
		} elseif ($this->method !== 'GET'
				&& $this->method !== 'POST'
				&& strpos($this->getHeader('Content-Type'), 'application/x-www-form-urlencoded') !== false) {
			parse_str(file_get_contents($this->inputStream), $params);
			if (\is_array($params)) {
				$this->items['params'] = $params;
			}
		}

		if (\is_array($params)) {
			$this->items['parameters'] = array_merge($this->items['parameters'], $params);
		}
		$this->contentDecoded = true;
	}


	/**
	 * Checks if the CSRF check was correct
	 * @return bool true if CSRF check passed
	 */
	public function passesCSRFCheck(): bool {
		if ($this->csrfTokenManager === null) {
			return false;
		}

		if (!$this->passesStrictCookieCheck()) {
			return false;
		}

		if (isset($this->items['get']['requesttoken'])) {
			$token = $this->items['get']['requesttoken'];
		} elseif (isset($this->items['post']['requesttoken'])) {
			$token = $this->items['post']['requesttoken'];
		} elseif (isset($this->items['server']['HTTP_REQUESTTOKEN'])) {
			$token = $this->items['server']['HTTP_REQUESTTOKEN'];
		} else {
			//no token found.
			return false;
		}
		$token = new CsrfToken($token);

		return $this->csrfTokenManager->isTokenValid($token);
	}

	/**
	 * Whether the cookie checks are required
	 *
	 * @return bool
	 */
	private function cookieCheckRequired(): bool {
		if ($this->getHeader('OCS-APIREQUEST')) {
			return false;
		}
		if ($this->getCookie(session_name()) === null && $this->getCookie('nc_token') === null) {
			return false;
		}

		return true;
	}

	/**
	 * Wrapper around session_get_cookie_params
	 *
	 * @return array
	 */
	public function getCookieParams(): array {
		return session_get_cookie_params();
	}

	/**
	 * Appends the __Host- prefix to the cookie if applicable
	 *
	 * @param string $name
	 * @return string
	 */
	protected function getProtectedCookieName(string $name): string {
		$cookieParams = $this->getCookieParams();
		$prefix = '';
		if ($cookieParams['secure'] === true && $cookieParams['path'] === '/') {
			$prefix = '__Host-';
		}

		return $prefix.$name;
	}

	/**
	 * Checks if the strict cookie has been sent with the request if the request
	 * is including any cookies.
	 *
	 * @return bool
	 * @since 9.1.0
	 */
	public function passesStrictCookieCheck(): bool {
		if (!$this->cookieCheckRequired()) {
			return true;
		}

		$cookieName = $this->getProtectedCookieName('nc_sameSiteCookiestrict');
		if ($this->getCookie($cookieName) === 'true'
			&& $this->passesLaxCookieCheck()) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if the lax cookie has been sent with the request if the request
	 * is including any cookies.
	 *
	 * @return bool
	 * @since 9.1.0
	 */
	public function passesLaxCookieCheck(): bool {
		if (!$this->cookieCheckRequired()) {
			return true;
		}

		$cookieName = $this->getProtectedCookieName('nc_sameSiteCookielax');
		if ($this->getCookie($cookieName) === 'true') {
			return true;
		}
		return false;
	}


	/**
	 * Returns an ID for the request, value is not guaranteed to be unique and is mostly meant for logging
	 * If `mod_unique_id` is installed this value will be taken.
	 * @return string
	 */
	public function getId(): string {
		if (isset($this->server['UNIQUE_ID'])) {
			return $this->server['UNIQUE_ID'];
		}

		if (empty($this->requestId)) {
			$validChars = ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS;
			$this->requestId = $this->secureRandom->generate(20, $validChars);
		}

		return $this->requestId;
	}

	/**
	 * Checks if given $remoteAddress matches given $trustedProxy.
	 * If $trustedProxy is an IPv4 IP range given in CIDR notation, true will be returned if
	 * $remoteAddress is an IPv4 address within that IP range.
	 * Otherwise $remoteAddress will be compared to $trustedProxy literally and the result
	 * will be returned.
	 * @return boolean true if $remoteAddress matches $trustedProxy, false otherwise
	 */
	protected function matchesTrustedProxy($trustedProxy, $remoteAddress) {
		$cidrre = '/^([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\/([0-9]{1,2})$/';

		if (preg_match($cidrre, $trustedProxy, $match)) {
			$net = $match[1];
			$shiftbits = min(32, max(0, 32 - intval($match[2])));
			$netnum = ip2long($net) >> $shiftbits;
			$ipnum = ip2long($remoteAddress) >> $shiftbits;

			return $ipnum === $netnum;
		}

		return $trustedProxy === $remoteAddress;
	}

	/**
	 * Checks if given $remoteAddress matches any entry in the given array $trustedProxies.
	 * For details regarding what "match" means, refer to `matchesTrustedProxy`.
	 * @return boolean true if $remoteAddress matches any entry in $trustedProxies, false otherwise
	 */
	protected function isTrustedProxy($trustedProxies, $remoteAddress) {
		foreach ($trustedProxies as $tp) {
			if ($this->matchesTrustedProxy($tp, $remoteAddress)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the remote address, if the connection came from a trusted proxy
	 * and `forwarded_for_headers` has been configured then the IP address
	 * specified in this header will be returned instead.
	 * Do always use this instead of $_SERVER['REMOTE_ADDR']
	 * @return string IP address
	 */
	public function getRemoteAddress(): string {
		$remoteAddress = isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : '';
		$trustedProxies = $this->config->getSystemValue('trusted_proxies', []);

		if (\is_array($trustedProxies) && $this->isTrustedProxy($trustedProxies, $remoteAddress)) {
			$forwardedForHeaders = $this->config->getSystemValue('forwarded_for_headers', [
				'HTTP_X_FORWARDED_FOR'
				// only have one default, so we cannot ship an insecure product out of the box
			]);

			foreach ($forwardedForHeaders as $header) {
				if (isset($this->server[$header])) {
					foreach (explode(',', $this->server[$header]) as $IP) {
						$IP = trim($IP);

						// remove brackets from IPv6 addresses
						if (strpos($IP, '[') === 0 && substr($IP, -1) === ']') {
							$IP = substr($IP, 1, -1);
						}

						if (filter_var($IP, FILTER_VALIDATE_IP) !== false) {
							return $IP;
						}
					}
				}
			}
		}

		return $remoteAddress;
	}

	/**
	 * Check overwrite condition
	 * @param string $type
	 * @return bool
	 */
	private function isOverwriteCondition(string $type = ''): bool {
		$regex = '/' . $this->config->getSystemValue('overwritecondaddr', '')  . '/';
		$remoteAddr = isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : '';
		return $regex === '//' || preg_match($regex, $remoteAddr) === 1
		|| $type !== 'protocol';
	}

	/**
	 * Returns the server protocol. It respects one or more reverse proxies servers
	 * and load balancers
	 * @return string Server protocol (http or https)
	 */
	public function getServerProtocol(): string {
		if ($this->config->getSystemValue('overwriteprotocol') !== ''
			&& $this->isOverwriteCondition('protocol')) {
			return $this->config->getSystemValue('overwriteprotocol');
		}

		if ($this->fromTrustedProxy() && isset($this->server['HTTP_X_FORWARDED_PROTO'])) {
			if (strpos($this->server['HTTP_X_FORWARDED_PROTO'], ',') !== false) {
				$parts = explode(',', $this->server['HTTP_X_FORWARDED_PROTO']);
				$proto = strtolower(trim($parts[0]));
			} else {
				$proto = strtolower($this->server['HTTP_X_FORWARDED_PROTO']);
			}

			// Verify that the protocol is always HTTP or HTTPS
			// default to http if an invalid value is provided
			return $proto === 'https' ? 'https' : 'http';
		}

		if (isset($this->server['HTTPS'])
			&& $this->server['HTTPS'] !== null
			&& $this->server['HTTPS'] !== 'off'
			&& $this->server['HTTPS'] !== '') {
			return 'https';
		}

		return 'http';
	}

	/**
	 * Returns the used HTTP protocol.
	 *
	 * @return string HTTP protocol. HTTP/2, HTTP/1.1 or HTTP/1.0.
	 */
	public function getHttpProtocol(): string {
		$claimedProtocol = $this->server['SERVER_PROTOCOL'];

		if (\is_string($claimedProtocol)) {
			$claimedProtocol = strtoupper($claimedProtocol);
		}

		$validProtocols = [
			'HTTP/1.0',
			'HTTP/1.1',
			'HTTP/2',
		];

		if (\in_array($claimedProtocol, $validProtocols, true)) {
			return $claimedProtocol;
		}

		return 'HTTP/1.1';
	}

	/**
	 * Returns the request uri, even if the website uses one or more
	 * reverse proxies
	 * @return string
	 */
	public function getRequestUri(): string {
		$uri = isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '';
		if ($this->config->getSystemValue('overwritewebroot') !== '' && $this->isOverwriteCondition()) {
			$uri = $this->getScriptName() . substr($uri, \strlen($this->server['SCRIPT_NAME']));
		}
		return $uri;
	}

	/**
	 * Get raw PathInfo from request (not urldecoded)
	 * @throws \Exception
	 * @return string Path info
	 */
	public function getRawPathInfo(): string {
		$requestUri = isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '';
		// remove too many slashes - can be caused by reverse proxy configuration
		$requestUri = preg_replace('%/{2,}%', '/', $requestUri);

		// Remove the query string from REQUEST_URI
		if ($pos = strpos($requestUri, '?')) {
			$requestUri = substr($requestUri, 0, $pos);
		}

		$scriptName = $this->server['SCRIPT_NAME'];
		$pathInfo = $requestUri;

		// strip off the script name's dir and file name
		// FIXME: Sabre does not really belong here
		[$path, $name] = \Sabre\Uri\split($scriptName);
		if (!empty($path)) {
			if ($path === $pathInfo || strpos($pathInfo, $path.'/') === 0) {
				$pathInfo = substr($pathInfo, \strlen($path));
			} else {
				throw new \Exception("The requested uri($requestUri) cannot be processed by the script '$scriptName')");
			}
		}
		if ($name === null) {
			$name = '';
		}

		if (strpos($pathInfo, '/'.$name) === 0) {
			$pathInfo = substr($pathInfo, \strlen($name) + 1);
		}
		if ($name !== '' && strpos($pathInfo, $name) === 0) {
			$pathInfo = substr($pathInfo, \strlen($name));
		}
		if ($pathInfo === false || $pathInfo === '/') {
			return '';
		} else {
			return $pathInfo;
		}
	}

	/**
	 * Get PathInfo from request
	 * @throws \Exception
	 * @return string|false Path info or false when not found
	 */
	public function getPathInfo() {
		$pathInfo = $this->getRawPathInfo();
		// following is taken from \Sabre\HTTP\URLUtil::decodePathSegment
		$pathInfo = rawurldecode($pathInfo);
		$encoding = mb_detect_encoding($pathInfo, ['UTF-8', 'ISO-8859-1']);

		switch ($encoding) {
			case 'ISO-8859-1':
				$pathInfo = utf8_encode($pathInfo);
		}
		// end copy

		return $pathInfo;
	}

	/**
	 * Returns the script name, even if the website uses one or more
	 * reverse proxies
	 * @return string the script name
	 */
	public function getScriptName(): string {
		$name = $this->server['SCRIPT_NAME'];
		$overwriteWebRoot = $this->config->getSystemValue('overwritewebroot');
		if ($overwriteWebRoot !== '' && $this->isOverwriteCondition()) {
			// FIXME: This code is untestable due to __DIR__, also that hardcoded path is really dangerous
			$serverRoot = str_replace('\\', '/', substr(__DIR__, 0, -\strlen('lib/private/appframework/http/')));
			$suburi = str_replace('\\', '/', substr(realpath($this->server['SCRIPT_FILENAME']), \strlen($serverRoot)));
			$name = '/' . ltrim($overwriteWebRoot . $suburi, '/');
		}
		return $name;
	}

	/**
	 * Checks whether the user agent matches a given regex
	 * @param array $agent array of agent names
	 * @return bool true if at least one of the given agent matches, false otherwise
	 */
	public function isUserAgent(array $agent): bool {
		if (!isset($this->server['HTTP_USER_AGENT'])) {
			return false;
		}
		foreach ($agent as $regex) {
			if (preg_match($regex, $this->server['HTTP_USER_AGENT'])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns the unverified server host from the headers without checking
	 * whether it is a trusted domain
	 * @return string Server host
	 */
	public function getInsecureServerHost(): string {
		if ($this->fromTrustedProxy() && $this->getOverwriteHost() !== null) {
			return $this->getOverwriteHost();
		}

		$host = 'localhost';
		if ($this->fromTrustedProxy() && isset($this->server['HTTP_X_FORWARDED_HOST'])) {
			if (strpos($this->server['HTTP_X_FORWARDED_HOST'], ',') !== false) {
				$parts = explode(',', $this->server['HTTP_X_FORWARDED_HOST']);
				$host = trim(current($parts));
			} else {
				$host = $this->server['HTTP_X_FORWARDED_HOST'];
			}
		} else {
			if (isset($this->server['HTTP_HOST'])) {
				$host = $this->server['HTTP_HOST'];
			} elseif (isset($this->server['SERVER_NAME'])) {
				$host = $this->server['SERVER_NAME'];
			}
		}

		return $host;
	}


	/**
	 * Returns the server host from the headers, or the first configured
	 * trusted domain if the host isn't in the trusted list
	 * @return string Server host
	 */
	public function getServerHost(): string {
		// overwritehost is always trusted
		$host = $this->getOverwriteHost();
		if ($host !== null) {
			return $host;
		}

		// get the host from the headers
		$host = $this->getInsecureServerHost();

		// Verify that the host is a trusted domain if the trusted domains
		// are defined
		// If no trusted domain is provided the first trusted domain is returned
		$trustedDomainHelper = new TrustedDomainHelper($this->config);
		if ($trustedDomainHelper->isTrustedDomain($host)) {
			return $host;
		}

		$trustedList = (array)$this->config->getSystemValue('trusted_domains', []);
		if (count($trustedList) > 0) {
			return reset($trustedList);
		}

		return '';
	}

	/**
	 * Returns the overwritehost setting from the config if set and
	 * if the overwrite condition is met
	 * @return string|null overwritehost value or null if not defined or the defined condition
	 * isn't met
	 */
	private function getOverwriteHost() {
		if ($this->config->getSystemValue('overwritehost') !== '' && $this->isOverwriteCondition()) {
			return $this->config->getSystemValue('overwritehost');
		}
		return null;
	}

	private function fromTrustedProxy(): bool {
		$remoteAddress = isset($this->server['REMOTE_ADDR']) ? $this->server['REMOTE_ADDR'] : '';
		$trustedProxies = $this->config->getSystemValue('trusted_proxies', []);

		return \is_array($trustedProxies) && $this->isTrustedProxy($trustedProxies, $remoteAddress);
	}
}
