<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Guillaume COMPAGNON <gcompagnon@outlook.com>
 * @author Hendrik Leppelsack <hendrik@leppelsack.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nils <git@to.nilsschnabel.de>
 * @author Remco Brenninkmeijer <requist1@starmail.nl>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC;

use OC\AppFramework\Http\Request;
use OC\Template\JSCombiner;
use OC\Template\JSConfigHelper;
use OC\Template\SCSSCacher;
use OCP\Defaults;
use OCP\Support\Subscription\IRegistry;

class TemplateLayout extends \OC_Template {

	private static $versionHash = '';

	/**
	 * @var \OCP\IConfig
	 */
	private $config;

	/**
	 * @param string $renderAs
	 * @param string $appId application id
	 */
	public function __construct($renderAs, $appId = '') {

		// yes - should be injected ....
		$this->config = \OC::$server->getConfig();

		if(\OCP\Util::isIE()) {
			\OC_Util::addStyle('ie');
		}

		// Decide which page we show
		if($renderAs === 'user') {
			parent::__construct('core', 'layout.user');
			if(in_array(\OC_App::getCurrentApp(), ['settings','admin', 'help']) !== false) {
				$this->assign('bodyid', 'body-settings');
			}else{
				$this->assign('bodyid', 'body-user');
			}

			// Add navigation entry
			$this->assign('application', '');
			$this->assign('appid', $appId);
			$navigation = \OC::$server->getNavigationManager()->getAll();
			$this->assign('navigation', $navigation);
			$settingsNavigation = \OC::$server->getNavigationManager()->getAll('settings');
			$this->assign('settingsnavigation', $settingsNavigation);
			foreach($navigation as $entry) {
				if ($entry['active']) {
					$this->assign('application', $entry['name']);
					break;
				}
			}

			foreach($settingsNavigation as $entry) {
				if ($entry['active']) {
					$this->assign('application', $entry['name']);
					break;
				}
			}
			$userDisplayName = \OC_User::getDisplayName();
			$this->assign('user_displayname', $userDisplayName);
			$this->assign('user_uid', \OC_User::getUser());

			if (\OC_User::getUser() === false) {
				$this->assign('userAvatarSet', false);
			} else {
				$this->assign('userAvatarSet', \OC::$server->getAvatarManager()->getAvatar(\OC_User::getUser())->exists());
				$this->assign('userAvatarVersion', $this->config->getUserValue(\OC_User::getUser(), 'avatar', 'version', 0));
			}

			// check if app menu icons should be inverted
			try {
				/** @var \OCA\Theming\Util $util */
				$util = \OC::$server->query(\OCA\Theming\Util::class);
				$this->assign('themingInvertMenu', $util->invertTextColor(\OC::$server->getThemingDefaults()->getColorPrimary()));
			} catch (\OCP\AppFramework\QueryException $e) {
				$this->assign('themingInvertMenu', false);
			} catch (\OCP\AutoloadNotAllowedException $e) {
				$this->assign('themingInvertMenu', false);
			}

		} else if ($renderAs === 'error') {
			parent::__construct('core', 'layout.guest', '', false);
			$this->assign('bodyid', 'body-login');
			$this->assign('user_displayname', '');
			$this->assign('user_uid', '');
		} else if ($renderAs === 'guest') {
			parent::__construct('core', 'layout.guest');
			\OC_Util::addStyle('guest');
			$this->assign('bodyid', 'body-login');

			$userDisplayName = \OC_User::getDisplayName();
			$this->assign('user_displayname', $userDisplayName);
			$this->assign('user_uid', \OC_User::getUser());
		} else if ($renderAs === 'public') {
			parent::__construct('core', 'layout.public');
			$this->assign('appid', $appId);
			$this->assign('bodyid', 'body-public');

			/** @var IRegistry $subscription */
			$subscription = \OC::$server->query(IRegistry::class);
			$showSimpleSignup = $this->config->getSystemValueBool('simpleSignUpLink.shown', true);
			if ($showSimpleSignup && $subscription->delegateHasValidSubscription()) {
				$showSimpleSignup = false;
			}
			$this->assign('showSimpleSignUpLink', $showSimpleSignup);
		} else {
			parent::__construct('core', 'layout.base');

		}
		// Send the language and the locale to our layouts
		$lang = \OC::$server->getL10NFactory()->findLanguage();
		$locale = \OC::$server->getL10NFactory()->findLocale($lang);

		$lang = str_replace('_', '-', $lang);
		$this->assign('language', $lang);
		$this->assign('locale', $locale);

		if(\OC::$server->getSystemConfig()->getValue('installed', false)) {
			if (empty(self::$versionHash)) {
				$v = \OC_App::getAppVersions();
				$v['core'] = implode('.', \OCP\Util::getVersion());
				self::$versionHash = substr(md5(implode(',', $v)), 0, 8);
			}
		} else {
			self::$versionHash = md5('not installed');
		}

		// Add the js files
		$jsFiles = self::findJavascriptFiles(\OC_Util::$scripts);
		$this->assign('jsfiles', []);
		if ($this->config->getSystemValue('installed', false) && $renderAs != 'error') {
			if (\OC::$server->getContentSecurityPolicyNonceManager()->browserSupportsCspV3()) {
				$jsConfigHelper = new JSConfigHelper(
					\OC::$server->getL10N('lib'),
					\OC::$server->query(Defaults::class),
					\OC::$server->getAppManager(),
					\OC::$server->getSession(),
					\OC::$server->getUserSession()->getUser(),
					$this->config,
					\OC::$server->getGroupManager(),
					\OC::$server->getIniWrapper(),
					\OC::$server->getURLGenerator(),
					\OC::$server->getCapabilitiesManager()
				);
				$this->assign('inline_ocjs', $jsConfigHelper->getConfig());
			} else {
				$this->append('jsfiles', \OC::$server->getURLGenerator()->linkToRoute('core.OCJS.getConfig', ['v' => self::$versionHash]));
			}
		}
		foreach($jsFiles as $info) {
			$web = $info[1];
			$file = $info[2];
			$this->append('jsfiles', $web.'/'.$file . $this->getVersionHashSuffix());
		}

		try {
			$pathInfo = \OC::$server->getRequest()->getPathInfo();
		} catch (\Exception $e) {
			$pathInfo = '';
		}

		// Do not initialise scss appdata until we have a fully installed instance
		// Do not load scss for update, errors, installation or login page
		if(\OC::$server->getSystemConfig()->getValue('installed', false)
			&& !\OCP\Util::needUpgrade()
			&& $pathInfo !== ''
			&& !preg_match('/^\/login/', $pathInfo)
			&& $renderAs !== 'error'
		) {
			$cssFiles = self::findStylesheetFiles(\OC_Util::$styles);
		} else {
			// If we ignore the scss compiler,
			// we need to load the guest css fallback
			\OC_Util::addStyle('guest');
			$cssFiles = self::findStylesheetFiles(\OC_Util::$styles, false);
		}

		$this->assign('cssfiles', []);
		$this->assign('printcssfiles', []);
		$this->assign('versionHash', self::$versionHash);
		foreach($cssFiles as $info) {
			$web = $info[1];
			$file = $info[2];

			if (substr($file, -strlen('print.css')) === 'print.css') {
				$this->append('printcssfiles', $web.'/'.$file . $this->getVersionHashSuffix());
			} else {
				$suffix = $this->getVersionHashSuffix($web, $file);

				if (strpos($file, '?v=') == false) {
					$this->append('cssfiles', $web.'/'.$file . $suffix);
				} else {
					$this->append('cssfiles', $web.'/'.$file . '-' . substr($suffix, 3));
				}

			}
		}

		/** @var InitialStateService $initialState */
		$initialState = \OC::$server->query(InitialStateService::class);
		$this->assign('initialStates', $initialState->getInitialStates());
	}

