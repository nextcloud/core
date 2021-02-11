<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Core\Controller;

use OC\CapabilitiesManager;
use OC\Security\IdentityProof\Manager;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class OCSController extends \OCP\AppFramework\OCSController {

	/** @var CapabilitiesManager */
	private $capabilitiesManager;
	/** @var IUserSession */
	private $userSession;
	/** @var IUserManager */
	private $userManager;
	/** @var Manager */
	private $keyManager;

	/**
	 * OCSController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param CapabilitiesManager $capabilitiesManager
	 * @param IUserSession $userSession
	 * @param IUserManager $userManager
	 * @param Manager $keyManager
	 */
	public function __construct($appName,
								IRequest $request,
								CapabilitiesManager $capabilitiesManager,
								IUserSession $userSession,
								IUserManager $userManager,
								Manager $keyManager) {
		parent::__construct($appName, $request);
		$this->capabilitiesManager = $capabilitiesManager;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->keyManager = $keyManager;
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function getConfig() {
		$data = [
			'version' => '1.7',
			'website' => 'Nextcloud',
			'host' => $this->request->getServerHost(),
			'contact' => '',
			'ssl' => 'false',
		];

		return new DataResponse($data);
	}

	/**
	 * @PublicPage
	 *
	 * @return DataResponse
	 */
	public function getCapabilities() {
		$result = [];
		list($major, $minor, $micro) = \OCP\Util::getVersion();
		$result['version'] = [
			'major' => $major,
			'minor' => $minor,
			'micro' => $micro,
			'string' => \OC_Util::getVersionString(),
			'edition' => '',
			'extendedSupport' => \OCP\Util::hasExtendedSupport()
		];

		if ($this->userSession->isLoggedIn()) {
			$result['capabilities'] = $this->capabilitiesManager->getCapabilities();
		} else {
			$result['capabilities'] = $this->capabilitiesManager->getCapabilities(true);
		}

		$response = new DataResponse($result);
		$response->setETag(md5(json_encode($result)));
		return $response;
	}

	/**
	 * @PublicPage
	 * @BruteForceProtection(action=login)
	 *
	 * @param string $login
	 * @param string $password
	 * @return DataResponse
	 */
	public function personCheck($login = '', $password = '') {
		if ($login !== '' && $password !== '') {
			if ($this->userManager->checkPassword($login, $password)) {
				return new DataResponse([
					'person' => [
						'personid' => $login
					]
				]);
			}

			$response = new DataResponse([], 102);
			$response->throttle();
			return $response;
		}
		return new DataResponse([], 101);
	}

	/**
	 * @PublicPage
	 *
	 * @param string $cloudId
	 * @return DataResponse
	 */
	public function getIdentityProof($cloudId) {
		$userObject = $this->userManager->get($cloudId);

		if ($userObject !== null) {
			$key = $this->keyManager->getKey($userObject);
			$data = [
				'public' => $key->getPublic(),
			];
			return new DataResponse($data);
		}

		return new DataResponse(['User not found'], 404);
	}
}
