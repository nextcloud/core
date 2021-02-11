<?php
/**
 * @copyright Copyright (c) 2016 Morris Jobke <hey@morrisjobke.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\AppInfo;

use Closure;
use OCA\WorkflowEngine\Controller\RequestTime;
use OCA\WorkflowEngine\Helper\LogContext;
use OCA\WorkflowEngine\Listener\LoadAdditionalSettingsScriptsListener;
use OCA\WorkflowEngine\Manager;
use OCA\WorkflowEngine\Service\Logger;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\QueryException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IEntityCompat;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IOperationCompat;

class Application extends App implements IBootstrap {
	public const APP_ID = 'workflowengine';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerServiceAlias('RequestTimeController', RequestTime::class);
		$context->registerEventListener(
			LoadSettingsScriptsEvent::class,
			LoadAdditionalSettingsScriptsListener::class,
			-100
		);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerRuleListeners']));
	}

	private function registerRuleListeners(IEventDispatcher $dispatcher,
										   IServerContainer $container,
										   ILogger $logger): void {
		/** @var Manager $manager */
		$manager = $container->query(Manager::class);
		$configuredEvents = $manager->getAllConfiguredEvents();

		foreach ($configuredEvents as $operationClass => $events) {
			foreach ($events as $entityClass => $eventNames) {
				array_map(function (string $eventName) use ($manager, $container, $dispatcher, $logger, $operationClass, $entityClass) {
					$dispatcher->addListener(
						$eventName,
						function ($event) use ($manager, $container, $eventName, $logger, $operationClass, $entityClass) {
							$ruleMatcher = $manager->getRuleMatcher();
							try {
								/** @var IEntity $entity */
								$entity = $container->query($entityClass);
								/** @var IOperation $operation */
								$operation = $container->query($operationClass);

								$ruleMatcher->setEventName($eventName);
								$ruleMatcher->setEntity($entity);
								$ruleMatcher->setOperation($operation);

								$ctx = new LogContext();
								$ctx
									->setOperation($operation)
									->setEntity($entity)
									->setEventName($eventName);

								/** @var Logger $flowLogger */
								$flowLogger = $container->query(Logger::class);
								$flowLogger->logEventInit($ctx);

								if ($event instanceof Event) {
									$entity->prepareRuleMatcher($ruleMatcher, $eventName, $event);
									$operation->onEvent($eventName, $event, $ruleMatcher);
								} elseif ($entity instanceof IEntityCompat && $operation instanceof IOperationCompat) {
									// TODO: Remove this block (and the compat classes) in the first major release in 2023
									$entity->prepareRuleMatcherCompat($ruleMatcher, $eventName, $event);
									$operation->onEventCompat($eventName, $event, $ruleMatcher);
								} else {
									$logger->debug(
										'Cannot handle event {name} of {event} against entity {entity} and operation {operation}',
										[
											'app' => self::APP_ID,
											'name' => $eventName,
											'event' => get_class($event),
											'entity' => $entityClass,
											'operation' => $operationClass,
										]
									);
								}
								$flowLogger->logEventDone($ctx);
							} catch (QueryException $e) {
								// Ignore query exceptions since they might occur when an entity/operation were setup before by an app that is disabled now
							}
						}
					);
				}, $eventNames ?? []);
			}
		}
	}
}
