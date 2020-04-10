<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Abijeet <abijeetpatro@gmail.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Felix Heidecke <felix@heidecke.me>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Template;

use bantu\IniGetWrapper\IniGetWrapper;
use OC\CapabilitiesManager;
use OCP\App\IAppManager;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\User\Backend\IPasswordConfirmationBackend;

class JSConfigHelper {

	/** @var IL10N */
	private $l;

	/** @var Defaults */
	private $defaults;

	/** @var IAppManager */
	private $appManager;

	/** @var ISession */
	private $session;

	/** @var IUser|null */
	private $currentUser;

	/** @var IConfig */
	private $config;

	/** @var IGroupManager */
	private $groupManager;

	/** @var IniGetWrapper */
	private $iniWrapper;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var CapabilitiesManager */
	private $capabilitiesManager;

	/** @var array user back-ends excluded from password verification */
	private $excludedUserBackEnds = ['user_saml' => true, 'user_globalsiteselector' => true];

	/**
	 * @param IL10N $l
	 * @param Defaults $defaults
	 * @param IAppManager $appManager
	 * @param ISession $session
	 * @param IUser|null $currentUser
	 * @param IConfig $config
	 * @param IGroupManager $groupManager
	 * @param IniGetWrapper $iniWrapper
	 * @param IURLGenerator $urlGenerator
	 * @param CapabilitiesManager $capabilitiesManager
	 */
	public function __construct(IL10N $l,
								Defaults $defaults,
								IAppManager $appManager,
								ISession $session,
								$currentUser,
								IConfig $config,
								IGroupManager $groupManager,
								IniGetWrapper $iniWrapper,
								IURLGenerator $urlGenerator,
								CapabilitiesManager $capabilitiesManager) {
		$this->l = $l;
		$this->defaults = $defaults;
		$this->appManager = $appManager;
		$this->session = $session;
		$this->currentUser = $currentUser;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->iniWrapper = $iniWrapper;
		$this->urlGenerator = $urlGenerator;
		$this->capabilitiesManager = $capabilitiesManager;
	}

	public function getConfig() {

		$userBackendAllowsPasswordConfirmation = true;
		if ($this->currentUser !== null) {
			$uid = $this->currentUser->getUID();

			$backend = $this->currentUser->getBackend();
			if ($backend instanceof IPasswordConfirmationBackend) {
				$userBackendAllowsPasswordConfirmation = $backend->canConfirmPassword($uid);
			} else if (isset($this->excludedUserBackEnds[$this->currentUser->getBackendClassName()])) {
				$userBackendAllowsPasswordConfirmation = false;
			}
		} else {
			$uid = null;
		}

		// Get the config
		$apps_paths = [];

		if ($this->currentUser === null) {
			$apps = $this->appManager->getInstalledApps();
		} else {
			$apps = $this->appManager->getEnabledAppsForUser($this->currentUser);
		}

		foreach($apps as $app) {
			$apps_paths[$app] = \OC_App::getAppWebPath($app);
		}


		$enableLinkPasswordByDefault = $this->config->getAppValue('core', 'shareapi_enable_link_password_by_default', 'no');
		$enableLinkPasswordByDefault = $enableLinkPasswordByDefault === 'yes';
		$defaultExpireDateEnabled = $this->config->getAppValue('core', 'shareapi_default_expire_date', 'no') === 'yes';
		$defaultExpireDate = $enforceDefaultExpireDate = null;
		if ($defaultExpireDateEnabled) {
			$defaultExpireDate = (int) $this->config->getAppValue('core', 'shareapi_expire_after_n_days', '7');
			$enforceDefaultExpireDate = $this->config->getAppValue('core', 'shareapi_enforce_expire_date', 'no') === 'yes';
		}
		$outgoingServer2serverShareEnabled = $this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'yes') === 'yes';

