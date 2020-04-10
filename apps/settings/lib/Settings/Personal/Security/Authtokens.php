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

namespace OCA\Settings\Settings\Personal\Security;

use OCP\IUserSession;
use function array_map;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\INamedToken;
use OC\Authentication\Token\IProvider as IAuthTokenProvider;
use OC\Authentication\Token\IToken;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IInitialStateService;
use OCP\ISession;
use OCP\Session\Exceptions\SessionNotAvailableException;
use OCP\Settings\ISettings;

class Authtokens implements ISettings {

	/** @var IAuthTokenProvider */
	private $tokenProvider;

	/** @var ISession */
	private $session;

	/** @var IInitialStateService */
	private $initialStateService;

	/** @var string|null */
	private $uid;

	/** @var IUserSession */
	private $userSession;

	public function __construct(IAuthTokenProvider $tokenProvider,
								ISession $session,
								IUserSession $userSession,
								IInitialStateService $initialStateService,
								?string $UserId) {
		$this->tokenProvider = $tokenProvider;
		$this->session = $session;
		$this->initialStateService = $initialStateService;
		$this->uid = $UserId;
		$this->userSession = $userSession;
	}

	public function getForm(): TemplateResponse {
		$this->initialStateService->provideInitialState(
			'settings',
			'app_tokens',
			$this->getAppTokens()
		);

		$this->initialStateService->provideInitialState(
			'settings',
			'can_create_app_token',
			$this->userSession->getImpersonatingUserID() === null
		);

		return new TemplateResponse('settings', 'settings/personal/security/authtokens');
	}

	public function getSection(): string {
		return 'security';
	}

	public function getPriority(): int {
		return 100;
	}

	private function getAppTokens(): array {
		$tokens = $this->tokenProvider->getTokenByUser($this->uid);

		try {
			$sessionId = $this->session->getId();
		} catch (SessionNotAvailableException $ex) {
			return [];
		}
		try {
			$sessionToken = $this->tokenProvider->getToken($sessionId);
		} catch (InvalidTokenException $ex) {
			return [];
		}

		return array_map(function (IToken $token) use ($sessionToken) {
			$data = $token->jsonSerialize();
			$data['canDelete'] = true;
			$data['canRename'] = $token instanceof INamedToken;
			if ($sessionToken->getId() === $token->getId()) {
				$data['canDelete'] = false;
				$data['canRename'] = false;
				$data['current'] = true;
			}
			return $data;
		}, $tokens);
	}

}
