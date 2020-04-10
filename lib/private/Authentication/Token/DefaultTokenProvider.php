<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Martin <github@diemattels.at>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Authentication\Token;

use Exception;
use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ICrypto;

class DefaultTokenProvider implements IProvider {

	/** @var DefaultTokenMapper */
	private $mapper;

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/** @var ILogger */
	private $logger;

	/** @var ITimeFactory */
	private $time;

	public function __construct(DefaultTokenMapper $mapper,
								ICrypto $crypto,
								IConfig $config,
								ILogger $logger,
								ITimeFactory $time) {
		$this->mapper = $mapper;
		$this->crypto = $crypto;
		$this->config = $config;
		$this->logger = $logger;
		$this->time = $time;
	}

	/**
	 * Create and persist a new token
	 *
	 * @param string $token
	 * @param string $uid
	 * @param string $loginName
	 * @param string|null $password
	 * @param string $name
	 * @param int $type token type
	 * @param int $remember whether the session token should be used for remember-me
	 * @return IToken
	 */
	public function generateToken(string $token,
								  string $uid,
								  string $loginName,
								  $password,
								  string $name,
								  int $type = IToken::TEMPORARY_TOKEN,
								  int $remember = IToken::DO_NOT_REMEMBER): IToken {
		$dbToken = new DefaultToken();
		$dbToken->setUid($uid);
		$dbToken->setLoginName($loginName);
		if (!is_null($password)) {
			$dbToken->setPassword($this->encryptPassword($password, $token));
		}
		$dbToken->setName($name);
		$dbToken->setToken($this->hashToken($token));
		$dbToken->setType($type);
		$dbToken->setRemember($remember);
		$dbToken->setLastActivity($this->time->getTime());
		$dbToken->setLastCheck($this->time->getTime());
		$dbToken->setVersion(DefaultToken::VERSION);

		$this->mapper->insert($dbToken);

		return $dbToken;
	}

	/**
	 * Save the updated token
	 *
	 * @param IToken $token
	 * @throws InvalidTokenException
	 */
	public function updateToken(IToken $token) {
		if (!($token instanceof DefaultToken)) {
			throw new InvalidTokenException();
		}
		$this->mapper->update($token);
	}

	/**
	 * Update token activity timestamp
	 *
	 * @throws InvalidTokenException
	 * @param IToken $token
	 */
	public function updateTokenActivity(IToken $token) {
		if (!($token instanceof DefaultToken)) {
			throw new InvalidTokenException();
		}
		/** @var DefaultToken $token */
		$now = $this->time->getTime();
		if ($token->getLastActivity() < ($now - 60)) {
			// Update token only once per minute
			$token->setLastActivity($now);
			$this->mapper->update($token);
		}
	}

	public function getTokenByUser(string $uid): array {
		return $this->mapper->getTokenByUser($uid);
	}

	/**
	 * Get a token by token
	 *
	 * @param string $tokenId
	 * @throws InvalidTokenException
	 * @throws ExpiredTokenException
	 * @return IToken
	 */
	public function getToken(string $tokenId): IToken {
		try {
			$token = $this->mapper->getToken($this->hashToken($tokenId));
		} catch (DoesNotExistException $ex) {
			throw new InvalidTokenException();
		}

		if ((int)$token->getExpires() !== 0 && $token->getExpires() < $this->time->getTime()) {
			throw new ExpiredTokenException($token);
		}

		return $token;
	}

	/**
	 * Get a token by token id
	 *
	 * @param int $tokenId
	 * @throws InvalidTokenException
	 * @throws ExpiredTokenException
	 * @return IToken
	 */
	public function getTokenById(int $tokenId): IToken {
		try {
			$token = $this->mapper->getTokenById($tokenId);
		} catch (DoesNotExistException $ex) {
			throw new InvalidTokenException();
		}

		if ((int)$token->getExpires() !== 0 && $token->getExpires() < $this->time->getTime()) {
			throw new ExpiredTokenException($token);
		}

		return $token;
	}

	/**
	 * @param string $oldSessionId
	 * @param string $sessionId
	 * @throws InvalidTokenException
	 * @return IToken
	 */
	public function renewSessionToken(string $oldSessionId, string $sessionId): IToken {
		$token = $this->getToken($oldSessionId);

		$newToken = new DefaultToken();
		$newToken->setUid($token->getUID());
		$newToken->setLoginName($token->getLoginName());
		if (!is_null($token->getPassword())) {
			$password = $this->decryptPassword($token->getPassword(), $oldSessionId);
			$newToken->setPassword($this->encryptPassword($password, $sessionId));
		}
		$newToken->setName($token->getName());
		$newToken->setToken($this->hashToken($sessionId));
		$newToken->setType(IToken::TEMPORARY_TOKEN);
		$newToken->setRemember($token->getRemember());
		$newToken->setLastActivity($this->time->getTime());
		$this->mapper->insert($newToken);
		$this->mapper->delete($token);

		return $newToken;
	}

