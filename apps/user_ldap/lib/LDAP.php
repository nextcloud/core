<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Alexander Bergolth <leo@strike.wu.ac.at>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Peter Kubica <peter@kubica.ch>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Roger Szabo <roger.szabo@web.de>
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

namespace OCA\User_LDAP;

use OC\ServerNotAvailableException;
use OCA\User_LDAP\Exceptions\ConstraintViolationException;
use OCA\User_LDAP\PagedResults\IAdapter;
use OCA\User_LDAP\PagedResults\Php54;
use OCA\User_LDAP\PagedResults\Php73;

class LDAP implements ILDAPWrapper {
	protected $curFunc = '';
	protected $curArgs = [];

	/** @var IAdapter */
	protected $pagedResultsAdapter;

	public function __construct() {
		if (version_compare(PHP_VERSION, '7.3', '<') === true) {
			$this->pagedResultsAdapter = new Php54();
		} else {
			$this->pagedResultsAdapter = new Php73();
		}
	}

	/**
	 * @param resource $link
	 * @param string $dn
	 * @param string $password
	 * @return bool|mixed
	 */
	public function bind($link, $dn, $password) {
		return $this->invokeLDAPMethod('bind', $link, $dn, $password);
	}

	/**
	 * @param string $host
	 * @param string $port
	 * @return mixed
	 */
	public function connect($host, $port) {
		if (strpos($host, '://') === false) {
			$host = 'ldap://' . $host;
		}
		if (strpos($host, ':', strpos($host, '://') + 1) === false) {
			//ldap_connect ignores port parameter when URLs are passed
			$host .= ':' . $port;
		}
		return $this->invokeLDAPMethod('connect', $host);
	}

	public function controlPagedResultResponse($link, $result, &$cookie): bool {
		$this->preFunctionCall(
			$this->pagedResultsAdapter->getResponseCallFunc(),
			$this->pagedResultsAdapter->getResponseCallArgs([$link, $result, &$cookie])
		);

		$result = $this->pagedResultsAdapter->responseCall($link);
		$cookie = $this->pagedResultsAdapter->getCookie($link);

		if ($this->isResultFalse($result)) {
			$this->postFunctionCall();
		}

		return $result;
	}

	/**
	 * @param LDAP $link
	 * @param int $pageSize
	 * @param bool $isCritical
	 * @return mixed|true
	 */
	public function controlPagedResult($link, $pageSize, $isCritical) {
		$fn = $this->pagedResultsAdapter->getRequestCallFunc();
		$this->pagedResultsAdapter->setRequestParameters($link, $pageSize, $isCritical);
		if ($fn === null) {
			return true;
		}

		$this->preFunctionCall($fn, $this->pagedResultsAdapter->getRequestCallArgs($link));
		$result = $this->pagedResultsAdapter->requestCall($link);

		if ($this->isResultFalse($result)) {
			$this->postFunctionCall();
		}

		return $result;
	}

