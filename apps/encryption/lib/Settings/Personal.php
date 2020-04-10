<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Encryption\Settings;

use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Personal implements  ISettings {

	/** @var IConfig */
	private $config;
	/** @var Session */
	private $session;
	/** @var Util */
	private $util;
	/** @var IUserSession */
	private $userSession;

	public function __construct(IConfig $config, Session $session, Util $util, IUserSession $userSession) {
		$this->config = $config;
		$this->session = $session;
		$this->util = $util;
		$this->userSession = $userSession;
	}

	/**
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	public function getForm() {
		$recoveryAdminEnabled = $this->config->getAppValue('encryption', 'recoveryAdminEnabled');
		$privateKeySet = $this->session->isPrivateKeySet();

		if (!$recoveryAdminEnabled && $privateKeySet) {
			return new TemplateResponse('settings', 'settings/empty', [], '');
		}

		$userId = $this->userSession->getUser()->getUID();
		$recoveryEnabledForUser = $this->util->isRecoveryEnabledForUser($userId);

		$parameters = [
			'recoveryEnabled' => $recoveryAdminEnabled,
			'recoveryEnabledForUser' => $recoveryEnabledForUser,
			'privateKeySet' => $privateKeySet,
			'initialized' => $this->session->getStatus(),
		];
		return new TemplateResponse('encryption', 'settings-personal', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	public function getSection() {
		return 'security';
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
		return 80;
	}
}
