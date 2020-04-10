<?php
/**
 * @copyright Copyright (c) 2017 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\DAV\Connector\Sabre;

use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use Sabre\DAV\Auth\Backend\AbstractBearer;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class BearerAuth extends AbstractBearer {
	/** @var IUserSession */
	private $userSession;
	/** @var ISession */
	private $session;
	/** @var IRequest */
	private $request;
	/** @var string */
	private $principalPrefix;

	/**
	 * @param IUserSession $userSession
	 * @param ISession $session
	 * @param string $principalPrefix
	 * @param IRequest $request
	 */
	public function __construct(IUserSession $userSession,
								ISession $session,
								IRequest $request,
								$principalPrefix = 'principals/users/') {
		$this->userSession = $userSession;
		$this->session = $session;
		$this->request = $request;
		$this->principalPrefix = $principalPrefix;

		// setup realm
		$defaults = new \OCP\Defaults();
		$this->realm = $defaults->getName();
	}

	private function setupUserFs($userId) {
		\OC_Util::setupFS($userId);
		$this->session->close();
		return $this->principalPrefix . $userId;
	}

	/**
	 * {@inheritdoc}
	 */
	public function validateBearerToken($bearerToken) {
		\OC_Util::setupFS();

		if(!$this->userSession->isLoggedIn()) {
			$this->userSession->tryTokenLogin($this->request);
		}
		if($this->userSession->isLoggedIn()) {
			return $this->setupUserFs($this->userSession->getUser()->getUID());
		}

		return false;
	}

	/**
	 * \Sabre\DAV\Auth\Backend\AbstractBearer::challenge sets an WWW-Authenticate
	 * header which some DAV clients can't handle. Thus we override this function
	 * and make it simply return a 401.
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 */
	public function challenge(RequestInterface $request, ResponseInterface $response) {
		$response->setStatus(401);
	}
}
