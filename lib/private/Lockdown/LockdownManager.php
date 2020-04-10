<?php
/**
 * @copyright Copyright (c) 2016, Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Lockdown;

use OC\Authentication\Token\IToken;
use OCP\ISession;
use OCP\Lockdown\ILockdownManager;

class LockdownManager implements ILockdownManager {
	/** @var ISession */
	private $sessionCallback;

	private $enabled = false;

	/** @var array|null */
	private $scope;

	/**
	 * LockdownManager constructor.
	 *
	 * @param callable $sessionCallback we need to inject the session lazily to avoid dependency loops
	 */
	public function __construct(callable $sessionCallback) {
		$this->sessionCallback = $sessionCallback;
	}


	public function enable() {
		$this->enabled = true;
	}

	/**
	 * @return ISession
	 */
	private function getSession() {
		$callback = $this->sessionCallback;
		return $callback();
	}

	private function getScopeAsArray() {
		if (!$this->scope) {
			$session = $this->getSession();
			$sessionScope = $session->get('token_scope');
			if ($sessionScope) {
				$this->scope = $sessionScope;
			}
		}
		return $this->scope;
	}

	public function setToken(IToken $token) {
		$this->scope = $token->getScopeAsArray();
		$session = $this->getSession();
		$session->set('token_scope', $this->scope);
		$this->enable();
	}

	public function canAccessFilesystem() {
		$scope = $this->getScopeAsArray();
		return !$scope || $scope['filesystem'];
	}
}
