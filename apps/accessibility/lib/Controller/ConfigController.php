<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2019 Janis Köhr <janiskoehr@icloud.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Janis Köhr <janis.koehr@novatec-gmbh.de>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Accessibility\Controller;

use OCA\Accessibility\AccessibilityProvider;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCSController;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;

class ConfigController extends OCSController {

	/** @var string */
	protected $appName;

	/** @var string */
	protected $userId;

	/** @var string */
	protected $serverRoot;

	/** @var IConfig */
	private $config;

	/** @var IUserSession */
	private $userSession;

	/** @var AccessibilityProvider */
	private $accessibilityProvider;

	/**
	 * Config constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param IUserSession $userSession
	 * @param AccessibilityProvider $accessibilityProvider
	 */
	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IUserSession $userSession,
								AccessibilityProvider $accessibilityProvider) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->config = $config;
		$this->userSession = $userSession;
		$this->accessibilityProvider = $accessibilityProvider;
		$this->userId = $userSession->getUser()->getUID();
	}

	/**
	 * @NoAdminRequired
	 *
	 * Get user accessibility config
	 *
	 * @param string $key theme or font
	 * @return DataResponse
	 */
	public function getConfig(): DataResponse {
		return new DataResponse([
			'highcontrast' => $this->config->getUserValue($this->userId, $this->appName, 'highcontrast', false),
			'theme' => $this->config->getUserValue($this->userId, $this->appName, 'theme', false),
			'font' => $this->config->getUserValue($this->userId, $this->appName, 'font', false)
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Set theme or font config
	 *
	 * @param string $key theme or font
	 * @return DataResponse
	 * @throws OCSBadRequestException|PreConditionNotMetException
	 */
	public function setConfig(string $key, $value): DataResponse {
		if ($key === 'theme' || $key === 'font' || $key === 'highcontrast') {
			if ($value === false || $value === '') {
				throw new OCSBadRequestException('Invalid value: ' . $value);
			}

			$themes = $this->accessibilityProvider->getThemes();
			$highcontrast = [$this->accessibilityProvider->getHighContrast()];
			$fonts = $this->accessibilityProvider->getFonts();

			$availableOptions = array_map(function ($option) {
				return $option['id'];
			}, array_merge($themes, $highcontrast, $fonts));

			if (in_array($value, $availableOptions)) {
				$this->config->setUserValue($this->userId, $this->appName, $key, $value);
				return new DataResponse();
			}

			throw new OCSBadRequestException('Invalid value: ' . $value);
		}

		throw new OCSBadRequestException('Invalid key: ' . $key);
	}

	/**
	 * @NoAdminRequired
	 *
	 * Unset theme or font config
	 *
	 * @param string $key theme or font
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 */
	public function deleteConfig(string $key): DataResponse {
		if ($key === 'theme' || $key === 'font' || $key === 'highcontrast') {
			$this->config->deleteUserValue($this->userId, $this->appName, $key);
			$userValues = $this->config->getUserKeys($this->userId, $this->appName);

			// remove hash if no settings selected
			if (count($userValues) === 1 && $userValues[0] === 'icons-css') {
				$this->config->deleteUserValue($this->userId, $this->appName, 'icons-css');
			}

			return new DataResponse();
		}

		throw new OCSBadRequestException('Invalid key: ' . $key);
	}
}
