<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2021 Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OC\AppFramework\Maintenance;

use OC\AppFramework\Bootstrap\Coordinator;
use OCP\AppFramework\Maintenance\IOptionalIndex;
use OCP\AppFramework\QueryException;
use OCP\IServerContainer;
use Psr\Log\LoggerInterface;

class OptionalIndexManager {

	/** @var IServerContainer */
	private $serverContainer;

	/** @var Coordinator */
	private $coordinator;

	/** @var LoggerInterface */
	private $logger;

	/** @var IOptionalIndex[] */
	private $optionalIndexes;

	public function __construct(
		IServerContainer $serverContainer,
		Coordinator $coordinator,
		LoggerInterface $logger
	) {
		$this->coordinator = $coordinator;
		$this->serverContainer = $serverContainer;
		$this->logger = $logger;
	}

	/**
	 * @return IOptionalIndex[]
	 */
	public function getPending(): array {
		if ($this->optionalIndexes !== null) {
			return $this->optionalIndexes;
		}

		$context = $this->coordinator->getRegistrationContext();

		foreach ($context->getOptionalIndexes() as $optionalIndex) {
			try {
				$class = $this->serverContainer->query($optionalIndex->getService());
			} catch (QueryException $e) {
				$this->logger->info('Could not initialize ' . $optionalIndex->getService() . ' for ' . $optionalIndex->getAppId());
				continue;
			}

			if (!($class instanceof IOptionalIndex)) {
				$this->logger->info($optionalIndex->getService() . ' is not an instance of ' . IOptionalIndex::class);
				continue;
			}

			if ($class->exists()) {
				$this->logger->debug($optionalIndex->getService() . ' is already added');
				continue;
			}

			$this->optionalIndexes[] = $class;
		}

		return $this->optionalIndexes;
	}
}
