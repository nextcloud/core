<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
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

namespace OC\Security\IdentityProof;

class Key {
	/** @var string */
	private $publicKey;
	/** @var string */
	private $privateKey;

	/**
	 * @param string $publicKey
	 * @param string $privateKey
	 */
	public function __construct(string $publicKey, string $privateKey) {
		$this->publicKey = $publicKey;
		$this->privateKey = $privateKey;
	}

	public function getPrivate(): string {
		return $this->privateKey;
	}

	public function getPublic(): string {
		return $this->publicKey;
	}
}
