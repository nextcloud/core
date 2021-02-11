<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Encryption\Settings;

use OC\Files\View;
use OCA\Encryption\Crypto\Crypt;
use OCA\Encryption\Session;
use OCA\Encryption\Util;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\ISession;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Admin implements ISettings {

	/** @var IL10N */
	private $l;

	/** @var ILogger */
	private $logger;

	/** @var IUserSession */
	private $userSession;

	/** @var IConfig */
	private $config;

	/** @var IUserManager */
	private $userManager;

	/** @var ISession */
	private $session;

	public function __construct(
		IL10N $l,
		ILogger $logger,
		IUserSession $userSession,
		IConfig $config,
		IUserManager $userManager,
		ISession $session
	) {
		$this->l = $l;
		$this->logger = $logger;
		$this->userSession = $userSession;
		$this->config = $config;
		$this->userManager = $userManager;
		$this->session = $session;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$crypt = new Crypt(
			$this->logger,
			$this->userSession,
			$this->config,
			$this->l);

		$util = new Util(
			new View(),
			$crypt,
			$this->logger,
			$this->userSession,
			$this->config,
			$this->userManager);

		// Check if an adminRecovery account is enabled for recovering files after lost pwd
		$recoveryAdminEnabled = $this->config->getAppValue('encryption', 'recoveryAdminEnabled', '0');
		$session = new Session($this->session);

		$encryptHomeStorage = $util->shouldEncryptHomeStorage();

		$parameters = [
			'recoveryEnabled' => $recoveryAdminEnabled,
			'initStatus' => $session->getStatus(),
			'encryptHomeStorage' => $encryptHomeStorage,
			'masterKeyEnabled' => $util->isMasterKeyEnabled(),
		];

		return new TemplateResponse('encryption', 'settings-admin', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
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
	 */
	public function getPriority() {
		return 11;
	}
}
