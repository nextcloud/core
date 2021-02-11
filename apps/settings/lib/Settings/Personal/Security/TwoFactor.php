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

namespace OCA\Settings\Settings\Personal\Security;

use Exception;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCA\TwoFactorBackupCodes\Provider\BackupCodesProvider;
use function array_filter;
use function array_map;
use function is_null;
use OC\Authentication\TwoFactorAuth\ProviderLoader;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class TwoFactor implements ISettings {

	/** @var ProviderLoader */
	private $providerLoader;

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	/** @var IUserSession */
	private $userSession;

	/** @var string|null */
	private $uid;

	/** @var IConfig */
	private $config;

	public function __construct(ProviderLoader $providerLoader,
								MandatoryTwoFactor $mandatoryTwoFactor,
								IUserSession $userSession,
								IConfig $config,
								?string $UserId) {
		$this->providerLoader = $providerLoader;
		$this->mandatoryTwoFactor = $mandatoryTwoFactor;
		$this->userSession = $userSession;
		$this->uid = $UserId;
		$this->config = $config;
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse('settings', 'settings/personal/security/twofactor', [
			'twoFactorProviderData' => $this->getTwoFactorProviderData(),
			'themedark' => $this->config->getUserValue($this->uid, 'accessibility', 'theme', false)
		]);
	}

	public function getSection(): ?string {
		if (!$this->shouldShow()) {
			return null;
		}
		return 'security';
	}

	public function getPriority(): int {
		return 15;
	}

	private function shouldShow(): bool {
		$user = $this->userSession->getUser();
		if (is_null($user)) {
			// Actually impossible, but still …
			return false;
		}

		// Anyone who's supposed to use 2FA should see 2FA settings
		if ($this->mandatoryTwoFactor->isEnforcedFor($user)) {
			return true;
		}

		// If there is at least one provider with personal settings but it's not
		// the backup codes provider, then these settings should show.
		try {
			$providers = $this->providerLoader->getProviders($user);
		} catch (Exception $e) {
			// Let's hope for the best
			return true;
		}
		foreach ($providers as $provider) {
			if ($provider instanceof IProvidesPersonalSettings
				&& !($provider instanceof BackupCodesProvider)) {
				return true;
			}
		}
		return false;
	}

	private function getTwoFactorProviderData(): array {
		$user = $this->userSession->getUser();
		if (is_null($user)) {
			// Actually impossible, but still …
			return [];
		}

		return [
			'providers' => array_map(function (IProvidesPersonalSettings $provider) use ($user) {
				return [
					'provider' => $provider,
					'settings' => $provider->getPersonalSettings($user)
				];
			}, array_filter($this->providerLoader->getProviders($user), function (IProvider $provider) {
				return $provider instanceof IProvidesPersonalSettings;
			}))
		];
	}
}
