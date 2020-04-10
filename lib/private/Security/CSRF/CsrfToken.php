<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Leon Klingele <git@leonklingele.de>
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

/**
 * Class CsrfToken represents the stored or provided CSRF token. To mitigate
 * BREACH alike vulnerabilities the token is returned in an encrypted value as
 * well in an unencrypted value. For display measures to the user always the
 * unencrypted one should be chosen.
 *
 * @package OC\Security\CSRF
 */
class CsrfToken {
	/** @var string */
	private $value;
	/** @var string */
	private $encryptedValue = '';

	/**
	 * @param string $value Value of the token. Can be encrypted or not encrypted.
	 */
	public function __construct(string $value) {
		$this->value = $value;
	}

	/**
	 * Encrypted value of the token. This is used to mitigate BREACH alike
	 * vulnerabilities. For display measures do use this functionality.
	 *
	 * @return string
	 */
	public function getEncryptedValue(): string {
		if($this->encryptedValue === '') {
			$sharedSecret = random_bytes(\strlen($this->value));
			$this->encryptedValue = base64_encode($this->value ^ $sharedSecret) . ':' . base64_encode($sharedSecret);
		}

		return $this->encryptedValue;
	}

	/**
	 * The unencrypted value of the token. Used for decrypting an already
	 * encrypted token.
	 *
	 * @return string
	 */
	public function getDecryptedValue(): string {
		$token = explode(':', $this->value);
		if (\count($token) !== 2) {
			return '';
		}
		$obfuscatedToken = $token[0];
		$secret = $token[1];
		return base64_decode($obfuscatedToken) ^ base64_decode($secret);
	}
}
