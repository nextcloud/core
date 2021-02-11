<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Olivier Paroz <github@oparoz.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC;

use Nextcloud\LogNormalizer\Normalizer;
use OCP\Log\IDataLogger;
use function array_merge;
use OC\Log\ExceptionSerializer;
use OCP\ILogger;
use OCP\Log\IFileBased;
use OCP\Log\IWriter;
use OCP\Support\CrashReport\IRegistry;

/**
 * logging utilities
 *
 * This is a stand in, this should be replaced by a Psr\Log\LoggerInterface
 * compatible logger. See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 * for the full interface specification.
 *
 * MonoLog is an example implementing this interface.
 */
class Log implements ILogger, IDataLogger {

	/** @var IWriter */
	private $logger;

	/** @var SystemConfig */
	private $config;

	/** @var boolean|null cache the result of the log condition check for the request */
	private $logConditionSatisfied = null;

	/** @var Normalizer */
	private $normalizer;

	/** @var IRegistry */
	private $crashReporters;

	/**
	 * @param IWriter $logger The logger that should be used
	 * @param SystemConfig $config the system config object
	 * @param Normalizer|null $normalizer
	 * @param IRegistry|null $registry
	 */
	public function __construct(IWriter $logger, SystemConfig $config = null, $normalizer = null, IRegistry $registry = null) {
		// FIXME: Add this for backwards compatibility, should be fixed at some point probably
		if ($config === null) {
			$config = \OC::$server->getSystemConfig();
		}

		$this->config = $config;
		$this->logger = $logger;
		if ($normalizer === null) {
			$this->normalizer = new Normalizer();
		} else {
			$this->normalizer = $normalizer;
		}
		$this->crashReporters = $registry;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function emergency(string $message, array $context = []) {
		$this->log(ILogger::FATAL, $message, $context);
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function alert(string $message, array $context = []) {
		$this->log(ILogger::ERROR, $message, $context);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function critical(string $message, array $context = []) {
		$this->log(ILogger::ERROR, $message, $context);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function error(string $message, array $context = []) {
		$this->log(ILogger::ERROR, $message, $context);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function warning(string $message, array $context = []) {
		$this->log(ILogger::WARN, $message, $context);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function notice(string $message, array $context = []) {
		$this->log(ILogger::INFO, $message, $context);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function info(string $message, array $context = []) {
		$this->log(ILogger::INFO, $message, $context);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function debug(string $message, array $context = []) {
		$this->log(ILogger::DEBUG, $message, $context);
	}


	/**
	 * Logs with an arbitrary level.
	 *
	 * @param int $level
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public function log(int $level, string $message, array $context = []) {
		$minLevel = $this->getLogLevel($context);

		array_walk($context, [$this->normalizer, 'format']);

		$app = $context['app'] ?? 'no app in context';

		// interpolate $message as defined in PSR-3
		$replace = [];
		foreach ($context as $key => $val) {
			$replace['{' . $key . '}'] = $val;
		}
		$message = strtr($message, $replace);

		try {
			if ($level >= $minLevel) {
				$this->writeLog($app, $message, $level);

				if ($this->crashReporters !== null) {
					$messageContext = array_merge(
						$context,
						[
							'level' => $level
						]
					);
					$this->crashReporters->delegateMessage($message, $messageContext);
				}
			} else {
				if ($this->crashReporters !== null) {
					$this->crashReporters->delegateBreadcrumb($message, 'log', $context);
				}
			}
		} catch (\Throwable $e) {
			// make sure we dont hard crash if logging fails
		}
	}

	private function getLogLevel($context) {
		$logCondition = $this->config->getValue('log.condition', []);

		/**
		 * check for a special log condition - this enables an increased log on
		 * a per request/user base
		 */
		if ($this->logConditionSatisfied === null) {
			// default to false to just process this once per request
			$this->logConditionSatisfied = false;
			if (!empty($logCondition)) {

				// check for secret token in the request
				if (isset($logCondition['shared_secret'])) {
					$request = \OC::$server->getRequest();

					if ($request->getMethod() === 'PUT' &&
						strpos($request->getHeader('Content-Type'), 'application/x-www-form-urlencoded') === false &&
						strpos($request->getHeader('Content-Type'), 'application/json') === false) {
						$logSecretRequest = '';
					} else {
						$logSecretRequest = $request->getParam('log_secret', '');
					}

					// if token is found in the request change set the log condition to satisfied
					if ($request && hash_equals($logCondition['shared_secret'], $logSecretRequest)) {
						$this->logConditionSatisfied = true;
					}
				}

				// check for user
				if (isset($logCondition['users'])) {
					$user = \OC::$server->getUserSession()->getUser();

					// if the user matches set the log condition to satisfied
					if ($user !== null && in_array($user->getUID(), $logCondition['users'], true)) {
						$this->logConditionSatisfied = true;
					}
				}
			}
		}

		// if log condition is satisfied change the required log level to DEBUG
		if ($this->logConditionSatisfied) {
			return ILogger::DEBUG;
		}

		if (isset($context['app'])) {
			$app = $context['app'];

			/**
			 * check log condition based on the context of each log message
			 * once this is met -> change the required log level to debug
			 */
			if (!empty($logCondition)
				&& isset($logCondition['apps'])
				&& in_array($app, $logCondition['apps'], true)) {
				return ILogger::DEBUG;
			}
		}

		return min($this->config->getValue('loglevel', ILogger::WARN), ILogger::FATAL);
	}

	/**
	 * Logs an exception very detailed
	 *
	 * @param \Exception|\Throwable $exception
	 * @param array $context
	 * @return void
	 * @since 8.2.0
	 */
	public function logException(\Throwable $exception, array $context = []) {
		$app = $context['app'] ?? 'no app in context';
		$level = $context['level'] ?? ILogger::ERROR;

		$serializer = new ExceptionSerializer($this->config);
		$data = $serializer->serializeException($exception);
		$data['CustomMessage'] = $context['message'] ?? '--';

		$minLevel = $this->getLogLevel($context);

		array_walk($context, [$this->normalizer, 'format']);

		try {
			if ($level >= $minLevel) {
				if (!$this->logger instanceof IFileBased) {
					$data = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);
				}
				$this->writeLog($app, $data, $level);
			}

			$context['level'] = $level;
			if (!is_null($this->crashReporters)) {
				$this->crashReporters->delegateReport($exception, $context);
			}
		} catch (\Throwable $e) {
			// make sure we dont hard crash if logging fails
		}
	}

	public function logData(string $message, array $data, array $context = []): void {
		$app = $context['app'] ?? 'no app in context';
		$level = $context['level'] ?? ILogger::ERROR;

		$minLevel = $this->getLogLevel($context);

		array_walk($context, [$this->normalizer, 'format']);

		try {
			if ($level >= $minLevel) {
				$data['message'] = $message;
				if (!$this->logger instanceof IFileBased) {
					$data = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES);
				}
				$this->writeLog($app, $data, $level);
			}

			$context['level'] = $level;
		} catch (\Throwable $e) {
			// make sure we dont hard crash if logging fails
		}
	}

	/**
	 * @param string $app
	 * @param string|array $entry
	 * @param int $level
	 */
	protected function writeLog(string $app, $entry, int $level) {
		$this->logger->write($app, $entry, $level);
	}

	public function getLogPath():string {
		if ($this->logger instanceof IFileBased) {
			return $this->logger->getLogFilePath();
		}
		throw new \RuntimeException('Log implementation has no path');
	}
}