	/**
	 * @param LDAP $link
	 * @param LDAP $result
	 * @return mixed
	 */
	public function countEntries($link, $result) {
		return $this->invokeLDAPMethod('count_entries', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @return integer
	 */
	public function errno($link) {
		return $this->invokeLDAPMethod('errno', $link);
	}

	/**
	 * @param LDAP $link
	 * @return string
	 */
	public function error($link) {
		return $this->invokeLDAPMethod('error', $link);
	}

	/**
	 * Splits DN into its component parts
	 * @param string $dn
	 * @param int @withAttrib
	 * @return array|false
	 * @link https://www.php.net/manual/en/function.ldap-explode-dn.php
	 */
	public function explodeDN($dn, $withAttrib) {
		return $this->invokeLDAPMethod('explode_dn', $dn, $withAttrib);
	}

	/**
	 * @param LDAP $link
	 * @param LDAP $result
	 * @return mixed
	 */
	public function firstEntry($link, $result) {
		return $this->invokeLDAPMethod('first_entry', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @param LDAP $result
	 * @return array|mixed
	 */
	public function getAttributes($link, $result) {
		return $this->invokeLDAPMethod('get_attributes', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @param LDAP $result
	 * @return mixed|string
	 */
	public function getDN($link, $result) {
		return $this->invokeLDAPMethod('get_dn', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @param LDAP $result
	 * @return array|mixed
	 */
	public function getEntries($link, $result) {
		return $this->invokeLDAPMethod('get_entries', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @param resource $result
	 * @return mixed
	 */
	public function nextEntry($link, $result) {
		return $this->invokeLDAPMethod('next_entry', $link, $result);
	}

	/**
	 * @param LDAP $link
	 * @param string $baseDN
	 * @param string $filter
	 * @param array $attr
	 * @return mixed
	 */
	public function read($link, $baseDN, $filter, $attr) {
		$this->pagedResultsAdapter->setReadArgs($link, $baseDN, $filter, $attr);
		return $this->invokeLDAPMethod('read', ...$this->pagedResultsAdapter->getReadArgs($link));
	}

	/**
	 * @param LDAP $link
	 * @param string[] $baseDN
	 * @param string $filter
	 * @param array $attr
	 * @param int $attrsOnly
	 * @param int $limit
	 * @return mixed
	 * @throws \Exception
	 */
	public function search($link, $baseDN, $filter, $attr, $attrsOnly = 0, $limit = 0) {
		$oldHandler = set_error_handler(function ($no, $message, $file, $line) use (&$oldHandler) {
			if (strpos($message, 'Partial search results returned: Sizelimit exceeded') !== false) {
				return true;
			}
			$oldHandler($no, $message, $file, $line);
			return true;
		});
		try {
			$this->pagedResultsAdapter->setSearchArgs($link, $baseDN, $filter, $attr, $attrsOnly, $limit);
			$result = $this->invokeLDAPMethod('search', ...$this->pagedResultsAdapter->getSearchArgs($link));

			restore_error_handler();
			return $result;
		} catch (\Exception $e) {
			restore_error_handler();
			throw $e;
		}
	}

	/**
	 * @param LDAP $link
	 * @param string $userDN
	 * @param string $password
	 * @return bool
	 */
	public function modReplace($link, $userDN, $password) {
		return $this->invokeLDAPMethod('mod_replace', $link, $userDN, ['userPassword' => $password]);
	}

	/**
	 * @param LDAP $link
	 * @param string $userDN
	 * @param string $oldPassword
	 * @param string $password
	 * @return bool
	 */
	public function exopPasswd($link, $userDN, $oldPassword, $password) {
		return $this->invokeLDAPMethod('exop_passwd', $link, $userDN, $oldPassword, $password);
	}

	/**
	 * @param LDAP $link
	 * @param string $option
	 * @param int $value
	 * @return bool|mixed
	 */
	public function setOption($link, $option, $value) {
		return $this->invokeLDAPMethod('set_option', $link, $option, $value);
	}

	/**
	 * @param LDAP $link
	 * @return mixed|true
	 */
	public function startTls($link) {
		return $this->invokeLDAPMethod('start_tls', $link);
	}

	/**
	 * @param resource $link
	 * @return bool|mixed
	 */
	public function unbind($link) {
		return $this->invokeLDAPMethod('unbind', $link);
	}

	/**
	 * Checks whether the server supports LDAP
	 * @return boolean if it the case, false otherwise
	 * */
	public function areLDAPFunctionsAvailable() {
		return function_exists('ldap_connect');
	}

	/**
	 * Checks whether the submitted parameter is a resource
	 * @param Resource $resource the resource variable to check
	 * @return bool true if it is a resource, false otherwise
	 */
	public function isResource($resource) {
		return is_resource($resource);
	}

	/**
	 * Checks whether the return value from LDAP is wrong or not.
	 *
	 * When using ldap_search we provide an array, in case multiple bases are
	 * configured. Thus, we need to check the array elements.
	 *
	 * @param $result
	 * @return bool
	 */
	protected function isResultFalse($result) {
		if ($result === false) {
			return true;
		}

		if ($this->curFunc === 'ldap_search' && is_array($result)) {
			foreach ($result as $singleResult) {
				if ($singleResult === false) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return mixed
	 */
	protected function invokeLDAPMethod() {
		$arguments = func_get_args();
		$func = 'ldap_' . array_shift($arguments);
		if (function_exists($func)) {
			$this->preFunctionCall($func, $arguments);
			$result = call_user_func_array($func, $arguments);
			if ($this->isResultFalse($result)) {
				$this->postFunctionCall();
			}
			return $result;
		}
		return null;
	}

	/**
	 * @param string $functionName
	 * @param array $args
	 */
	private function preFunctionCall($functionName, $args) {
		$this->curFunc = $functionName;
		$this->curArgs = $args;
	}

	/**
	 * Analyzes the returned LDAP error and acts accordingly if not 0
	 *
	 * @param resource $resource the LDAP Connection resource
	 * @throws ConstraintViolationException
	 * @throws ServerNotAvailableException
	 * @throws \Exception
	 */
	private function processLDAPError($resource) {
		$errorCode = ldap_errno($resource);
		if ($errorCode === 0) {
			return;
		}
		$errorMsg = ldap_error($resource);

		if ($this->curFunc === 'ldap_get_entries'
			&& $errorCode === -4) {
		} elseif ($errorCode === 32) {
			//for now
		} elseif ($errorCode === 10) {
			//referrals, we switch them off, but then there is AD :)
		} elseif ($errorCode === -1) {
			throw new ServerNotAvailableException('Lost connection to LDAP server.');
		} elseif ($errorCode === 52) {
			throw new ServerNotAvailableException('LDAP server is shutting down.');
		} elseif ($errorCode === 48) {
			throw new \Exception('LDAP authentication method rejected', $errorCode);
		} elseif ($errorCode === 1) {
			throw new \Exception('LDAP Operations error', $errorCode);
		} elseif ($errorCode === 19) {
			ldap_get_option($this->curArgs[0], LDAP_OPT_ERROR_STRING, $extended_error);
			throw new ConstraintViolationException(!empty($extended_error)?$extended_error:$errorMsg, $errorCode);
		} else {
			\OC::$server->getLogger()->debug('LDAP error {message} ({code}) after calling {func}', [
				'app' => 'user_ldap',
				'message' => $errorMsg,
				'code' => $errorCode,
				'func' => $this->curFunc,
			]);
		}
	}

	/**
	 * Called after an ldap method is run to act on LDAP error if necessary
	 * @throw \Exception
	 */
	private function postFunctionCall() {
		if ($this->isResource($this->curArgs[0])) {
			$resource = $this->curArgs[0];
		} elseif (
			   $this->curFunc === 'ldap_search'
			&& is_array($this->curArgs[0])
			&& $this->isResource($this->curArgs[0][0])
		) {
			// we use always the same LDAP connection resource, is enough to
			// take the first one.
			$resource = $this->curArgs[0][0];
		} else {
			return;
		}

		$this->processLDAPError($resource);

		$this->curFunc = '';
		$this->curArgs = [];
	}
}
