<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Robin Windey <ro.windey@gmail.com>
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

namespace OC\AppFramework\Bootstrap;

use Closure;
use OC\Support\CrashReport\Registry;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Middleware;
use OCP\Authentication\IAlternativeLogin;
use OCP\Capabilities\ICapability;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Template\ICustomTemplateProvider;
use OCP\Http\WellKnown\IHandler;
use OCP\ILogger;
use OCP\Search\IProvider;
use OCP\Support\CrashReport\IReporter;
use Throwable;

class RegistrationContext {

	/** @var ServiceRegistration<ICapability>[] */
	private $capabilities = [];

	/** @var ServiceRegistration<IReporter>[] */
	private $crashReporters = [];

	/** @var ServiceRegistration<IWidget>[] */
	private $dashboardPanels = [];

	/** @var ServiceFactoryRegistration[] */
	private $services = [];

	/** @var ServiceAliasRegistration[] */
	private $aliases = [];

	/** @var ParameterRegistration[] */
	private $parameters = [];

	/** @var EventListenerRegistration[] */
	private $eventListeners = [];

	/** @var ServiceRegistration<Middleware>[] */
	private $middlewares = [];

	/** @var ServiceRegistration<IProvider>[] */
	private $searchProviders = [];

	/** @var ServiceRegistration<IAlternativeLogin>[] */
	private $alternativeLogins = [];

	/** @var array[] */
	private $initialStates = [];

	/** @var ServiceRegistration<IHandler>[] */
	private $wellKnownHandlers = [];

	/** @var ServiceRegistration<ICustomTemplateProvider>[] */
	private $templateProviders = [];

	/** @var ILogger */
	private $logger;

	public function __construct(ILogger $logger) {
		$this->logger = $logger;
	}

	public function for(string $appId): IRegistrationContext {
		return new class($appId, $this) implements IRegistrationContext {
			/** @var string */
			private $appId;

			/** @var RegistrationContext */
			private $context;

			public function __construct(string $appId, RegistrationContext $context) {
				$this->appId = $appId;
				$this->context = $context;
			}

			public function registerCapability(string $capability): void {
				$this->context->registerCapability(
					$this->appId,
					$capability
				);
			}

			public function registerCrashReporter(string $reporterClass): void {
				$this->context->registerCrashReporter(
					$this->appId,
					$reporterClass
				);
			}

			public function registerDashboardWidget(string $widgetClass): void {
				$this->context->registerDashboardPanel(
					$this->appId,
					$widgetClass
				);
			}

			public function registerService(string $name, callable $factory, bool $shared = true): void {
				$this->context->registerService(
					$this->appId,
					$name,
					$factory,
					$shared
				);
			}

			public function registerServiceAlias(string $alias, string $target): void {
				$this->context->registerServiceAlias(
					$this->appId,
					$alias,
					$target
				);
			}

			public function registerParameter(string $name, $value): void {
				$this->context->registerParameter(
					$this->appId,
					$name,
					$value
				);
			}

			public function registerEventListener(string $event, string $listener, int $priority = 0): void {
				$this->context->registerEventListener(
					$this->appId,
					$event,
					$listener,
					$priority
				);
			}

			public function registerMiddleware(string $class): void {
				$this->context->registerMiddleware(
					$this->appId,
					$class
				);
			}

			public function registerSearchProvider(string $class): void {
				$this->context->registerSearchProvider(
					$this->appId,
					$class
				);
			}

			public function registerAlternativeLogin(string $class): void {
				$this->context->registerAlternativeLogin(
					$this->appId,
					$class
				);
			}

			public function registerInitialStateProvider(string $class): void {
				$this->context->registerInitialState(
					$this->appId,
					$class
				);
			}

			public function registerWellKnownHandler(string $class): void {
				$this->context->registerWellKnown(
					$this->appId,
					$class
				);
			}

			public function registerTemplateProvider(string $providerClass): void {
				$this->context->registerTemplateProvider(
					$this->appId,
					$providerClass
				);
			}
		};
	}

	/**
	 * @psalm-param class-string<ICapability> $capability
	 */
	public function registerCapability(string $appId, string $capability): void {
		$this->capabilities[] = new ServiceRegistration($appId, $capability);
	}

	/**
	 * @psalm-param class-string<IReporter> $capability
	 */
	public function registerCrashReporter(string $appId, string $reporterClass): void {
		$this->crashReporters[] = new ServiceRegistration($appId, $reporterClass);
	}

	/**
	 * @psalm-param class-string<IWidget> $capability
	 */
	public function registerDashboardPanel(string $appId, string $panelClass): void {
		$this->dashboardPanels[] = new ServiceRegistration($appId, $panelClass);
	}

