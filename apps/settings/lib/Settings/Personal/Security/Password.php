<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OCA\Settings\Settings\Personal\Security;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserManager;
use OCP\Settings\ISettings;

class Password implements ISettings {

	/** @var IUserManager */
	private $userManager;

	/** @var string|null */
	private $uid;

	public function __construct(IUserManager $userManager,
								?string $UserId) {
		$this->userManager = $userManager;
		$this->uid = $UserId;
	}

	public function getForm(): TemplateResponse {
		$user = $this->userManager->get($this->uid);
		$passwordChangeSupported = false;
		if ($user !== null) {
			$passwordChangeSupported = $user->canChangePassword();
		}

		return new TemplateResponse('settings', 'settings/personal/security/password', [
			'passwordChangeSupported' => $passwordChangeSupported,
		]);
	}

	public function getSection(): string {
		return 'security';
	}

	public function getPriority(): int {
		return 10;
	}
}
