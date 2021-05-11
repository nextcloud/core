<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCP\Log;

use OC;
use OCP\AppFramework\QueryException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use function class_exists;

/**
 * Get a PSR logger
 *
 * Whenever possible, inject a logger into your classes instead of relying on
 * this helper function.
 *
 * @warning the returned logger implementation is not guaranteed to be the same
 *          between two function calls. During early stages of the process you
 *          might in fact get a noop implementation when Nextcloud isn't ready
 *          to log. Therefore you MUST NOT cache the result of this function but
 *          fetch a new logger for every log line you want to write.
 *
 * @param string|null $appId optional parameter to acquire the app-specific logger
 *
 * @return LoggerInterface
 * @since 22.0.0
 */
function logger(string $appId = null): LoggerInterface {
	if (!class_exists(OC::class) || OC::$server === null) {
		// If someone calls this log before Nextcloud is initialized, there is
		// no logging available. In that case we return a noop implementation
		// TODO: evaluate whether logging to error_log could be an alternative
		return new NullLogger();
	}

	if ($appId !== null) {
		try {
			$appContainer = OC::$server->getRegisteredAppContainer($appId);
			return $appContainer->get(LoggerInterface::class);
		} catch (QueryException $e) {
			// Ignore and return the server logger below
		}
	}

	try {
		return OC::$server->get(LoggerInterface::class);
	} catch (QueryException $e) {
		return new NullLogger();
	}
}