	public function registerService(string $appId, string $name, callable $factory, bool $shared = true): void {
		$this->services[] = new ServiceFactoryRegistration($appId, $name, $factory, $shared);
	}

	public function registerServiceAlias(string $appId, string $alias, string $target): void {
		$this->aliases[] = new ServiceAliasRegistration($appId, $alias, $target);
	}

	public function registerParameter(string $appId, string $name, $value): void {
		$this->parameters[] = new ParameterRegistration($appId, $name, $value);
	}

	public function registerEventListener(string $appId, string $event, string $listener, int $priority = 0): void {
		$this->eventListeners[] = new EventListenerRegistration($appId, $event, $listener, $priority);
	}

	/**
	 * @psalm-param class-string<Middleware> $class
	 */
	public function registerMiddleware(string $appId, string $class): void {
		$this->middlewares[] = new ServiceRegistration($appId, $class);
	}

	public function registerSearchProvider(string $appId, string $class) {
		$this->searchProviders[] = new ServiceRegistration($appId, $class);
	}

	public function registerAlternativeLogin(string $appId, string $class): void {
		$this->alternativeLogins[] = new ServiceRegistration($appId, $class);
	}

	public function registerInitialState(string $appId, string $class): void {
		$this->initialStates[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	public function registerWellKnown(string $appId, string $class): void {
		$this->wellKnownHandlers[] = new ServiceRegistration($appId, $class);
	}

	public function registerTemplateProvider(string $appId, string $class): void {
		$this->templateProviders[] = new ServiceRegistration($appId, $class);
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateCapabilityRegistrations(array $apps): void {
		while (($registration = array_shift($this->capabilities)) !== null) {
			try {
				$apps[$registration->getAppId()]
					->getContainer()
					->registerCapability($registration->getService());
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during capability registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateCrashReporterRegistrations(array $apps, Registry $registry): void {
		while (($registration = array_shift($this->crashReporters)) !== null) {
			try {
				$registry->registerLazy($registration->getService());
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during crash reporter registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateDashboardPanelRegistrations(array $apps, IManager $dashboardManager): void {
		while (($panel = array_shift($this->dashboardPanels)) !== null) {
			try {
				$dashboardManager->lazyRegisterWidget($panel->getService());
			} catch (Throwable $e) {
				$appId = $panel->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during dashboard registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	public function delegateEventListenerRegistrations(IEventDispatcher $eventDispatcher): void {
		while (($registration = array_shift($this->eventListeners)) !== null) {
			try {
				$eventDispatcher->addServiceListener(
					$registration->getEvent(),
					$registration->getService(),
					$registration->getPriority()
				);
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during event listener registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateContainerRegistrations(array $apps): void {
		while (($registration = array_shift($this->services)) !== null) {
			try {
				/**
				 * Register the service and convert the callable into a \Closure if necessary
				 */
				$apps[$registration->getAppId()]
					->getContainer()
					->registerService(
						$registration->getName(),
						Closure::fromCallable($registration->getFactory()),
						$registration->isShared()
					);
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during service registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}

		foreach ($this->aliases as $registration) {
			try {
				$apps[$registration->getAppId()]
					->getContainer()
					->registerAlias(
						$registration->getAlias(),
						$registration->getTarget()
					);
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during service alias registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}

		foreach ($this->parameters as $registration) {
			try {
				$apps[$registration->getAppId()]
					->getContainer()
					->registerParameter(
						$registration->getName(),
						$registration->getValue()
					);
			} catch (Throwable $e) {
				$appId = $registration->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during service alias registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateMiddlewareRegistrations(array $apps): void {
		while (($middleware = array_shift($this->middlewares)) !== null) {
			try {
				$apps[$middleware->getAppId()]
					->getContainer()
					->registerMiddleWare($middleware->getService());
			} catch (Throwable $e) {
				$appId = $middleware->getAppId();
				$this->logger->logException($e, [
					'message' => "Error during capability registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @return ServiceRegistration<IProvider>[]
	 */
	public function getSearchProviders(): array {
		return $this->searchProviders;
	}

	/**
	 * @return ServiceRegistration<IAlternativeLogin>[]
	 */
	public function getAlternativeLogins(): array {
		return $this->alternativeLogins;
	}

	/**
	 * @return array[]
	 */
	public function getInitialStates(): array {
		return $this->initialStates;
	}

	/**
	 * @return ServiceRegistration<IHandler>[]
	 */
	public function getWellKnownHandlers(): array {
		return $this->wellKnownHandlers;
	}

	/**
	 * @return ServiceRegistration<ICustomTemplateProvider>[]
	 */
	public function getTemplateProviders(): array {
		return $this->templateProviders;
	}
}
