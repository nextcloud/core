<?php

declare(strict_types=1);

/**
 * @copyright 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Authentication\LoginCredentials;

use OC\Authentication\Exceptions\InvalidTokenException;
use OC\Authentication\Exceptions\PasswordlessTokenException;
use OC\Authentication\Token\IProvider;
use OCP\Authentication\Exceptions\CredentialsUnavailableException;
use OCP\Authentication\LoginCredentials\ICredentials;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\ISession;
use OCP\Session\Exceptions\SessionNotAvailableException;
use OCP\Util;
use Psr\Log\LoggerInterface;

class Store implements IStore {

	/** @var ISession */
	private $session;

	/** @var LoggerInterface */
	private $logger;

	/** @var IProvider|null */
	private $tokenProvider;

	public function __construct(ISession $session,
								LoggerInterface $logger,
								IProvider $tokenProvider = null) {
		$this->session = $session;
		$this->logger = $logger;
		$this->tokenProvider = $tokenProvider;

		Util::connectHook('OC_User', 'post_login', $this, 'authenticate');
	}

	/**
	 * Hook listener on post login
	 *
	 * @param array $params
	 */
	public function authenticate(array $params) {
		$this->session->set('login_credentials', json_encode($params));
	}

	/**
	 * Replace the session implementation
	 *
	 * @param ISession $session
	 */
	public function setSession(ISession $session) {
		$this->session = $session;
	}

	/**
	 * @since 12
	 *
	 * @return ICredentials the login credentials of the current user
	 * @throws CredentialsUnavailableException
	 */
	public function getLoginCredentials(): ICredentials {
		if ($this->tokenProvider === null) {
			throw new CredentialsUnavailableException();
		}

		$trySession = false;
		try {
			$sessionId = $this->session->getId();
			$token = $this->tokenProvider->getToken($sessionId);

			$uid = $token->getUID();
			$user = $token->getLoginName();
			$password = $this->tokenProvider->getPassword($token, $sessionId);

			return new Credentials($uid, $user, $password);
		} catch (SessionNotAvailableException $ex) {
			$this->logger->debug('could not get login credentials because session is unavailable', ['app' => 'core']);
		} catch (InvalidTokenException $ex) {
			$this->logger->debug('could not get login credentials because the token is invalid', ['app' => 'core']);
			$trySession = true;
		} catch (PasswordlessTokenException $ex) {
			$this->logger->debug('could not get login credentials because the token has no password', ['app' => 'core']);
			$trySession = true;
		}

		if ($trySession && $this->session->exists('login_credentials')) {
			/** @var array $creds */
			$creds = json_decode($this->session->get('login_credentials'), true);
			return new Credentials(
				$creds['uid'],
				$creds['loginName'] ?? $this->session->get('loginname') ?? $creds['uid'], // Pre 20 didn't have a loginName property, hence fall back to the session value and then to the UID
				$creds['password']
			);
		}

		// If we reach this line, an exception was thrown.
		throw new CredentialsUnavailableException();
	}
}
