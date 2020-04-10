<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\AdminAudit\Actions;

use OCP\ILogger;

class Action {
	/** @var ILogger */
	private $logger;

	/**
	 * @param ILogger $logger
	 */
	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	/**
	 * Log a single action with a log level of info
	 *
	 * @param string $text
	 * @param array $params
	 * @param array $elements
	 * @param bool $obfuscateParameters
	 */
	public function log(string $text,
						array $params,
						array $elements,
						bool $obfuscateParameters = false) {
		foreach($elements as $element) {
			if(!isset($params[$element])) {
				if ($obfuscateParameters) {
					$this->logger->critical(
						'$params["'.$element.'"] was missing.',
						['app' => 'admin_audit']
					);
				} else {
					$this->logger->critical(
						sprintf(
							'$params["'.$element.'"] was missing. Transferred value: %s',
							print_r($params, true)
						),
						['app' => 'admin_audit']
					);
				}
				return;
			}
		}

		$replaceArray = [];
		foreach($elements as $element) {
			if($params[$element] instanceof \DateTime) {
				$params[$element] = $params[$element]->format('Y-m-d H:i:s');
			}
			$replaceArray[] = $params[$element];
		}

		$this->logger->info(
			vsprintf(
				$text,
				$replaceArray
			),
			[
				'app' => 'admin_audit'
			]
		);
	}
}
