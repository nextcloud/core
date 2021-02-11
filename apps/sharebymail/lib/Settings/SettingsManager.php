<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCA\ShareByMail\Settings;

use OCP\IConfig;

class SettingsManager {

	/** @var IConfig */
	private $config;

	private $sendPasswordByMailDefault = 'yes';

	private $enforcePasswordProtectionDefault = 'no';

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * should the password for a mail share be send to the recipient
	 *
	 * @return bool
	 */
	public function sendPasswordByMail(): bool {
		$sendPasswordByMail = $this->config->getAppValue('sharebymail', 'sendpasswordmail', $this->sendPasswordByMailDefault);
		return $sendPasswordByMail === 'yes';
	}

	/**
	 * do we require a share by mail to be password protected
	 *
	 * @return bool
	 */
	public function enforcePasswordProtection(): bool {
		$enforcePassword = $this->config->getAppValue('sharebymail', 'enforcePasswordProtection', $this->enforcePasswordProtectionDefault);
		return $enforcePassword === 'yes';
	}
}
