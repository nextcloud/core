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
use OCP\Dashboard\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use Throwable;
use function array_shift;

class RegistrationContext {

	/** @var array[] */
	private $capabilities = [];

	/** @var array[] */
	private $crashReporters = [];

	/** @var array[] */
	private $dashboardPanels = [];

	/** @var array[] */
	private $services = [];

	/** @var array[] */
	private $aliases = [];

	/** @var array[] */
	private $parameters = [];

	/** @var array[] */
	private $eventListeners = [];

	/** @var array[] */
	private $middlewares = [];

	/** @var array[] */
	private $searchProviders = [];

	/** @var array[] */
	private $alternativeLogins = [];

	/** @var array[] */
	private $initialStates = [];

	/** @var array[] */
	private $wellKnownHandlers = [];

	/** @var array[] */
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

	public function registerCapability(string $appId, string $capability): void {
		$this->capabilities[] = [
			'appId' => $appId,
			'capability' => $capability
		];
	}

	public function registerCrashReporter(string $appId, string $reporterClass): void {
		$this->crashReporters[] = [
			'appId' => $appId,
			'class' => $reporterClass,
		];
	}

	public function registerDashboardPanel(string $appId, string $panelClass): void {
		$this->dashboardPanels[] = [
			'appId' => $appId,
			'class' => $panelClass
		];
	}

	public function registerService(string $appId, string $name, callable $factory, bool $shared = true): void {
		$this->services[] = [
			"appId" => $appId,
			"name" => $name,
			"factory" => $factory,
			"shared" => $shared,
		];
	}

	public function registerServiceAlias(string $appId, string $alias, string $target): void {
		$this->aliases[] = [
			"appId" => $appId,
			"alias" => $alias,
			"target" => $target,
		];
	}

	public function registerParameter(string $appId, string $name, $value): void {
		$this->parameters[] = [
			"appId" => $appId,
			"name" => $name,
			"value" => $value,
		];
	}

	public function registerEventListener(string $appId, string $event, string $listener, int $priority = 0): void {
		$this->eventListeners[] = [
			"appId" => $appId,
			"event" => $event,
			"listener" => $listener,
			"priority" => $priority,
		];
	}

	public function registerMiddleware(string $appId, string $class): void {
		$this->middlewares[] = [
			"appId" => $appId,
			"class" => $class,
		];
	}

	public function registerSearchProvider(string $appId, string $class) {
		$this->searchProviders[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	public function registerAlternativeLogin(string $appId, string $class): void {
		$this->alternativeLogins[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	public function registerInitialState(string $appId, string $class): void {
		$this->initialStates[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	public function registerWellKnown(string $appId, string $class): void {
		$this->wellKnownHandlers[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	public function registerTemplateProvider(string $appId, string $class): void {
		$this->templateProviders[] = [
			'appId' => $appId,
			'class' => $class,
		];
	}

	/**
	 * @param App[] $apps
	 */
	public function delegateCapabilityRegistrations(array $apps): void {
		while (($registration = array_shift($this->capabilities)) !== null) {
			$appId = $registration->getAppId();
			if (!isset($apps[$appId])) {
				// If we land here something really isn't right. But at least we caught the
				// notice that is otherwise emitted for the undefined index
				$this->logger->error("App $appId not loaded for the capability registration");

				continue;
			}

			try {
				$apps[$registration['appId']]
					->getContainer()
					->registerCapability($registration['capability']);
			} catch (Throwable $e) {
				$appId = $registration['appId'];
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
				$registry->registerLazy($registration['class']);
			} catch (Throwable $e) {
				$appId = $registration['appId'];
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
				$dashboardManager->lazyRegisterWidget($panel['class']);
			} catch (Throwable $e) {
				$appId = $panel['appId'];
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
				if (isset($registration['priority'])) {
					$eventDispatcher->addServiceListener(
						$registration['event'],
						$registration['listener'],
						$registration['priority']
					);
				} else {
					$eventDispatcher->addServiceListener(
						$registration['event'],
						$registration['listener']
					);
				}
			} catch (Throwable $e) {
				$appId = $registration['appId'];
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
			$appId = $registration->getAppId();
			if (!isset($apps[$appId])) {
				// If we land here something really isn't right. But at least we caught the
				// notice that is otherwise emitted for the undefined index
				$this->logger->error("App $appId not loaded for the container service registration");

				continue;
			}

			try {
				/**
				 * Register the service and convert the callable into a \Closure if necessary
				 */
				$apps[$registration['appId']]
					->getContainer()
					->registerService(
						$registration['name'],
						Closure::fromCallable($registration['factory']),
						$registration['shared'] ?? true
					);
			} catch (Throwable $e) {
				$appId = $registration['appId'];
				$this->logger->logException($e, [
					'message' => "Error during service registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}

		while (($registration = array_shift($this->aliases)) !== null) {
			$appId = $registration->getAppId();
			if (!isset($apps[$appId])) {
				// If we land here something really isn't right. But at least we caught the
				// notice that is otherwise emitted for the undefined index
				$this->logger->error("App $appId not loaded for the container alias registration");

				continue;
			}

			try {
				$apps[$registration['appId']]
					->getContainer()
					->registerAlias(
						$registration['alias'],
						$registration['target']
					);
			} catch (Throwable $e) {
				$appId = $registration['appId'];
				$this->logger->logException($e, [
					'message' => "Error during service alias registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}

		while (($registration = array_shift($this->parameters)) !== null) {
			$appId = $registration->getAppId();
			if (!isset($apps[$appId])) {
				// If we land here something really isn't right. But at least we caught the
				// notice that is otherwise emitted for the undefined index
				$this->logger->error("App $appId not loaded for the container parameter registration");

				continue;
			}

			try {
				$apps[$registration['appId']]
					->getContainer()
					->registerParameter(
						$registration['name'],
						$registration['value']
					);
			} catch (Throwable $e) {
				$appId = $registration['appId'];
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
			$appId = $middleware->getAppId();
			if (!isset($apps[$appId])) {
				// If we land here something really isn't right. But at least we caught the
				// notice that is otherwise emitted for the undefined index
				$this->logger->error("App $appId not loaded for the container middleware registration");

				continue;
			}

			try {
				$apps[$middleware['appId']]
					->getContainer()
					->registerMiddleWare($middleware['class']);
			} catch (Throwable $e) {
				$appId = $middleware['appId'];
				$this->logger->logException($e, [
					'message' => "Error during capability registration of $appId: " . $e->getMessage(),
					'level' => ILogger::ERROR,
				]);
			}
		}
	}

	/**
	 * @return array[]
	 */
	public function getSearchProviders(): array {
		return $this->searchProviders;
	}

	/**
	 * @return array[]
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
	 * @return array[]
	 */
	public function getWellKnownHandlers(): array {
		return $this->wellKnownHandlers;
	}

	public function getTemplateProviders(): array {
		return $this->templateProviders;
	}
}
