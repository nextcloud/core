<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Session;

use Exception;
use OCP\Session\Exceptions\SessionNotAvailableException;

/**
 * Class Internal
 *
 * store session data in an in-memory array, not persistent
 *
 * @package OC\Session
 */
class Memory extends Session {
	protected $data;

	public function __construct(string $name) {
		//no need to use $name since all data is already scoped to this instance
		$this->data = [];
	}

	/**
	 * @param string $key
	 * @param integer $value
	 */
	public function set(string $key, $value) {
		$this->validateSession();
		$this->data[$key] = $value;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function get(string $key) {
		if (!$this->exists($key)) {
			return null;
		}
		return $this->data[$key];
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function exists(string $key): bool {
		return isset($this->data[$key]);
	}

	/**
	 * @param string $key
	 */
	public function remove(string $key) {
		$this->validateSession();
		unset($this->data[$key]);
	}

	public function clear() {
		$this->data = [];
	}

	/**
	 * Stub since the session ID does not need to get regenerated for the cache
	 *
	 * @param bool $deleteOldSession
	 */
	public function regenerateId(bool $deleteOldSession = true, bool $updateToken = false) {}

	/**
	 * Wrapper around session_id
	 *
	 * @return string
	 * @throws SessionNotAvailableException
	 * @since 9.1.0
	 */
	public function getId(): string {
		throw new SessionNotAvailableException('Memory session does not have an ID');
	}

	/**
	 * Helper function for PHPUnit execution - don't use in non-test code
	 */
	public function reopen() {
		$this->sessionClosed = false;
	}

	/**
	 * In case the session has already been locked an exception will be thrown
	 *
	 * @throws Exception
	 */
	private function validateSession() {
		if ($this->sessionClosed) {
			throw new Exception('Session has been closed - no further changes to the session are allowed');
		}
	}
}
