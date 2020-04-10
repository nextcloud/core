<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\Settings\Settings\Personal;

use OC\Accounts\AccountManager;
use OCA\FederatedFileSharing\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Settings\ISettings;

class PersonalInfo implements ISettings {

	/** @var IConfig */
	private $config;
	/** @var IUserManager */
	private $userManager;
	/** @var AccountManager */
	private $accountManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IAppManager */
	private $appManager;
	/** @var IFactory */
	private $l10nFactory;
	/** @var IL10N */
	private $l;

	/**
	 * @param IConfig $config
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param AccountManager $accountManager
	 * @param IFactory $l10nFactory
	 * @param IL10N $l
	 */
	public function __construct(
		IConfig $config,
		IUserManager $userManager,
		IGroupManager $groupManager,
		AccountManager $accountManager,
		IAppManager $appManager,
		IFactory $l10nFactory,
		IL10N $l
	) {
		$this->config = $config;
		$this->userManager = $userManager;
		$this->accountManager = $accountManager;
		$this->groupManager = $groupManager;
		$this->appManager = $appManager;
		$this->l10nFactory = $l10nFactory;
		$this->l = $l;
	}

	/**
	 * @return TemplateResponse returns the instance with all parameters set, ready to be rendered
	 * @since 9.1
	 */
	public function getForm() {
		$federatedFileSharingEnabled = $this->appManager->isEnabledForUser('federatedfilesharing');
		$lookupServerUploadEnabled = false;
		if($federatedFileSharingEnabled) {
			$federatedFileSharing = \OC::$server->query(Application::class);
			$shareProvider = $federatedFileSharing->getFederatedShareProvider();
			$lookupServerUploadEnabled = $shareProvider->isLookupServerUploadEnabled();
		}

		$uid = \OC_User::getUser();
		$user = $this->userManager->get($uid);
		$userData = $this->accountManager->getUser($user);

		$storageInfo = \OC_Helper::getStorageInfo('/');
		if ($storageInfo['quota'] === FileInfo::SPACE_UNLIMITED) {
			$totalSpace = $this->l->t('Unlimited');
		} else {
			$totalSpace = \OC_Helper::humanFileSize($storageInfo['total']);
		}

		$languageParameters = $this->getLanguages($user);
		$localeParameters = $this->getLocales($user);
		$messageParameters = $this->getMessageParameters($userData);

		$parameters = [
			'total_space' => $totalSpace,
			'usage' => \OC_Helper::humanFileSize($storageInfo['used']),
			'usage_relative' => round($storageInfo['relative']),
			'quota' => $storageInfo['quota'],
			'avatarChangeSupported' => $user->canChangeAvatar(),
			'lookupServerUploadEnabled' => $lookupServerUploadEnabled,
			'avatarScope' => $userData[AccountManager::PROPERTY_AVATAR]['scope'],
			'displayNameChangeSupported' => $user->canChangeDisplayName(),
			'displayName' => $userData[AccountManager::PROPERTY_DISPLAYNAME]['value'],
			'displayNameScope' => $userData[AccountManager::PROPERTY_DISPLAYNAME]['scope'],
			'email' => $userData[AccountManager::PROPERTY_EMAIL]['value'],
			'emailScope' => $userData[AccountManager::PROPERTY_EMAIL]['scope'],
			'emailVerification' => $userData[AccountManager::PROPERTY_EMAIL]['verified'],
			'phone' => $userData[AccountManager::PROPERTY_PHONE]['value'],
			'phoneScope' => $userData[AccountManager::PROPERTY_PHONE]['scope'],
			'address' => $userData[AccountManager::PROPERTY_ADDRESS]['value'],
			'addressScope' => $userData[AccountManager::PROPERTY_ADDRESS]['scope'],
			'website' =>  $userData[AccountManager::PROPERTY_WEBSITE]['value'],
			'websiteScope' =>  $userData[AccountManager::PROPERTY_WEBSITE]['scope'],
			'websiteVerification' => $userData[AccountManager::PROPERTY_WEBSITE]['verified'],
			'twitter' => $userData[AccountManager::PROPERTY_TWITTER]['value'],
			'twitterScope' => $userData[AccountManager::PROPERTY_TWITTER]['scope'],
			'twitterVerification' => $userData[AccountManager::PROPERTY_TWITTER]['verified'],
			'groups' => $this->getGroups($user),
		] + $messageParameters + $languageParameters + $localeParameters;


		return new TemplateResponse('settings', 'settings/personal/personal.info', $parameters, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 * @since 9.1
	 */
	public function getSection() {
		return 'personal-info';
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
		return 10;
	}

	/**
	 * returns a sorted list of the user's group GIDs
	 *
	 * @param IUser $user
	 * @return array
	 */
	private function getGroups(IUser $user) {
		$groups = array_map(
			function (IGroup $group) {
				return $group->getDisplayName();
			},
			$this->groupManager->getUserGroups($user)
		);
		sort($groups);

		return $groups;
	}

	/**
	 * returns the user language, common language and other languages in an
	 * associative array
	 *
	 * @param IUser $user
	 * @return array
	 */
	private function getLanguages(IUser $user) {
		$forceLanguage = $this->config->getSystemValue('force_language', false);
		if($forceLanguage !== false) {
			return [];
		}

		$uid = $user->getUID();

		$userConfLang = $this->config->getUserValue($uid, 'core', 'lang', $this->l10nFactory->findLanguage());
		$languages = $this->l10nFactory->getLanguages();

		// associate the user language with the proper array
		$userLangIndex = array_search($userConfLang, array_column($languages['commonlanguages'], 'code'));
		$userLang = $languages['commonlanguages'][$userLangIndex];
		// search in the other languages
		if ($userLangIndex === false) {
			$userLangIndex = array_search($userConfLang, array_column($languages['languages'], 'code'));
			$userLang = $languages['languages'][$userLangIndex];
		}
		// if user language is not available but set somehow: show the actual code as name
		if (!is_array($userLang)) {
			$userLang = [
				'code' => $userConfLang,
				'name' => $userConfLang,
			];
		}

		return array_merge(
			['activelanguage' => $userLang],
			$languages
		);
	}

	private function getLocales(IUser $user) {
		$forceLanguage = $this->config->getSystemValue('force_locale', false);
		if($forceLanguage !== false) {
			return [];
		}

		$uid = $user->getUID();

		$userLocaleString = $this->config->getUserValue($uid, 'core', 'locale', $this->l10nFactory->findLocale());

		$userLang = $this->config->getUserValue($uid, 'core', 'lang', $this->l10nFactory->findLanguage());

		$localeCodes = $this->l10nFactory->findAvailableLocales();

		$userLocale = array_filter($localeCodes, function ($value) use ($userLocaleString) {
			return $userLocaleString === $value['code'];
		});

		if (!empty($userLocale))
		{
			$userLocale = reset($userLocale);
		}

		$localesForLanguage = array_filter($localeCodes, function ($localeCode) use ($userLang) {
			return 0 === strpos($localeCode['code'], $userLang);
		});

		if (!$userLocale) {
			$userLocale = [
				'code' => 'en',
				'name' => 'English'
			];
		}

		return [
			'activelocaleLang' => $userLocaleString,
			'activelocale' => $userLocale,
			'locales' => $localeCodes,
			'localesForLanguage' => $localesForLanguage,
		];
	}

	/**
	 * @param array $userData
	 * @return array
	 */
	private function getMessageParameters(array $userData) {
		$needVerifyMessage = [AccountManager::PROPERTY_EMAIL, AccountManager::PROPERTY_WEBSITE, AccountManager::PROPERTY_TWITTER];
		$messageParameters = [];
		foreach ($needVerifyMessage as $property) {
			switch ($userData[$property]['verified']) {
				case AccountManager::VERIFIED:
					$message = $this->l->t('Verifying');
					break;
				case AccountManager::VERIFICATION_IN_PROGRESS:
					$message = $this->l->t('Verifying …');
					break;
				default:
					$message = $this->l->t('Verify');
			}
			$messageParameters[$property . 'Message'] = $message;
		}
		return $messageParameters;
	}

}
