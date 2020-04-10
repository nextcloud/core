<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use OC\Authentication\Token\IToken;
use OC\User\Session;
use OCP\IConfig;

class CreateSessionTokenCommand extends ALoginCommand {

	/** @var IConfig */
	private $config;

	/** @var Session */
	private $userSession;

	public function __construct(IConfig $config,
								Session $userSession) {
		$this->config = $config;
		$this->userSession = $userSession;
	}

	public function process(LoginData $loginData): LoginResult {
		$tokenType = IToken::REMEMBER;
		if ((int)$this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15) === 0) {
			$loginData->setRememberLogin(false);
			$tokenType = IToken::DO_NOT_REMEMBER;
		}

		if ($loginData->getPassword() === '') {
			$this->userSession->createSessionToken(
				$loginData->getRequest(),
				$loginData->getUser()->getUID(),
				$loginData->getUsername(),
				null,
				$tokenType
			);
			$this->userSession->updateTokens(
				$loginData->getUser()->getUID(),
				''
			);
		} else {
			$this->userSession->createSessionToken(
				$loginData->getRequest(),
				$loginData->getUser()->getUID(),
				$loginData->getUsername(),
				$loginData->getPassword(),
				$tokenType
			);
			$this->userSession->updateTokens(
				$loginData->getUser()->getUID(),
				$loginData->getPassword()
			);
		}

		return $this->processNextOrFinishSuccessfully($loginData);
	}
}
