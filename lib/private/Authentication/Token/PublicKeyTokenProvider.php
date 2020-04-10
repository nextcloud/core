<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Authentication\Token;

use OC\Authentication\Exceptions\ExpiredTokenException;
use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\TokenPasswordExpiredException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OC\Authentication\Exceptions\WipeTokenException;
use OC\Cache\CappedMemoryCache;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Security\ICrypto;

class PublicKeyTokenProvider implements IProvider {
	/** @var PublicKeyTokenMapper */
	private $mapper;

	/** @var ICrypto */
	private $crypto;

	/** @var IConfig */
	private $config;

	/** @var ILogger $logger */
	private $logger;

	/** @var ITimeFactory $time */
	private $time;

	/** @var CappedMemoryCache */
	private $cache;

	public function __construct(PublicKeyTokenMapper $mapper,
								ICrypto $crypto,
								IConfig $config,
								ILogger $logger,
								ITimeFactory $time) {
		$this->mapper = $mapper;
		$this->crypto = $crypto;
		$this->config = $config;
		$this->logger = $logger;
		$this->time = $time;

		$this->cache = new CappedMemoryCache();
	}

	/**
	 * {@inheritDoc}
	 */
	public function generateToken(string $token,
								  string $uid,
								  string $loginName,
								  $password,
								  string $name,
								  int $type = IToken::TEMPORARY_TOKEN,
								  int $remember = IToken::DO_NOT_REMEMBER): IToken {
		$dbToken = $this->newToken($token, $uid, $loginName, $password, $name, $type, $remember);
		$this->mapper->insert($dbToken);

		// Add the token to the cache
		$this->cache[$dbToken->getToken()] = $dbToken;

		return $dbToken;
	}

	public function getToken(string $tokenId): IToken {
		$tokenHash = $this->hashToken($tokenId);

		if (isset($this->cache[$tokenHash])) {
			$token = $this->cache[$tokenHash];
		} else {
			try {
				$token = $this->mapper->getToken($this->hashToken($tokenId));
				$this->cache[$token->getToken()] = $token;
			} catch (DoesNotExistException $ex) {
				throw new InvalidTokenException();
			}
		}

		if ((int)$token->getExpires() !== 0 && $token->getExpires() < $this->time->getTime()) {
			throw new ExpiredTokenException($token);
		}

		if ($token->getType() === IToken::WIPE_TOKEN) {
			throw new WipeTokenException($token);
		}

		if ($token->getPasswordInvalid() === true) {
			//The password is invalid we should throw an TokenPasswordExpiredException
			throw new TokenPasswordExpiredException($token);
		}

		return $token;
	}

	public function getTokenById(int $tokenId): IToken {
		try {
			$token = $this->mapper->getTokenById($tokenId);
		} catch (DoesNotExistException $ex) {
			throw new InvalidTokenException();
		}

		if ((int)$token->getExpires() !== 0 && $token->getExpires() < $this->time->getTime()) {
			throw new ExpiredTokenException($token);
		}

		if ($token->getType() === IToken::WIPE_TOKEN) {
			throw new WipeTokenException($token);
		}

		if ($token->getPasswordInvalid() === true) {
			//The password is invalid we should throw an TokenPasswordExpiredException
			throw new TokenPasswordExpiredException($token);
		}

		return $token;
	}

	public function renewSessionToken(string $oldSessionId, string $sessionId): IToken {
		$this->cache->clear();

		$token = $this->getToken($oldSessionId);

		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}

		$password = null;
		if (!is_null($token->getPassword())) {
			$privateKey = $this->decrypt($token->getPrivateKey(), $oldSessionId);
			$password = $this->decryptPassword($token->getPassword(), $privateKey);
		}

		$newToken = $this->generateToken(
			$sessionId,
			$token->getUID(),
			$token->getLoginName(),
			$password,
			$token->getName(),
			IToken::TEMPORARY_TOKEN,
			$token->getRemember()
		);

		$this->mapper->delete($token);

