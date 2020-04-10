<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\OAuth2\Controller;

use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Token\IProvider as TokenProvider;
use OC\Security\Bruteforce\Throttler;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Exceptions\AccessTokenNotFoundException;
use OCA\OAuth2\Exceptions\ClientNotFoundException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

class OauthApiController extends Controller {
	/** @var AccessTokenMapper */
	private $accessTokenMapper;
	/** @var ClientMapper */
	private $clientMapper;
	/** @var ICrypto */
	private $crypto;
	/** @var TokenProvider */
	private $tokenProvider;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var ITimeFactory */
	private $time;
	/** @var Throttler */
	private $throttler;

	public function __construct(string $appName,
								IRequest $request,
								ICrypto $crypto,
								AccessTokenMapper $accessTokenMapper,
								ClientMapper $clientMapper,
								TokenProvider $tokenProvider,
								ISecureRandom $secureRandom,
								ITimeFactory $time,
								Throttler $throttler) {
		parent::__construct($appName, $request);
		$this->crypto = $crypto;
		$this->accessTokenMapper = $accessTokenMapper;
		$this->clientMapper = $clientMapper;
		$this->tokenProvider = $tokenProvider;
		$this->secureRandom = $secureRandom;
		$this->time = $time;
		$this->throttler = $throttler;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $grant_type
	 * @param string $code
	 * @param string $refresh_token
	 * @param string $client_id
	 * @param string $client_secret
	 * @return JSONResponse
	 */
	public function getToken($grant_type, $code, $refresh_token, $client_id, $client_secret): JSONResponse {

		// We only handle two types
		if ($grant_type !== 'authorization_code' && $grant_type !== 'refresh_token') {
			return new JSONResponse([
				'error' => 'invalid_grant',
			], Http::STATUS_BAD_REQUEST);
		}

		// We handle the initial and refresh tokens the same way
		if ($grant_type === 'refresh_token') {
			$code = $refresh_token;
		}

		try {
			$accessToken = $this->accessTokenMapper->getByCode($code);
		} catch (AccessTokenNotFoundException $e) {
			return new JSONResponse([
				'error' => 'invalid_request',
			], Http::STATUS_BAD_REQUEST);
		}

		try {
			$client = $this->clientMapper->getByUid($accessToken->getClientId());
		} catch (ClientNotFoundException $e) {
			return new JSONResponse([
				'error' => 'invalid_request',
			], Http::STATUS_BAD_REQUEST);
		}

		if (isset($this->request->server['PHP_AUTH_USER'])) {
			$client_id = $this->request->server['PHP_AUTH_USER'];
			$client_secret = $this->request->server['PHP_AUTH_PW'];
		}

		// The client id and secret must match. Else we don't provide an access token!
		if ($client->getClientIdentifier() !== $client_id || $client->getSecret() !== $client_secret) {
			return new JSONResponse([
				'error' => 'invalid_client',
			], Http::STATUS_BAD_REQUEST);
		}

		$decryptedToken = $this->crypto->decrypt($accessToken->getEncryptedToken(), $code);

		// Obtain the appToken assoicated
		try {
			$appToken = $this->tokenProvider->getTokenById($accessToken->getTokenId());
		} catch (ExpiredTokenException $e) {
			$appToken = $e->getToken();
		} catch (InvalidTokenException $e) {
			//We can't do anything...
			$this->accessTokenMapper->delete($accessToken);
			return new JSONResponse([
				'error' => 'invalid_request',
			], Http::STATUS_BAD_REQUEST);
		}

		// Rotate the apptoken (so the old one becomes invalid basically)
		$newToken = $this->secureRandom->generate(72, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS);

		$appToken = $this->tokenProvider->rotate(
			$appToken,
			$decryptedToken,
			$newToken
		);

		// Expiration is in 1 hour again
		$appToken->setExpires($this->time->getTime() + 3600);
		$this->tokenProvider->updateToken($appToken);

		// Generate a new refresh token and encrypt the new apptoken in the DB
		$newCode = $this->secureRandom->generate(128, ISecureRandom::CHAR_UPPER.ISecureRandom::CHAR_LOWER.ISecureRandom::CHAR_DIGITS);
		$accessToken->setHashedCode(hash('sha512', $newCode));
		$accessToken->setEncryptedToken($this->crypto->encrypt($newToken, $newCode));
		$this->accessTokenMapper->update($accessToken);

		$this->throttler->resetDelay($this->request->getRemoteAddress(), 'login', ['user' => $appToken->getUID()]);

		return new JSONResponse(
			[
				'access_token' => $newToken,
				'token_type' => 'Bearer',
				'expires_in' => 3600,
				'refresh_token' => $newCode,
				'user_id' => $appToken->getUID(),
			]
		);
	}
}
