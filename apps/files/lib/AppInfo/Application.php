<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\Files\AppInfo;

use Closure;
use OC\Search\Provider\File;
use OCA\Files\Capabilities;
use OCA\Files\Collaboration\Resources\Listener;
use OCA\Files\Collaboration\Resources\ResourceProvider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Files\Listener\LegacyLoadAdditionalScriptsAdapter;
use OCA\Files\Listener\LoadSidebarListener;
use OCA\Files\Notification\Notifier;
use OCA\Files\Search\FilesSearchProvider;
use OCA\Files\Service\TagService;
use OCA\Files\SidebarNavigationManager;
use OCP\Activity\IManager as IActivityManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\IL10N;
use OCP\ISearch;
use OCP\IServerContainer;
use OCP\ITagManager;
use OCP\IUserSession;
use OCP\Notification\IManager;
use OCP\Util;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		/**
		 * Services
		 */
		$context->registerService(TagService::class, function (ContainerInterface $c) {
			/** @var IServerContainer $server */
			$server = $c->get(IServerContainer::class);

			return new TagService(
				$c->get(IUserSession::class),
				$c->get(IActivityManager::class),
				$c->get(ITagManager::class)->load(self::APP_ID),
				$server->getUserFolder(),
				$server->getEventDispatcher()
			);
		});

		/*
		 * Register capabilities
		 */
		$context->registerCapability(Capabilities::class);

		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LegacyLoadAdditionalScriptsAdapter::class);
		$context->registerEventListener(LoadSidebar::class, LoadSidebarListener::class);

		$context->registerSearchProvider(FilesSearchProvider::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerCollaboration']));
		$context->injectFn([Listener::class, 'register']);
		$context->injectFn(Closure::fromCallable([$this, 'registerNotification']));
		$context->injectFn(Closure::fromCallable([$this, 'registerSearchProvider']));
		$this->registerTemplates();
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		$this->registerHooks();
	}

	private function registerCollaboration(IProviderManager $providerManager): void {
		$providerManager->registerResourceProvider(ResourceProvider::class);
	}

	private function registerNotification(IManager $notifications): void {
		$notifications->registerNotifierService(Notifier::class);
	}

	private function registerSearchProvider(ISearch $search): void {
		$search->registerProvider(File::class, ['apps' => ['files']]);
	}

	private function registerTemplates(): void {
		$templateManager = \OC_Helper::getFileTemplateManager();
		$templateManager->registerTemplate('application/vnd.oasis.opendocument.presentation', 'core/templates/filetemplates/template.odp');
		$templateManager->registerTemplate('application/vnd.oasis.opendocument.text', 'core/templates/filetemplates/template.odt');
		$templateManager->registerTemplate('application/vnd.oasis.opendocument.spreadsheet', 'core/templates/filetemplates/template.ods');
	}

	private function registerNavigation(IL10N $l10n, SidebarNavigationManager $navigation): void {
		$navigation->add(function () use ($l10n) {
			return [
				'id' => 'files',
				'appname' => 'files',
				'script' => 'list.php',
				'order' => 0,
				'name' => $l10n->t('All files')
			];
		});
		$navigation->add(function () use ($l10n) {
			return [
				'id' => 'recent',
				'appname' => 'files',
				'script' => 'recentlist.php',
				'order' => 2,
				'name' => $l10n->t('Recent')
			];
		});
		$navigation->add(function () use ($l10n) {
			return [
				'id' => 'favorites',
				'appname' => 'files',
				'script' => 'simplelist.php',
				'order' => 5,
				'name' => $l10n->t('Favorites'),
				'expandedState' => 'show_Quick_Access'
			];
		});
	}

	private function registerHooks(): void {
		Util::connectHook('\OCP\Config', 'js', '\OCA\Files\App', 'extendJsConfig');
	}
}