	/**
	 * @param string $path
	 * @param string $file
	 * @return string
	 */
	protected function getVersionHashSuffix($path = false, $file = false) {
		if ($this->config->getSystemValue('debug', false)) {
			// allows chrome workspace mapping in debug mode
			return "";
		}
		$themingSuffix = '';
		$v = [];

		if ($this->config->getSystemValue('installed', false)) {
			if (\OC::$server->getAppManager()->isInstalled('theming')) {
				$themingSuffix = '-' . $this->config->getAppValue('theming', 'cachebuster', '0');
			}
			$v = \OC_App::getAppVersions();
		}

		// Try the webroot path for a match
		if ($path !== false && $path !== '') {
			$appName = $this->getAppNamefromPath($path);
			if(array_key_exists($appName, $v)) {
				$appVersion = $v[$appName];
				return '?v=' . substr(md5($appVersion), 0, 8) . $themingSuffix;
			}
		}
		// fallback to the file path instead
		if ($file !== false && $file !== '') {
			$appName = $this->getAppNamefromPath($file);
			if(array_key_exists($appName, $v)) {
				$appVersion = $v[$appName];
				return '?v=' . substr(md5($appVersion), 0, 8) . $themingSuffix;
			}
		}

		return '?v=' . self::$versionHash . $themingSuffix;
	}

	/**
	 * @param array $styles
	 * @return array
	 */
	static public function findStylesheetFiles($styles, $compileScss = true) {
		// Read the selected theme from the config file
		$theme = \OC_Util::getTheme();

		if($compileScss) {
			$SCSSCacher = \OC::$server->query(SCSSCacher::class);
		} else {
			$SCSSCacher = null;
		}

		$locator = new \OC\Template\CSSResourceLocator(
			\OC::$server->getLogger(),
			$theme,
			[ \OC::$SERVERROOT => \OC::$WEBROOT ],
			[ \OC::$SERVERROOT => \OC::$WEBROOT ],
			$SCSSCacher
		);
		$locator->find($styles);
		return $locator->getResources();
	}

	/**
	 * @param string $path
	 * @return string|boolean
	 */
	public function getAppNamefromPath($path) {
		if ($path !== '' && is_string($path)) {
			$pathParts = explode('/', $path);
			if ($pathParts[0] === 'css') {
				// This is a scss request
				return $pathParts[1];
			}
			return end($pathParts);
		}
		return false;

	}

	/**
	 * @param array $scripts
	 * @return array
	 */
	static public function findJavascriptFiles($scripts) {
		// Read the selected theme from the config file
		$theme = \OC_Util::getTheme();

		$locator = new \OC\Template\JSResourceLocator(
			\OC::$server->getLogger(),
			$theme,
			[ \OC::$SERVERROOT => \OC::$WEBROOT ],
			[ \OC::$SERVERROOT => \OC::$WEBROOT ],
			\OC::$server->query(JSCombiner::class)
			);
		$locator->find($scripts);
		return $locator->getResources();
	}

	/**
	 * Converts the absolute file path to a relative path from \OC::$SERVERROOT
	 * @param string $filePath Absolute path
	 * @return string Relative path
	 * @throws \Exception If $filePath is not under \OC::$SERVERROOT
	 */
	public static function convertToRelativePath($filePath) {
		$relativePath = explode(\OC::$SERVERROOT, $filePath);
		if(count($relativePath) !== 2) {
			throw new \Exception('$filePath is not under the \OC::$SERVERROOT');
		}

		return $relativePath[1];
	}
}
