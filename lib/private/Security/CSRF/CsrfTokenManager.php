<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
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

namespace OC\Security\CSRF;

use OC\Security\CSRF\TokenStorage\SessionStorage;

/**
 * Class CsrfTokenManager is the manager for all CSRF token related activities.
 *
 * @package OC\Security\CSRF
 */
class CsrfTokenManager {
	/** @var CsrfTokenGenerator */
	private $tokenGenerator;
	/** @var SessionStorage */
	private $sessionStorage;
	/** @var CsrfToken|null */
	private $csrfToken = null;

	/**
	 * @param CsrfTokenGenerator $tokenGenerator
	 * @param SessionStorage $storageInterface
	 */
	public function __construct(CsrfTokenGenerator $tokenGenerator,
								SessionStorage $storageInterface) {
		$this->tokenGenerator = $tokenGenerator;
		$this->sessionStorage = $storageInterface;
	}

	/**
	 * Returns the current CSRF token, if none set it will create a new one.
	 *
	 * @return CsrfToken
	 */
	public function getToken(): CsrfToken {
		if(!\is_null($this->csrfToken)) {
			return $this->csrfToken;
		}

		if($this->sessionStorage->hasToken()) {
			$value = $this->sessionStorage->getToken();
		} else {
			$value = $this->tokenGenerator->generateToken();
			$this->sessionStorage->setToken($value);
		}

		$this->csrfToken = new CsrfToken($value);
		return $this->csrfToken;
	}

	/**
	 * Invalidates any current token and sets a new one.
	 *
	 * @return CsrfToken
	 */
	public function refreshToken(): CsrfToken {
		$value = $this->tokenGenerator->generateToken();
		$this->sessionStorage->setToken($value);
		$this->csrfToken = new CsrfToken($value);
		return $this->csrfToken;
	}

	/**
	 * Remove the current token from the storage.
	 */
	public function removeToken() {
		$this->csrfToken = null;
		$this->sessionStorage->removeToken();
	}

	/**
	 * Verifies whether the provided token is valid.
	 *
	 * @param CsrfToken $token
	 * @return bool
	 */
	public function isTokenValid(CsrfToken $token): bool {
		if(!$this->sessionStorage->hasToken()) {
			return false;
		}

		return hash_equals(
			$this->sessionStorage->getToken(),
			$token->getDecryptedValue()
		);
	}
}
