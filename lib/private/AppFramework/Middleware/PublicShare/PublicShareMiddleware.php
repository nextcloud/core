<?php
/**
 *
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
namespace OC\AppFramework\Middleware\PublicShare;

use OC\AppFramework\Middleware\PublicShare\Exceptions\NeedAuthenticationException;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\PublicShareController;
use OCP\Files\NotFoundException;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;

class PublicShareMiddleware extends Middleware {
	/** @var IRequest */
	private $request;

	/** @var ISession */
	private $session;

	/** @var IConfig */
	private $config;

	public function __construct(IRequest $request, ISession $session, IConfig $config) {
		$this->request = $request;
		$this->session = $session;
		$this->config = $config;
	}

	public function beforeController($controller, $methodName) {
		if (!($controller instanceof PublicShareController)) {
			return;
		}

		if (!$this->isLinkSharingEnabled()) {
			throw new NotFoundException('Link sharing is disabled');
		}

		// We require the token parameter to be set
		$token = $this->request->getParam('token');
		if ($token === null) {
			throw new NotFoundException();
		}

		// Set the token
		$controller->setToken($token);

		if (!$controller->isValidToken()) {
			$controller->shareNotFound();
			throw new NotFoundException();
		}

		// No need to check for authentication when we try to authenticate
		if ($methodName === 'authenticate' || $methodName === 'showAuthenticate') {
			return;
		}

		// If authentication succeeds just continue
		if ($controller->isAuthenticated()) {
			return;
		}

		// If we can authenticate to this controller do it else we throw a 404 to not leak any info
		if ($controller instanceof AuthPublicShareController) {
			$this->session->set('public_link_authenticate_redirect', json_encode($this->request->getParams()));
			throw new NeedAuthenticationException();
		}

		throw new NotFoundException();

	}

	public function afterException($controller, $methodName, \Exception $exception) {
		if (!($controller instanceof PublicShareController)) {
			throw $exception;
		}

		if ($exception instanceof NotFoundException) {
			return new NotFoundResponse();
		}

		if ($controller instanceof AuthPublicShareController && $exception instanceof NeedAuthenticationException) {
			return $controller->getAuthenticationRedirect($this->getFunctionForRoute($this->request->getParam('_route')));
		}

		throw $exception;
	}

	private function getFunctionForRoute(string $route): string {
		$tmp = explode('.', $route);
		return array_pop($tmp);
	}

	/**
	 * Check if link sharing is allowed
	 */
	private function isLinkSharingEnabled(): bool {
		// Check if the shareAPI is enabled
		if ($this->config->getAppValue('core', 'shareapi_enabled', 'yes') !== 'yes') {
			return false;
		}

		// Check whether public sharing is enabled
		if($this->config->getAppValue('core', 'shareapi_allow_links', 'yes') !== 'yes') {
			return false;
		}

		return true;
	}
}
