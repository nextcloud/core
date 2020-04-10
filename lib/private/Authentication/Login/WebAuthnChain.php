<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Authentication\Login;

class WebAuthnChain {
	/** @var UserDisabledCheckCommand */
	private $userDisabledCheckCommand;

	/** @var LoggedInCheckCommand */
	private $loggedInCheckCommand;

	/** @var CompleteLoginCommand */
	private $completeLoginCommand;

	/** @var CreateSessionTokenCommand */
	private $createSessionTokenCommand;

	/** @var ClearLostPasswordTokensCommand */
	private $clearLostPasswordTokensCommand;

	/** @var UpdateLastPasswordConfirmCommand */
	private $updateLastPasswordConfirmCommand;

	/** @var SetUserTimezoneCommand */
	private $setUserTimezoneCommand;

	/** @var TwoFactorCommand */
	private $twoFactorCommand;

	/** @var FinishRememberedLoginCommand */
	private $finishRememberedLoginCommand;

	/** @var WebAuthnLoginCommand */
	private $webAuthnLoginCommand;

	public function __construct(UserDisabledCheckCommand $userDisabledCheckCommand,
								WebAuthnLoginCommand $webAuthnLoginCommand,
								LoggedInCheckCommand $loggedInCheckCommand,
								CompleteLoginCommand $completeLoginCommand,
								CreateSessionTokenCommand $createSessionTokenCommand,
								ClearLostPasswordTokensCommand $clearLostPasswordTokensCommand,
								UpdateLastPasswordConfirmCommand $updateLastPasswordConfirmCommand,
								SetUserTimezoneCommand $setUserTimezoneCommand,
								TwoFactorCommand $twoFactorCommand,
								FinishRememberedLoginCommand $finishRememberedLoginCommand
	) {
		$this->userDisabledCheckCommand = $userDisabledCheckCommand;
		$this->webAuthnLoginCommand = $webAuthnLoginCommand;
		$this->loggedInCheckCommand = $loggedInCheckCommand;
		$this->completeLoginCommand = $completeLoginCommand;
		$this->createSessionTokenCommand = $createSessionTokenCommand;
		$this->clearLostPasswordTokensCommand = $clearLostPasswordTokensCommand;
		$this->updateLastPasswordConfirmCommand = $updateLastPasswordConfirmCommand;
		$this->setUserTimezoneCommand = $setUserTimezoneCommand;
		$this->twoFactorCommand = $twoFactorCommand;
		$this->finishRememberedLoginCommand = $finishRememberedLoginCommand;
	}

	public function process(LoginData $loginData): LoginResult {
		$chain = $this->userDisabledCheckCommand;
		$chain
			->setNext($this->webAuthnLoginCommand)
			->setNext($this->loggedInCheckCommand)
			->setNext($this->completeLoginCommand)
			->setNext($this->createSessionTokenCommand)
			->setNext($this->clearLostPasswordTokensCommand)
			->setNext($this->updateLastPasswordConfirmCommand)
			->setNext($this->setUserTimezoneCommand)
			->setNext($this->twoFactorCommand)
			->setNext($this->finishRememberedLoginCommand);

		return $chain->process($loginData);
	}
}