		return $newToken;
	}

	public function invalidateToken(string $token) {
		$this->cache->clear();

		$this->mapper->invalidate($this->hashToken($token));
	}

	public function invalidateTokenById(string $uid, int $id) {
		$this->cache->clear();

		$this->mapper->deleteById($uid, $id);
	}

	public function invalidateOldTokens() {
		$this->cache->clear();

		$olderThan = $this->time->getTime() - (int) $this->config->getSystemValue('session_lifetime', 60 * 60 * 24);
		$this->logger->debug('Invalidating session tokens older than ' . date('c', $olderThan), ['app' => 'cron']);
		$this->mapper->invalidateOld($olderThan, IToken::DO_NOT_REMEMBER);
		$rememberThreshold = $this->time->getTime() - (int) $this->config->getSystemValue('remember_login_cookie_lifetime', 60 * 60 * 24 * 15);
		$this->logger->debug('Invalidating remembered session tokens older than ' . date('c', $rememberThreshold), ['app' => 'cron']);
		$this->mapper->invalidateOld($rememberThreshold, IToken::REMEMBER);
	}

	public function updateToken(IToken $token) {
		$this->cache->clear();

		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}
		$this->mapper->update($token);
	}

	public function updateTokenActivity(IToken $token) {
		$this->cache->clear();

		if (!($token instanceof PublicKeyToken)) {
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

	public function getPassword(IToken $token, string $tokenId): string {
		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}

		if ($token->getPassword() === null) {
			throw new PasswordlessTokenException();
		}

		// Decrypt private key with tokenId
		$privateKey = $this->decrypt($token->getPrivateKey(), $tokenId);

		// Decrypt password with private key
		return $this->decryptPassword($token->getPassword(), $privateKey);
	}

	public function setPassword(IToken $token, string $tokenId, string $password) {
		$this->cache->clear();

		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}

		// When changing passwords all temp tokens are deleted
		$this->mapper->deleteTempToken($token);

		// Update the password for all tokens
		$tokens = $this->mapper->getTokenByUser($token->getUID());
		foreach ($tokens as $t) {
			$publicKey = $t->getPublicKey();
			$t->setPassword($this->encryptPassword($password, $publicKey));
			$this->updateToken($t);
		}
	}

	public function rotate(IToken $token, string $oldTokenId, string $newTokenId): IToken {
		$this->cache->clear();

		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}

		// Decrypt private key with oldTokenId
		$privateKey = $this->decrypt($token->getPrivateKey(), $oldTokenId);
		// Encrypt with the new token
		$token->setPrivateKey($this->encrypt($privateKey, $newTokenId));

		$token->setToken($this->hashToken($newTokenId));
		$this->updateToken($token);

		return $token;
	}

	private function encrypt(string $plaintext, string $token): string {
		$secret = $this->config->getSystemValue('secret');
		return $this->crypto->encrypt($plaintext, $token . $secret);
	}

	/**
	 * @throws InvalidTokenException
	 */
	private function decrypt(string $cipherText, string $token): string {
		$secret = $this->config->getSystemValue('secret');
		try {
			return $this->crypto->decrypt($cipherText, $token . $secret);
		} catch (\Exception $ex) {
			// Delete the invalid token
			$this->invalidateToken($token);
			throw new InvalidTokenException();
		}
	}

	private function encryptPassword(string $password, string $publicKey): string {
		openssl_public_encrypt($password, $encryptedPassword, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
		$encryptedPassword = base64_encode($encryptedPassword);

		return $encryptedPassword;
	}

	private function decryptPassword(string $encryptedPassword, string $privateKey): string {
		$encryptedPassword = base64_decode($encryptedPassword);
		openssl_private_decrypt($encryptedPassword, $password, $privateKey, OPENSSL_PKCS1_OAEP_PADDING);

		return $password;
	}

	private function hashToken(string $token): string {
		$secret = $this->config->getSystemValue('secret');
		return hash('sha512', $token . $secret);
	}

	/**
	 * Convert a DefaultToken to a publicKeyToken
	 * This will also be updated directly in the Database
	 * @throws \RuntimeException when OpenSSL reports a problem
	 */
	public function convertToken(DefaultToken $defaultToken, string $token, $password): PublicKeyToken {
		$this->cache->clear();

		$pkToken = $this->newToken(
			$token,
			$defaultToken->getUID(),
			$defaultToken->getLoginName(),
			$password,
			$defaultToken->getName(),
			$defaultToken->getType(),
			$defaultToken->getRemember()
		);

		$pkToken->setExpires($defaultToken->getExpires());
		$pkToken->setId($defaultToken->getId());

		return $this->mapper->update($pkToken);
	}

	/**
	 * @throws \RuntimeException when OpenSSL reports a problem
	 */
	private function newToken(string $token,
							  string $uid,
							  string $loginName,
							  $password,
							  string $name,
							  int $type,
							  int $remember): PublicKeyToken {
		$dbToken = new PublicKeyToken();
		$dbToken->setUid($uid);
		$dbToken->setLoginName($loginName);

		$config = array_merge([
			'digest_alg' => 'sha512',
			'private_key_bits' => 2048,
		], $this->config->getSystemValue('openssl', []));

		// Generate new key
		$res = openssl_pkey_new($config);
		if ($res === false) {
			$this->logOpensslError();
			throw new \RuntimeException('OpenSSL reported a problem');
		}

		if (openssl_pkey_export($res, $privateKey, null, $config) === false) {
			$this->logOpensslError();
			throw new \RuntimeException('OpenSSL reported a problem');
		}

		// Extract the public key from $res to $pubKey
		$publicKey = openssl_pkey_get_details($res);
		$publicKey = $publicKey['key'];

		$dbToken->setPublicKey($publicKey);
		$dbToken->setPrivateKey($this->encrypt($privateKey, $token));

		if (!is_null($password)) {
			$dbToken->setPassword($this->encryptPassword($password, $publicKey));
		}

		$dbToken->setName($name);
		$dbToken->setToken($this->hashToken($token));
		$dbToken->setType($type);
		$dbToken->setRemember($remember);
		$dbToken->setLastActivity($this->time->getTime());
		$dbToken->setLastCheck($this->time->getTime());
		$dbToken->setVersion(PublicKeyToken::VERSION);

		return $dbToken;
	}

	public function markPasswordInvalid(IToken $token, string $tokenId) {
		$this->cache->clear();

		if (!($token instanceof PublicKeyToken)) {
			throw new InvalidTokenException();
		}

		$token->setPasswordInvalid(true);
		$this->mapper->update($token);
	}

	public function updatePasswords(string $uid, string $password) {
		$this->cache->clear();

		if (!$this->mapper->hasExpiredTokens($uid)) {
			// Nothing to do here
			return;
		}

		// Update the password for all tokens
		$tokens = $this->mapper->getTokenByUser($uid);
		foreach ($tokens as $t) {
			$publicKey = $t->getPublicKey();
			$t->setPassword($this->encryptPassword($password, $publicKey));
			$this->updateToken($t);
		}
	}

	private function logOpensslError() {
		$errors = [];
		while ($error = openssl_error_string()) {
			$errors[] = $error;
		}
		$this->logger->critical('Something is wrong with your openssl setup: ' . implode(', ', $errors));
	}
}
