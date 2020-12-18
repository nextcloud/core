<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\AppFramework\Bootstrap;

use OC\Avatar\GuestAvatarProvider;
use OC\Avatar\UserAvatarProvider;
use OC\Support\CrashReport\Registry;
use OC_App;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\QueryException;
use OCP\Dashboard\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\IServerContainer;
use Throwable;
use function class_exists;
use function class_implements;
use function in_array;

class Coordinator {

	/** @var IServerContainer */
	private $serverContainer;

	/** @var Registry */
	private $registry;

	/** @var IManager */
	private $dashboardManager;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var ILogger */
	private $logger;

	/** @var RegistrationContext|null */
	private $registrationContext;

	/** @var string[] */
	private $bootedApps = [];

	public function __construct(IServerContainer $container,
								Registry $registry,
								IManager $dashboardManager,
								IEventDispatcher $eventListener,
								ILogger $logger) {
		$this->serverContainer = $container;
		$this->registry = $registry;
		$this->dashboardManager = $dashboardManager;
		$this->eventDispatcher = $eventListener;
		$this->logger = $logger;
	}

	public function runInitialRegistration(): void {
		if ($this->registrationContext === null) {
			$this->registrationContext = new RegistrationContext($this->logger);
		}

		$this->registerCore();
		$this->registerApps(OC_App::getEnabledApps());
	}

	public function runLazyRegistration(string $appId): void {
		$this->registerApps([$appId]);
	}

	private function registerCore(): void {
		$this->registrationContext->registerAvatarProvider('', 'user', UserAvatarProvider::class);
		$this->registrationContext->registerAvatarProvider('', 'guest', GuestAvatarProvider::class);
	}

	/**
	 * @param string[] $appIds
	 */
	private function registerApps(array $appIds): void {
		$apps = [];
		foreach ($appIds as $appId) {
			/*
			 * First, we have to enable the app's autoloader
			 *
			 * @todo use $this->appManager->getAppPath($appId) here
			 */
			$path = OC_App::getAppPath($appId);
			if ($path === false) {
				// Ignore
				continue;
			}
			OC_App::registerAutoloading($appId, $path);

			/*
			 * Next we check if there is an application class and it implements
			 * the \OCP\AppFramework\Bootstrap\IBootstrap interface
			 */
			$appNameSpace = App::buildAppNamespace($appId);
			$applicationClassName = $appNameSpace . '\\AppInfo\\Application';
			if (class_exists($applicationClassName) && in_array(IBootstrap::class, class_implements($applicationClassName), true)) {
				try {
					/** @var IBootstrap|App $application */
					$apps[$appId] = $application = $this->serverContainer->query($applicationClassName);
				} catch (QueryException $e) {
					// Weird, but ok
					continue;
				}
				try {
					$application->register($this->registrationContext->for($appId));
				} catch (Throwable $e) {
					$this->logger->logException($e, [
						'message' => 'Error during app service registration: ' . $e->getMessage(),
						'level' => ILogger::FATAL,
					]);
				}
			}
		}

		/**
		 * Now that all register methods have been called, we can delegate the registrations
		 * to the actual services
		 */
		$this->registrationContext->delegateCapabilityRegistrations($apps);
		$this->registrationContext->delegateCrashReporterRegistrations($apps, $this->registry);
		$this->registrationContext->delegateDashboardPanelRegistrations($apps, $this->dashboardManager);
		$this->registrationContext->delegateEventListenerRegistrations($this->eventDispatcher);
		$this->registrationContext->delegateContainerRegistrations($apps);
		$this->registrationContext->delegateMiddlewareRegistrations($apps);
	}

	public function getRegistrationContext(): ?RegistrationContext {
		return $this->registrationContext;
	}

	public function bootApp(string $appId): void {
		if (isset($this->bootedApps[$appId])) {
			return;
		}
		$this->bootedApps[$appId] = true;

		$appNameSpace = App::buildAppNamespace($appId);
		$applicationClassName = $appNameSpace . '\\AppInfo\\Application';
		if (!class_exists($applicationClassName)) {
			// Nothing to boot
			return;
		}

		/*
		 * Now it is time to fetch an instance of the App class. For classes
		 * that implement \OCP\AppFramework\Bootstrap\IBootstrap this means
		 * the instance was already created for register, but any other
		 * (legacy) code will now do their magic via the constructor.
		 */
		try {
			/** @var App $application */
			$application = $this->serverContainer->query($applicationClassName);
			if ($application instanceof IBootstrap) {
				/** @var BootContext $context */
				$context = new BootContext($application->getContainer());
				$application->boot($context);
			}
		} catch (QueryException $e) {
			$this->logger->logException($e, [
				'message' => "Could not boot $appId" . $e->getMessage(),
			]);
		} catch (Throwable $e) {
			$this->logger->logException($e, [
				'message' => "Could not boot $appId" . $e->getMessage(),
				'level' => ILogger::FATAL,
			]);
		}
	}

	public function isBootable(string $appId) {
		$appNameSpace = App::buildAppNamespace($appId);
		$applicationClassName = $appNameSpace . '\\AppInfo\\Application';
		return class_exists($applicationClassName) &&
			in_array(IBootstrap::class, class_implements($applicationClassName), true);
	}
}
