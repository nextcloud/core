<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCP;

/**
 * Interface ILogger
 * @since 7.0.0
 *
 * This logger interface follows the design guidelines of PSR-3
 * https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md#3-psrlogloggerinterface
 * @deprecated 20.0.0 use the PSR-3 logger \Psr\Log\LoggerInterface
 */
interface ILogger {
	/**
	 * @since 14.0.0
	 * @deprecated 20.0.0
	 */
	public const DEBUG = 0;
	/**
	 * @since 14.0.0
	 * @deprecated 20.0.0
	 */
	public const INFO = 1;
	/**
	 * @since 14.0.0
	 * @deprecated 20.0.0
	 */
	public const WARN = 2;
	/**
	 * @since 14.0.0
	 * @deprecated 20.0.0
	 */
	public const ERROR = 3;
	/**
	 * @since 14.0.0
	 * @deprecated 20.0.0
	 */
	public const FATAL = 4;

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::emergency
	 */
	public function emergency(string $message, array $context = []);

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::alert
	 */
	public function alert(string $message, array $context = []);

	/**
	 * Critical conditions.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::critical
	 */
	public function critical(string $message, array $context = []);

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::error
	 */
	public function error(string $message, array $context = []);

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::warning
	 */
	public function warning(string $message, array $context = []);

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::notice
	 */
	public function notice(string $message, array $context = []);

	/**
	 * Interesting events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::info
	 */
	public function info(string $message, array $context = []);

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::debug
	 */
	public function debug(string $message, array $context = []);

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param int $level
	 * @param string $message
	 * @param array $context
	 * @return mixed
	 * @since 7.0.0
	 * @deprecated 20.0.0 use \Psr\Log\LoggerInterface::log
	 */
	public function log(int $level, string $message, array $context = []);

	/**
	 * Logs an exception very detailed
	 * An additional message can we written to the log by adding it to the
	 * context.
	 *
	 * <code>
	 * $logger->logException($ex, [
	 *     'message' => 'Exception during background job execution'
	 * ]);
	 * </code>
	 *
	 * @param \Exception|\Throwable $exception
	 * @param array $context
	 * @return void
	 * @since 8.2.0
	 * @deprecated 20.0.0 use the `exception` entry in the context of any method in \Psr\Log\LoggerInterface
	 */
	public function logException(\Throwable $exception, array $context = []);
}
