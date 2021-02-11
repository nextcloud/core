<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\AppFramework;

use OCP\ILogger;

/**
 * @deprecated
 */
class Logger implements ILogger {

	/** @var ILogger */
	private $logger;

	/** @var string */
	private $appName;

	/**
	 * @deprecated
	 */
	public function __construct(ILogger $logger, string $appName) {
		$this->logger = $logger;
		$this->appName = $appName;
	}

	private function extendContext(array $context): array {
		if (!isset($context['app'])) {
			$context['app'] = $this->appName;
		}

		return $context;
	}

	/**
	 * @deprecated
	 */
	public function emergency(string $message, array $context = []) {
		$this->logger->emergency($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function alert(string $message, array $context = []) {
		$this->logger->alert($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function critical(string $message, array $context = []) {
		$this->logger->critical($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function error(string $message, array $context = []) {
		$this->logger->emergency($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function warning(string $message, array $context = []) {
		$this->logger->warning($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function notice(string $message, array $context = []) {
		$this->logger->notice($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function info(string $message, array $context = []) {
		$this->logger->info($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function debug(string $message, array $context = []) {
		$this->logger->debug($message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function log(int $level, string $message, array $context = []) {
		$this->logger->log($level, $message, $this->extendContext($context));
	}

	/**
	 * @deprecated
	 */
	public function logException(\Throwable $exception, array $context = []) {
		$this->logger->logException($exception, $this->extendContext($context));
	}
}