	/**
	 * @param IToken $savedToken
	 * @param string $tokenId session token
	 * @throws InvalidTokenException
	 * @throws PasswordlessTokenException
	 * @return string
	 */
	public function getPassword(IToken $savedToken, string $tokenId): string {
		$password = $savedToken->getPassword();
		if (is_null($password)) {
			throw new PasswordlessTokenException();
		}
		return $this->decryptPassword($password, $tokenId);
	}

	/**
	 * Encrypt and set the password of the given token
	 *
	 * @param IToken $token
	 * @param string $tokenId
	 * @param string $password
	 * @throws InvalidTokenException
	 */
	public function setPassword(IToken $token, string $tokenId, string $password) {
		if (!($token instanceof DefaultToken)) {
			throw new InvalidTokenException();
		}
		/** @var DefaultToken $token */
		$token->setPassword($this->encryptPassword($password, $tokenId));
		$this->mapper->update($token);
	}

	/**
	 * Invalidate (delete) the given session token
	 *
	 * @param string $token
	 */
	public function invalidateToken(string $token) {
		$this->mapper->invalidate($this->hashToken($token));
	}

	public function invalidateTokenById(string $uid, int $id) {
		$this->mapper->deleteById($uid, $id);
	}

	/**
	 * Invalidate (delete) old session tokens
	 */
	public function invalidateOldTokens() {
		$olderThan = $this->time->getTime() - (int) $this->config->getSystemValue('session_lifetime', 60 * 60 * 24);
		$this->logger->debug('Invalidating session tokens older than ' . date('c', $olderThan), ['app' => 'cron']);
		$this->mapper->invalidateOld($olderThan, IToken::DO_NOT_REMEMBER);
		$rememberThreshold = $this->time->getTime() - (int) $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		$this->logger->debug('Invalidating remembered session tokens older than ' . date('c', $rememberThreshold), ['app' => 'cron']);
		$this->mapper->invalidateOld($rememberThreshold, IToken::REMEMBER);
	}

	/**
	 * Rotate the token. Usefull for for example oauth tokens
	 *
	 * @param IToken $token
	 * @param string $oldTokenId
	 * @param string $newTokenId
	 * @return IToken
	 */
	public function rotate(IToken $token, string $oldTokenId, string $newTokenId): IToken {
		try {
			$password = $this->getPassword($token, $oldTokenId);
			$token->setPassword($this->encryptPassword($password, $newTokenId));
		} catch (PasswordlessTokenException $e) {

		}

		$token->setToken($this->hashToken($newTokenId));
		$this->updateToken($token);

		return $token;
	}

	/**
	 * @param string $token
	 * @return string
	 */
	private function hashToken(string $token): string {
		$secret = $this->config->getSystemValue('secret');
		return hash('sha512', $token . $secret);
	}

	/**
	 * Encrypt the given password
	 *
	 * The token is used as key
	 *
	 * @param string $password
	 * @param string $token
	 * @return string encrypted password
	 */
	private function encryptPassword(string $password, string $token): string {
		$secret = $this->config->getSystemValue('secret');
		return $this->crypto->encrypt($password, $token . $secret);
	}

	/**
	 * Decrypt the given password
	 *
	 * The token is used as key
	 *
	 * @param string $password
	 * @param string $token
	 * @throws InvalidTokenException
	 * @return string the decrypted key
	 */
	private function decryptPassword(string $password, string $token): string {
		$secret = $this->config->getSystemValue('secret');
		try {
			return $this->crypto->decrypt($password, $token . $secret);
		} catch (Exception $ex) {
			// Delete the invalid token
			$this->invalidateToken($token);
			throw new InvalidTokenException();
		}
	}

	public function markPasswordInvalid(IToken $token, string $tokenId) {
		if (!($token instanceof DefaultToken)) {
			throw new InvalidTokenException();
		}

		//No need to mark as invalid. We just invalide default tokens
		$this->invalidateToken($tokenId);
	}

	public function updatePasswords(string $uid, string $password) {
		// Nothing to do here
	}
}
