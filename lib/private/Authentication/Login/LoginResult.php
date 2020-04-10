<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

class LoginResult {

	/** @var bool */
	private $success;

	/** @var LoginData */
	private $loginData;

	/** @var string|null */
	private $redirectUrl;

	/** @var string|null */
	private $errorMessage;

	private function __construct(bool $success, LoginData $loginData) {
		$this->success = $success;
		$this->loginData = $loginData;
	}

	private function setRedirectUrl(string $url) {
		$this->redirectUrl = $url;
	}

	private function setErrorMessage(string $msg) {
		$this->errorMessage = $msg;
	}

	public static function success(LoginData $data, ?string $redirectUrl = null) {
		$result = new static(true, $data);
		if ($redirectUrl !== null) {
			$result->setRedirectUrl($redirectUrl);
		}
		return $result;
	}

	public static function failure(LoginData $data, string $msg = null): LoginResult {
		$result = new static(false, $data);
		if ($msg !== null) {
			$result->setErrorMessage($msg);
		}
		return $result;
	}

	public function isSuccess(): bool {
		return $this->success;
	}

	public function getRedirectUrl(): ?string {
		return $this->redirectUrl;
	}

	public function getErrorMessage(): ?string {
		return $this->errorMessage;
	}

}
