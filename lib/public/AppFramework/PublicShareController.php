<?php
/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
declare(strict_types=1);

namespace OCP\AppFramework;

use OCP\IRequest;
use OCP\ISession;

/**
 * Base controller for public shares
 *
 * It will verify if the user is properly authenticated to the share. If not a 404
 * is thrown by the PublicShareMiddleware.
 *
 * Use this for example for a controller that is not to be called via a webbrowser
 * directly. For example a PublicPreviewController. As this is not meant to be
 * called by a user direclty.
 *
 * To show an auth page extend the AuthPublicShareController
 *
 * @since 14.0.0
 */
abstract class PublicShareController extends Controller {

	/** @var ISession */
	protected $session;

	/** @var string */
	private $token;

	/**
	 * @since 14.0.0
	 */
	public function __construct(string $appName,
								IRequest $request,
								ISession $session) {
		parent::__construct($appName, $request);

		$this->session = $session;
	}

	/**
	 * Middleware set the token for the request
	 *
	 * @since 14.0.0
	 */
	final public function setToken(string $token) {
		$this->token = $token;
	}

	/**
	 * Get the token for this request
	 *
	 * @since 14.0.0
	 */
	final public function getToken(): string {
		return $this->token;
	}

	/**
	 * Get a hash of the password for this share
	 *
	 * To ensure access is blocked when the password to a share is changed we store
	 * a hash of the password for this token.
	 *
	 * @since 14.0.0
	 */
	abstract protected function getPasswordHash(): string;

	/**
	 * Is the provided token a valid token
	 *
	 * This function is already called from the middleware directly after setting the token.
	 *
	 * @since 14.0.0
	 */
	abstract public function isValidToken(): bool;

	/**
	 * Is a share with this token password protected
	 *
	 * @since 14.0.0
	 */
	abstract protected function isPasswordProtected(): bool;

	/**
	 * Check if a share is authenticated or not
	 *
	 * @since 14.0.0
	 */
	final public function isAuthenticated(): bool {
		// Always authenticated against non password protected shares
		if (!$this->isPasswordProtected()) {
			return true;
		}

		// If we are authenticated properly
		if ($this->session->get('public_link_authenticated_token') === $this->getToken() &&
			$this->session->get('public_link_authenticated_password_hash') === $this->getPasswordHash()) {
			return true;
		}

		// Fail by default if nothing matches
		return false;
	}

	/**
	 * Function called if the share is not found.
	 *
	 * You can use this to do some logging for example
	 *
	 * @since 14.0.0
	 */
	public function shareNotFound() {

	}
}
