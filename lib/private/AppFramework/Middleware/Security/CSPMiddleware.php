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

namespace OC\AppFramework\Middleware\Security;

use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OC\Security\CSRF\CsrfTokenManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\EmptyContentSecurityPolicy;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;

class CSPMiddleware extends Middleware {

	/** @var ContentSecurityPolicyManager */
	private $contentSecurityPolicyManager;
	/** @var ContentSecurityPolicyNonceManager */
	private $cspNonceManager;
	/** @var CsrfTokenManager */
	private $csrfTokenManager;

	public function __construct(ContentSecurityPolicyManager $policyManager,
								ContentSecurityPolicyNonceManager $cspNonceManager,
								CsrfTokenManager $csrfTokenManager) {
		$this->contentSecurityPolicyManager = $policyManager;
		$this->cspNonceManager = $cspNonceManager;
		$this->csrfTokenManager = $csrfTokenManager;
	}

	/**
	 * Performs the default CSP modifications that may be injected by other
	 * applications
	 *
	 * @param Controller $controller
	 * @param string $methodName
	 * @param Response $response
	 * @return Response
	 */
	public function afterController($controller, $methodName, Response $response): Response {
		$policy = !is_null($response->getContentSecurityPolicy()) ? $response->getContentSecurityPolicy() : new ContentSecurityPolicy();

		if (get_class($policy) === EmptyContentSecurityPolicy::class) {
			return $response;
		}

		$defaultPolicy = $this->contentSecurityPolicyManager->getDefaultPolicy();
		$defaultPolicy = $this->contentSecurityPolicyManager->mergePolicies($defaultPolicy, $policy);

		if($this->cspNonceManager->browserSupportsCspV3()) {
			$defaultPolicy->useJsNonce($this->csrfTokenManager->getToken()->getEncryptedValue());
		}

		$response->setContentSecurityPolicy($defaultPolicy);

		return $response;
	}
}
