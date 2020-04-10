<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
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

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files;

/**
 * Class ForbiddenException
 *
 * @package OCP\Files
 * @since 9.0.0
 */
class ForbiddenException extends \Exception {

	/** @var bool */
	private $retry;

	/**
	 * @param string $message
	 * @param bool $retry
	 * @param \Exception|null $previous previous exception for cascading
	 * @since 9.0.0
	 */
	public function __construct($message, $retry, \Exception $previous = null) {
		parent::__construct($message, 0, $previous);
		$this->retry = $retry;
	}

	/**
	 * @return bool
	 * @since 9.0.0
	 */
	public function getRetry() {
		return (bool) $this->retry;
	}
}
