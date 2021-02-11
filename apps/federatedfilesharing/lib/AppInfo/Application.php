<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\FederatedFileSharing\AppInfo;

use Closure;
use OCA\FederatedFileSharing\Listeners\LoadAdditionalScriptsListener;
use OCA\FederatedFileSharing\Notifier;
use OCA\FederatedFileSharing\OCM\CloudFederationProviderFiles;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Notification\IManager as INotifiactionManager;

class Application extends App implements IBootstrap {
	public function __construct() {
		parent::__construct('federatedfilesharing');
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalScriptsListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerCloudFederationProvider']));
		$context->injectFn(Closure::fromCallable([$this, 'registerNotificationManager']));
	}

	private function registerCloudFederationProvider(ICloudFederationProviderManager $manager,
													 IAppContainer $appContainer): void {
		$manager->addCloudFederationProvider('file',
			'Federated Files Sharing',
			function () use ($appContainer) {
				return $appContainer->get(CloudFederationProviderFiles::class);
			});
	}

	private function registerNotificationManager(INotifiactionManager $manager): void {
		$manager->registerNotifierService(Notifier::class);
	}
}
