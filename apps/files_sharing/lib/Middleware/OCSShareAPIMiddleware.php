<?php
/**
 *
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Files_Sharing\Middleware;

use OCA\Files_Sharing\Controller\ShareAPIController;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\IL10N;
use OCP\Share\IManager;

class OCSShareAPIMiddleware extends Middleware {
	/** @var IManager */
	private $shareManager;
	/** @var IL10N */
	private $l;

	public function __construct(IManager $shareManager,
								IL10N $l) {
		$this->shareManager = $shareManager;
		$this->l = $l;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 *
	 * @throws OCSNotFoundException
	 */
	public function beforeController($controller, $methodName) {
		if ($controller instanceof ShareAPIController) {
			if (!$this->shareManager->shareApiEnabled()) {
				throw new OCSNotFoundException($this->l->t('Share API is disabled'));
			}
		}
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @param Response $response
	 * @return Response
	 */
	public function afterController($controller, $methodName, Response $response) {
		if ($controller instanceof ShareAPIController) {
			/** @var ShareAPIController $controller */
			$controller->cleanup();
		}

		return $response;
	}
}
