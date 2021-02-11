<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Controller;

use OC\Core\Db\LoginFlowV2;
use OC\Core\Exception\LoginFlowV2NotFoundException;
use OC\Core\Service\LoginFlowV2Service;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\StandaloneTemplateResponse;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Security\ISecureRandom;

class ClientFlowLoginV2Controller extends Controller {
	public const TOKEN_NAME = 'client.flow.v2.login.token';
	public const STATE_NAME = 'client.flow.v2.state.token';

	/** @var LoginFlowV2Service */
	private $loginFlowV2Service;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var ISession */
	private $session;
	/** @var ISecureRandom */
	private $random;
	/** @var Defaults */
	private $defaults;
	/** @var string */
	private $userId;
	/** @var IL10N */
	private $l10n;

	public function __construct(string $appName,
								IRequest $request,
								LoginFlowV2Service $loginFlowV2Service,
								IURLGenerator $urlGenerator,
								ISession $session,
								ISecureRandom $random,
								Defaults $defaults,
								?string $userId,
								IL10N $l10n) {
		parent::__construct($appName, $request);
		$this->loginFlowV2Service = $loginFlowV2Service;
		$this->urlGenerator = $urlGenerator;
		$this->session = $session;
		$this->random = $random;
		$this->defaults = $defaults;
		$this->userId = $userId;
		$this->l10n = $l10n;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function poll(string $token): JSONResponse {
		try {
			$creds = $this->loginFlowV2Service->poll($token);
		} catch (LoginFlowV2NotFoundException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse($creds);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * @UseSession
	 */
	public function landing(string $token): Response {
		if (!$this->loginFlowV2Service->startLoginFlow($token)) {
			return $this->loginTokenForbiddenResponse();
		}

		$this->session->set(self::TOKEN_NAME, $token);

		return new RedirectResponse(
			$this->urlGenerator->linkToRouteAbsolute('core.ClientFlowLoginV2.showAuthPickerPage')
		);
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * @UseSession
	 */
	public function showAuthPickerPage(): StandaloneTemplateResponse {
		try {
			$flow = $this->getFlowByLoginToken();
		} catch (LoginFlowV2NotFoundException $e) {
			return $this->loginTokenForbiddenResponse();
		}

		$stateToken = $this->random->generate(
			64,
			ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_DIGITS
		);
		$this->session->set(self::STATE_NAME, $stateToken);

		return new StandaloneTemplateResponse(
			$this->appName,
			'loginflowv2/authpicker',
			[
				'client' => $flow->getClientName(),
				'instanceName' => $this->defaults->getName(),
				'urlGenerator' => $this->urlGenerator,
				'stateToken' => $stateToken,
			],
			'guest'
		);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 * @NoCSRFRequired
	 * @NoSameSiteCookieRequired
	 */
	public function grantPage(string $stateToken): StandaloneTemplateResponse {
		if (!$this->isValidStateToken($stateToken)) {
			return $this->stateTokenForbiddenResponse();
		}

		try {
			$flow = $this->getFlowByLoginToken();
		} catch (LoginFlowV2NotFoundException $e) {
			return $this->loginTokenForbiddenResponse();
		}

		return new StandaloneTemplateResponse(
			$this->appName,
			'loginflowv2/grant',
			[
				'client' => $flow->getClientName(),
				'instanceName' => $this->defaults->getName(),
				'urlGenerator' => $this->urlGenerator,
				'stateToken' => $stateToken,
			],
			'guest'
		);
	}

	/**
	 * @NoAdminRequired
	 * @UseSession
	 */
	public function generateAppPassword(string $stateToken): Response {
		if (!$this->isValidStateToken($stateToken)) {
			return $this->stateTokenForbiddenResponse();
		}

		try {
			$this->getFlowByLoginToken();
		} catch (LoginFlowV2NotFoundException $e) {
			return $this->loginTokenForbiddenResponse();
		}

		$loginToken = $this->session->get(self::TOKEN_NAME);

		// Clear session variables
		$this->session->remove(self::TOKEN_NAME);
		$this->session->remove(self::STATE_NAME);
		$sessionId = $this->session->getId();

		$result = $this->loginFlowV2Service->flowDone($loginToken, $sessionId, $this->getServerPath(), $this->userId);

		if ($result) {
			return new StandaloneTemplateResponse(
				$this->appName,
				'loginflowv2/done',
				[],
				'guest'
			);
		}

		$response = new StandaloneTemplateResponse(
			$this->appName,
			'403',
			[
				'message' => $this->l10n->t('Could not complete login'),
			],
			'guest'
		);
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	public function init(): JSONResponse {
		// Get client user agent
		$userAgent = $this->request->getHeader('USER_AGENT');

		$tokens = $this->loginFlowV2Service->createTokens($userAgent);

		$data = [
			'poll' => [
				'token' => $tokens->getPollToken(),
				'endpoint' => $this->urlGenerator->linkToRouteAbsolute('core.ClientFlowLoginV2.poll')
			],
			'login' => $this->urlGenerator->linkToRouteAbsolute('core.ClientFlowLoginV2.landing', ['token' => $tokens->getLoginToken()]),
		];

		return new JSONResponse($data);
	}

	private function isValidStateToken(string $stateToken): bool {
		$currentToken = $this->session->get(self::STATE_NAME);
		if (!is_string($stateToken) || !is_string($currentToken)) {
			return false;
		}
		return hash_equals($currentToken, $stateToken);
	}

	private function stateTokenForbiddenResponse(): StandaloneTemplateResponse {
		$response = new StandaloneTemplateResponse(
			$this->appName,
			'403',
			[
				'message' => $this->l10n->t('State token does not match'),
			],
			'guest'
		);
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}

	/**
	 * @return LoginFlowV2
	 * @throws LoginFlowV2NotFoundException
	 */
	private function getFlowByLoginToken(): LoginFlowV2 {
		$currentToken = $this->session->get(self::TOKEN_NAME);
		if (!is_string($currentToken)) {
			throw new LoginFlowV2NotFoundException('Login token not set in session');
		}

		return $this->loginFlowV2Service->getByLoginToken($currentToken);
	}

	private function loginTokenForbiddenResponse(): StandaloneTemplateResponse {
		$response = new StandaloneTemplateResponse(
			$this->appName,
			'403',
			[
				'message' => $this->l10n->t('Your login token is invalid or has expired'),
			],
			'guest'
		);
		$response->setStatus(Http::STATUS_FORBIDDEN);
		return $response;
	}

	private function getServerPath(): string {
		$serverPostfix = '';

		if (strpos($this->request->getRequestUri(), '/index.php') !== false) {
			$serverPostfix = substr($this->request->getRequestUri(), 0, strpos($this->request->getRequestUri(), '/index.php'));
		} elseif (strpos($this->request->getRequestUri(), '/login/v2') !== false) {
			$serverPostfix = substr($this->request->getRequestUri(), 0, strpos($this->request->getRequestUri(), '/login/v2'));
		}

		$protocol = $this->request->getServerProtocol();
		return $protocol . '://' . $this->request->getServerHost() . $serverPostfix;
	}
}
