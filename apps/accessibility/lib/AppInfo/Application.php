<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Accessibility\AppInfo;

use OCA\Accessibility\Service\JSDataService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use function count;
use function implode;
use function md5;

class Application extends App implements IBootstrap {

	/** @var string */
	public const APP_ID = 'accessibility';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerInitialStateProvider(JSDataService::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn([$this, 'injectCss']);
	}

	public function injectCss(IUserSession $userSession,
							   IConfig $config,
							   IURLGenerator $urlGenerator) {
		// Inject the fake css on all pages if enabled and user is logged
		$loggedUser = $userSession->getUser();
		if ($loggedUser !== null) {
			$userValues = $config->getUserKeys($loggedUser->getUID(), self::APP_ID);
			// we want to check if any theme or font is enabled.
			if (count($userValues) > 0) {
				$hash = $config->getUserValue($loggedUser->getUID(), self::APP_ID, 'icons-css', md5(implode('-', $userValues)));
				$linkToCSS = $urlGenerator->linkToRoute(self::APP_ID . '.accessibility.getCss', ['md5' => $hash]);
				\OCP\Util::addHeader('link', ['rel' => 'stylesheet', 'href' => $linkToCSS]);
			}
			\OCP\Util::addScript('accessibility', 'accessibilityoca');
		} else {
			$userValues = ['dark'];

			$hash = md5(implode('-', $userValues));
			$linkToCSS = $urlGenerator->linkToRoute(self::APP_ID . '.accessibility.getCss', ['md5' => $hash]);
			\OCP\Util::addHeader('link', ['rel' => 'stylesheet', 'media' => '(prefers-color-scheme: dark)', 'href' => $linkToCSS]);
		}
	}
}
