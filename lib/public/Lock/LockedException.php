<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCP\Lock;

/**
 * Class LockedException
 *
 * @package OCP\Lock
 * @since 8.1.0
 */
class LockedException extends \Exception {

	/**
	 * Locked path
	 *
	 * @var string
	 */
	private $path;

	/** @var string|null */
	private $existingLock;

	/**
	 * LockedException constructor.
	 *
	 * @param string $path locked path
	 * @param \Exception|null $previous previous exception for cascading
	 * @param string $existingLock since 14.0.0
	 * @since 8.1.0
	 */
	public function __construct(string $path, \Exception $previous = null, string $existingLock = null) {
		$message = '"' . $path . '" is locked';
		$this->existingLock = $existingLock;
		if ($existingLock) {
			$message .= ', existing lock on file: ' . $existingLock;
		}
		parent::__construct($message, 0, $previous);
		$this->path = $path;
	}

	/**
	 * @return string
	 * @since 8.1.0
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @return string
	 * @since 19.0.0
	 */
	public function getExistingLock(): ?string {
		return $this->existingLock;
	}
}
