<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OCP\AppFramework\QueryException;
use OCP\Capabilities\ICapability;
use OCP\Capabilities\IPublicCapability;
use OCP\ILogger;

class CapabilitiesManager {

	/** @var \Closure[] */
	private $capabilities = [];

	/** @var ILogger */
	private $logger;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	/**
	 * Get an array of al the capabilities that are registered at this manager
	 *
	 * @param bool $public get public capabilities only
	 * @throws \InvalidArgumentException
	 * @return array
	 */
	public function getCapabilities(bool $public = false) : array {
		$capabilities = [];
		foreach($this->capabilities as $capability) {
			try {
				$c = $capability();
			} catch (QueryException $e) {
				$this->logger->logException($e, [
					'message' => 'CapabilitiesManager',
					'level' => ILogger::ERROR,
					'app' => 'core',
				]);
				continue;
			}

			if ($c instanceof ICapability) {
				if(!$public || $c instanceof IPublicCapability) {
					$capabilities = array_replace_recursive($capabilities, $c->getCapabilities());
				}
			} else {
				throw new \InvalidArgumentException('The given Capability (' . get_class($c) . ') does not implement the ICapability interface');
			}
		}

		return $capabilities;
	}

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * capabilities are actually requested
	 *
	 * $callable has to return an instance of OCP\Capabilities\ICapability
	 *
	 * @param \Closure $callable
	 */
	public function registerCapability(\Closure $callable) {
		$this->capabilities[] = $callable;
	}
}
