<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2019 Janis Köhr <janiskoehr@icloud.com>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Accessibility\Settings;

use OCA\Accessibility\AccessibilityProvider;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {

	/** @var string */
	protected $appName;

	/** @var IConfig */
	private $config;

	/** @var IUserSession */
	private $userSession;

	/** @var IL10N */
	private $l;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var AccessibilityProvider */
	private $accessibilityProvider;

	/** @var IInitialStateService */
	private $initialStateService;

	/**
	 * Settings constructor.
	 *
	 * @param string $appName
	 * @param IConfig $config
	 * @param IUserSession $userSession
	 * @param IL10N $l
	 * @param IURLGenerator $urlGenerator
	 * @param AccessibilityProvider $accessibilityProvider
	 */
	public function __construct(string $appName,
								IConfig $config,
								IUserSession $userSession,
								IL10N $l,
								IURLGenerator $urlGenerator,
								AccessibilityProvider $accessibilityProvider,
								IInitialStateService $initialStateService) {
		$this->appName               = $appName;
		$this->config                = $config;
		$this->userSession           = $userSession;
		$this->l                     = $l;
		$this->urlGenerator          = $urlGenerator;
		$this->accessibilityProvider = $accessibilityProvider;
		$this->initialStateService   = $initialStateService;
	}

	/**
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	public function getForm() {
		Util::addScript('accessibility', 'accessibility');
		Util::addStyle('accessibility', 'style');

		$availableConfig = [
			'themes' => $this->accessibilityProvider->getThemes(),
			'fonts'  => $this->accessibilityProvider->getFonts(),
			'highcontrast' => $this->accessibilityProvider->getHighContrast()
		];

		$userConfig = [
			'highcontrast' => $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'highcontrast', false),
			'theme'  => $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'theme', false),
			'font'   => $this->config->getUserValue($this->userSession->getUser()->getUID(), $this->appName, 'font', false)
		];

		$this->initialStateService->provideInitialState($this->appName, 'available-config', $availableConfig);
		$this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);

		return new TemplateResponse($this->appName, 'settings-personal');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	public function getSection() {
		return $this->appName;
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 * @since 9.1
	 */
	public function getPriority() {
		return 40;
	}
}
