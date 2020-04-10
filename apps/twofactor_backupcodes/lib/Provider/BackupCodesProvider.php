<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Provider;

use OC\App\AppManager;
use OCA\TwoFactorBackupCodes\Service\BackupCodeStorage;
use OCA\TwoFactorBackupCodes\Settings\Personal;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Template;

class BackupCodesProvider implements IProvider, IProvidesPersonalSettings {

	/** @var string */
	private $appName;

	/** @var BackupCodeStorage */
	private $storage;

	/** @var IL10N */
	private $l10n;

	/** @var AppManager */
	private $appManager;
	/** @var IInitialStateService */
	private $initialStateService;

	/**
	 * @param string $appName
	 * @param BackupCodeStorage $storage
	 * @param IL10N $l10n
	 * @param AppManager $appManager
	 */
	public function __construct(string $appName,
								BackupCodeStorage $storage,
								IL10N $l10n,
								AppManager $appManager,
								IInitialStateService $initialStateService) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->storage = $storage;
		$this->appManager = $appManager;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * Get unique identifier of this 2FA provider
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'backup_codes';
	}

	/**
	 * Get the display name for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDisplayName(): string {
		return $this->l10n->t('Backup code');
	}

	/**
	 * Get the description for selecting the 2FA provider
	 *
	 * @return string
	 */
	public function getDescription(): string {
		return $this->l10n->t('Use backup code');
	}

	/**
	 * Get the template for rending the 2FA provider view
	 *
	 * @param IUser $user
	 * @return Template
	 */
	public function getTemplate(IUser $user): Template {
		return new Template('twofactor_backupcodes', 'challenge');
	}

	/**
	 * Verify the given challenge
	 *
	 * @param IUser $user
	 * @param string $challenge
	 * @return bool
	 */
	public function verifyChallenge(IUser $user, string $challenge): bool {
		return $this->storage->validateCode($user, $challenge);
	}

	/**
	 * Decides whether 2FA is enabled for the given user
	 *
	 * @param IUser $user
	 * @return boolean
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return $this->storage->hasBackupCodes($user);
	}

	/**
	 * Determine whether backup codes should be active or not
	 *
	 * Backup codes only make sense if at least one 2FA provider is active,
	 * hence this method checks all enabled apps on whether they provide 2FA
	 * functionality or not. If there's at least one app, backup codes are
	 * enabled on the personal settings page.
	 *
	 * @param IUser $user
	 * @return boolean
	 */
	public function isActive(IUser $user): bool {
		$appIds = array_filter($this->appManager->getEnabledAppsForUser($user), function ($appId) {
			return $appId !== $this->appName;
		});
		foreach ($appIds as $appId) {
			$info = $this->appManager->getAppInfo($appId);
			if (isset($info['two-factor-providers']) && count($info['two-factor-providers']) > 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param IUser $user
	 *
	 * @return IPersonalProviderSettings
	 */
	public function getPersonalSettings(IUser $user): IPersonalProviderSettings {
		$state = $this->storage->getBackupCodesState($user);
		$this->initialStateService->provideInitialState($this->appName, 'state', $state);
		return new Personal();
	}

}