		$defaultInternalExpireDateEnabled = $this->config->getAppValue('core', 'shareapi_default_internal_expire_date', 'no') === 'yes';
		$defaultInternalExpireDate = $defaultInternalExpireDateEnforced = null;
		if ($defaultInternalExpireDateEnabled) {
			$defaultInternalExpireDate = (int) $this->config->getAppValue('core', 'shareapi_internal_expire_after_n_days', '7');
			$defaultInternalExpireDateEnforced = $this->config->getAppValue('core', 'shareapi_internal_enforce_expire_date', 'no') === 'yes';
		}

		$countOfDataLocation = 0;
		$dataLocation = str_replace(\OC::$SERVERROOT .'/', '', $this->config->getSystemValue('datadirectory', ''), $countOfDataLocation);
		if($countOfDataLocation !== 1 || !$this->groupManager->isAdmin($uid)) {
			$dataLocation = false;
		}

		if ($this->currentUser instanceof IUser) {
			$lastConfirmTimestamp = $this->session->get('last-password-confirm');
			if (!is_int($lastConfirmTimestamp)) {
				$lastConfirmTimestamp = 0;
			}
		} else {
			$lastConfirmTimestamp = 0;
		}

		$capabilities = $this->capabilitiesManager->getCapabilities();

		$array = [
			"_oc_debug" => $this->config->getSystemValue('debug', false) ? 'true' : 'false',
			"_oc_isadmin" => $this->groupManager->isAdmin($uid) ? 'true' : 'false',
			"backendAllowsPasswordConfirmation" => $userBackendAllowsPasswordConfirmation ? 'true' : 'false',
			"oc_dataURL" => is_string($dataLocation) ? "\"".$dataLocation."\"" : 'false',
			"_oc_webroot" => "\"".\OC::$WEBROOT."\"",
			"_oc_appswebroots" =>  str_replace('\\/', '/', json_encode($apps_paths)), // Ugly unescape slashes waiting for better solution
			"datepickerFormatDate" => json_encode($this->l->l('jsdate', null)),
			'nc_lastLogin' => $lastConfirmTimestamp,
			'nc_pageLoad' => time(),
			"dayNames" =>  json_encode([
				(string)$this->l->t('Sunday'),
				(string)$this->l->t('Monday'),
				(string)$this->l->t('Tuesday'),
				(string)$this->l->t('Wednesday'),
				(string)$this->l->t('Thursday'),
				(string)$this->l->t('Friday'),
				(string)$this->l->t('Saturday')
			]),
			"dayNamesShort" =>  json_encode([
				(string)$this->l->t('Sun.'),
				(string)$this->l->t('Mon.'),
				(string)$this->l->t('Tue.'),
				(string)$this->l->t('Wed.'),
				(string)$this->l->t('Thu.'),
				(string)$this->l->t('Fri.'),
				(string)$this->l->t('Sat.')
			]),
			"dayNamesMin" =>  json_encode([
				(string)$this->l->t('Su'),
				(string)$this->l->t('Mo'),
				(string)$this->l->t('Tu'),
				(string)$this->l->t('We'),
				(string)$this->l->t('Th'),
				(string)$this->l->t('Fr'),
				(string)$this->l->t('Sa')
			]),
			"monthNames" => json_encode([
				(string)$this->l->t('January'),
				(string)$this->l->t('February'),
				(string)$this->l->t('March'),
				(string)$this->l->t('April'),
				(string)$this->l->t('May'),
				(string)$this->l->t('June'),
				(string)$this->l->t('July'),
				(string)$this->l->t('August'),
				(string)$this->l->t('September'),
				(string)$this->l->t('October'),
				(string)$this->l->t('November'),
				(string)$this->l->t('December')
			]),
			"monthNamesShort" => json_encode([
				(string)$this->l->t('Jan.'),
				(string)$this->l->t('Feb.'),
				(string)$this->l->t('Mar.'),
				(string)$this->l->t('Apr.'),
				(string)$this->l->t('May.'),
				(string)$this->l->t('Jun.'),
				(string)$this->l->t('Jul.'),
				(string)$this->l->t('Aug.'),
				(string)$this->l->t('Sep.'),
				(string)$this->l->t('Oct.'),
				(string)$this->l->t('Nov.'),
				(string)$this->l->t('Dec.')
			]),
			"firstDay" => json_encode($this->l->l('firstday', null)) ,
			"_oc_config" => json_encode([
				'session_lifetime'	=> min($this->config->getSystemValue('session_lifetime', $this->iniWrapper->getNumeric('session.gc_maxlifetime')), $this->iniWrapper->getNumeric('session.gc_maxlifetime')),
				'session_keepalive'	=> $this->config->getSystemValue('session_keepalive', true),
				'version'			=> implode('.', \OCP\Util::getVersion()),
				'versionstring'		=> \OC_Util::getVersionString(),
				'enable_avatars'	=> true, // here for legacy reasons - to not crash existing code that relies on this value
				'lost_password_link'=> $this->config->getSystemValue('lost_password_link', null),
				'modRewriteWorking'	=> $this->config->getSystemValue('htaccess.IgnoreFrontController', false) === true || getenv('front_controller_active') === 'true',
				'sharing.maxAutocompleteResults' => (int)$this->config->getSystemValue('sharing.maxAutocompleteResults', 0),
				'sharing.minSearchStringLength' => (int)$this->config->getSystemValue('sharing.minSearchStringLength', 0),
				'blacklist_files_regex' => \OCP\Files\FileInfo::BLACKLIST_FILES_REGEX,
			]),
			"oc_appconfig" => json_encode([
				'core' => [
					'defaultExpireDateEnabled' => $defaultExpireDateEnabled,
					'defaultExpireDate' => $defaultExpireDate,
					'defaultExpireDateEnforced' => $enforceDefaultExpireDate,
					'enforcePasswordForPublicLink' => \OCP\Util::isPublicLinkPasswordRequired(),
					'enableLinkPasswordByDefault' => $enableLinkPasswordByDefault,
					'sharingDisabledForUser' => \OCP\Util::isSharingDisabledForUser(),
					'resharingAllowed' => \OC\Share\Share::isResharingAllowed(),
					'remoteShareAllowed' => $outgoingServer2serverShareEnabled,
					'federatedCloudShareDoc' => $this->urlGenerator->linkToDocs('user-sharing-federated'),
					'allowGroupSharing' => \OC::$server->getShareManager()->allowGroupSharing(),
					'defaultInternalExpireDateEnabled' => $defaultInternalExpireDateEnabled,
					'defaultInternalExpireDate' => $defaultInternalExpireDate,
					'defaultInternalExpireDateEnforced' => $defaultInternalExpireDateEnforced,
				]
			]),
			"_theme" => json_encode([
				'entity' => $this->defaults->getEntity(),
				'name' => $this->defaults->getName(),
				'title' => $this->defaults->getTitle(),
				'baseUrl' => $this->defaults->getBaseUrl(),
				'syncClientUrl' => $this->defaults->getSyncClientUrl(),
				'docBaseUrl' => $this->defaults->getDocBaseUrl(),
				'docPlaceholderUrl' => $this->defaults->buildDocLinkToKey('PLACEHOLDER'),
				'slogan' => $this->defaults->getSlogan(),
				'logoClaim' => '',
				'shortFooter' => $this->defaults->getShortFooter(),
				'longFooter' => $this->defaults->getLongFooter(),
				'folder' => \OC_Util::getTheme(),
			]),
			"_oc_capabilities" => json_encode($capabilities),
		];

		if ($this->currentUser !== null) {
			$array['oc_userconfig'] = json_encode([
				'avatar' => [
					'version' => (int)$this->config->getUserValue($uid, 'avatar', 'version', 0),
					'generated' => $this->config->getUserValue($uid, 'avatar', 'generated', 'true') === 'true',
				]
			]);
		}

		// Allow hooks to modify the output values
		\OC_Hook::emit('\OCP\Config', 'js', ['array' => &$array]);

		$result = '';

		// Echo it
		foreach ($array as  $setting => $value) {
			$result .= 'var '. $setting . '='. $value . ';' . PHP_EOL;
		}

		return $result;
	}
}
