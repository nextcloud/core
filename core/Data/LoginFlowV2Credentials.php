<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Core\Data;

class LoginFlowV2Credentials implements \JsonSerializable {
	/** @var string */
	private $server;
	/** @var string */
	private $loginName;
	/** @var string */
	private $appPassword;

	public function __construct(string $server, string $loginName, string $appPassword) {
		$this->server = $server;
		$this->loginName = $loginName;
		$this->appPassword = $appPassword;
	}

	/**
	 * @return string
	 */
	public function getServer(): string {
		return $this->server;
	}

	/**
	 * @return string
	 */
	public function getLoginName(): string {
		return $this->loginName;
	}

	/**
	 * @return string
	 */
	public function getAppPassword(): string {
		return $this->appPassword;
	}

	public function jsonSerialize(): array {
		return [
			'server' => $this->server,
			'loginName' => $this->loginName,
			'appPassword' => $this->appPassword,
		];
	}


}
