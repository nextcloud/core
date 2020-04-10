<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Alexey Pyltsyn <lex61rus@gmail.com>
 * @author Janis Köhr <janis.koehr@novatec-gmbh.de>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Accessibility\AppInfo;

use OCP\AppFramework\App;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;

class Application extends App {

	/** @var string */
	public const APP_NAME = 'accessibility';

	/** @var IConfig */
	private $config;

	/** @var IUserSession */
	private $userSession;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct() {
		parent::__construct(self::APP_NAME);
		$this->config       = \OC::$server->getConfig();
		$this->userSession  = \OC::$server->getUserSession();
		$this->urlGenerator = \OC::$server->getURLGenerator();
	}

	public function injectCss() {
		// Inject the fake css on all pages if enabled and user is logged
		$loggedUser = $this->userSession->getUser();
		if (!is_null($loggedUser)) {
			$userValues = $this->config->getUserKeys($loggedUser->getUID(), self::APP_NAME);
			// we want to check if any theme or font is enabled.
			if (count($userValues) > 0) {
				$hash = $this->config->getUserValue($loggedUser->getUID(), self::APP_NAME, 'icons-css', md5(implode('-', $userValues)));
				$linkToCSS = $this->urlGenerator->linkToRoute(self::APP_NAME . '.accessibility.getCss', ['md5' => $hash]);
				\OCP\Util::addHeader('link', ['rel' => 'stylesheet', 'href' => $linkToCSS]);
			}
		}
	}

	public function injectJavascript() {
		$linkToJs = $this->urlGenerator->linkToRoute(
			self::APP_NAME . '.accessibility.getJavascript',
			[
				'v' => \OC::$server->getConfig()->getAppValue('accessibility', 'cachebuster', '0'),
			]
		);

		\OCP\Util::addHeader(
			'script',
			[
				'src' => $linkToJs,
				'nonce' => \OC::$server->getContentSecurityPolicyNonceManager()->getNonce()
			],
			''
		);
	}
}
