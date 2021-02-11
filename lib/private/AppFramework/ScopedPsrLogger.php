<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

use Psr\Log\LoggerInterface;
use function array_merge;

class ScopedPsrLogger implements LoggerInterface {

	/** @var LoggerInterface */
	private $inner;

	/** @var string */
	private $appId;

	public function __construct(LoggerInterface $inner,
								string $appId) {
		$this->inner = $inner;
		$this->appId = $appId;
	}

	public function emergency($message, array $context = []) {
		$this->inner->emergency(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function alert($message, array $context = []) {
		$this->inner->alert(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function critical($message, array $context = []) {
		$this->inner->critical(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function error($message, array $context = []) {
		$this->inner->error(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function warning($message, array $context = []) {
		$this->inner->warning(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function notice($message, array $context = []) {
		$this->inner->notice(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function info($message, array $context = []) {
		$this->inner->info(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function debug($message, array $context = []) {
		$this->inner->debug(
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}

	public function log($level, $message, array $context = []) {
		$this->inner->log(
			$level,
			$message,
			array_merge(
				[
					'app' => $this->appId,
				],
				$context
			)
		);
	}
}
